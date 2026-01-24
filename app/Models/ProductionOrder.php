<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    use HasFactory;
    protected $fillable = [
        "order_code",
        "product_id",
        "quantity",
        "status",
        "start_date",
        "end_date"
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function logs ()
    {
        return $this->hasMany(ProductionLog::class);
    }

}
