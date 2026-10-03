# Self-restarting wrapper for `php artisan crash:loop`.
#
# Same recipe as run-crash-tick.ps1: the loop exits voluntarily at 70 %
# of its memory ceiling so this wrapper restarts it before any leak in
# the broadcast pipeline can hard-OOM the process. Orphan rounds from
# the prior incarnation are auto-cleaned on the next start (see
# RunCrashLoopCommand::handle).
#
# Usage (from repo root):
#   pwsh scripts/run-crash-loop.ps1
#   pwsh scripts/run-crash-loop.ps1 -Memory 768M

param(
    [string]$Memory = '512M',
    [string]$Game   = 'crash',
    [int]$RestartCooldownMs = 250
)

$ErrorActionPreference = 'Stop'

Write-Host "Wrapping crash:loop -- game=${Game} memory=${Memory}" -ForegroundColor Cyan
Write-Host "Ctrl+C to stop the wrapper.`n" -ForegroundColor DarkGray

$restarts = 0
while ($true) {
    $restarts++
    Write-Host "[$([DateTime]::Now.ToString('HH:mm:ss'))] start #$restarts" -ForegroundColor DarkGray
    & php artisan crash:loop --game=$Game --memory=$Memory
    $code = $LASTEXITCODE
    if ($code -ne 0) {
        Write-Host "[$([DateTime]::Now.ToString('HH:mm:ss'))] exited non-zero ($code) -- waiting 2s before retry" -ForegroundColor Yellow
        Start-Sleep -Seconds 2
    } else {
        Start-Sleep -Milliseconds $RestartCooldownMs
    }
}
