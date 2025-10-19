#!/bin/bash
# Event Restore Script
# Restores event images and database entries from backup

set -e

# Load environment variables
if [ -f .env ]; then
    while IFS= read -r line; do
        # Skip empty lines and comments
        [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
        # Remove inline comments and trailing whitespace
        line=$(echo "$line" | sed 's/#.*$//' | sed 's/[[:space:]]*$//')
        # Export the variable
        export "$line"
    done < .env
fi

TEMP_DIR="./temp_restore"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to display usage
usage() {
    echo "Usage: $0 <backup-file.tar.gz>"
    echo ""
    echo "Examples:"
    echo "  $0 ./backups/my-event-2024_backup_20240315_143022.tar.gz"
    echo "  $0 ./backups/full_backup_20240315_143022.tar.gz"
    exit 1
}

# Check if argument provided
if [ $# -eq 0 ]; then
    usage
fi

BACKUP_FILE=$1

# Check if backup file exists
if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}Error: Backup file not found: $BACKUP_FILE${NC}"
    exit 1
fi

# Check if docker-compose is running
if ! docker-compose -f docker-compose.dev.yml ps | grep -q "Up"; then
    echo -e "${RED}Error: Docker containers are not running. Start with 'make dev-up'${NC}"
    exit 1
fi

echo -e "${YELLOW}Restoring from backup: $BACKUP_FILE${NC}"

# Extract backup
echo "Extracting backup..."
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"
tar -xzf "$BACKUP_FILE" -C "$TEMP_DIR"

# Find the extracted directory
EXTRACTED_DIR=$(find "$TEMP_DIR" -maxdepth 1 -type d ! -path "$TEMP_DIR" | head -n 1)

if [ -z "$EXTRACTED_DIR" ]; then
    echo -e "${RED}Error: Could not find extracted backup directory${NC}"
    rm -rf "$TEMP_DIR"
    exit 1
fi

# Read backup info
if [ -f "$EXTRACTED_DIR/backup_info.txt" ]; then
    echo -e "${BLUE}Backup Information:${NC}"
    cat "$EXTRACTED_DIR/backup_info.txt"
    echo ""
fi

# Check backup type
if [ -f "$EXTRACTED_DIR/database_full.sql" ]; then
    # Full backup restore
    echo -e "${YELLOW}This is a FULL BACKUP restore${NC}"
    echo -e "${RED}WARNING: This will replace ALL events and photos!${NC}"
    read -p "Are you sure you want to continue? (yes/no): " CONFIRM

    if [ "$CONFIRM" != "yes" ]; then
        echo "Restore cancelled."
        rm -rf "$TEMP_DIR"
        exit 0
    fi

    echo "Restoring complete database..."
    docker-compose -f docker-compose.dev.yml exec -T db mysql \
        -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        < "$EXTRACTED_DIR/database_full.sql"

    echo "Restoring all uploads..."
    if [ -d "$EXTRACTED_DIR/data" ]; then
        rm -rf ./app/data/*
        cp -r "$EXTRACTED_DIR/data/"* ./app/data/
        echo -e "${GREEN}  Restored app/data${NC}"
    else
        echo -e "${YELLOW}Warning: No data directory found in backup${NC}"
    fi

else
    # Event-specific restore
    SQL_FILE=$(find "$EXTRACTED_DIR" -name "database_*.sql" | head -n 1)

    if [ -z "$SQL_FILE" ]; then
        echo -e "${RED}Error: No database SQL file found in backup${NC}"
        rm -rf "$TEMP_DIR"
        exit 1
    fi

    # Extract event slug from filename
    EVENT_SLUG=$(basename "$SQL_FILE" | sed 's/database_\(.*\)\.sql/\1/')

    echo -e "${YELLOW}Restoring event: $EVENT_SLUG${NC}"

    # Check if event already exists
    EVENT_EXISTS=$(docker-compose -f docker-compose.dev.yml exec -T db mysql \
        -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        -se "SELECT COUNT(*) FROM events WHERE event_slug='${EVENT_SLUG}'" 2>/dev/null || echo "0")

    if [ "$EVENT_EXISTS" -gt 0 ]; then
        echo -e "${YELLOW}Warning: Event '$EVENT_SLUG' already exists${NC}"
        read -p "Overwrite existing event? (yes/no): " CONFIRM

        if [ "$CONFIRM" != "yes" ]; then
            echo "Restore cancelled."
            rm -rf "$TEMP_DIR"
            exit 0
        fi

        # Delete existing event and photos
        echo "Removing existing event data..."

        # First get event_id
        EVENT_ID=$(docker-compose -f docker-compose.dev.yml exec -T db mysql \
            -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
            -se "SELECT id FROM events WHERE event_slug='${EVENT_SLUG}'")

        # Delete photos using event_id (photos table has event_id, not event_slug)
        docker-compose -f docker-compose.dev.yml exec -T db mysql \
            -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
            -e "DELETE FROM photos WHERE event_id=${EVENT_ID}"

        # Delete event
        docker-compose -f docker-compose.dev.yml exec -T db mysql \
            -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
            -e "DELETE FROM events WHERE event_slug='${EVENT_SLUG}'"

        # Remove existing uploads (from app/data)
        rm -rf "./app/data/${EVENT_SLUG}"
    fi

    echo "Restoring event database..."
    docker-compose -f docker-compose.dev.yml exec -T db mysql \
        -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        < "$SQL_FILE"

    echo "Restoring event images..."
    if [ -d "$EXTRACTED_DIR/data" ]; then
        mkdir -p "./app/data/${EVENT_SLUG}"
        cp -r "$EXTRACTED_DIR/data/"* "./app/data/${EVENT_SLUG}/"

        # Count restored photos
        PHOTO_COUNT=$(find "./app/data/${EVENT_SLUG}/photos" -type f 2>/dev/null | wc -l || echo "0")
        echo -e "${GREEN}  Restored ${PHOTO_COUNT} photos${NC}"
    else
        echo -e "${YELLOW}Warning: No data directory found in backup${NC}"
    fi
fi

# Cleanup
echo "Cleaning up..."
rm -rf "$TEMP_DIR"

echo ""
echo -e "${GREEN}✓ Restore completed successfully!${NC}"
if [ -n "$EVENT_SLUG" ]; then
    echo -e "${GREEN}Event '$EVENT_SLUG' has been restored.${NC}"
else
    echo -e "${GREEN}All events and data have been restored.${NC}"
fi
