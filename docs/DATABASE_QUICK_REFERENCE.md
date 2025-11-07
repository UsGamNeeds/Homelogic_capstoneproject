# Database Quick Reference Guide

## Table Count: 28 Core Tables

### 🔐 Authentication & Security (3 tables)
- `users` - Staff members with roles and credentials
- `password_reset_tokens` - Password recovery
- `personal_access_tokens` - API authentication

### 🏢 Organization Structure (2 tables)
- `facilities` - Parent organizations
- `branches` - Individual care homes

### 👴 Resident Management (2 tables)
- `residents` - Resident profiles and medical info
- `resident_documents` - Document storage

### 💊 Medication System (3 tables)
- `medications` - Medication master list
- `resident_medications` - Prescriptions
- `medication_administrations` - Administration records (MAR)

### ❤️ Vital Signs (2 tables)
- `vital_signs` - Vital measurements
- `vital_ranges` - Normal/warning/critical ranges

### 📅 Appointments (2 tables)
- `healthcare_providers` - Provider directory
- `appointments` - Appointment scheduling

### 🧠 Behavior Tracking (3 tables)
- `behavior_categories` - Behavior groupings (ADL, Behavioral, Medical, Safety)
- `behaviors` - Individual behavior types
- `behavior_charts` - Behavior occurrence logs

### 😴 Sleep Monitoring (3 tables)
- `sleep_records` - Individual sleep sessions
- `sleep_patterns` - Monthly aggregations
- `sleep_hourly_data` - Heatmap data (24-hour breakdown)

### 📝 Assessments (3 tables)
- `assessments` - Assessment records
- `assessment_sections` - Assessment categories
- `assessment_questions` - Questions and responses

### 🚨 Incident Management (1 table)
- `incident_reports` - Comprehensive incident tracking

### 👥 Staff Management (2 tables)
- `leave_requests` - Time-off management
- `caregiver_assignments` - Shift assignments

### 💬 Communication (2 tables)
- `communications` - Internal messaging
- `tasks` - Task assignments

### 📊 System & Analytics (2 tables)
- `charts` - Chart configurations
- `audit_logs` - Complete audit trail

---

## User Roles

### 5 Role Types:
1. **super_admin** - System administrator with full access
2. **administrator** - Facility administrator
3. **clinical_supervisor** - Nursing/clinical oversight
4. **caregiver** - Direct care staff
5. **family_member** - Limited resident access

---

## Key Enum Values

### Resident Status
- `active` - Currently in care
- `inactive` - Temporarily not in care
- `discharged` - No longer a resident

### User Status
- `active` - Can access system
- `inactive` - Cannot access system
- `pending` - Awaiting activation

### Medication Instructions
- `t.i.d` - Three times daily
- `q.i.d` - Four times a day
- `b.i.d` - Twice daily
- `PRN` - As needed
- `h.s` - At bedtime
- `a.m` - Morning
- `p.m` - Evening

### Medication Administration Status
- `administered` - Given successfully
- `missed` - Not given
- `refused` - Resident refused
- `held` - Intentionally withheld

### Vital Status
- `approved` - Normal reading
- `pending_review` - Needs review
- `declined` - Resident declined
- `critical` - Immediate attention needed

### Appointment Status
- `scheduled` - Booked
- `confirmed` - Confirmed with provider
- `in_progress` - Currently happening
- `completed` - Finished
- `cancelled` - Cancelled
- `no_show` - Resident didn't attend

### Appointment Types
- `clinician` - Mental health provider
- `counsellor` - Counselor/therapist
- `primary_care` - Primary doctor
- `specialist` - Medical specialist
- `dental` - Dentist
- `vision` - Eye doctor
- `therapy` - Physical/occupational therapy
- `other` - Other appointments

### Behavior Severity
- `low` - Minor concern
- `medium` - Moderate concern
- `high` - Significant concern
- `critical` - Emergency level

### Incident Priority
- `critical` - Immediate action required
- `high` - Urgent
- `medium` - Normal priority
- `low` - Can wait

### Incident Status
- `open` - Newly reported
- `in_progress` - Being addressed
- `resolved` - Fixed
- `closed` - Completed and documented
- `on_hold` - Waiting for something

---

## Foreign Key Relationships

