<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    protected $fillable = [
        "employee_id",
        "base_salary",
        "allowance",
        "deduction",
        "total_salary",
        "month",
        "year",
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
