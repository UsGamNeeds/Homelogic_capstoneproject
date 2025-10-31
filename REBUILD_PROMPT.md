# 🏥 Edmond Serenity AFH - Complete System Rebuild Prompt

## Project Vision
Build a comprehensive healthcare management system for assisted living facilities using **Laravel 12** and **Filament 3.2**. The system should manage residents, staff, medications, appointments, assessments, vital signs, behavior tracking, sleep monitoring, and incidents with role-based access control and intuitive dashboards.

---

## 📋 Technology Stack

### Core Technologies
- **Backend Framework**: Laravel 12.x
- **PHP Version**: 8.2+ (8.3 recommended)
- **Admin Panel**: Filament 3.2
- **Database**: MySQL (production) / SQLite (development)
- **Frontend**: Blade templates with Tailwind CSS
- **Authentication**: Laravel's built-in authentication with role-based permissions
- **Real-time**: Livewire (built into Filament)

### Key Packages (in composer.json)
```json
{
  "php": "^8.2",
  "filament/filament": "^3.2",
  "laravel/framework": "^12.0",
  "laravel/tinker": "^2.10.1"
}
```

---

## 🗄️ Complete Database Schema (28 Core Tables)

### Authentication & Security (3 tables)
1. **users** - Staff members with roles (super_admin, administrator, clinical_supervisor, caregiver, family_member)
2. **password_reset_tokens** - Password recovery system
3. **personal_access_tokens** - API authentication

### Organization Structure (2 tables)
4. **facilities** - Parent organizations
5. **branches** - Individual care homes

### Resident Management (2 tables)
6. **residents** - Resident profiles with demographics, medical history, allergies, room assignments
7. **resident_documents** - Document storage (insurance, medical, legal)

### Medication System (3 tables)
8. **medications** - Medication master list with codes and instructions
9. **resident_medications** - Resident-specific prescriptions with schedules
10. **medication_administrations** - MAR (Medication Administration Records) with timestamps

### Vital Signs (2 tables)
11. **vital_signs** - Blood pressure, temperature, pulse, O2 saturation, pain levels
12. **vital_ranges** - Normal/warning/critical ranges per vital sign

### Appointments (2 tables)
13. **healthcare_providers** - Provider directory (doctors, specialists, therapists)
14. **appointments** - Appointment scheduling with recurrence patterns

### Behavior Tracking (3 tables)
15. **behavior_categories** - ADL, Behavioral Symptoms, Medical Indicators, Safety Concerns
16. **behaviors** - Individual behavior types within categories
17. **behavior_charts** - Behavior occurrence logs with severity and interventions

### Sleep Monitoring (3 tables)
18. **sleep_records** - Individual sleep sessions
19. **sleep_patterns** - Monthly sleep aggregations
20. **sleep_hourly_data** - 24-hour heatmap data

### Assessments (3 tables)
21. **assessments** - Assessment records (initial, periodic, focused, discharge)
22. **assessment_sections** - Assessment categories (demographic, medical, functional, cognitive, behavioral, nutritional, environmental, risk)
23. **assessment_questions** - Questions and responses

### Incident Management (1 table)
24. **incident_reports** - Comprehensive incident tracking with priority levels

### Staff Management (2 tables)
25. **leave_requests** - Staff leave management
26. **caregiver_assignments** - Shift assignments (caregiver-resident-branch relationships)

### Communication & Tasks (2 tables)
27. **communications** - Internal messaging system
28. **tasks** - Task assignments with priorities

### System & Analytics (2 tables)
29. **charts** - Chart configurations for analytics
30. **audit_logs** - Complete audit trail for compliance

---

## 👥 User Roles & Permissions

### Role Hierarchy
1. **super_admin** - Full system access, all permissions
2. **administrator** - Facility management, resident/staff oversight
3. **clinical_supervisor** - Clinical oversight, approve critical decisions
4. **caregiver** - Direct care documentation (vitals, meds, behaviors, sleep)
5. **family_member** - Limited read-only access to assigned resident data

### Role-Based Dashboards
- **Admin Dashboard**: System-wide statistics, facility management
- **Caregiver Dashboard**: Assigned residents, quick actions, daily tasks
- **Nurse Dashboard**: Clinical oversight, medication approvals