### Resident-Centric Relationships
```
residents
├── resident_documents
├── resident_medications
│   └── medication_administrations
├── vital_signs
├── appointments
├── behavior_charts
├── sleep_records
│   └── sleep_patterns
│       └── sleep_hourly_data
├── assessments
│   └── assessment_sections
│       └── assessment_questions
└── incident_reports (optional)
```

### Organization Hierarchy
```
facilities
└── branches
    ├── residents
    ├── vital_signs
    ├── appointments
    ├── sleep_records
    └── caregiver_assignments
```

### User Relationships
```
users (staff)
├── medication_administrations (administered_by)
├── vital_signs (taken_by)
├── behavior_charts (created_by)
├── sleep_records (created_by)
├── incident_reports (raised_by, assigned_to)
├── leave_requests (staff_id)
├── caregiver_assignments (caregiver_id)
├── communications (sender_id, recipient_id)
└── tasks (assigned_to, assigned_by)
```

---

## Commonly Used Queries

### Get Active Residents by Branch
```sql
SELECT * FROM residents 
WHERE branch_id = ? AND status = 'active' 
ORDER BY last_name, first_name;
```

### Get Pending Medications for Today
```sql
SELECT rm.*, r.first_name, r.last_name, m.name 
FROM resident_medications rm
JOIN residents r ON rm.resident_id = r.id
JOIN medications m ON rm.medication_id = m.id
WHERE rm.status = 'active' 
  AND DATE(rm.medication_time) = CURDATE()
  AND NOT EXISTS (
    SELECT 1 FROM medication_administrations ma 
    WHERE ma.resident_medication_id = rm.id 
      AND DATE(ma.administration_time) = CURDATE()
  );
```

### Get Critical Vitals
```sql
SELECT vs.*, r.first_name, r.last_name 
FROM vital_signs vs
JOIN residents r ON vs.resident_id = r.id
WHERE vs.status = 'critical' 
  AND vs.measurement_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
ORDER BY vs.measurement_date DESC;
```

### Get Open Incidents
```sql
SELECT * FROM incident_reports 
WHERE status IN ('open', 'in_progress') 
ORDER BY priority DESC, created_at DESC;
```

### Get Sleep Summary for Resident
```sql
SELECT * FROM sleep_patterns 
WHERE resident_id = ? 
  AND year = YEAR(CURDATE()) 
ORDER BY year DESC, month DESC 
LIMIT 12;
```

---

## Seeded Data (Default)

### Behavior Categories (4)
1. Activities of Daily Living (Blue - #3B82F6)
2. Behavioral Symptoms (Red - #EF4444)
3. Medical Indicators (Green - #10B981)
4. Safety Concerns (Orange - #F59E0B)

### Behaviors (11)
**ADL:**
- Meals
- Shower
- Grooming
- Medication
- Log in and out

**Behavioral:**
- Anxiety
- Agitation
- Threatening or intimidation

**Medical:**
- Blood sugar low
- Blood sugar high
- Sick / 911 call

### Vital Ranges (5 parameters)
- Systolic BP: 90-120 normal, 70-180 critical
- Diastolic BP: 60-80 normal, 40-120 critical
- Temperature: 97.7-99.5°F normal, 95-104°F critical
- Pulse: 60-100 normal, 40-140 critical
- O2 Saturation: 95-100% normal, 85-100% critical

---

## Index Strategy

**High Priority Indexes (Already Created):**
- User lookups: email, role, status
- Resident searches: branch_id, status, last_name
- Medication tracking: resident_medication_id, administration_time
- Vital monitoring: resident_id + date, status
- Appointments: resident_id + date, status
- Behavior tracking: resident_id + date, behavior_id
- Sleep records: resident_id + date
- Incidents: status + priority, raised_by
- Leave requests: staff_id + status, date range

**Additional Indexes to Consider:**
- Full-text search on resident names
- Composite indexes for common filter combinations
- Partial indexes for active records only

---

## Data Integrity Notes

1. **Soft Deletes** - Users, Residents, Branches, Facilities
2. **Cascade Deletes** - All child records when parent is deleted
3. **Set NULL** - Incidents when resident is deleted
4. **Audit Logging** - All critical operations logged
5. **Timestamps** - All tables have created_at and updated_at

---

## Security Considerations

- Passwords hashed with bcrypt
- API tokens for mobile/external access
- Audit logs track all changes
- Soft deletes preserve historical data
- Foreign keys prevent orphaned records
- Role-based access control built-in

