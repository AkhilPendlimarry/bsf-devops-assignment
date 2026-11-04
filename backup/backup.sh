#!/bin/sh
# Nightly WordPress database backup -> S3 (MinIO locally, AWS S3 in production)
set -e

export AWS_ACCESS_KEY_ID=bsfadmin
export AWS_SECRET_ACCESS_KEY=bsf-s3-root-secret
export AWS_DEFAULT_REGION=us-east-1
S3="aws --endpoint-url http://s3:9000 s3"

BUCKET=bsf-backups
STAMP=$(date +%Y%m%d-%H%M%S)
FILE=/tmp/wordpress-$STAMP.sql.gz

echo "[backup] dumping database ..."
mariadb-dump -h db -u root -proot-pass-2026 --all-databases | gzip > "$FILE"

echo "[backup] uploading ..."
$S3 mb "s3://$BUCKET" 2>/dev/null || true
$S3 cp "$FILE" "s3://$BUCKET/site-x/"

echo "[backup] done: s3://$BUCKET/site-x/$(basename "$FILE")"
