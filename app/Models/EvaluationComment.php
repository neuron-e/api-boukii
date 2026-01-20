<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationComment extends Model
{
    use HasFactory;

    public $table = 'evaluation_comments';

    public $fillable = [
        'evaluation_id',
        'user_id',
        'monitor_id',
        'comment'
    ];

    protected $casts = [
        'comment' => 'string'
    ];

    public function evaluation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Evaluation::class, 'evaluation_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function monitor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Monitor::class, 'monitor_id');
    }
}
