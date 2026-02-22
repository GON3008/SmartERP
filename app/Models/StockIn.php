<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\WareHouse;

class StockIn extends Model
{
    use HasFactory;

    protected $table = 'stock_in';
    protected $fillable = [
        "product_id",
        "warehouse_id",
        "quantity",
        "import_date",
        "note",
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
