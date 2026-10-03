# Self-restarting wrapper for `php artisan crash:tick`.
#
# The Laravel + Reverb + Guzzle broadcast pipeline leaks slowly inside a
# long-running PHP CLI process. crash:tick now exits voluntarily at 70 %
# of its memory ceiling so this loop can restart it cleanly before a
# hard OOM. Ctrl+C breaks out of the wrapper.
#
# Usage (from repo root):
#   pwsh scripts/run-crash-tick.ps1
#   pwsh scripts/run-crash-tick.ps1 -IntervalMs 100 -Memory 768M

param(
    [int]$IntervalMs = 100,
    [string]$Memory  = '512M',
    [int]$RestartCooldownMs = 250
)

$ErrorActionPreference = 'Stop'

Write-Host "Wrapping crash:tick -- interval=${IntervalMs}ms memory=${Memory}" -ForegroundColor Cyan
Write-Host "Ctrl+C to stop the wrapper.`n" -ForegroundColor DarkGray

$restarts = 0
while ($true) {
    $restarts++
    Write-Host "[$([DateTime]::Now.ToString('HH:mm:ss'))] start #$restarts" -ForegroundColor DarkGray
    & php artisan crash:tick --interval-ms=$IntervalMs --memory=$Memory
    $code = $LASTEXITCODE
    if ($code -ne 0) {
        Write-Host "[$([DateTime]::Now.ToString('HH:mm:ss'))] exited non-zero ($code) -- waiting 2s before retry" -ForegroundColor Yellow
        Start-Sleep -Seconds 2
    } else {
        Start-Sleep -Milliseconds $RestartCooldownMs
    }
}
