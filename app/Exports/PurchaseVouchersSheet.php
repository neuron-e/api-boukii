<?php

namespace App\Exports;

use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class PurchaseVouchersSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    use Exportable;

    private int $schoolId;
    private ?string $createdFrom;
    private ?string $createdTo;

    public function __construct(int $schoolId, ?string $createdFrom = null, ?string $createdTo = null)
    {
        $this->schoolId = $schoolId;
        $this->createdFrom = $createdFrom;
        $this->createdTo = $createdTo;
    }

    public function title(): string
    {
        return 'Purchase Vouchers';
    }

    public function headings(): array
    {
        return [
            'Code',
            'Type',
            'Amount',
            'Remaining Balance',
            'Buyer Name',
            'Buyer Email',
            'Recipient Name',
            'Recipient Email',
            'Assigned Client',
            'Uses Count',
            'Max Uses',
            'Status',
            'Created At',
            'Expires At',
            'First Used At',
            'Last Used At',
        ];
    }

    /**
    * @return Collection<int, Voucher>
    */
    public function collection(): Collection
    {
        $from = $this->createdFrom ? Carbon::parse($this->createdFrom)->startOfDay() : null;
        $to = $this->createdTo ? Carbon::parse($this->createdTo)->endOfDay() : null;

        return Voucher::with(['client', 'vouchersLogs' => function ($q) {
                $q->orderBy('created_at');
            }])
            ->where('school_id', $this->schoolId)
            ->where(function ($q) {
                $q->whereNull('is_gift')->orWhere('is_gift', false);
            })
            ->when($from && $to, function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to]);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @param Voucher $voucher
     * @return array<int, string|int|float|null>
     */
    public function map($voucher): array
    {
        $firstLog = $voucher->vouchersLogs->first();
        $lastLog = $voucher->vouchersLogs->last();

        return [
            $voucher->code,
            'Purchase Voucher',
            $voucher->quantity,
            $voucher->remaining_balance,
            $voucher->buyer_name ?? optional($voucher->client)->full_name,
            $voucher->buyer_email ?? optional($voucher->client)->email,
            $voucher->recipient_name,
            $voucher->recipient_email,
            optional($voucher->client)->full_name,
            $voucher->uses_count,
            $voucher->max_uses,
            $this->resolveStatus($voucher),
            optional($voucher->created_at)?->format('Y-m-d'),
            optional($voucher->expires_at)?->format('Y-m-d'),
            optional($firstLog?->created_at)?->format('Y-m-d'),
            optional($lastLog?->created_at)?->format('Y-m-d'),
        ];
    }

    private function resolveStatus(Voucher $voucher): string
    {
        if ($voucher->trashed()) {
            return 'Inactive';
        }

        if ($voucher->expires_at && $voucher->expires_at->isPast()) {
            return 'Expired';
        }

        if ($voucher->remaining_balance <= 0) {
            return 'Used';
        }

        if ($voucher->max_uses !== null && $voucher->uses_count >= $voucher->max_uses) {
            return 'Used';
        }

        return 'Active';
    }
}