---

## 🎨 Filament Resources & Pages

### Core Resources (CRUD Interfaces)
1. **UserResource** - Staff management with role assignments
2. **ResidentResource** - Complete resident profiles
3. **FacilityResource** - Facility management
4. **BranchResource** - Branch management
5. **MedicationResource** - Prescription management with drug selection
6. **MedicationAdministrationResource** - MAR with branch filtering
7. **DrugResource** - Medication database management
8. **VitalSignResource** - Vital signs recording
9. **VitalRangeResource** - Vital ranges configuration
10. **AppointmentResource** - Appointment scheduling
11. **HealthcareProviderResource** - Provider directory management
12. **AssessmentResource** - Assessment management
13. **LeaveRequestResource** - Leave request management
14. **IncidentResource** - Incident report management
15. **BehaviorResource** - Behavior tracking
16. **BehaviorCategoryResource** - Behavior categorization
17. **SleepRecordResource** - Sleep pattern recording
18. **AssignmentResource** - Caregiver-resident assignments

### Custom Dashboard Pages
1. **Dashboard** (`/admin/dashboard`) - Main entry point with role-based routing
2. **AdminDashboard** (`/admin/admin-dashboard`) - Admin statistics and overview
3. **CaregiverDashboard** (`/admin`) - Caregiver dashboard with 6 stat cards + activity chart
4. **AssessmentDashboard** (`/admin/assessment-dashboard`) - Assessment management hub
5. **CustomAppointments** (`/admin/custom-appointments`) - Card-based appointment management
6. **AppointmentHistory** (`/admin/appointment-history`) - Historical appointment view
7. **ResidentAppointments** (`/admin/resident-appointments`) - Resident-specific appointments
8. **MedicationManagement** (`/admin/medication-management`) - Medication overview
9. **MedicationCalendar** (`/admin/medication-calendar`) - Medication calendar view
10. **MedicationHistory** (`/admin/medication-history`) - Medication administration history
11. **ViewVitals** (`/admin/view-vitals`) - Vital signs viewing interface
12. **ResidentManager** (`/admin/resident-manager`) - Advanced resident management
13. **AssessmentPage** (`/admin/assessment-page`) - Assessment creation workflow
14. **AssessmentForm** (`/admin/assessment-form`) - Dynamic assessment forms
15. **ChartReports** (`/admin/chart-reports`) - Reporting hub

### Chart & Report Pages
16. **Reports** (`/admin/reports`) - Main reports page
17. **VitalsCharts** (`/admin/vitals-charts`) - Vital signs visualization
18. **VitalsReports** (`/admin/vitals-reports`) - Vital signs reporting
19. **VitalsHistory** (`/admin/vitals-history`) - Vital signs history
20. **AppointmentsCharts** (`/admin/appointments-charts`) - Appointment analytics
21. **AssessmentsCharts** (`/admin/assessments-charts`) - Assessment analytics
22. **SleepCharts** (`/admin/sleep-charts`) - Sleep pattern visualization
23. **StaffCharts** (`/admin/staff-charts`) - Staff performance analytics
24. **ResidentCharts** (`/admin/resident-charts`) - Resident analytics

### Management Pages
25. **AssignResident** (`/admin/assign-resident`) - Resident assignment tool

---

## 📦 Filament Widgets

### Statistics Widgets
1. **CaregiverOverviewStatsWidget** - 6 stat cards for caregivers (using StatsOverviewWidget)
   - My Residents
   - Today's Appointments
   - Pending Assessments
   - Vitals Recorded Today
   - Leave Requests Pending
   - Weekly Appointments
2. **CaregiverWeeklyActivityChartWidget** - Line chart showing vital signs and assessments trend (using ChartWidget)
3. **ResidentStatsWidget** - Total residents, caregivers, branches, active assignments
4. **ActivityStatsWidget** - Pending assessments, today's vitals, upcoming appointments, pending leave
5. **StatsOverviewWidget** - System-wide statistics
6. **AdminStatsWidget** - Admin-specific statistics
7. **CaregiverStatsWidget** - Legacy widget (can be removed)
8. **ReportsStatsWidget** - Report statistics

