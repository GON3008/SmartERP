<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        "user_id",
        "action",
        "table_name",
        "record_id",
        "description",
        "ip_address"
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
