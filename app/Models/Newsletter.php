<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Newsletter extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'subject',
        'content',
        'recipients_config',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'opened_count',
        'clicked_count',
        'status',
        'template_type',
        'scheduled_at',
        'sent_at',
        'metadata'
    ];

    protected $casts = [
        'recipients_config' => 'array',
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'delivered_count' => 'integer',
        'opened_count' => 'integer',
        'clicked_count' => 'integer'
    ];

    protected $dates = [
        'scheduled_at',
        'sent_at'
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // Accessors & Mutators
    public function getOpenRateAttribute()
    {
        return $this->sent_count > 0 
            ? round(($this->opened_count / $this->sent_count) * 100, 2)
            : 0;
    }

    public function getClickRateAttribute()
    {
        return $this->opened_count > 0
            ? round(($this->clicked_count / $this->opened_count) * 100, 2)
            : 0;
    }

    public function getDeliveryRateAttribute()
    {
        return $this->sent_count > 0
            ? round(($this->delivered_count / $this->sent_count) * 100, 2)
            : 0;
    }

    // Helper methods
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function markAsSent()
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }

    public function incrementSentCount($count = 1)
    {
        $this->increment('sent_count', $count);
    }

    public function incrementOpenedCount($count = 1)
    {
        $this->increment('opened_count', $count);
    }

    public function incrementClickedCount($count = 1)
    {
        $this->increment('clicked_count', $count);
    }
}
