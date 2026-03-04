#!/usr/bin/env bash
set -euo pipefail

# ------------------------
# Environment
# ------------------------

export PATH="/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin"

APP_DIR="{{ $app_path }}"
LOG_DIR="{{ $log_dir }}"
LOG_FILE="${LOG_DIR}/deployment-{{ $script }}.log"

# Commit parameters (optional)
COMMIT_INPUT_HASH="${1:-}"
COMMIT_INPUT_MSG="${2:-}"

# ------------------------
# Logging setup
# ------------------------

sudo mkdir -p "$LOG_DIR"
TEMP_LOG=$(mktemp)
START_TIMESTAMP=$(date +%s)
DEPLOY_FAILED=0

# Save original stdout/stderr and redirect current output to TEMP_LOG
exec 3>&1 4>&2
exec > "$TEMP_LOG" 2>&1

log() {
echo "$*"
}

log_cmd() {
log "CMD: $*"
if ! "$@"; then
DEPLOY_FAILED=1
log "ERROR: Command failed: $*"
return 1
fi
}

run() {
log "RUN: $*"
if ! "$@"; then
DEPLOY_FAILED=1
log "ERROR: Execution failed: $*"
return 1
fi
}

# ------------------------
# Start deploy
# ------------------------

# Trap to report status on exit
cleanup() {
@include('self-deploy::end')

}
trap cleanup EXIT

cd "$APP_DIR"

@includeif($script)