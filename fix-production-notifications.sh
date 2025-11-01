#!/bin/bash

# Fix Production Notifications - Verify and setup cron job

echo "🔔 Checking production notification setup..."

# Check if cron job exists
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    echo "✅ Cron job for schedule:run exists"
    crontab -l | grep "schedule:run"
else
    echo "❌ No cron job found for schedule:run"
    echo ""
    echo "To fix this, add the following cron job in Laravel Forge:"
    echo "  - Command: php artisan schedule:run"
    echo "  - User: forge"
    echo "  - Directory: /home/forge/evergreen-gpga9dpd.on-forge.com"
    echo "  - Frequency: * * * * * (every minute)"
fi

echo ""
echo "Testing notification generation..."
php artisan notifications:generate

echo ""
echo "Check recent notifications:"
php artisan tinker --execute="echo 'Notifications count: ' . App\Models\Notification::count() . PHP_EOL; echo 'Recent 5:' . PHP_EOL; App\Models\Notification::latest()->limit(5)->get(['id', 'title', 'created_at', 'is_read'])->each(fn(\$n) => print_r(\$n->toArray()));"

