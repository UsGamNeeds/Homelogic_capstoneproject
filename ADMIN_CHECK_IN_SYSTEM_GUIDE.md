# Facility Administrator Guide: Check-In/Check-Out System

## Overview

As a facility administrator, you have **full access** to view, manage, and report on all check-in/check-out activities in your facility. You can access this system through two interfaces:

1. **Filament Admin Panel** (Recommended for detailed management and reports)
2. **React App Pages** (For operational use and quick access)

---

## 🔐 Access Methods

### Method 1: Filament Admin Panel (Full Management)

**URL**: `https://your-domain.com/admin`

**Login**: Use your administrator credentials

#### Navigation Paths:

1. **Staff Clock-Ins**
   - Navigate: **Staff Management** → **Staff Clock-Ins**
   - Direct URL: `/admin/staff-clock-ins`

2. **Resident Sign-Outs**
   - Navigate: **Resident Management** → **Resident Sign-Outs**
   - Direct URL: `/admin/resident-sign-outs`

3. **Visitors**
   - Navigate: **Operations** → **Visitors**
   - Direct URL: `/admin/visitors`

---

## 📊 Staff Clock-Ins Management

### Features Available:

✅ **View All Clock-Ins**
- See complete history of all staff clock-in/out records
- Filter by date range, staff member, branch, status
- View active clock-ins (currently clocked in)
- View completed clock-ins (already clocked out)

✅ **Detailed Information**
- Staff member name
- Branch/Facility location
- Clock-in time and location coordinates
- Clock-out time and location coordinates
- Total hours worked (automatically calculated)
- Clock method (authenticated vs public)
- Status (Active/Completed)

✅ **Filtering & Search**
- Filter by status (Active/Completed)
- Filter by clock method (Authenticated/Public)
- Filter by date range
- Search by staff name
- Filter by branch

✅ **Actions**
- View individual clock-in details
- Edit clock-in records (for corrections)
- Manually create clock-in records
- Delete records (if needed)

✅ **Export & Reports**
- Export filtered data
- View detailed statistics
- Track attendance patterns

### Quick Access:
```
Admin Panel → Staff Management → Staff Clock-Ins
```

---

## 🏠 Resident Sign-Outs Management

### Features Available:

✅ **View All Sign-Outs**
- Complete history of resident movements
- Filter by resident, date range, status
- View active sign-outs (currently out)
- View overdue sign-outs (past expected return time)
- View completed sign-outs (already returned)

✅ **Detailed Information**
- Resident name
- Sign-out time and date
- Sign-in time (when returned)
- Destination
- Purpose of trip
- Accompanied by (if applicable)
- Expected return time
- Emergency contact notification status
- Signed out by / Signed in by (staff member)

✅ **Filtering & Search**
- Filter by status (Out/Overdue/Returned)
- Filter by resident
- Filter by date range
- Filter by branch
- Search by resident name

✅ **Actions**
- View individual sign-out details
- Edit sign-out records
- Manually create sign-out records
- Sign residents in (if they forgot to sign in)
- Mark overdue alerts

✅ **Overdue Alerts**
- Automatic identification of overdue residents
- Visual indicators for overdue status
- Filter to show only overdue sign-outs

### Quick Access:
```
Admin Panel → Resident Management → Resident Sign-Outs
```

---

## 👥 Visitors Management

### Features Available:

✅ **View All Visitors**
- Complete visitor check-in history
- Filter by status (Active/Checked Out)
- Filter by date range
- Filter by who they're visiting (resident or staff)

✅ **Detailed Information**
- Visitor name and contact information
- Check-in time
- Check-out time (when applicable)
- Visit purpose
- Who they're visiting (resident/staff)
- Expected duration
- Checked in by / Checked out by (staff member)
- Notes

✅ **Filtering & Search**
- Filter by status (Active/Checked Out)
- Filter by visiting resident
- Filter by visiting staff
- Filter by date range
- Filter by branch
- Search by visitor name

✅ **Actions**
- View individual visitor details
- Edit visitor records
- Check visitors out manually
- Create new visitor records
- View active visitors only

### Quick Access:
```
Admin Panel → Operations → Visitors
```

---

## 📱 React App Access (Operational Use)

**Base URL**: `https://your-domain.com/app/login`

After logging in, access these pages through the navigation menu:

### Navigation Menu Access:

In the React app sidebar, you'll find a **"Check-In/Out"** section with:
- Staff Clock-In/Out
- Resident Sign-Outs  
- Visitors

Simply click on the "Check-In/Out" menu item to expand and access all three features.

### Direct URL Access:

### Available Pages:

1. **Staff Clock-In/Out**
   - **URL**: `/app/staff/clock`
   - **Purpose**: For staff to clock in/out with stats and history
   - **Features**:
     - Real-time clock in/out
     - Today's hours worked
     - Weekly/monthly statistics
     - Recent clock-in history
     - Location verification

