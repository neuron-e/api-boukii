<?php

namespace App\Exports;

use App\Models\GiftVoucher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class GiftVouchersSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
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
        return 'Gift Vouchers';
    }

    public function headings(): array
    {
        return [
            'Code',
            'Type',
            'Amount',
            'Balance',
            'Buyer Name',
            'Buyer Email',
            'Recipient Name',
            'Recipient Email',
            'Status',
            'Created At',
            'Delivered At',
            'Redeemed At',
            'Expires At',
            'Is Paid',
            'Voucher ID',
        ];
    }

    /**
    * @return Collection<int, GiftVoucher>
    */
    public function collection(): Collection
    {
        $from = $this->createdFrom ? Carbon::parse($this->createdFrom)->startOfDay() : null;
        $to = $this->createdTo ? Carbon::parse($this->createdTo)->endOfDay() : null;

        return GiftVoucher::with(['purchasedBy', 'redeemedBy'])
            ->where('school_id', $this->schoolId)
            ->when($from && $to, function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to]);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @param GiftVoucher $giftVoucher
     * @return array<int, string|int|float|null|bool>
     */
    public function map($giftVoucher): array
    {
        return [
            $giftVoucher->code,
            'Gift Voucher',
            $giftVoucher->amount,
            $giftVoucher->balance,
            $giftVoucher->buyer_name,
            $giftVoucher->buyer_email,
            $giftVoucher->recipient_name,
            $giftVoucher->recipient_email,
            $this->resolveStatus($giftVoucher),
            optional($giftVoucher->created_at)?->format('Y-m-d'),
            optional($giftVoucher->delivered_at)?->format('Y-m-d'),
            optional($giftVoucher->redeemed_at)?->format('Y-m-d'),
            optional($giftVoucher->expires_at)?->format('Y-m-d'),
            $giftVoucher->is_paid ? 'Yes' : 'No',
            $giftVoucher->voucher_id,
        ];
    }

    private function resolveStatus(GiftVoucher $giftVoucher): string
    {
        if ($giftVoucher->trashed()) {
            return 'Inactive';
        }

        if ($giftVoucher->status) {
            return ucfirst($giftVoucher->status);
        }

        if ($giftVoucher->is_redeemed) {
            return 'Redeemed';
        }

        if ($giftVoucher->expires_at && $giftVoucher->expires_at->isPast()) {
            return 'Expired';
        }

        if ($giftVoucher->is_delivered) {
            return 'Delivered';
        }

        if ($giftVoucher->is_paid) {
            return 'Active';
        }

        return 'Pending';
    }
}
