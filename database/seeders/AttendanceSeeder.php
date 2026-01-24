<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = Employee::where('status', true)->get();

        // Create attendance records for the last 30 days
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        foreach ($employees as $employee) {
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                // Skip weekends (Saturday and Sunday)
                if (!$currentDate->isWeekend()) {
                    // 95% attendance rate
                    if (rand(1, 100) <= 95) {
                        Attendance::create([
                            'employee_id' => $employee->id,
                            'date' => $currentDate->format('Y-m-d'),
                            'check_in' => $currentDate->copy()->setTime(rand(7, 9), rand(0, 59), 0),
                            'check_out' => rand(1, 100) <= 90 ? $currentDate->copy()->setTime(rand(17, 19), rand(0, 59), 0) : null,
                        ]);
                    }
                }

                $currentDate->addDay();
            }
        }
    }
}
