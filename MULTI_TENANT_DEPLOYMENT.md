# Multi-Tenant Deployment Guide

**Branch**: `multi-tenant`  
**Date**: November 2025  
**Status**: Ready for Deployment

---

## Pre-Deployment Checklist

### ✅ Multi-Tenant Features Implemented

- [x] Database migrations for facility customization
- [x] Facility registrations table
- [x] Multi-tenancy data isolation (Global Scopes)
- [x] Facility context middleware
- [x] Super admin dashboard and resources
- [x] Facility registration workflow
- [x] Dynamic theme system (CSS variables)
- [x] React frontend with theme support
- [x] Facility logo and color customization

---

## Deployment Steps

### 1. Environment Configuration

#### Required Environment Variables

Add these to your `.env` file for multi-tenant support:

```bash
# Application
APP_NAME="Evergreen Oasis Care Home"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Multi-Tenant Configuration
# If using subdomains, configure these:
# SESSION_DOMAIN=.your-domain.com
# SANCTUM_STATEFUL_DOMAINS=your-domain.com,*.your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=evergreen_production
DB_USERNAME=forge
DB_PASSWORD=your_secure_password

# File Storage (for facility logos)
FILESYSTEM_DISK=public
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Session (use database for multi-tenant)
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false

# Cache
CACHE_STORE=database
CACHE_PREFIX=

# Queue
QUEUE_CONNECTION=database

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"

# Logging
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error
```

### 2. Database Migrations

Run all migrations including multi-tenant specific ones:

```bash
# Check migration status
php artisan migrate:status

# Run migrations
php artisan migrate --force

# Verify multi-tenant migrations ran:
# - add_customization_to_facilities_table
# - add_facility_id_to_users_table
# - create_facility_registrations_table
# - migrate_existing_data_to_facility
```

### 3. Database Seeding

**IMPORTANT**: Create super admin account and migrate existing data:

```bash
# Seed super admin account
php artisan db:seed --class=SuperAdminSeeder

# Or run the migration that assigns existing data to default facility
# This is automatically run by the migration: migrate_existing_data_to_facility

# Default super admin credentials (CHANGE AFTER FIRST LOGIN):
# Email: superadmin@evergreen.com
# Password: SuperAdmin@2025!
```

### 4. Build Frontend Assets

Build React assets with theme support:

```bash
# Install dependencies
npm install

# Build for production
npm run build

# Verify build succeeded
ls -lh public/build
```

### 5. Storage Permissions

Ensure storage is writable for facility logo uploads:

```bash
# Set permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Create storage link if not exists
php artisan storage:link
```

### 6. Cache Optimization

Clear and rebuild all caches:

```bash
# Clear all caches
php artisan optimize:clear

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Cache events
php artisan event:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

### 7. Queue Workers (if using queues)

If using queues for notifications:

```bash
# Restart queue workers
php artisan queue:restart

