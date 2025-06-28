#!/bin/sh

DB_PATH="/database/recipe.db"

if [ ! -f "$DB_PATH" ]; then
    php /code/init_db.php
else
    echo "Database already exists at $DB_PATH"
fi

# Start PHP-FPM (the main service)
php-fpm
