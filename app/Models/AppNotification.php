<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AppNotification extends Model
{
    use HasFactory;

    protected $table = 'app_notifications';

    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'actor_id',
        'school_id',
        'type',
        'title',
        'body',
        'payload',
        'event_date',
        'scheduled_at',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'event_date' => 'date',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];
}
