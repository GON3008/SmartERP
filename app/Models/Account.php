<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'contact_type',
        'contact_id',
        'reference_type',
        'reference_id',
        'total_amount',
        'paid_amount',
        'balance',
        'due_date',
        'status',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'total_amount' => 'decimal:2',
        'paid_amount'  => 'decimal:2',
        'balance'      => 'decimal:2',
    ];

    public function contact()
    {
        return $this->morphTo();
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
