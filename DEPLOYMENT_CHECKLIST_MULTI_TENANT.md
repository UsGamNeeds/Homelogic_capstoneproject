# Multi-Tenant Deployment Checklist

**Branch**: `multi-tenant`  
**Date**: November 2025

---

## Pre-Deployment

### Environment Setup
- [ ] Copy `forge.env.example` to `.env` on production server
- [ ] Update `APP_URL` in `.env` with production domain
- [ ] Set `APP_DEBUG=false`
- [ ] Generate `APP_KEY` if not set: `php artisan key:generate`
- [ ] Configure database credentials in `.env`
- [ ] Set `SESSION_DRIVER=database` for multi-tenant sessions
- [ ] Configure `FILESYSTEM_DISK=public` for logo storage
- [ ] (Optional) Set `SESSION_DOMAIN` and `SANCTUM_STATEFUL_DOMAINS` if using subdomains

### Server Requirements
- [ ] PHP >= 8.2
- [ ] MySQL >= 8.0
- [ ] Composer installed
- [ ] Node.js and npm installed
- [ ] Storage directory writable: `chmod -R 775 storage bootstrap/cache`
- [ ] Public storage symlink: `php artisan storage:link`

---

## Deployment Steps

### 1. Code Deployment
- [ ] Pull latest code: `git pull origin multi-tenant`
- [ ] Verify branch is correct: `git branch --show-current`
- [ ] Run deployment script: `./deploy-multi-tenant.sh` OR `./deploy.sh`

### 2. Dependencies
- [ ] Install Composer packages: `composer install --optimize-autoloader --no-dev`
- [ ] Install NPM packages: `npm ci`
- [ ] Build frontend assets: `npm run build`
- [ ] Verify build succeeded: Check `public/build` directory exists

### 3. Database
- [ ] Run migrations: `php artisan migrate --force`
- [ ] Verify multi-tenant migrations ran:
  - [ ] `add_customization_to_facilities_table`
  - [ ] `add_facility_id_to_users_table`
  - [ ] `create_facility_registrations_table`
  - [ ] `migrate_existing_data_to_facility`
- [ ] Create super admin: `php artisan db:seed --class=SuperAdminSeeder`
- [ ] Verify super admin exists: `php artisan tinker` → `User::where('role', 'super_admin')->count()`

### 4. Storage Setup
- [ ] Create storage symlink: `php artisan storage:link`
- [ ] Verify symlink: `ls -la public/storage` should link to `storage/app/public`
- [ ] Set permissions: `chmod -R 775 storage bootstrap/cache`
- [ ] Create directories: `mkdir -p storage/app/public/facilities/logos`

### 5. Caching
- [ ] Clear all caches: `php artisan optimize:clear`
- [ ] Cache configuration: `php artisan config:cache`
- [ ] Cache routes: `php artisan route:cache`
- [ ] Cache views: `php artisan view:cache`
- [ ] Cache events: `php artisan event:cache`
- [ ] Optimize autoloader: `composer install --optimize-autoloader`

### 6. Services
- [ ] Restart PHP-FPM: `sudo service php8.2-fpm restart` (or your PHP version)
- [ ] Restart queue workers: `php artisan queue:restart`
- [ ] Verify scheduler is running: Check cron job for `php artisan schedule:run`

---

## Post-Deployment Verification

### 1. Super Admin Access
- [ ] Login with super admin credentials (check SuperAdminSeeder)
- [ ] Verify Super Admin Dashboard loads
- [ ] Verify "Facility Registrations" menu item visible
- [ ] Verify "Facilities" menu item visible
- [ ] **IMPORTANT**: Change default super admin password!

### 2. Facility Registration Workflow
- [ ] Access public registration: `/facility-registration`
- [ ] Submit test registration form
- [ ] As super admin, verify registration appears in Facility Registrations
- [ ] Approve registration
- [ ] Verify facility is created
- [ ] Verify branch is created
- [ ] Verify owner account is created
- [ ] Test owner account login

### 3. Facility Customization
- [ ] Edit a facility
- [ ] Upload a logo file
- [ ] Verify logo uploads successfully
- [ ] Change primary color
- [ ] Change secondary color
- [ ] Change accent color
- [ ] Save changes
- [ ] Verify colors persist in database

