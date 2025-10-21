#!/bin/bash
# Event Backup Script
# Creates a backup of event images and database entries

set -e

# Docker Compose Befehl automatisch erkennen (docker-compose vs docker compose)
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
else
    DOCKER_COMPOSE="docker compose"
fi

# Environment detection function
detect_environment() {
    local dev_running=false
    local prod_running=false
    
    # Check if dev environment is running (look for phpmyadmin container)
    if $DOCKER_COMPOSE -f docker-compose.dev.yml ps | grep -q "phpmyadmin.*Up" 2>/dev/null; then
        dev_running=true
    fi
    
    # Check if prod environment is running (no phpmyadmin container)
    if $DOCKER_COMPOSE ps | grep -q "Up" 2>/dev/null && ! $DOCKER_COMPOSE ps | grep -q "phpmyadmin" 2>/dev/null; then
        prod_running=true
    fi
    
    if [ "$dev_running" = true ]; then
        echo "development"
    elif [ "$prod_running" = true ]; then
        echo "production"
    else
        echo "none"
    fi
}

# Detect running environment
ENVIRONMENT=$(detect_environment)

# Set compose file based on environment
case "$ENVIRONMENT" in
    "production")
        COMPOSE_FILE=""
        echo -e "${BLUE}Using production environment${NC}"
        ;;
    "development")
        COMPOSE_FILE="-f docker-compose.dev.yml"
        echo -e "${BLUE}Using development environment${NC}"
        ;;
    "none")
        echo -e "${RED}Error: No Docker containers are running.${NC}"
        echo -e "${YELLOW}Start with 'make dev-up' or 'make prod-up'${NC}"
        exit 1
        ;;
esac

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

BACKUP_DIR="./backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Function to display usage
usage() {
    echo "Usage: $0 [event-slug|--all]"
    echo ""
    echo "Options:"
    echo "  event-slug    Backup specific event by slug"
    echo "  --all         Backup all events and complete database"
    echo ""
    echo "Examples:"
    echo "  $0 my-event-2024"
    echo "  $0 --all"
    exit 1
}

# Check if argument provided
if [ $# -eq 0 ]; then
    usage
fi

EVENT_SLUG=$1

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Environment already detected above, no need to check again

if [ "$EVENT_SLUG" = "--all" ]; then
    # Full backup
    BACKUP_NAME="full_backup_${TIMESTAMP}"
    BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"

    echo -e "${YELLOW}Creating full backup...${NC}"
    mkdir -p "$BACKUP_PATH"

    # Backup complete database
    echo "Backing up complete database..."
    $DOCKER_COMPOSE $COMPOSE_FILE exec -T db mysqldump \
        -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        > "$BACKUP_PATH/database_full.sql"

    # Backup all uploads (from app/data - the application uses DATA_PATH)
    echo "Backing up all uploads..."
    if [ -d "./app/data" ]; then
        cp -r ./app/data "$BACKUP_PATH/data"
        echo "  Backed up app/data"
    else
        echo -e "${YELLOW}Warning: app/data directory not found${NC}"
    fi

    # Create metadata file
    cat > "$BACKUP_PATH/backup_info.txt" << EOF
Backup Type: Full Backup
Backup Date: $(date '+%Y-%m-%d %H:%M:%S')
Database: ${DB_NAME}
Includes: All events, complete database, all uploads
EOF

    # Create compressed archive
    echo "Compressing backup..."
    cd "$BACKUP_DIR"
    tar -czf "${BACKUP_NAME}.tar.gz" "$BACKUP_NAME"
    rm -rf "$BACKUP_NAME"
    cd - > /dev/null

    echo -e "${GREEN}✓ Full backup created: $BACKUP_DIR/${BACKUP_NAME}.tar.gz${NC}"

else
    # Event-specific backup
    BACKUP_NAME="${EVENT_SLUG}_backup_${TIMESTAMP}"
    BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"

    echo -e "${YELLOW}Creating backup for event: $EVENT_SLUG${NC}"

    # Check if event exists in database
    EVENT_CHECK=$($DOCKER_COMPOSE $COMPOSE_FILE exec -T db mysql \
        -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        -se "SELECT COUNT(*) FROM events WHERE event_slug='${EVENT_SLUG}'")

    if [ "$EVENT_CHECK" -eq 0 ]; then
        echo -e "${RED}Error: Event '$EVENT_SLUG' not found in database${NC}"
        exit 1
    fi

    mkdir -p "$BACKUP_PATH"

    # Backup event data from database
    echo "Backing up event database entries..."

    # First, get the event ID for this slug
    EVENT_ID=$($DOCKER_COMPOSE $COMPOSE_FILE exec -T db mysql \
        -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        -se "SELECT id FROM events WHERE event_slug='${EVENT_SLUG}'")

    # Backup events table (using event_slug)
    $DOCKER_COMPOSE $COMPOSE_FILE exec -T db mysqldump \
        -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        events \
        --where="event_slug='${EVENT_SLUG}'" \
        > "$BACKUP_PATH/database_${EVENT_SLUG}.sql"

    # Backup photos table (using event_id)
    $DOCKER_COMPOSE $COMPOSE_FILE exec -T db mysqldump \
        -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        photos \
        --where="event_id=${EVENT_ID}" \
        >> "$BACKUP_PATH/database_${EVENT_SLUG}.sql"

    # Backup event images (from app/data - the application uses DATA_PATH)
    echo "Backing up event images..."
    PHOTO_COUNT=0
    EVENT_DATA_DIR="./app/data/${EVENT_SLUG}"

    if [ -d "$EVENT_DATA_DIR" ]; then
        echo "  Found images in app/data/${EVENT_SLUG}"
        cp -r "$EVENT_DATA_DIR" "$BACKUP_PATH/data"
        # Count photos
        if [ -d "$EVENT_DATA_DIR/photos" ]; then
            PHOTO_COUNT=$(find "$EVENT_DATA_DIR/photos" -type f 2>/dev/null | wc -l)
        fi
    else
        echo -e "${YELLOW}Warning: No data directory found for event (app/data/${EVENT_SLUG})${NC}"
    fi

    # Create metadata file
    cat > "$BACKUP_PATH/backup_info.txt" << EOF
Backup Type: Event Backup
Event Slug: ${EVENT_SLUG}
Backup Date: $(date '+%Y-%m-%d %H:%M:%S')
Photo Count: ${PHOTO_COUNT}
Database: ${DB_NAME}
EOF

    # Create compressed archive
    echo "Compressing backup..."
    cd "$BACKUP_DIR"
    tar -czf "${BACKUP_NAME}.tar.gz" "$BACKUP_NAME"
    rm -rf "$BACKUP_NAME"
    cd - > /dev/null

    echo -e "${GREEN}✓ Event backup created: $BACKUP_DIR/${BACKUP_NAME}.tar.gz${NC}"
    echo -e "${GREEN}  Photos: ${PHOTO_COUNT}${NC}"
fi

echo ""
echo -e "${GREEN}Backup completed successfully!${NC}"
echo -e "To restore this backup, use: ./scripts/restore-event.sh $BACKUP_DIR/${BACKUP_NAME}.tar.gz"
