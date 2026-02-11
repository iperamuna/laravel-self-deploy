#!/usr/bin/env bash
set -euo pipefail

LOG_DIR="{{ $log_dir }}"
LOG_FILE="${LOG_DIR}/{{ $script }}-deployment-$(date +%F_%H%M%S).log"
# ------------------------

# Log everything (stdout + stderr) to file AND console
sudo mkdir -p "$LOG_DIR"
exec > >(tee -a "$LOG_FILE") 2>&1

log() { echo "[$(date '+%F %T')] $*"; }

# Run a command and log it clearly
run() {
log "RUN: $*"
"$@"
}

log "==== {{ $script }} deployment started ===="
log "Log file: $LOG_FILE"

@includeif($script)
