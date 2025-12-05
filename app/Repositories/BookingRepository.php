<?php

namespace App\Repositories;

use App\Models\Booking;
use App\Repositories\BaseRepository;
use Illuminate\Http\Request;

class BookingRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'id',
        'school_id',
        'client_main_id',
        'price_total',
        'currency',
        'payment_method_id',
        'paid_total',
        'paid',
        'attendance',
        'notes',
        'paxes',
        'color'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Booking::class;
    }

    /**
     * Aplica filtro de status (acepta lista separada por comas o array)
     */
    public function applyStatusFilter($query, Request $request): void
    {
        if ($request->filled('status')) {
            $status = $request->input('status');
            $status = is_array($status) ? $status : explode(',', $status);
            $status = array_filter($status, fn($s) => $s !== '' && $s !== null);
            if (!empty($status)) {
                $query->whereIn('status', $status);
            }
        }
    }
}
