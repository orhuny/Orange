# Orange dev stack -- graceful shutdown.
#
# Finds the windows spawned by start-stack.ps1 (matched by title prefix) and
# closes them. PHP child processes inside terminate when their parent shell
# exits.

$titles = @(
    'orange-http',
    'orange-reverb',
    'orange-queue',
    'crash:loop',
    'crash:tick',
    'consumer'
)

$killed = 0
foreach ($t in $titles) {
    Get-Process powershell -ErrorAction SilentlyContinue |
        Where-Object { $_.MainWindowTitle -like "$t*" } |
        ForEach-Object {
            Write-Host ("[x] " + $_.MainWindowTitle) -ForegroundColor Yellow
            Stop-Process -Id $_.Id -Force
            $killed++
        }
}

if ($killed -eq 0) {
    Write-Host "No stack windows found." -ForegroundColor DarkGray
} else {
    Write-Host ("Stopped $killed window(s).") -ForegroundColor Green
}
