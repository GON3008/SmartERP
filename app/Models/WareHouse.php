<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Inventory;
use App\Models\StockIn;
use App\Models\StockOut;

class WareHouse extends Model
{
    use HasFactory;

    protected $table = 'warehouses';
    protected $fillable = [
        "name",
        "location"
    ];

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function stockIns()
    {
        return $this->hasMany(StockIn::class);
    }

    public function stockOuts()
    {
        return $this->hasMany(StockOut::class);
    }
}
