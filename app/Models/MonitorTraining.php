<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MonitorTraining extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'monitor_trainings';

    public $fillable = [
        'monitor_id',
        'sport_id',
        'school_id',
        'training_name',
        'training_proof'
    ];

    protected $casts = [
        'monitor_id' => 'integer',
        'sport_id' => 'integer',
        'school_id' => 'integer',
        'training_name' => 'string',
        'training_proof' => 'string'
    ];

    public static array $rules = [
        'monitor_id' => 'required|integer|exists:monitors,id',
        'sport_id' => 'required|integer|exists:sports,id',
        'school_id' => 'required|integer|exists:schools,id',
        'training_name' => 'required|string|max:255',
        'training_proof' => 'nullable|string'
    ];

    public function monitor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Monitor::class, 'monitor_id');
    }

    public function sport(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Sport::class, 'sport_id');
    }

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class, 'school_id');
    }
}
