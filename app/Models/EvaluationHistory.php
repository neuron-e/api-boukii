<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationHistory extends Model
{
    use HasFactory;

    public $table = 'evaluation_history';

    public $fillable = [
        'evaluation_id',
        'user_id',
        'type',
        'payload'
    ];

    protected $casts = [
        'payload' => 'array'
    ];

    public function evaluation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Evaluation::class, 'evaluation_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
