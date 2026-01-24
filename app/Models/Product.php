<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        "sku",
        "name",
        "category",
        "unit",
        "price",
        "min_stock"
    ];

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function stockIns()
    {
        return $this->hasMany(StockIn::class);
    }

    public function stockOút()
    {
        return $this->hasMany(StockOut::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function billOfMaterials()
    {
        return $this->hasMany(BillOfMaterial::class);
    }
}
