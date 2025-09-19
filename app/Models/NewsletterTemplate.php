<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'subject',
        'content',
        'school_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the school that owns the template.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
