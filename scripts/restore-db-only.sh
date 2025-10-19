#!/bin/bash
# Database-Only Restore Script
# Restores only database entries from backup (no photos)

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

TEMP_DIR="./temp_restore_db"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to display usage
usage() {
    echo "Usage: $0 <backup-file.tar.gz|database.sql>"
    echo ""
    echo "This will restore ONLY the database from the backup."
    echo "Photos and uploads will NOT be affected."
    echo ""
    echo "Supports:"
    echo "  - .tar.gz backup files (event or full backups)"
    echo "  - .sql database dump files directly"
    echo ""
    echo "Examples:"
    echo "  $0 ./backups/my-event-2024_backup_20240315_143022.tar.gz"
    echo "  $0 ./backups/full_backup_20240315_143022.tar.gz"
    echo "  $0 ./backups/database_demo-event.sql"
    echo "  $0 ./backups/database_full.sql"
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

echo -e "${YELLOW}Database-only restore from: $BACKUP_FILE${NC}"
echo -e "${BLUE}Note: Photos will NOT be restored${NC}"
echo ""

# Determine file type and handle accordingly
if [[ "$BACKUP_FILE" == *.sql ]]; then
    # Direct SQL file import
    echo -e "${BLUE}Direct SQL file detected${NC}"
    SQL_FILE="$BACKUP_FILE"
    EXTRACTED_DIR=""

    # Determine if it's a full or event-specific dump
    BASENAME=$(basename "$SQL_FILE")
    if [[ "$BASENAME" == "database_full.sql" ]]; then
        IS_FULL_BACKUP=true
    else
        IS_FULL_BACKUP=false
        # Try to extract event slug from filename (database_eventslug.sql)
        EVENT_SLUG=$(echo "$BASENAME" | sed 's/database_\(.*\)\.sql/\1/')
    fi

elif [[ "$BACKUP_FILE" == *.tar.gz ]]; then
    # Extract tar.gz backup
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

    # Determine backup type
    if [ -f "$EXTRACTED_DIR/database_full.sql" ]; then
        IS_FULL_BACKUP=true
        SQL_FILE="$EXTRACTED_DIR/database_full.sql"
    else
        IS_FULL_BACKUP=false
        SQL_FILE=$(find "$EXTRACTED_DIR" -name "database_*.sql" | head -n 1)

        if [ -z "$SQL_FILE" ]; then
            echo -e "${RED}Error: No database SQL file found in backup${NC}"
            rm -rf "$TEMP_DIR"
            exit 1
        fi

        # Extract event slug from filename
        EVENT_SLUG=$(basename "$SQL_FILE" | sed 's/database_\(.*\)\.sql/\1/')
    fi
else
    echo -e "${RED}Error: Unsupported file format. Use .tar.gz or .sql files${NC}"
    exit 1
fi

# Check backup type and restore
if [ "$IS_FULL_BACKUP" = true ]; then
    # Full backup restore
    echo -e "${YELLOW}This is a FULL DATABASE restore${NC}"
    echo -e "${RED}WARNING: This will replace ALL events in the database!${NC}"
    echo -e "${BLUE}Photos will NOT be affected.${NC}"
    read -p "Are you sure you want to continue? (yes/no): " CONFIRM

    if [ "$CONFIRM" != "yes" ]; then
        echo "Restore cancelled."
        rm -rf "$TEMP_DIR"
        exit 0
    fi

    echo "Restoring complete database..."
    docker-compose -f docker-compose.dev.yml exec -T db mysql \
        -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        < "$SQL_FILE"

    echo -e "${GREEN}✓ Database restored successfully${NC}"

else
    # Event-specific restore
    echo -e "${YELLOW}Restoring event database: $EVENT_SLUG${NC}"
    echo -e "${BLUE}Photos will NOT be restored${NC}"

    # Check if event already exists
    EVENT_EXISTS=$(docker-compose -f docker-compose.dev.yml exec -T db mysql \
        -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        -se "SELECT COUNT(*) FROM events WHERE event_slug='${EVENT_SLUG}'" 2>/dev/null || echo "0")

    if [ "$EVENT_EXISTS" -gt 0 ]; then
        echo -e "${YELLOW}Warning: Event '$EVENT_SLUG' already exists in database${NC}"
        read -p "Overwrite existing event database entries? (yes/no): " CONFIRM

        if [ "$CONFIRM" != "yes" ]; then
            echo "Restore cancelled."
            rm -rf "$TEMP_DIR"
            exit 0
        fi

        # Delete existing event and photos from database only
        echo "Removing existing database entries..."

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
    fi

    echo "Restoring event database..."
    docker-compose -f docker-compose.dev.yml exec -T db mysql \
        -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        < "$SQL_FILE"

    echo -e "${GREEN}✓ Event database restored${NC}"
fi

# Cleanup
if [ -n "$TEMP_DIR" ] && [ -d "$TEMP_DIR" ]; then
    echo "Cleaning up..."
    rm -rf "$TEMP_DIR"
fi

echo ""
echo -e "${GREEN}✓ Database-only restore completed successfully!${NC}"
if [ -n "$EVENT_SLUG" ]; then
    echo -e "${GREEN}Event '$EVENT_SLUG' database has been restored.${NC}"
    echo -e "${YELLOW}Note: Photos were NOT restored. Use 'make restore' for full restore.${NC}"
else
    echo -e "${GREEN}All event databases have been restored.${NC}"
    echo -e "${YELLOW}Note: Photos were NOT restored. Use 'make restore' for full restore.${NC}"
fi
