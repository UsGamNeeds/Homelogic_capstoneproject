

# Install/update Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Run Filament upgrade after Composer install
echo "🔧 Running Filament upgrade..."
php artisan filament:upgrade --force

# Install/update NPM dependencies and build assets
echo "🎨 Building frontend assets..."
npm ci
npm run build

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Seed production data (only on first deployment)
if [ ! -f .deployed ]; then
    echo "🌱 Seeding production database..."
    php artisan db:seed --class=ProductionSeeder --force
    touch .deployed
fi

# Clear and cache configuration
echo "⚡ Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Clear application cache
php artisan cache:clear

# Restart PHP-FPM
echo "🔄 Restarting PHP-FPM..."
sudo service php8.3-fpm restart

# Restart queue workers (if using queues)
echo "🔄 Restarting queue workers..."
php artisan queue:restart

echo "✅ Deployment completed successfully!"
echo "🌐 Application is now live at: https://your-domain.com"