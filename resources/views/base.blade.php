#!/usr/bin/env bash
set -euo pipefail

# ------------------------
# Environment
# ------------------------

export PATH="/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin"

APP_DIR="{{ $app_path }}"
LOG_DIR="{{ $log_dir }}/{{ $script }}"
LOG_FILE="${LOG_DIR}/deployment-$(date +%F_%H%M%S).log"

# ------------------------
# Logging setup
# ------------------------

sudo mkdir -p "$LOG_DIR"
exec > >(sudo tee -a "$LOG_FILE") 2>&1

log() {
echo "[$(date '+%F %T')] $*"
}

log_cmd() {
log "CMD: $*"
"$@"
}

run() {
log "RUN: $*"
"$@"
}

# ------------------------
# Start deploy
# ------------------------

log "==== {{ $script }} deployment started ===="
log "Log file: $LOG_FILE"

cd "$APP_DIR"

@includeif($script)