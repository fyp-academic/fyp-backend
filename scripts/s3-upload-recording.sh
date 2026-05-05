#!/bin/bash
# S3 Upload Script for Jitsi Recordings
# Place this at /config/finalize_recording.sh on your Jitsi/Jibri server
# Or call it from your existing finalize script

# Configuration - Update these values
S3_BUCKET="your-lms-recordings-bucket"
S3_REGION="us-east-1"  # Change to your region
S3_PREFIX="recordings/"  # Folder path in bucket
AWS_ACCESS_KEY_ID="${AWS_ACCESS_KEY_ID:-}"
AWS_SECRET_ACCESS_KEY="${AWS_SECRET_ACCESS_KEY:-}"

# Backend API to notify when upload is complete (CODAGENZ PRODUCTION)
LMS_BACKEND_URL="https://api.codagenz.com/api/v1"
LMS_API_KEY="${LMS_API_KEY:-your-webhook-api-key-here}"

# Recording file passed by Jibri
RECORDING_FILE="$1"
SESSION_ID="$2"  # Optional: Session ID if passed by custom Jibri config

# Logging
LOG_FILE="/var/log/jibri/s3-upload.log"
mkdir -p "$(dirname "$LOG_FILE")"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Check if recording file exists
if [ -z "$RECORDING_FILE" ] || [ ! -f "$RECORDING_FILE" ]; then
    log "ERROR: Recording file not found: $RECORDING_FILE"
    exit 1
fi

FILENAME=$(basename "$RECORDING_FILE")
FILESIZE=$(stat -f%z "$RECORDING_FILE" 2>/dev/null || stat -c%s "$RECORDING_FILE" 2>/dev/null)
log "Processing recording: $FILENAME (${FILESIZE} bytes)"

# Extract metadata from filename if available
# Jibri format: session_id_2024-01-15-10-30-00.mp4
if [[ $FILENAME =~ ^([a-zA-Z0-9_-]+)_([0-9]{4}-[0-9]{2}-[0-9]{2}) ]]; then
    SESSION_ID_FROM_FILE="${BASH_REMATCH[1]}"
    RECORDING_DATE="${BASH_REMATCH[2]}"
    log "Extracted Session ID: $SESSION_ID_FROM_FILE, Date: $RECORDING_DATE"
fi

# Use provided session ID or extracted one
FINAL_SESSION_ID="${SESSION_ID:-$SESSION_ID_FROM_FILE}"

# Generate unique S3 key
TIMESTAMP=$(date +%s)
UUID=$(uuidgen 2>/dev/null || echo "$TIMESTAMP-$RANDOM")
S3_KEY="${S3_PREFIX}${FINAL_SESSION_ID:-unknown}/${UUID}/${FILENAME}"

log "Uploading to s3://${S3_BUCKET}/${S3_KEY}"

# Install AWS CLI if not present (for minimal installations)
if ! command -v aws &> /dev/null; then
    log "AWS CLI not found, installing..."
    apt-get update && apt-get install -y awscli || {
        log "ERROR: Failed to install AWS CLI"
        exit 1
    }
fi

# Configure AWS credentials if provided via environment
if [ -n "$AWS_ACCESS_KEY_ID" ] && [ -n "$AWS_SECRET_ACCESS_KEY" ]; then
    export AWS_ACCESS_KEY_ID
    export AWS_SECRET_ACCESS_KEY
    export AWS_DEFAULT_REGION="$S3_REGION"
fi

# Upload to S3 with multipart for large files
MAX_PART_SIZE="100MB"
UPLOAD_START=$(date +%s)

aws s3 cp "$RECORDING_FILE" "s3://${S3_BUCKET}/${S3_KEY}" \
    --region "$S3_REGION" \
    --metadata "session-id=${FINAL_SESSION_ID}" \
    --metadata "recording-date=$(date -Iseconds)" \
    --metadata "original-filename=${FILENAME}" \
    --content-type "video/mp4" \
    2>&1 | tee -a "$LOG_FILE"

UPLOAD_EXIT_CODE=${PIPESTATUS[0]}
UPLOAD_END=$(date +%s)
UPLOAD_DURATION=$((UPLOAD_END - UPLOAD_START))

if [ $UPLOAD_EXIT_CODE -eq 0 ]; then
    log "SUCCESS: Upload completed in ${UPLOAD_DURATION}s"
    log "S3 URL: s3://${S3_BUCKET}/${S3_KEY}"
    
    # Generate presigned URL for backend
    PRESIGNED_URL=$(aws s3 presign "s3://${S3_BUCKET}/${S3_KEY}" \
        --region "$S3_REGION" \
        --expires-in 3600 2>/dev/null)
    
    # Notify backend about completed upload
    if [ -n "$LMS_BACKEND_URL" ] && [ -n "$FINAL_SESSION_ID" ]; then
        log "Notifying backend at ${LMS_BACKEND_URL}/webhooks/recording-complete"
        
        curl -s -X POST "${LMS_BACKEND_URL}/webhooks/recording-complete" \
            -H "Content-Type: application/json" \
            -H "X-API-Key: ${LMS_API_KEY}" \
            -d "{
                \"session_id\": \"${FINAL_SESSION_ID}\",
                \"s3_bucket\": \"${S3_BUCKET}\",
                \"s3_key\": \"${S3_KEY}\",
                \"filename\": \"${FILENAME}\",
                \"filesize\": ${FILESIZE},
                \"presigned_url\": \"${PRESIGNED_URL}\",
                \"recording_date\": \"$(date -Iseconds)\",
                \"duration_seconds\": ${UPLOAD_DURATION}
            }" 2>&1 | tee -a "$LOG_FILE"
        
        log "Backend notification sent"
    fi
    
    # Clean up local file after successful upload
    # Uncomment to enable automatic cleanup
    # rm -f "$RECORDING_FILE"
    # log "Local file removed: $RECORDING_FILE"
    
    # Move to archive folder instead of deleting (safer)
    ARCHIVE_DIR="/config/recordings/uploaded"
    mkdir -p "$ARCHIVE_DIR"
    mv "$RECORDING_FILE" "$ARCHIVE_DIR/"
    log "File moved to archive: $ARCHIVE_DIR/$FILENAME"
    
else
    log "ERROR: Upload failed with exit code $UPLOAD_EXIT_CODE"
    
    # Move to failed folder for retry
    FAILED_DIR="/config/recordings/failed"
    mkdir -p "$FAILED_DIR"
    mv "$RECORDING_FILE" "$FAILED_DIR/"
    log "File moved to failed folder: $FAILED_DIR/$FILENAME"
    
    # Send failure notification
    if [ -n "$LMS_BACKEND_URL" ] && [ -n "$FINAL_SESSION_ID" ]; then
        curl -s -X POST "${LMS_BACKEND_URL}/webhooks/recording-failed" \
            -H "Content-Type: application/json" \
            -H "X-API-Key: ${LMS_API_KEY}" \
            -d "{
                \"session_id\": \"${FINAL_SESSION_ID}\",
                \"filename\": \"${FILENAME}\",
                \"error\": \"S3 upload failed with exit code ${UPLOAD_EXIT_CODE}\"
            }" 2>&1 | tee -a "$LOG_FILE"
    fi
    
    exit 1
fi

log "=== Upload process complete ==="
exit 0
