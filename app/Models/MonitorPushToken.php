<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MonitorPushToken extends Model
{
    use HasFactory;

    protected $table = 'monitor_push_tokens';

    protected $fillable = [
        'monitor_id',
        'token',
        'platform',
        'device_id',
        'locale',
        'app',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function monitor()
    {
        return $this->belongsTo(Monitor::class, 'monitor_id');
    }
}
