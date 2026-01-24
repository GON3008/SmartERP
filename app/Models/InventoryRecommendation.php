<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryRecommendation extends Model
{
    protected $fillable = [
        "product_id",
        "avg_daily_sales",
        "forecast_days",
        "recommended_quantity",
        "ai_summary",
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
