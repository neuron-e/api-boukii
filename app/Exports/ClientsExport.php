<?php

namespace App\Exports;

use App\Models\Client;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClientsExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    private int $schoolId;
    private ?int $seasonId;
    private ?string $dateFrom;
    private ?string $dateTo;

    public function __construct(int $schoolId, ?int $seasonId = null, ?string $dateFrom = null, ?string $dateTo = null)
    {
        $this->schoolId = $schoolId;
        $this->seasonId = $seasonId;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function headings(): array
    {
        return [
            'Type',
            'Id',
            'Client',
            'Niveau',
            'Participants',
            'Email',
            'Sports',
            'Inscrits',
            'Etat',
            'Actions',
        ];
    }

    /**
     * @return Collection<int, Client>
     */
    public function collection(): Collection
    {
        [$startDate, $endDate] = $this->resolveDateRange();

        return Client::query()
            ->whereHas('clientsSchools', function (Builder $q) {
                $q->where('school_id', $this->schoolId);
            })
            ->with([
                'clientSports' => function ($q) {
                    $q->where('school_id', $this->schoolId)
                        ->with(['sport', 'degree']);
                },
                'bookings' => function ($q) use ($startDate, $endDate) {
                    $q->where('school_id', $this->schoolId);
                    $this->applyDateRange($q, 'created_at', $startDate, $endDate);
                },
                'bookingUsers' => function ($q) use ($startDate, $endDate) {
                    $q->whereHas('booking', function ($bookingQuery) use ($startDate, $endDate) {
                        $bookingQuery->where('school_id', $this->schoolId);
                        $this->applyDateRange($bookingQuery, 'created_at', $startDate, $endDate);
                    });
                },
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @param Client $client
     * @return array<int, string|int|null>
     */
    public function map($client): array
    {
        $latestBooking = $client->bookings->sortByDesc('created_at')->first();
        $lastBookingDate = $latestBooking?->created_at;
        $isActive = $lastBookingDate ? $lastBookingDate->greaterThanOrEqualTo(now()->subDays(365)) : false;

        $sports = $client->clientSports
            ->map(fn($cs) => optional($cs->sport)->name)
            ->filter()
            ->unique()
            ->implode(', ');

        $level = $client->clientSports
            ->filter(fn($cs) => $cs->degree)
            ->sortByDesc(fn($cs) => $cs->updated_at ?? $cs->created_at)
            ->first();

        $participantsCount = $client->bookingUsers->count();
        $bookingsCount = $client->bookings->count();

        $lastAction = $lastBookingDate ? 'Last booking: ' . $lastBookingDate->format('Y-m-d') : 'N/A';

        return [
            'Individual',
            $client->id,
            trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
            $level?->degree?->name,
            $participantsCount,
            $client->email,
            $sports,
            $bookingsCount,
            $isActive ? 'Active' : 'Inactive',
            $lastAction,
        ];
    }

    /**
     * @return array{?string, ?string}
     */
    private function resolveDateRange(): array
    {
        if ($this->seasonId) {
            $season = Season::where('id', $this->seasonId)
                ->where('school_id', $this->schoolId)
                ->firstOrFail();

            return [
                $season->start_date->format('Y-m-d'),
                $season->end_date->format('Y-m-d'),
            ];
        }

        if ($this->dateFrom && $this->dateTo) {
            return [$this->dateFrom, $this->dateTo];
        }

        return [null, null];
    }

    private function applyDateRange($query, string $column, ?string $startDate, ?string $endDate): void
    {
        if ($startDate && $endDate) {
            $query->whereBetween($column, [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }
    }
}




