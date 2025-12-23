<?php

namespace App\Repositories;

use App\Models\Booking;
use App\Models\BookingLog;
use App\Repositories\BaseRepository;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

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
        'color',
        'status'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Booking::class;
    }

    public function allQuery(array $searchArray = [], string $search = null, int $skip = null, int $limit = null,
                             string $order = 'desc', string $orderColumn = 'id', array $with = [],
                             $additionalConditions = null, $onlyTrashed = false): Builder
    {
        $query = parent::allQuery($searchArray, $search, $skip, $limit, $order, $orderColumn, $with, $additionalConditions, $onlyTrashed);

        $query->addSelect([
            'last_pay_link_sent_at' => BookingLog::select('created_at')
                ->whereColumn('booking_id', 'bookings.id')
                ->where('action', 'send_pay_link')
                ->latest('created_at')
                ->limit(1)
        ]);

        return $query;
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
