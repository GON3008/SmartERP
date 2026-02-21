<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Inventory;
use  App\Models\StockIn;
use App\Models\StockOut;
use App\Models\OrderItem;
use App\Models\BillOfMaterial;
use App\Models\InventoryRecommendation;
use App\Models\ProductionOrder;

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

    public function stockOut()
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

    public function materialsUsedIn()
    {
        return $this->hasMany(BillOfMaterial::class, 'material_id');
    }

    public function inventoryRecommendations()
    {
        return $this->hasMany(InventoryRecommendation::class);
    }

    public function productionOrders()
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
