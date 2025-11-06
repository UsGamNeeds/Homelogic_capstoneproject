# Activity Logging System

The Evergreen application includes a comprehensive activity logging system that tracks user actions, model changes, and system events for audit and monitoring purposes.

## Features

- **Automatic Logging**: Models using the `Loggable` trait automatically log create, update, and delete events
- **Manual Logging**: Use `ActivityLogService` to log custom events
- **Multiple Log Types**: Activity, Audit, Error, and System logs
- **Rich Context**: Logs include user, IP address, user agent, branch, and additional properties
- **Filament Integration**: View and filter logs through the admin panel

## Database Schema

The `activity_logs` table stores:
- **Log Type**: `activity`, `audit`, `error`, or `system`
- **Event**: The action performed (e.g., `created`, `updated`, `deleted`, `viewed`, `login`)
- **Subject**: The model that was acted upon (polymorphic relationship)
- **Description**: Human-readable description of the action
- **Properties**: JSON field for changed attributes, old/new values, etc.
- **Context**: JSON field for additional context (URL, method, IP, etc.)
- **User**: The user who performed the action
- **Branch**: The branch associated with the action
- **Level**: Log level (`debug`, `info`, `warning`, `error`, `critical`)
- **Timestamps**: When the action occurred

## Usage

### Automatic Logging with Loggable Trait

Add the `Loggable` trait to any model to automatically log create, update, and delete events:

```php
use App\Traits\Loggable;

class Resident extends Model
{
    use Loggable;
    // ...
}
```

### Manual Logging

Use the `ActivityLogService` for custom logging:

```php
use App\Services\ActivityLogService;

// Log a custom activity
ActivityLogService::activity(
    event: 'exported',
    description: 'Exported resident data to CSV',
    subject: $resident,
    properties: ['format' => 'csv', 'record_count' => 50]
);

// Log a view
ActivityLogService::viewed($resident);

// Log a user login
ActivityLogService::login($user);

// Log an error
ActivityLogService::error(
    description: 'Failed to process medication administration',
    subject: $medication,
    properties: ['error_code' => 'MED001']
);
```

### Logging from Models

Models using the `Loggable` trait can also log custom activities:

```php
$resident->logActivity('exported', 'Resident data exported');
$resident->logView();
```

### Controlling Logging

You can control what gets logged by setting properties on your model:

```php
// Disable logging for a specific operation
$model->disableLogging = true;
$model->save(); // This won't be logged

// Only log specific events (define in model)
protected static $logEvents = ['created', 'deleted'];

// Exclude specific events (define in model)
protected static $logExcept = ['updated'];
```

## Viewing Logs

### In Filament Admin Panel

Navigate to **Administration > Activity Logs** to view all logs. You can:
- Filter by log type, event, level, user, branch, or date range
- View detailed log information including properties and context
- Search logs by description, user, or subject

### Programmatically

```php
use App\Models\ActivityLog;

// Get logs for a specific model
$resident = Resident::find(1);
$logs = ActivityLog::forSubject(Resident::class, $resident->id)->get();

// Get logs for a user
$logs = ActivityLog::forUser($userId)->get();

// Get recent audit logs
$logs = ActivityLog::ofType('audit')->recent(7)->get();

// Get error logs
$logs = ActivityLog::level('error')->get();
```

## Log Types

- **Activity**: General user activities (default for manual logging)
- **Audit**: Model changes (create, update, delete) - automatically logged
- **Error**: Error events and exceptions
- **System**: System-level events (login, logout, etc.)

## Events

Common events include:
- `created` - Model was created
- `updated` - Model was updated
- `deleted` - Model was deleted
- `viewed` - Model was viewed
- `restored` - Soft-deleted model was restored
- `login` - User logged in
- `logout` - User logged out
- `exported` - Data was exported
- `imported` - Data was imported

## Models with Logging Enabled

The following models currently have logging enabled:
- `User` - User account changes
- `Resident` - Resident information changes
- `Medication` - Medication changes
- `MedicationAdministration` - Medication administration records

You can add the `Loggable` trait to any other model that needs logging.

## Permissions

To view activity logs, users need the `view_activity_logs` permission. To delete logs, users need the `delete_activity_logs` permission.

Caregivers will only see logs for their assigned branch.

## Best Practices

1. **Don't log sensitive data**: Avoid logging passwords, tokens, or other sensitive information in properties
2. **Use appropriate log levels**: Use `error` for errors, `warning` for warnings, `info` for general information
3. **Keep descriptions clear**: Write human-readable descriptions that explain what happened
4. **Use properties for details**: Store detailed information in the `properties` field, not in the description
5. **Don't over-log**: Consider what's truly important to log to avoid database bloat

## Maintenance

Consider implementing a cleanup job to remove old logs:

```php
// In a scheduled command
ActivityLog::where('logged_at', '<', now()->subMonths(6))->delete();
```

Or archive old logs to a separate storage system before deleting.

