# Orange dev stack — single-shot launcher.
#
# Spawns every service the local dev loop needs, each in its own PowerShell
# window so logs stay separated and Ctrl+C on any one of them won't kill
# the rest.
#
# Order matters:
#   1. orange-http       php artisan serve   (HTTP API + iframe page, :8000)
#   2. orange-reverb     php artisan reverb:start  (WebSocket :8090)
#   3. orange-queue      php artisan queue:work    (wallet callbacks + webhooks)
#   4. crash:loop        round lifecycle driver
#   5. crash:tick        multiplier tick broadcaster
#   6. consumer          OrangeConsumer panel (:8001)
#
# Usage:
#   pwsh scripts/start-stack.ps1
#   pwsh scripts/start-stack.ps1 -ConsumerPath D:\some\where\OrangeConsumer
#   pwsh scripts/start-stack.ps1 -SkipConsumer        # Orange only
#   pwsh scripts/start-stack.ps1 -Migrate             # run migrate --seed first

param(
    [string]$OrangePath   = (Split-Path -Parent $PSScriptRoot),
    [string]$ConsumerPath = (Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'OrangeConsumer'),
    [string]$BindHost     = '0.0.0.0',
    # Host shown in the "open these URLs" banner at the end. Defaults to the
    # LAN IP this machine uses so the links work from other devices too.
    [string]$PublicHost   = '172.22.1.180',
    [int]$OrangePort      = 8000,
    [int]$ReverbPort      = 8090,
    [int]$ConsumerPort    = 8001,
    [string]$Memory       = '512M',
    [switch]$SkipConsumer,
    [switch]$Migrate
)

function Launch {
    param(
        [string]$Title,
        [string]$WorkDir,
        [string]$Command,
        [string]$Color = 'Cyan'
    )
    Write-Host ("[+] " + $Title) -ForegroundColor $Color
    $wrapped = @"
`$Host.UI.RawUI.WindowTitle = '$Title'
Set-Location '$WorkDir'
Write-Host '== $Title ==' -ForegroundColor $Color
$Command
"@
    Start-Process powershell -ArgumentList '-NoExit', '-Command', $wrapped -WindowStyle Normal
}

if (-not (Test-Path (Join-Path $OrangePath 'artisan'))) {
    Write-Error "Orange artisan not found at $OrangePath. Pass -OrangePath to override."
    exit 1
}

Write-Host ""
Write-Host "Launching Orange stack" -ForegroundColor Cyan
Write-Host ("  Orange:        " + $OrangePath)
Write-Host ("  Host/port:     " + $BindHost + ":" + $OrangePort + "  (Reverb :" + $ReverbPort + ")")
if (-not $SkipConsumer) {
    Write-Host ("  Consumer:      " + $ConsumerPath + "  (port " + $ConsumerPort + ")")
}
Write-Host ""

# 0. (optional) one-shot DB setup
if ($Migrate) {
    Write-Host "[*] Running migrate --seed in current shell..." -ForegroundColor DarkGray
    Push-Location $OrangePath
    & php artisan migrate --seed --force
    Pop-Location
    Write-Host ""
}

# PHP CLI flags applied to every daemon below. `cli-server` SAPI (what
# artisan serve uses under the hood) honours opcache.enable_cli — and our
# system php.ini has it disabled. Passing -d here flips it on per-process
# without touching the system config. Massive boost for `artisan serve`:
# class loading goes from per-request to once-per-worker.
$PhpOpcacheFlags = "-d opcache.enable_cli=1 -d opcache.validate_timestamps=1 -d opcache.revalidate_freq=2"

# 1. Orange HTTP API + /crash iframe page (:8000)
Launch -Title "orange-http (:$OrangePort)" `
       -WorkDir $OrangePath `
       -Color 'Cyan' `
       -Command "php $PhpOpcacheFlags artisan serve --host=$BindHost --port=$OrangePort"

Start-Sleep -Milliseconds 800

# 2. Orange Reverb WebSocket (:8090) — pushes round.* events to the panel
Launch -Title "orange-reverb (:$ReverbPort)" `
       -WorkDir $OrangePath `
       -Color 'Magenta' `
       -Command "php $PhpOpcacheFlags artisan reverb:start --host=$BindHost --port=$ReverbPort"

Start-Sleep -Milliseconds 1200

# 3. Queue worker — REQUIRED for wallet callbacks (DispatchWalletCallbackJob)
#    and operator webhooks (DeliverWebhookJob). Without this, bets succeed
#    but the operator's balance never updates and wallet_transactions stay
#    in `pending` forever.
Launch -Title "orange-queue" `
       -WorkDir $OrangePath `
       -Color 'Yellow' `
       -Command "php $PhpOpcacheFlags artisan queue:work --queue=default --tries=3 --backoff=3 --timeout=120"

Start-Sleep -Milliseconds 400

# 4. Crash round lifecycle (auto-restarting wrapper)
Launch -Title "crash:loop" `
       -WorkDir $OrangePath `
       -Color 'Green' `
       -Command ".\scripts\run-crash-loop.ps1 -Memory $Memory"

Start-Sleep -Milliseconds 400

# 5. Tick broadcaster (auto-restarting wrapper)
Launch -Title "crash:tick" `
       -WorkDir $OrangePath `
       -Color 'Green' `
       -Command ".\scripts\run-crash-tick.ps1 -Memory $Memory"

# 6. OrangeConsumer (operator-side panel)
if (-not $SkipConsumer) {
    if (-not (Test-Path (Join-Path $ConsumerPath 'artisan'))) {
        Write-Warning "OrangeConsumer not found at $ConsumerPath -- skipping. Pass -ConsumerPath or -SkipConsumer."
    } else {
        Start-Sleep -Milliseconds 800
        Launch -Title "consumer (:$ConsumerPort)" `
               -WorkDir $ConsumerPath `
               -Color 'White' `
               -Command "php $PhpOpcacheFlags artisan serve --host=$BindHost --port=$ConsumerPort"
    }
}

Write-Host ""
Write-Host "All windows launched." -ForegroundColor Green
Write-Host ("  Admin panel:   http://" + $PublicHost + ":" + $OrangePort + "/admin")
Write-Host ("  Game iframe:   http://" + $PublicHost + ":" + $OrangePort + "/crash")
if (-not $SkipConsumer) {
    Write-Host ("  Operator UI:   http://" + $PublicHost + ":" + $ConsumerPort + "/consumer/players/1")
}
Write-Host ""
Write-Host "Queue worker IS up — wallet callbacks + webhooks will fire async." -ForegroundColor DarkGray
Write-Host "Stop everything with: pwsh scripts/stop-stack.ps1" -ForegroundColor DarkGray
