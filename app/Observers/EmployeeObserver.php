<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        $this->logActivity('created', $employee);
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        $this->logActivity('updated', $employee);
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        $this->logActivity('deleted', $employee);
    }

    /**
     * Handle the Employee "restored" event.
     */
    public function restored(Employee $employee): void
    {
        $this->logActivity('restored', $employee);
    }

    /**
     * Handle the Employee "force deleted" event.
     */
    public function forceDeleted(Employee $employee): void
    {
        $this->logActivity('force_deleted', $employee);
    }

    /**
     * Log activity to ActivityLog table
     */
    private function logActivity(string $action, Employee $employee): void
    {
        // Get changed attributes for updated action
        $description = $this->buildDescription($action, $employee);

        ActivityLog::create([
            'user_id' => Auth::id() ?? null,
            'action' => $action,
            'table_name' => 'employees',
            'record_id' => $employee->id,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Build description based on action
     */
    private function buildDescription(string $action, Employee $employee): string
    {
        switch ($action) {
            case 'created':
                return "Created employee: {$employee->full_name} (Code: {$employee->employee_code})";
            
            case 'updated':
                $changes = $employee->getChanges();
                unset($changes['updated_at']); // Remove updated_at from changes
                
                if (empty($changes)) {
                    return "Updated employee: {$employee->full_name}";
                }
                
                $changedFields = implode(', ', array_keys($changes));
                return "Updated employee: {$employee->full_name} - Changed fields: {$changedFields}";
            
            case 'deleted':
                return "Deleted employee: {$employee->full_name} (Code: {$employee->employee_code})";
            
            case 'restored':
                return "Restored employee: {$employee->full_name} (Code: {$employee->employee_code})";
            
            case 'force_deleted':
                return "Force deleted employee: {$employee->full_name} (Code: {$employee->employee_code})";
            
            default:
                return "Action {$action} on employee: {$employee->full_name}";
        }
    }
}
