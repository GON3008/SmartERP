<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOut extends Model
{
    use HasFactory;

    protected $table= 'stock_out';
    protected $fillable = [
        "product_id",
        "warehouse_id",
        "quantity",
        "export_date",
        "reason",
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse ()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
