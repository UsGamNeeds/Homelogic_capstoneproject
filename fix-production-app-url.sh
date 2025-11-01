#!/bin/bash

# Fix Production APP_URL for Profile Images
# This script should be run on your production server

echo "🔧 Fixing APP_URL in production..."

# Detect the production domain
PROJECT_DIR="/home/forge/evergreen-gpga9dpd.on-forge.com"
cd "$PROJECT_DIR/current" || cd "$PROJECT_DIR" || exit 1

# Check current APP_URL
echo "📋 Current APP_URL:"
php artisan config:show app.url

# Update .env file with correct APP_URL
echo "🔨 Updating .env file..."
sed -i 's|APP_URL=http://127.0.0.1:8000|APP_URL=https://evergreen-gpga9dpd.on-forge.com|g' .env

# Clear and recache configuration
echo "🧹 Clearing configuration cache..."
php artisan config:clear
php artisan cache:clear

echo "✅ APP_URL fixed!"
echo "🌐 Profile images should now load correctly from: https://evergreen-gpga9dpd.on-forge.com"

