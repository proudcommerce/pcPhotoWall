#!/bin/bash
set -e

# Entrypoint script for PC PhotoWall
# Ensures Composer dependencies are installed before starting Apache

echo "Starting PC PhotoWall entrypoint..."

# Set HOME environment variable for Composer (required when running as root)
export HOME="${HOME:-/root}"
export COMPOSER_HOME="${COMPOSER_HOME:-/tmp/composer}"

# Detect Apache user (www-data, apache, httpd, etc.)
# Check common environment variables and fallback to detecting from Apache config
if [ -n "$APACHE_RUN_USER" ]; then
    APACHE_USER="$APACHE_RUN_USER"
    APACHE_GROUP="${APACHE_RUN_GROUP:-$APACHE_RUN_USER}"
elif [ -f /etc/apache2/envvars ]; then
    # Debian/Ubuntu style
    . /etc/apache2/envvars
    APACHE_USER="${APACHE_RUN_USER:-www-data}"
    APACHE_GROUP="${APACHE_RUN_GROUP:-www-data}"
else
    # Fallback: try to detect from running process or use common defaults
    APACHE_USER=$(grep -E '^User' /etc/apache2/apache2.conf /etc/httpd/conf/httpd.conf 2>/dev/null | awk '{print $2}' | head -1)
    APACHE_GROUP=$(grep -E '^Group' /etc/apache2/apache2.conf /etc/httpd/conf/httpd.conf 2>/dev/null | awk '{print $2}' | head -1)

    # Final fallback to www-data if detection failed
    APACHE_USER="${APACHE_USER:-www-data}"
    APACHE_GROUP="${APACHE_GROUP:-www-data}"
fi

echo "Detected Apache user: $APACHE_USER:$APACHE_GROUP"

# Function to safely change ownership (works with rootless Docker)
# Falls back to chmod if chown fails (rootless scenarios)
safe_chown() {
    local target="$1"

    if chown -R "$APACHE_USER:$APACHE_GROUP" "$target" 2>/dev/null; then
        echo "  ✓ Changed ownership: $target -> $APACHE_USER:$APACHE_GROUP"
        return 0
    else
        # Rootless Docker or insufficient permissions - use chmod instead
        echo "  ⚠ Cannot change ownership (rootless mode?), ensuring write permissions instead"
        chmod -R 777 "$target" 2>/dev/null || true
        return 1
    fi
}

# Check if vendor directory exists and has dependencies
if [ ! -d "/var/www/html/vendor" ] || [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    echo "Composer dependencies not found. Attempting to install..."

    # Check if composer.json exists
    if [ ! -f "/var/www/html/composer.json" ]; then
        echo "ERROR: composer.json not found in /var/www/html/"
        echo "Cannot install dependencies without composer.json"
        exit 1
    fi

    # Check if Composer is available
    if command -v composer >/dev/null 2>&1; then
        echo "Installing dependencies with Composer..."
        cd /var/www/html
        composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

        # Fix ownership of vendor directory (created as root)
        echo "Fixing vendor directory permissions..."
        safe_chown /var/www/html/vendor

        echo "Composer dependencies installed successfully."
    else
        echo "ERROR: Composer not found in container"
        echo "Please either:"
        echo "  1. Install dependencies locally: cd app && composer install"
        echo "  2. Rebuild Docker image: docker-compose build --no-cache"
        exit 1
    fi
else
    echo "Composer dependencies already installed."

    # Ensure vendor directory has correct permissions even if already installed
    if [ -d "/var/www/html/vendor" ]; then
        echo "Verifying vendor directory permissions..."
        safe_chown /var/www/html/vendor
    fi
fi

# Ensure required directories exist with proper permissions
echo "Setting up application directories..."
mkdir -p /var/www/html/uploads /var/www/html/data /var/www/html/logs

# Try to set ownership, fallback to permissions if rootless
safe_chown /var/www/html/uploads
safe_chown /var/www/html/data
safe_chown /var/www/html/logs

# Ensure at least readable/writable permissions
chmod -R 755 /var/www/html/uploads /var/www/html/data /var/www/html/logs 2>/dev/null || true

echo "Starting Apache..."
exec apache2-foreground