### 4. Theme System
- [ ] Login as facility user
- [ ] Verify sidebar uses facility primary color
- [ ] Verify logo displays in sidebar
- [ ] Verify buttons use facility colors
- [ ] Verify form inputs use facility colors on focus
- [ ] Verify links use facility colors
- [ ] Check browser DevTools for CSS variables (--theme-primary, --theme-secondary)

### 5. Data Isolation
- [ ] Create two test facilities
- [ ] Create users for each facility
- [ ] Login as user from Facility A
- [ ] Verify they only see Facility A's residents
- [ ] Verify they only see Facility A's branches
- [ ] Login as user from Facility B
- [ ] Verify they only see Facility B's data
- [ ] Login as super admin
- [ ] Verify super admin can see all facilities
- [ ] Verify super admin can see all registrations

### 6. API Endpoints
- [ ] Test `/api/user` returns facility_branding
- [ ] Test `/api/facilities` endpoint
- [ ] Test `/api/facility-registrations` endpoint
- [ ] Verify API responses include correct facility data

### 7. File Uploads
- [ ] Upload facility logo
- [ ] Verify file saves to `storage/app/public/facilities/logos/`
- [ ] Verify logo accessible via URL
- [ ] Test logo display in UI

---

## Subdomain Configuration (Optional)

If using subdomain routing:

- [ ] Configure DNS wildcard: `*.your-domain.com`
- [ ] Update Nginx/Apache config for subdomain routing
- [ ] Set `SESSION_DOMAIN=.your-domain.com` in `.env`
- [ ] Set `SANCTUM_STATEFUL_DOMAINS=your-domain.com,*.your-domain.com` in `.env`
- [ ] Test subdomain access: `https://facility-subdomain.your-domain.com/app/login`
- [ ] Verify facility context is set automatically
- [ ] Verify correct branding is applied

---

## Performance Checks

- [ ] Page load times are acceptable
- [ ] API response times are good
- [ ] Asset loading is optimized
- [ ] Database queries are efficient (check slow query log)
- [ ] No N+1 query problems

---

## Security Checks

- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` in production
- [ ] Super admin password changed from default
- [ ] SSL certificate installed and working
- [ ] File uploads validated (logo size/type)
- [ ] CSRF protection enabled
- [ ] Rate limiting configured (if needed)
- [ ] Sensitive data not exposed in responses

---

## Rollback Plan

If deployment fails:

```bash
# 1. Rollback migrations
php artisan migrate:rollback --step=4

# 2. Clear caches
php artisan optimize:clear

# 3. Revert code
git checkout <previous-commit>

# 4. Rebuild assets
npm run build

# 5. Restart services
php artisan queue:restart
sudo service php-fpm restart
```

---

## Troubleshooting

### Issue: Assets not loading
**Fix**: `npm run build && php artisan optimize:clear`

### Issue: Logo not displaying
**Fix**: `php artisan storage:link && chmod -R 775 storage`

### Issue: Theme colors not applying
**Fix**: `npm run build && php artisan view:clear` + clear browser cache

### Issue: Data isolation not working
**Fix**: Verify `FacilityScope` is applied, check middleware registration

### Issue: Super admin cannot access
**Fix**: Verify role is `super_admin`, clear config cache: `php artisan config:clear`

---

## Files Created for Deployment

- ✅ `MULTI_TENANT_DEPLOYMENT.md` - Detailed deployment guide
- ✅ `DEPLOYMENT_QUICK_START.md` - Quick reference guide
- ✅ `deploy-multi-tenant.sh` - Automated deployment script
- ✅ `DEPLOYMENT_CHECKLIST_MULTI_TENANT.md` - This checklist
- ✅ `forge.env.example` - Updated with multi-tenant config
- ✅ `deploy.sh` - Updated with multi-tenant steps

---

## Deployment Sign-Off

- [ ] All pre-deployment checks completed
- [ ] All deployment steps completed
- [ ] All post-deployment verifications passed
- [ ] Security checks completed
- [ ] Performance verified
- [ ] Documentation updated

**Deployment Date**: _______________  
**Deployed By**: _______________  
**Production URL**: _______________

---

**Status**: ✅ Ready for Deployment

