#!/bin/bash

# Multi-Tenant Deployment Script for Evergreen
# Branch: multi-tenant
# Usage: ./deploy-multi-tenant.sh

set -e  # Exit on any error

echo "🚀 Starting Multi-Tenant Deployment for Evergreen..."
echo "Branch: multi-tenant"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo -e "${GREEN}✓${NC} Working directory: $(pwd)"
echo ""

# Step 1: Check Git status
echo -e "${YELLOW}[1/10]${NC} Checking Git status..."
if ! git status &> /dev/null; then
    echo -e "${RED}✗${NC} Not a git repository"
    exit 1
fi

CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "multi-tenant" ]; then
    echo -e "${YELLOW}⚠${NC} Current branch is '$CURRENT_BRANCH', expected 'multi-tenant'"
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi
echo -e "${GREEN}✓${NC} On branch: $CURRENT_BRANCH"
echo ""

# Step 2: Pull latest changes
echo -e "${YELLOW}[2/10]${NC} Pulling latest changes from repository..."
git pull origin multi-tenant || {
    echo -e "${RED}✗${NC} Failed to pull from repository"
    exit 1
}
echo -e "${GREEN}✓${NC} Repository updated"
echo ""

# Step 3: Install/Update Composer dependencies
echo -e "${YELLOW}[3/10]${NC} Installing Composer dependencies..."
composer install --optimize-autoloader --no-dev --no-interaction || {
    echo -e "${RED}✗${NC} Failed to install Composer dependencies"
    exit 1
}
echo -e "${GREEN}✓${NC} Composer dependencies installed"
echo ""

# Step 4: Install/Update NPM dependencies
echo -e "${YELLOW}[4/10]${NC} Installing NPM dependencies..."
if [ -f "package.json" ]; then
    npm ci --production=false || {
        echo -e "${RED}✗${NC} Failed to install NPM dependencies"
        exit 1
    }
    echo -e "${GREEN}✓${NC} NPM dependencies installed"
else
    echo -e "${YELLOW}⚠${NC} package.json not found, skipping NPM install"
fi
echo ""

# Step 5: Run database migrations
echo -e "${YELLOW}[5/10]${NC} Running database migrations..."
php artisan migrate --force --no-interaction || {
    echo -e "${RED}✗${NC} Failed to run migrations"
    exit 1
}
echo -e "${GREEN}✓${NC} Migrations completed"
echo ""

# Step 6: Check if super admin exists, create if needed
echo -e "${YELLOW}[6/10]${NC} Checking super admin account..."
php artisan db:seed --class=SuperAdminSeeder --force || {
    echo -e "${YELLOW}⚠${NC} Super admin seeder failed or already exists (this is OK)"
}
echo -e "${GREEN}✓${NC} Super admin check completed"
echo ""

# Step 7: Build frontend assets
echo -e "${YELLOW}[7/10]${NC} Building frontend assets..."
if [ -f "package.json" ]; then
    npm run build || {
        echo -e "${RED}✗${NC} Failed to build frontend assets"
        exit 1
    }
    echo -e "${GREEN}✓${NC} Frontend assets built"
else
    echo -e "${YELLOW}⚠${NC} package.json not found, skipping build"
fi
echo ""

# Step 8: Create storage link
echo -e "${YELLOW}[8/10]${NC} Creating storage symlink..."
php artisan storage:link || {
    echo -e "${YELLOW}⚠${NC} Storage link already exists or failed (this may be OK)"
}
echo -e "${GREEN}✓${NC} Storage link checked"
echo ""

# Step 9: Clear and cache configuration
echo -e "${YELLOW}[9/10]${NC} Optimizing application caches..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
echo -e "${GREEN}✓${NC} Caches optimized"
echo ""

# Step 10: Set permissions
echo -e "${YELLOW}[10/10]${NC} Setting file permissions..."
if [ -d "storage" ]; then
    chmod -R 775 storage bootstrap/cache 2>/dev/null || {
        echo -e "${YELLOW}⚠${NC} Could not set permissions (may need sudo)"
    }
    echo -e "${GREEN}✓${NC} Permissions set"
else
    echo -e "${YELLOW}⚠${NC} Storage directory not found"
fi
echo ""

# Restart queue workers if using queues
if command -v php &> /dev/null && php artisan queue:restart &> /dev/null; then
    echo -e "${GREEN}✓${NC} Queue workers restarted"
    echo ""
fi

# Deployment summary
echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ✅ Multi-Tenant Deployment Completed Successfully!${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════${NC}"
echo ""
echo "Next steps:"
echo "  1. Verify super admin account: php artisan tinker"
echo "     → User::where('role', 'super_admin')->first()"
echo ""
echo "  2. Test facility registration workflow:"
echo "     → Visit: https://your-domain.com/facility-registration"
echo ""
echo "  3. Test facility customization:"
echo "     → Login as super admin"
echo "     → Go to Facilities → Edit facility → Upload logo, change colors"
echo ""
echo "  4. Verify theme system:"
echo "     → Login as facility user"
echo "     → Check sidebar colors and logo match facility settings"
echo ""
echo -e "${YELLOW}⚠  IMPORTANT: Change default super admin password!${NC}"
echo ""

