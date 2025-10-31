# React Frontend Setup

## Overview
We've set up a modern React frontend for your healthcare management system, matching the design from the screenshots you provided.

## What's Been Set Up

### 1. React Application Structure
- **React Router** for client-side routing
- **TanStack Query** for data fetching and caching
- **Axios** for API requests
- **Lucide React** for icons
- **Tailwind CSS** for styling (already configured)

### 2. Components & Pages
- **Layout Component**: Dark blue sidebar navigation matching screenshot design
- **Dashboard Page**: Statistics cards with modern UI
- **Residents Page**: Search functionality with resident cards
- **Appointments Page**: Filter controls and appointment cards
- **Other Pages**: Placeholder pages for Vitals, Medications, Reports

### 3. API Structure
- API routes at `/api/v1/*`
- Controllers for Residents, Appointments, Dashboard
- JSON responses ready for React consumption

## How to Use

### Development Mode
1. Start Laravel server:
   ```bash
   php artisan serve
   ```

2. Start Vite dev server:
   ```bash
   npm run dev
   ```

3. Access the React app at: `http://localhost:8000/app`

### Production Build
```bash
npm run build
```

## File Structure

```
resources/js/
├── App.jsx                 # Main React app component
├── app.jsx                 # React entry point
├── components/
│   └── Layout.jsx          # Main layout with sidebar
├── pages/
│   ├── Dashboard.jsx       # Dashboard with stats
│   ├── Residents.jsx       # Residents listing
│   ├── Appointments.jsx    # Appointments management
│   └── ...
└── services/
    └── api.js              # Axios API client

routes/
└── api.php                 # API routes

app/Http/Controllers/Api/
├── ResidentController.php
├── AppointmentController.php
├── DashboardController.php
└── AuthController.php
```

## Next Steps

1. **Install Laravel Sanctum** for API authentication:
   ```bash
   composer require laravel/sanctum
   php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
   php artisan migrate
   ```

2. **Update User Model** to use HasApiTokens trait:
   ```php
   use Laravel\Sanctum\HasApiTokens;
   
   class User extends Authenticatable
   {
       use HasApiTokens, HasFactory, Notifiable;
       // ...
   }
   ```

3. **Add Authentication** to API routes middleware

4. **Complete API endpoints** for remaining resources (Vitals, Medications, etc.)

5. **Enhance React components** with more features and polish

## Design Features

- ✅ Dark blue sidebar navigation (matching screenshots)
- ✅ Clean white main content area
- ✅ Card-based layouts
- ✅ Modern filter controls
- ✅ Responsive design
- ✅ Professional healthcare management UI

## Notes

- The React app runs alongside Filament admin panel
- Filament remains accessible at `/admin/*`
- React app is accessible at `/app/*`
- Both can coexist in the same Laravel application

