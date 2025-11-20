# Quick Deployment Guide - Multi-Tenant Branch

## 🚀 Fast Deployment

### 1. Run Deployment Script

```bash
./deploy-multi-tenant.sh
```

Or manually:

```bash
# Pull latest code
git pull origin multi-tenant

# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci

# Run migrations
php artisan migrate --force

# Create super admin (first time only)
php artisan db:seed --class=SuperAdminSeeder

# Build assets
npm run build

# Optimize
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Create storage link
php artisan storage:link

# Set permissions
chmod -R 775 storage bootstrap/cache
```

### 2. Configure Environment

Copy and update `.env`:

```bash
cp .env.multi-tenant.example .env
# Edit .env with your production values
php artisan key:generate
```

### 3. Verify Deployment

```bash
# Check migration status
php artisan migrate:status

# Check routes
php artisan route:list | grep facility

# Test super admin
php artisan tinker
>>> User::where('role', 'super_admin')->first()
```

### 4. Access Application

- **React Frontend**: `https://your-domain.com/app`
- **Super Admin**: Login with credentials from `SuperAdminSeeder`
- **Public Registration**: `https://your-domain.com/facility-registration`

---

## 📋 Post-Deployment Checklist

- [ ] Super admin can login
- [ ] Facility Registrations page accessible
- [ ] Can create new facility with logo and colors
- [ ] Theme colors apply correctly in UI
- [ ] Data isolation works (test with multiple facilities)
- [ ] Logo uploads work
- [ ] Storage link exists (`public/storage` → `storage/app/public`)

---

## 🔧 Common Issues

**Assets not loading?**
```bash
npm run build
php artisan optimize:clear
```

**Logo not displaying?**
```bash
php artisan storage:link
chmod -R 775 storage
```

**Theme colors not applying?**
```bash
npm run build
php artisan view:clear
# Clear browser cache
```

---

For detailed deployment guide, see `MULTI_TENANT_DEPLOYMENT.md`

