#!/usr/bin/env bash
# Bash equivalent of run-crash-tick.ps1 — for Linux / macOS production.
# Self-restarts crash:tick after the soft memory ceiling exit.
#
# Usage (from repo root):
#   ./scripts/run-crash-tick.sh
#   INTERVAL_MS=100 MEMORY=768M ./scripts/run-crash-tick.sh
#
# In production prefer systemd (see docs/deploy.md for a unit file
# template) — this script is fine for tmux/screen sessions and dev hosts.

set -u

INTERVAL_MS="${INTERVAL_MS:-100}"
MEMORY="${MEMORY:-512M}"
RESTART_COOLDOWN="${RESTART_COOLDOWN:-0.25}"
PHP="${PHP:-php}"

printf '\e[36mWrapping crash:tick — interval=%sms memory=%s\e[0m\n' "$INTERVAL_MS" "$MEMORY"
printf '\e[90mCtrl+C to stop the wrapper.\e[0m\n\n'

trap 'echo; echo "Wrapper stopped."; exit 0' INT TERM

restarts=0
while true; do
    restarts=$((restarts + 1))
    printf '\e[90m[%s] start #%d\e[0m\n' "$(date '+%H:%M:%S')" "$restarts"

    "$PHP" artisan crash:tick --interval-ms="$INTERVAL_MS" --memory="$MEMORY"
    code=$?

    if [ "$code" -ne 0 ]; then
        printf '\e[33m[%s] exited non-zero (%d) — waiting 2s\e[0m\n' "$(date '+%H:%M:%S')" "$code"
        sleep 2
    else
        sleep "$RESTART_COOLDOWN"
    fi
done