2. **Resident Sign-Outs**
   - **URL**: `/app/residents/sign-out`
   - **Purpose**: Sign residents out/in operationally
   - **Features**:
     - Quick resident search
     - Sign out with details
     - View active sign-outs
     - View overdue alerts
     - Sign residents back in

3. **Visitors**
   - **URL**: `/app/visitors`
   - **Purpose**: Check visitors in/out operationally
   - **Features**:
     - Check in new visitors
     - View active visitors
     - Search and filter
     - Check visitors out

---

## 📈 Reports & Analytics

### Current Reporting Capabilities:

#### In Filament Admin Panel:

1. **Staff Clock-Ins Reports**
   - Filter by date range
   - Filter by staff member
   - Filter by branch
   - View total hours per staff
   - Export data for payroll

2. **Resident Sign-Outs Reports**
   - Filter by date range
   - Filter by resident
   - View overdue reports
   - Export sign-out history

3. **Visitor Reports**
   - Filter by date range
   - Filter by visiting resident/staff
   - View visitor logs
   - Export visitor data

### Export Options:
- Export filtered data to CSV/Excel
- Print reports
- View detailed records

---

## 🎯 Common Administrative Tasks

### 1. View Today's Active Clock-Ins
```
Admin Panel → Staff Management → Staff Clock-Ins → Filter: Status = Active
```

### 2. Check Overdue Resident Sign-Outs
```
Admin Panel → Resident Management → Resident Sign-Outs → Filter: Status = Overdue
```

### 3. View Active Visitors
```
Admin Panel → Operations → Visitors → Filter: Status = Active
```

### 4. Generate Monthly Attendance Report
```
Admin Panel → Staff Management → Staff Clock-Ins → 
Filter: Date Range (start/end of month) → Export
```

### 5. Manually Correct a Clock-In Record
```
Admin Panel → Staff Management → Staff Clock-Ins → 
Find record → Edit → Update times/details → Save
```

### 6. View Staff Member's Clock-In History
```
Admin Panel → Staff Management → Staff Clock-Ins → 
Filter: Staff Member → View all records
```

---

## 🔍 Data Visibility

### What You Can See:

✅ **All Staff Clock-Ins** (for your facility)
- Full history
- Location coordinates
- Hours worked
- Clock method used

✅ **All Resident Sign-Outs** (for your facility)
- Complete movement history
- Overdue status
- Expected returns

✅ **All Visitors** (for your facility)
- Complete visitor log
- Active visitors
- Visit history

### Data Scope:
- Facility administrators see **all data for their facility**
- Super admins see **all facilities**
- Data is automatically filtered by facility context

---

## 📋 Recommended Workflow

### Daily Operations:
1. **Morning**: Check active visitors from previous day
2. **Throughout Day**: Monitor active staff clock-ins
3. **Afternoon**: Check for overdue resident sign-outs
4. **End of Day**: Review today's clock-ins and visitor activity

### Weekly Reports:
1. **Monday**: Review previous week's attendance
2. Generate weekly attendance report
3. Check for any anomalies or missing clock-outs

### Monthly Tasks:
1. Generate monthly attendance reports for payroll
2. Review visitor statistics
3. Analyze resident sign-out patterns
4. Export data for records

---

## 🚨 Important Alerts & Notifications

### Automatic Alerts:

- **Overdue Resident Sign-Outs**: Automatically flagged when past expected return time
- **Active Clock-Ins**: See who is currently clocked in
- **Active Visitors**: See who is currently in the facility

### Manual Monitoring:

- Check "Active" filters regularly
- Review "Overdue" status for resident sign-outs
- Monitor clock-in patterns for attendance issues

---

## 💡 Tips for Administrators

1. **Use Filters Effectively**: Combine multiple filters to get precise data
2. **Export Regularly**: Export monthly reports for record-keeping
3. **Monitor Active Records**: Regularly check active clock-ins and sign-outs
4. **Set Up Alerts**: Check overdue resident sign-outs daily
5. **Review Patterns**: Use date range filters to identify attendance patterns
6. **Location Verification**: Check location coordinates to verify clock-in authenticity

---

## 🔗 Quick Links

- **Filament Admin Panel**: `/admin`
- **Staff Clock-Ins**: `/admin/staff-clock-ins`
- **Resident Sign-Outs**: `/admin/resident-sign-outs`
- **Visitors**: `/admin/visitors`
- **React App Login**: `/app/login`

---

## 📞 Need Help?

If you need additional features or have questions:
- Check the main system documentation
- Contact system support
- Review API documentation for advanced integrations

---

## 🔄 System Updates

All check-in/check-out data is logged in real-time and:
- Automatically calculates total hours worked
- Tracks location for staff clock-ins
- Flags overdue resident returns
- Maintains complete audit trail
- Supports export for payroll/reporting