### Chart Widgets
9. **VitalTrendsChartWidget** - Vital signs trends over time
10. **AssessmentChartWidget** - Assessment completion chart
11. **BranchChartWidget** - Branch statistics chart
12. **MedicationComplianceWidget** - Medication compliance chart
13. **FinancialSummaryWidget** - Financial overview

### Data Widgets
14. **MyResidentsWidget** - List of assigned residents with health status
15. **CaregiverDataVisualizationWidget** - Health data overview with charts
16. **TodayTasksWidget** - Today's tasks for caregivers
17. **UpcomingAppointmentsWidget** - Upcoming appointments table
18. **RecentAssessmentsWidget** - Recent assessment activity
19. **RecentVitalsWidget** - Recent vital signs
20. **RecentMedicationsWidget** - Recent medication activity
21. **RecentMedicationActivityWidget** - Medication activity log
22. **HeroSectionWidget** - Dashboard hero section
23. **QuickActionsWidget** - Quick action buttons

---

## 🎯 Key Features & Functionality

### 1. Role-Based Access Control
- Granular permissions per resource
- Navigation filtering by role
- Data filtering based on assignments
- Custom dashboard per role
- Route protection via Filament's canAccess()

### 2. Smart Routing
- Root URL (`/`) → Redirects to `/admin/login`
- `/admin/dashboard` → Role-based redirect to appropriate dashboard
- Admins → AdminDashboard (`/admin/admin-dashboard`)
- Caregivers → CaregiverDashboard (`/admin`)
- All dashboards use Filament's BaseDashboard with widgets

### 3. Dynamic Forms & Relationships
- Cascading dropdowns: Branch → Resident → Medication
- Dynamic time pickers based on medication frequency
- Auto-populate forms with relevant data
- Relationship-based data loading
- Form validation rules

### 4. Data Visualization
- Chart.js integration for charts
- Widget-based dashboard design
- Statistics cards with icons and colors
- Trend analysis and reporting
- Real-time updates

### 5. Healthcare-Specific Features
- Medication Administration Records (MAR)
- Vital sign range checking with alerts
- Behavior tracking with categories
- Sleep pattern monitoring with heatmaps
- Assessment workflow management
- Incident reporting and tracking
- Leave request management
- Caregiver-resident assignments

---

## 🏗️ Project Structure

```
Evergreen/
├── app/
│   ├── Console/Commands/
│   ├── Filament/
│   │   ├── Navigation/         # Custom navigation provider
│   │   ├── Pages/              # 26 custom dashboard pages
│   │   ├── Resources/          # 79 CRUD resources
│   │   └── Widgets/            # 31 dashboard widgets
│   ├── Http/Controllers/
│   ├── Models/                 # 22 Eloquent models
│   └── Providers/
├── config/                     # Laravel configuration files
├── database/
│   ├── migrations/             # 22 active migrations
│   ├── seeders/               # 34 seeders
│   └── database.sqlite
├── resources/
│   ├── css/                   # Custom CSS
│   ├── js/                    # JavaScript files
│   └── views/                 # 39 Blade templates
├── routes/
│   └── web.php                # Routes configuration
├── public/
│   ├── build/                 # Compiled assets
│   ├── css/                   # Public CSS
│   ├── js/                    # Public JS
│   └── images/                # Brand assets
└── storage/
    ├── app/                   # File storage
    ├── framework/             # Cache
    └── logs/                  # Log files
```

---

## 🚀 Installation & Setup Instructions

### Step 1: Project Initialization
```bash
# Create new Laravel 12 project
composer create-project laravel/laravel evergreen --prefer-dist

# Navigate to project
cd evergreen

# Install Filament
composer require filament/filament
php artisan filament:install --panels
```

### Step 2: Database Configuration
```bash
# Copy environment file
cp .env.example .env

# Configure database in .env
# For development:
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite

# For production:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=edmond_serenity_afh
DB_USERNAME=forge
DB_PASSWORD=your_secure_password

# Generate app key
php artisan key:generate
```

### Step 3: Run Migrations
```bash
# Create all 30 tables
php artisan migrate

# Seed initial data
php artisan db:seed
```

### Step 4: Create Filament Admin Panel
```bash
# Install admin panel
php artisan make:filament-user

# Configure panel provider
php artisan make:filament-panel admin
```

