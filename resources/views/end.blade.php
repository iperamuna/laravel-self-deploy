# ------------------------
# End deploy (Cleanup & Reporting)
# ------------------------

# 1. Restore original stdout/stderr (re-open terminal pipe)
exec 1>&3 2>&4

# 2. Timing calculation
END_TIMESTAMP=$(date +%s)
DURATION=$((END_TIMESTAMP - START_TIMESTAMP))

# Calculate human readable duration
HUMAN_DURATION=""
if [ $DURATION -ge 3600 ]; then
HUMAN_DURATION="$((DURATION/3600))h "
fi
if [ $DURATION -ge 60 ] || [ $DURATION -ge 3600 ]; then
HUMAN_DURATION="${HUMAN_DURATION}$((DURATION%3600/60))m "
fi
HUMAN_DURATION="${HUMAN_DURATION:-0s}"
if [ $DURATION -lt 60 ]; then
HUMAN_DURATION="${DURATION}s"
fi

# 3. Final status and logging LEVEL
STATUS="success"
LEVEL="INFO"
if [[ ${DEPLOY_FAILED:-0} -ne 0 ]]; then
STATUS="failed"
LEVEL="ERROR"
fi

# 4. Commit info detection
FINAL_HASH="${COMMIT_INPUT_HASH:-$(git rev-parse --short HEAD 2>/dev/null || echo "N/A")}"
FINAL_MSG="${COMMIT_INPUT_MSG:-$(git log -1 --pretty=%B 2>/dev/null | head -n 1 || echo "N/A")}"

# 5. Write the final unified log entry
SUMMARY_HEADER="==== Deployment Summary: {{ $script }} (${STATUS}) within ${HUMAN_DURATION} ===="
echo "[$(date '+%F %T')] {{ app()->environment() }}.${LEVEL}: ${SUMMARY_HEADER}" | sudo tee -a "$LOG_FILE" > /dev/null

# Context metadata lines
echo "#0 Status: ${STATUS}" | sudo tee -a "$LOG_FILE" > /dev/null
echo "#1 Duration: ${HUMAN_DURATION}" | sudo tee -a "$LOG_FILE" > /dev/null
echo "#2 Commit Hash: ${FINAL_HASH}" | sudo tee -a "$LOG_FILE" > /dev/null
echo "#3 Commit Message: ${FINAL_MSG}" | sudo tee -a "$LOG_FILE" > /dev/null

# 6. Append buffered command output from TEMP_LOG (numbered sequentially)
if [ -f "$TEMP_LOG" ]; then
awk '{print "#" (NR+3) " " $0}' "$TEMP_LOG" | sudo tee -a "$LOG_FILE" > /dev/null
rm -f "$TEMP_LOG"
fi

# 7. Final Footer/main reference for Log Viewer
TOTAL_LINES=$(sudo wc -l < "$LOG_FILE" 2>/dev/null || echo "0")
    echo "#${TOTAL_LINES} {main}" | sudo tee -a "$LOG_FILE" > /dev/null
    echo "#$((TOTAL_LINES+1)) ==== Deployment Summary: {{ $script }} ====" | sudo tee -a "$LOG_FILE" > /dev/null