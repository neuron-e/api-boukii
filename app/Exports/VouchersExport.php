<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class VouchersExport implements WithMultipleSheets
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

    public function sheets(): array
    {
        return [
            new PurchaseVouchersSheet($this->schoolId, $this->createdFrom, $this->createdTo),
            new GiftVouchersSheet($this->schoolId, $this->createdFrom, $this->createdTo),
        ];
    }
}
