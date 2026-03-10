<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CoursesBySeasonLegacyCourseSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(
        private int $courseId,
        private string $sheetTitle,
        private string $startDate,
        private string $endDate
    ) {
    }

    public function title(): string
    {
        return $this->sheetTitle;
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
        return DB::table('courses2 as c')
            ->leftJoin('course_types as ct', 'ct.id', '=', 'c.course_type_id')
            ->leftJoin('sports as sp', 'sp.id', '=', 'c.sport_id')
            ->leftJoin('stations as st', 'st.id', '=', 'c.station_id')
            ->leftJoin('course_dates2 as cd', function ($join) {
                $join->on('cd.course2_id', '=', 'c.id')
                    ->whereBetween('cd.date', [$this->startDate, $this->endDate])
                    ->whereNull('cd.deleted_at');
            })
            ->leftJoinSub(
                DB::table('course_groups2 as cg')
                    ->leftJoin('degrees as d', 'd.id', '=', 'cg.degree_id')
                    ->leftJoin('course_groups_subgroups2 as cgs', function ($join) {
                        $join->on('cgs.course_group2_id', '=', 'cg.id')
                            ->whereNull('cgs.deleted_at');
                    })
                    ->whereNull('cg.deleted_at')
                    ->groupBy('cg.course2_id')
                    ->selectRaw('
                        cg.course2_id as course2_id,
                        COUNT(DISTINCT cg.id) as groups_count,
                        COUNT(DISTINCT cgs.id) as subgroups_count,
                        COALESCE(SUM(cgs.max_participants), 0) as total_subgroup_capacity,
                        GROUP_CONCAT(DISTINCT cg.degree_id ORDER BY cg.degree_id SEPARATOR \',\') as degree_ids,
                        GROUP_CONCAT(DISTINCT d.name ORDER BY d.name SEPARATOR \', \') as degree_names
                    '),
                'group_stats',
                function ($join) {
                    $join->on('group_stats.course2_id', '=', 'c.id');
                }
            )
            ->leftJoinSub(
                DB::table('booking_users2 as bu')
                    ->leftJoin('bookings2 as b', function ($join) {
                        $join->on('b.id', '=', 'bu.booking2_id')
                            ->whereNull('b.deleted_at');
                    })
                    ->whereNull('bu.deleted_at')
                    ->groupBy('bu.course2_id', 'bu.date', 'bu.hour')
                    ->selectRaw('
                        bu.course2_id as course2_id,
                        bu.date as booking_date,
                        bu.hour as booking_hour,
                        COUNT(bu.id) as booked_participants,
                        COALESCE(SUM(bu.price), 0) as booked_amount,
                        COALESCE(SUM(CASE WHEN b.paid = 1 THEN bu.price ELSE 0 END), 0) as paid_amount
                    '),
                'booking_stats',
                function ($join) {
                    $join->on('booking_stats.course2_id', '=', 'c.id')
                        ->on('booking_stats.booking_date', '=', 'cd.date')
                        ->on('booking_stats.booking_hour', '=', 'cd.hour');
                }
            )
            ->where('c.id', $this->courseId)
            ->selectRaw('
                c.id as course_id,
                c.name as course_name,
                c.course_supertype_id as course_supertype_id,
                c.course_type_id as course_type_id,
                ct.name as course_type_name,
                c.sport_id as sport_id,
                sp.name as sport_name,
                c.station_id as station_id,
                st.name as station_name,
                c.price as course_price,
                c.max_participants as max_participants,
                c.duration as duration,
                c.duration_flexible as duration_flexible,
                c.date_start_res as reservation_start,
                c.date_end_res as reservation_end,
                c.day_start_res as reservation_day_start,
                c.day_end_res as reservation_day_end,
                c.hour_min as reservation_hour_min,
                c.hour_max as reservation_hour_max,
                c.group_id as legacy_group_id,
                c.confirm_attendance as confirm_attendance,
                c.online as online,
                c.active as active,
                c.date_start as course_date_start,
                c.date_end as course_date_end,
                cd.id as course_date_id,
                cd.date as date,
                cd.hour as hour,
                COALESCE(group_stats.groups_count, 0) as groups_count,
                COALESCE(group_stats.subgroups_count, 0) as subgroups_count,
                COALESCE(group_stats.total_subgroup_capacity, 0) as total_subgroup_capacity,
                group_stats.degree_ids as degree_ids,
                group_stats.degree_names as degree_names,
                COALESCE(booking_stats.booked_participants, 0) as booked_participants,
                COALESCE(booking_stats.booked_amount, 0) as booked_amount,
                COALESCE(booking_stats.paid_amount, 0) as paid_amount
            ')
            ->orderBy('cd.date')
            ->orderBy('cd.hour')
            ->get()
            ->map(function ($row) {
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