### Step 5: Install Dependencies
```bash
# Backend dependencies
composer install

# Frontend dependencies
npm install

# Build assets
npm run build
```

### Step 6: File Permissions
```bash
# Set proper permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Step 7: Start Development Server
```bash
# Start Laravel server
php artisan serve

# Access at http://127.0.0.1:8000/admin/login
```

---

## 🔧 Filament Panel Configuration

### Admin Panel Provider
File: `app/Providers/Filament/AdminPanelProvider.php`

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login()
        ->colors([
            'primary' => '#2D5016',   // Dark green
            'gray' => '#654321',      // Dark brown
            'success' => '#2D5016',
            'warning' => '#D4A574',   // Warm beige
            'danger' => '#8B4513',    // Dark brown
        ])
        ->discoverResources(in: app_path('Filament/Resources'))
        ->discoverPages(in: app_path('Filament/Pages'))
        ->discoverWidgets(in: app_path('Filament/Widgets'))
        ->navigationGroups([
            'Dashboard',
            'Resident Care',
            'Medications',
            'Staff Management',
            'Reports',
            'Administration',
        ])
        ->topNavigation()
        ->brandName('Evergreen Oasis Care Home')
        ->brandLogo(asset('images/logo.jpeg'))
        ->brandLogoHeight('3rem')
        ->maxContentWidth('full')
        ->renderHook(
            'panels::topbar.end',
            fn (): string => view('filament.components.user-menu'),
        );
}
```

---

## 📝 Custom Pages Configuration

### Dashboard Routing Logic
File: `app/Filament/Pages/Dashboard.php`

**Extends**: `Filament\Pages\Page` (NOT BaseDashboard)

**Purpose**: Main entry point that routes users to role-specific dashboards

**Logic**:
- Admins/Administrators → `filament.admin.pages.admin-dashboard`
- Caregivers/Nurses → `filament.admin.pages.caregiver-dashboard`
- Default fallback → Admin dashboard
- Not authenticated → Login page

### AdminDashboard Configuration
File: `app/Filament/Pages/AdminDashboard.php`

**Extends**: `Filament\Pages\Dashboard as BaseDashboard`

**Critical Property**: 
```php
protected static string $routePath = 'admin-dashboard';
```

**Widgets**: 4 widgets in 2 columns
- ResidentStatsWidget
- ActivityStatsWidget
- VitalTrendsChartWidget
- BranchChartWidget

**Access Control**: Only admin/administrator/super_admin roles

### CaregiverDashboard Configuration
File: `app/Filament/Pages/CaregiverDashboard.php`

**Extends**: `Filament\Pages\Dashboard as BaseDashboard`

**Widgets**: 2 widgets using default Filament widgets
- CaregiverOverviewStatsWidget (StatsOverviewWidget) - 6 stat cards
- CaregiverWeeklyActivityChartWidget (ChartWidget) - Line chart

**Access Control**: caregiver/care_giver/nurse/registered_nurse/licensed_nurse roles

---

## 🎨 Default Filament Widgets (NOT Custom Blade)

### Key Principle
**USE FILAMENT'S DEFAULT WIDGETS** - Do NOT create custom Blade-based widgets.

### Recommended Widget Types
1. **StatsOverviewWidget** - For stat cards (extends Filament\Widgets\StatsOverviewWidget)
2. **ChartWidget** - For charts (extends Filament\Widgets\ChartWidget)
3. **TableWidget** - For data tables (extends Filament\Widgets\TableWidget)

### Example: StatsOverviewWidget
```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CaregiverOverviewStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('My Residents', $count)
                ->description('Assigned to me')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
            // ... more stats
        ];
    }
}
```

### Example: ChartWidget
```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class CaregiverWeeklyActivityChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Weekly Activity';
    
    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getData(): array
    {
        return [
            'datasets' => [...],
            'labels' => [...],
        ];
    }
}
```

---

## 🌐 Routes Configuration

