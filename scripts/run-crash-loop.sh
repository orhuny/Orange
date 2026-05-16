#!/usr/bin/env bash
# Bash equivalent of run-crash-loop.ps1 — for Linux / macOS production.
# Self-restarts crash:loop after the soft memory ceiling exit. Orphan
# rounds from the previous incarnation are auto-cleaned on next start.
#
# Usage (from repo root):
#   ./scripts/run-crash-loop.sh
#   GAME=crash MEMORY=768M ./scripts/run-crash-loop.sh

set -u

GAME="${GAME:-crash}"
MEMORY="${MEMORY:-512M}"
RESTART_COOLDOWN="${RESTART_COOLDOWN:-0.25}"
PHP="${PHP:-php}"

printf '\e[36mWrapping crash:loop — game=%s memory=%s\e[0m\n' "$GAME" "$MEMORY"
printf '\e[90mCtrl+C to stop the wrapper.\e[0m\n\n'

trap 'echo; echo "Wrapper stopped."; exit 0' INT TERM

restarts=0
while true; do
    restarts=$((restarts + 1))
    printf '\e[90m[%s] start #%d\e[0m\n' "$(date '+%H:%M:%S')" "$restarts"

    "$PHP" artisan crash:loop --game="$GAME" --memory="$MEMORY"
    code=$?

    if [ "$code" -ne 0 ]; then
        printf '\e[33m[%s] exited non-zero (%d) — waiting 2s\e[0m\n' "$(date '+%H:%M:%S')" "$code"
        sleep 2
    else
        sleep "$RESTART_COOLDOWN"
    fi
done