# Start queue worker (use supervisor for production)
php artisan queue:work --tries=3
```

---

## Post-Deployment Verification

### 1. Test Super Admin Access

1. Login with super admin credentials
2. Verify Super Admin Dashboard appears
3. Check Facility Registrations menu item is visible
4. Verify Facilities menu item is visible

### 2. Test Facility Registration Workflow

1. Access public registration form: `/facility-registration`
2. Submit a test registration
3. As super admin, verify registration appears in Facility Registrations
4. Approve registration
5. Verify facility, branch, and owner account are created

### 3. Test Facility Customization

1. Go to Facilities page
2. Edit a facility
3. Upload a logo
4. Change primary/secondary colors
5. Save changes
6. Verify logo and colors update in the UI

### 4. Test Data Isolation

1. Create two test facilities
2. Create users for each facility
3. Login as user from Facility A
4. Verify they only see Facility A's data
5. Login as user from Facility B
6. Verify they only see Facility B's data
7. Login as super admin
8. Verify super admin can see all facilities

### 5. Test Theme System

1. Edit facility colors
2. Login as facility user
3. Verify sidebar uses facility colors
4. Verify buttons use facility colors
5. Verify forms use facility colors
6. Verify logo displays correctly

---

## Subdomain Configuration (Optional)

If you want to use subdomain routing:

### 1. DNS Configuration

Set up wildcard DNS:
```
*.your-domain.com → Your server IP
```

### 2. Web Server Configuration

#### Nginx

Add server block for subdomains:

```nginx
server {
    listen 80;
    server_name *.your-domain.com;
    
    root /home/forge/your-domain.com/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 3. Update Environment Variables

```bash
SESSION_DOMAIN=.your-domain.com
SANCTUM_STATEFUL_DOMAINS=your-domain.com,*.your-domain.com
```

### 4. Test Subdomain Access

1. Access facility via subdomain: `https://facility-subdomain.your-domain.com/app/login`
2. Verify facility context is automatically set
3. Verify correct branding is applied

---

## Troubleshooting

### Issue: Facility colors not applying

**Solution**:
1. Clear browser cache
2. Clear Laravel view cache: `php artisan view:clear`
3. Rebuild frontend assets: `npm run build`
4. Verify CSS variables are set in browser DevTools

### Issue: Logo not displaying

**Solution**:
1. Verify storage link exists: `php artisan storage:link`
2. Check file permissions: `chmod -R 775 storage`
3. Verify logo path in database
4. Check public/storage directory exists

### Issue: Data isolation not working

**Solution**:
1. Verify FacilityScope is applied to models
2. Check middleware is registered: `app/Providers/Filament/AdminPanelProvider.php`
3. Verify user has `facility_id` set
4. Check global scope is active: `php artisan tinker` → `Resident::withoutGlobalScopes()->count()`

### Issue: Super admin cannot access facilities

**Solution**:
1. Verify user role is `super_admin`
2. Check `canAccessPanel` method in User model
3. Clear config cache: `php artisan config:clear`
4. Verify super admin has no `facility_id` set (should be null)

---

## Rollback Plan

If deployment fails:

```bash
# 1. Rollback last migrations
php artisan migrate:rollback --step=4

# 2. Clear caches
php artisan optimize:clear

# 3. Revert code
git checkout <previous-commit-hash>

# 4. Rebuild assets
npm run build

# 5. Restart services
php artisan queue:restart
```

---

## Security Considerations

1. **Change default super admin password** immediately after deployment
2. **Enable SSL** for all subdomains if using subdomain routing
3. **Set APP_DEBUG=false** in production
4. **Use secure session configuration** (SESSION_ENCRYPT=true for production)
5. **Limit super admin access** - only trusted users should have super_admin role
6. **Regular backups** - ensure database backups include facility data
7. **File upload validation** - logos are validated, but ensure file size limits are set

---

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Verify migrations: `php artisan migrate:status`
3. Check routes: `php artisan route:list`
4. Verify permissions: Check file permissions on storage directories

---

## Files Modified for Multi-Tenant

### New Files
- `app/Models/FacilityRegistration.php`
- `app/Models/Scopes/FacilityScope.php`
- `app/Http/Middleware/SetFacilityContext.php`
- `app/Filament/Resources/FacilityRegistrationResource.php`
- `app/Filament/Pages/SuperAdminDashboard.php`
- `app/Filament/Widgets/SuperAdminStatsWidget.php`
- `resources/js/contexts/ThemeContext.jsx`
- `resources/js/hooks/useThemeVariables.js`
- `resources/js/utils/colorUtils.js`
- `resources/js/components/ThemeWrapper.jsx`
- `database/migrations/2025_11_19_181645_add_customization_to_facilities_table.php`
- `database/migrations/2025_11_19_181708_add_facility_id_to_users_table.php`
- `database/migrations/2025_11_19_181730_create_facility_registrations_table.php`
- `database/migrations/2025_11_19_182230_migrate_existing_data_to_facility.php`
- `database/seeders/SuperAdminSeeder.php`

### Modified Files
- `app/Models/Facility.php` - Added customization fields
- `app/Models/User.php` - Added facility_id and relationships
- `app/Models/Resident.php`, `Branch.php`, `Medication.php` - Added FacilityScope
- `app/Providers/Filament/AdminPanelProvider.php` - Added middleware, dynamic branding
- All React components - Updated to use theme CSS variables

---

**Deployment Status**: ✅ Ready for Production

