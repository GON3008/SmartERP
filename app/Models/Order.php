<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Customer;
use App\Models\OrderItem;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        "customer_id",
        "order_code",
        "order_date",
        "status",
        "total_amount"
    ];

    public function customer ()
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItems ()
    {
        return $this->hasMany(OrderItem::class);
    }
}


