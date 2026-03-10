<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CoursesBySeasonLegacySheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    private Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function title(): string
    {
        return 'Courses legacy';
    }

    public function headings(): array
    {
        return [
            'Course ID',
            'Course name',
            'Course type',
            'Course type ID',
            'Sport ID',
            'Sport name',
            'Station ID',
            'Station',
            'Price',
            'Max participants',
            'Duration',
            'Flexible duration',
            'Reservation start',
            'Reservation end',
            'Reservation day start',
            'Reservation day end',
            'Reservation hour min',
            'Reservation hour max',
            'Legacy group ID',
            'Confirm attendance',
            'Online',
            'Active',
            'Range start',
            'Range end',
            'Course date ID',
            'Date',
            'Hour',
            'Groups count',
            'Subgroups count',
            'Subgroup total capacity',
            'Degree IDs',
            'Degree names',
            'Booked participants',
            'Booked amount',
            'Paid amount',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            return [
                $row->course_id,
                $row->course_name,
                $this->mapSupertype($row->course_supertype_id),
                $row->course_type_id,
                $row->sport_id,
                $row->sport_name,
                $row->station_id,
                $row->station_name,
                $row->course_price,
                $row->max_participants,
                $row->duration,
                (int) $row->duration_flexible === 1 ? 'yes' : 'no',
                $row->reservation_start,
                $row->reservation_end,
                $row->reservation_day_start,
                $row->reservation_day_end,
                $row->reservation_hour_min,
                $row->reservation_hour_max,
                $row->legacy_group_id,
                (int) $row->confirm_attendance === 1 ? 'yes' : 'no',
                (int) $row->online === 1 ? 'yes' : 'no',
                (int) $row->active === 1 ? 'yes' : 'no',
                $row->course_date_start,
                $row->course_date_end,
                $row->course_date_id,
                $row->date,
                $row->hour,
                $row->groups_count,
                $row->subgroups_count,
                $row->total_subgroup_capacity,
                $row->degree_ids,
                $row->degree_names,
                $row->booked_participants,
                $row->booked_amount,
                $row->paid_amount,
            ];
        });
    }

    private function mapSupertype($supertypeId): string
    {
        return match ((int) $supertypeId) {
            1 => 'collective',
            2 => 'private',
            default => 'unknown',
        };
    }
}
