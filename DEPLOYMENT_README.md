# Deployment Files - Multi-Tenant Branch

This directory contains all necessary files for deploying the multi-tenant version of Evergreen.

---

## 📁 Deployment Files Overview

### 1. **MULTI_TENANT_DEPLOYMENT.md** ⭐ MAIN GUIDE
Complete deployment guide with step-by-step instructions, configuration details, troubleshooting, and security considerations.

### 2. **DEPLOYMENT_QUICK_START.md** ⚡ QUICK REFERENCE
Fast deployment commands and quick reference for experienced users.

### 3. **DEPLOYMENT_CHECKLIST_MULTI_TENANT.md** ✅ CHECKLIST
Comprehensive checklist to ensure nothing is missed during deployment.

### 4. **deploy-multi-tenant.sh** 🤖 AUTOMATED SCRIPT
Full-featured deployment script with error handling and status messages.

### 5. **deploy.sh** (Updated)
Updated existing deployment script with multi-tenant steps.

### 6. **forge.env.example** (Updated)
Environment configuration template with multi-tenant settings.

---

## 🚀 Quick Start

### Option 1: Automated Deployment (Recommended)

```bash
# Make script executable (if needed)
chmod +x deploy-multi-tenant.sh

# Run deployment
./deploy-multi-tenant.sh
```

### Option 2: Manual Deployment

Follow the steps in `DEPLOYMENT_QUICK_START.md`

### Option 3: Using Existing Script

```bash
./deploy.sh
```

---

## 📋 Pre-Deployment Checklist

Before deploying, ensure:

- [ ] `.env` file is configured (use `forge.env.example` as template)
- [ ] Database credentials are correct
- [ ] `APP_DEBUG=false` for production
- [ ] `APP_URL` is set to production domain
- [ ] Storage directory is writable
- [ ] PHP >= 8.2 and required extensions installed
- [ ] Node.js and npm installed for frontend build

---

## 🔑 Key Multi-Tenant Features

1. **Facility Registration System**
   - Public registration form
   - Super admin approval workflow
   - Automatic facility, branch, and owner account creation

2. **Dynamic Theme System**
   - Facility logo customization
   - Primary, secondary, and accent color schemes
   - CSS variable-based theming across entire interface

3. **Data Isolation**
   - Global scopes automatically filter by facility
   - Middleware protection prevents cross-facility access
   - Super admins can view all facilities

4. **Super Admin Dashboard**
   - Dedicated dashboard for system management
   - Facility registration management
   - Facility customization interface

---

## 📦 What's Included in This Deployment

### New Migrations
- ✅ Facility customization fields (logo, colors, subdomain)
- ✅ Facility ID on users table
- ✅ Facility registrations table
- ✅ Data migration for existing records

### New Components
- ✅ Super Admin Dashboard
- ✅ Facility Registration Resource
- ✅ Theme Context and Provider
- ✅ CSS Variable System
- ✅ React Theme Components

### Updated Components
- ✅ All React pages use facility theme
- ✅ Layout component with dynamic branding
- ✅ Form components with theme colors
- ✅ UI components with CSS variables

---

## 🎯 Deployment Steps Summary

1. **Configure Environment** - Update `.env` file
2. **Pull Code** - `git pull origin multi-tenant`
3. **Install Dependencies** - `composer install` & `npm ci`
4. **Run Migrations** - `php artisan migrate --force`
5. **Create Super Admin** - `php artisan db:seed --class=SuperAdminSeeder`
6. **Build Assets** - `npm run build`
7. **Optimize** - Cache config, routes, views
8. **Verify** - Test all features

---

## 🔐 Default Credentials

After first deployment, super admin account is created:
- **Email**: `superadmin@evergreen.com`
- **Password**: `SuperAdmin@2025!`

⚠️ **IMPORTANT**: Change this password immediately after first login!

---

## 📞 Support & Troubleshooting

For detailed troubleshooting, see:
- `MULTI_TENANT_DEPLOYMENT.md` - Troubleshooting section
- `DEPLOYMENT_CHECKLIST_MULTI_TENANT.md` - Verification steps

Common issues:
- Assets not loading → `npm run build`
- Logo not displaying → `php artisan storage:link`
- Theme not working → Clear browser cache + rebuild assets
- Data isolation issues → Check FacilityScope is applied

---

## 📚 Additional Documentation

- `MULTI_TENANT_DEPLOYMENT.md` - Full deployment guide
- `FACILITY_OWNER_LOGIN_GUIDE.md` - Guide for facility owners
- `public/multi-tenant-plan.html` - Visual architecture plan

---

## ✅ Deployment Verification

After deployment, verify:
1. Super admin can login
2. Facility Registrations page works
3. Can create facility with logo and colors
4. Theme applies correctly in UI
5. Data isolation works (test with multiple facilities)

---

**Ready to deploy!** 🚀

Use the checklist in `DEPLOYMENT_CHECKLIST_MULTI_TENANT.md` to ensure everything is verified.

