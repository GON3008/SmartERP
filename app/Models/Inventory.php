<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\WareHouse;

class Inventory extends Model
{
    use HasFactory;
    protected $fillable = [
        "product_id",
        "quantity",
        "warehouse_id",
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function wareHouse()
    {
        return $this->belongsTo(WareHouse::class, 'warehouse_id');
    }
}
