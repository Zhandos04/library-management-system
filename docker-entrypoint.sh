#!/bin/bash
set -e

# Wait for MySQL to be ready
if [ -n "$DB_HOST" ]; then
    echo "Waiting for MySQL to be ready..."
    
    ATTEMPTS=0
    MAX_ATTEMPTS=30
    until mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1" >/dev/null 2>&1; do
        ATTEMPTS=$((ATTEMPTS+1))
        if [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; then
            echo "Error: MySQL is not available after $MAX_ATTEMPTS attempts."
            exit 1
        fi
        echo "Waiting for MySQL to be ready... ($ATTEMPTS/$MAX_ATTEMPTS)"
        sleep 2
    done
    
    echo "MySQL is ready!"
fi

# Copy .env.example to .env if .env doesn't exist
if [ ! -f /var/www/html/.env ]; then
    echo "Creating .env file from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
    echo "Please update your .env file with proper credentials."
fi

# Set proper permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Execute passed command
exec "$@"