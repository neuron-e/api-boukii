<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SimpleMessageSheet implements FromCollection, WithHeadings, WithTitle
{
    private string $title;
    private string $message;

    public function __construct(string $title, string $message)
    {
        $this->title = $title;
        $this->message = $message;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return ['Message'];
    }

    public function collection(): Collection
    {
        return collect([[$this->message]]);
    }
}
