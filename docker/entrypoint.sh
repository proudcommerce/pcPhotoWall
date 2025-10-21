#!/bin/bash
set -e

# Entrypoint script for PC PhotoWall
# Verifies dependencies and prepares environment before starting Apache

echo "Starting PC PhotoWall entrypoint..."

# Verify vendor directory exists (should be copied from builder stage)
if [ ! -d "/var/www/html/vendor" ] || [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    echo "WARNING: Composer dependencies not found in /var/www/html/vendor/"
    echo "This should not happen if the Docker image was built correctly."
    echo "The dependencies should be installed during the build stage."
    echo "Please rebuild the Docker image: docker-compose build --no-cache"
else
    echo "Composer dependencies verified."
fi

# Ensure required directories exist with proper permissions
mkdir -p /var/www/html/uploads /var/www/html/data /var/www/html/logs
chown -R www-data:www-data /var/www/html/uploads /var/www/html/data /var/www/html/logs
chmod -R 755 /var/www/html/uploads /var/www/html/data /var/www/html/logs

echo "Starting Apache..."
exec apache2-foreground