### Web Routes
File: `routes/web.php`

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/login');
});
```

**Purpose**: Root URL redirects to admin login

---

## 🎨 Design System

### Brand Identity
- **Name**: Evergreen Oasis Care Home
- **Logo**: Green tree logo (public/images/logo.jpeg)
- **Colors**: 
  - Primary: Dark green (#2D5016)
  - Secondary: Dark brown (#654321)
  - Success: Dark green (#2D5016)
  - Warning: Warm beige (#D4A574)
  - Danger: Dark brown (#8B4513)

### UI Guidelines
- **Navigation**: Top navigation bar
- **Layout**: Full width content
- **Cards**: Filament's default card styling
- **Charts**: Chart.js for data visualization
- **Icons**: Heroicons
- **Responsive**: Mobile-first design

---

## 📊 Key Business Logic

### Medication Administration
- Dynamic time fields based on frequency (b.i.d = 2 times, t.i.d = 3 times, q.i.d = 4 times)
- Branch filtering when administering
- Status tracking: administered, missed, refused, held
- Verification workflow for controlled substances

### Vital Signs
- Range checking against configured normal/warning/critical ranges
- Automatic status assignment based on values
- Trend visualization over time
- Critical alert system

### Behavior Tracking
- Category-based organization
- Severity levels: low, medium, high, critical
- Trigger and intervention documentation
- Provider notification workflow

### Caregiver Assignments
- Multi-assignment support (caregiver can have multiple residents)
- Active/inactive assignment tracking
- Shift-based assignments
- Assignment history

---

## 🔐 Default Credentials

### After Seeding
- **Admin Email**: admin@edmondserenity.com
- **Admin Password**: admin123!
- **Role**: super_admin with all permissions

**⚠️ SECURITY**: Change immediately after first deployment!

---

## 📈 Performance Optimizations

### Database Indexes
- User lookups: email, role, status
- Resident searches: branch_id, status, last_name
- Medication tracking: resident_medication_id, administration_time
- Vital monitoring: resident_id + date, status
- Appointments: resident_id + date, status

### Caching
- Config cache: `php artisan config:cache`
- Route cache: `php artisan route:cache`
- View cache: `php artisan view:cache`
- Optimize autoloader: `composer dump-autoload`

---

## 🚢 Deployment Configuration

### Production Environment
- **Server**: Laravel Forge
- **PHP**: 8.3+
- **Database**: MySQL 8.0+
- **Queue**: Database driver
- **Cache**: Database driver
- **Session**: Database driver

### Deployment Script
```bash
#!/bin/bash
cd /home/forge/your-domain.com
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear
sudo service php8.3-fpm restart
php artisan queue:restart
```

---

## 🧪 Testing Requirements

### Unit Tests
- Model relationships
- Business logic methods
- Permission checks

### Feature Tests
- Authentication flows
- Role-based access
- Dashboard routing
- Widget data loading

### Integration Tests
- Database operations
- File uploads
- Complex workflows

---

## 📚 Key Documentation Files

1. **REBUILD_PROMPT.md** - This comprehensive rebuild guide
2. **WEBSITE_PROMPT.md** - Marketing website specifications
3. **FORGE_DEPLOYMENT_GUIDE.md** - Production deployment steps
4. **docs/DATABASE_SCHEMA.md** - Complete database schema reference
5. **docs/DATABASE_QUICK_REFERENCE.md** - Quick reference guide
6. **docs/PROJECT_PLAN.md** - Project timeline and sprints
7. **docs/README.md** - Project overview

---

## ✅ Implementation Checklist

### Phase 1: Foundation
- [ ] Laravel 12 project setup
- [ ] Filament 3.2 installation
- [ ] Database schema (30 tables)
- [ ] All Eloquent models (22 models)
- [ ] Basic authentication
- [ ] Role and permission system

### Phase 2: Core Resources
- [ ] UserResource (staff management)
- [ ] ResidentResource
- [ ] FacilityResource & BranchResource
- [ ] MedicationResource
- [ ] MedicationAdministrationResource
- [ ] VitalSignResource
- [ ] AppointmentResource
- [ ] AssessmentResource
- [ ] IncidentResource
- [ ] LeaveRequestResource
- [ ] BehaviorResource
- [ ] SleepRecordResource

### Phase 3: Dashboards
- [ ] Dashboard (main entry with routing)
- [ ] AdminDashboard with 4 widgets
- [ ] CaregiverDashboard with 2 widgets
- [ ] Custom widget implementation
- [ ] Navigation configuration
- [ ] Role-based access control

### Phase 4: Custom Pages
- [ ] AssessmentDashboard
- [ ] CustomAppointments
- [ ] AppointmentHistory
- [ ] MedicationManagement
- [ ] ViewVitals
- [ ] ResidentManager
- [ ] All chart and report pages

### Phase 5: Widgets
- [ ] All statistics widgets
- [ ] All chart widgets
- [ ] All data widgets
- [ ] Widget data fetching logic
- [ ] Chart rendering

### Phase 6: Advanced Features
- [ ] Branch filtering
- [ ] Dynamic form fields
- [ ] File uploads
- [ ] Export functionality
- [ ] Search and filtering
- [ ] Pagination

### Phase 7: Styling & Polish
- [ ] Brand colors and logo
- [ ] Navigation styling
- [ ] Custom CSS
- [ ] Responsive design
- [ ] Icon system

### Phase 8: Testing & Deployment
- [ ] Unit tests
- [ ] Feature tests
- [ ] Performance testing
- [ ] Security audit
- [ ] Production deployment
- [ ] Documentation

---

## 🔍 Important Implementation Notes

### Dashboard Architecture
1. **Dashboard** extends `Page` - Main router, never displayed
2. **AdminDashboard** extends `BaseDashboard` - Admin widgets, needs `$routePath = 'admin-dashboard'`
3. **CaregiverDashboard** extends `BaseDashboard` - Caregiver widgets, root route
4. Use **default Filament widgets** (StatsOverviewWidget, ChartWidget, TableWidget)
5. DO NOT create custom Blade-based widgets

### Route Registration
- Filament auto-discovers pages via `discoverPages()`
- Custom routes need `$routePath` property
- `$slug` property optional for BaseDashboard pages
- Routes cached with `php artisan route:cache`

### Widget Best Practices
- Use inherited widget classes from Filament
- Keep widgets focused on single responsibility
- Filter data based on authenticated user
- Handle null/empty states gracefully
- Use descriptive widget names

### Security
- Soft deletes on critical tables
- Foreign key constraints
- Audit logging for compliance
- Role-based access control
- Input validation
- SQL injection protection via Eloquent

---

## 🎯 Success Criteria

### Functional Requirements
✅ Admins can manage all system entities
✅ Caregivers can track assigned residents
✅ Medications tracked with proper MAR
✅ Vital signs recorded with range checking
✅ Behaviors logged by category
✅ Sleep patterns visualized
✅ Appointments scheduled and tracked
✅ Assessments created and managed
✅ Incidents reported and resolved
✅ Leave requests processed

### Technical Requirements
✅ Laravel 12 compatibility
✅ Filament 3.2 integration
✅ MySQL database schema
✅ Role-based access control
✅ Default Filament widgets
✅ Responsive design
✅ Mobile-optimized
✅ Secure authentication
✅ Audit logging

### Performance Requirements
✅ < 2s page load times
✅ Efficient database queries
✅ Proper indexing
✅ Caching strategy
✅ Optimized assets
✅ Scalable architecture

---

## 🔗 Key Reference Links

### Documentation
- Laravel: https://laravel.com/docs/12.x
- Filament: https://filamentphp.com/docs/3.x
- Chart.js: https://www.chartjs.org/docs/

### Important Commands
```bash
# Development
php artisan serve
php artisan migrate:fresh --seed
php artisan optimize:clear

# Production
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --no-dev --optimize-autoloader

# Debugging
php artisan tinker
tail -f storage/logs/laravel.log
php artisan route:list
```

---

## 🚨 Critical Considerations

1. **Database**: MySQL in production, SQLite for development
2. **Widgets**: Use Filament's default widget classes ONLY
3. **Dashboard Pages**: BaseDashboard for widget-based dashboards, Page for router
4. **Route Paths**: Explicitly set for custom routes
5. **Cache Clearing**: Always clear after code changes
6. **Role Permissions**: Test thoroughly with different roles
7. **Responsive**: Ensure mobile-friendly interface
8. **Security**: Follow HIPAA considerations for healthcare data
9. **Performance**: Index database properly
10. **Testing**: Test all workflows before deployment

---

This comprehensive guide contains everything needed to rebuild the Edmond Serenity AFH system from scratch. Follow the implementation checklist sequentially for best results.
