<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductionOrder;

class ProductionLog extends Model
{
    use HasFactory;
    protected $fillable = [
        "product_order_id",
        "note"
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }
}
