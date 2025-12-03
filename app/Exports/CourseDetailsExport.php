<?php

namespace App\Exports;

use App\Models\Course;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class CourseDetailsExport implements FromView, WithTitle
{
    use Exportable;

    private $courseId;

    public function __construct($courseId)
    {
        $this->courseId = $courseId;
    }

    public function view(): View
    {
        // Cargar curso y relaciones específicas según tipo
        $course = Course::with(['courseDates'])->findOrFail($this->courseId);
        $bookingUsersPrivate = collect();
        $privateGroups = collect();

        if ($course->course_type == 1) {
            // Colectivos: incluir grupos/subgrupos y booking users relacionados
            $course->load([
                'courseDates.courseGroups.courseSubgroups.bookingUsers.client',
                'courseDates.courseGroups.courseSubgroups.bookingUsers.bookingUserExtras.courseExtra',
                'courseDates.courseGroups.courseSubgroups.bookingUsers.booking.clientMain',
                'courseDates.courseGroups.courseSubgroups.bookingUsers.monitor',
                'courseDates.courseGroups.degree',
            ]);
        } else {
            // Privados: obtener bookingUsers directamente del curso (incluye date/hour)
            $bookingUsersPrivate = $course->bookingUsers()
                ->with([
                    'client',
                    'monitor',
                    'courseGroup.monitor',
                    'courseSubGroup.monitor',
                    'booking.clientMain',
                    'bookingUserExtras.courseExtra',
                ])
                ->orderBy('date')
                ->orderBy('hour_start')
                ->get();

            // Resolver monitor por booking user y agrupar por reserva/actividad
            $bookingUsersPrivate = $bookingUsersPrivate->map(function ($bookingUser) {
                $bookingUser->resolved_monitor = $this->resolveMonitorName($bookingUser);
                return $bookingUser;
            });

            $privateGroups = $bookingUsersPrivate
                ->groupBy(function ($bookingUser) {
                    $bookingId = $bookingUser->booking_id ?? 'no_booking';
                    $groupId = $bookingUser->group_id ?? 'no_group';
                    return $bookingId . '|' . $groupId;
                })
                ->map(function (Collection $items) {
                    $items = $items->sortBy(function ($item) {
                        $datePart = $item->date ? $item->date->format('Y-m-d') : (string) $item->date;
                        $hourPart = $item->hour_start ?? '';
                        return $datePart . ' ' . $hourPart;
                    });
                    $first = $items->first();

                    return [
                        'booking_id' => $first->booking_id,
                        'group_id' => $first->group_id,
                        'booking' => $first->booking,
                        'client_main' => optional($first->booking)->clientMain,
                        'monitor' => $first->resolved_monitor,
                        'items' => $items,
                    ];
                })
                ->values();
        }

        // Retorna la vista con los datos
        return view('exports.course_details', [
            'course' => $course,
            'bookingUsersPrivate' => $bookingUsersPrivate,
            'privateGroups' => $privateGroups,
        ]);
    }

    // Aplicar estilos básicos a la hoja de cálculo
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:K1')->getFont()->setBold(true); // Negrita para el encabezado
        $sheet->getStyle('A:K')->getAlignment()->setHorizontal('center'); // Centrar el texto
    }

    public function title(): string
    {
        $course = Course::find($this->courseId);
        $name = $course?->name ?? ('course_' . $this->courseId);
        return mb_substr($name, 0, 31);
    }

    /**
     * Resuelve el nombre del monitor priorizando el asignado al booking user.
     */
    private function resolveMonitorName($bookingUser): ?string
    {
        $monitorFullName = function ($monitor) {
            if (!$monitor) {
                return null;
            }

            $full = $monitor->fullname
                ?? trim(($monitor->first_name ?? '') . ' ' . ($monitor->last_name ?? ''));

            return $full !== '' ? $full : null;
        };

        return $monitorFullName($bookingUser->monitor)
            ?? $monitorFullName(optional($bookingUser->courseGroup)->monitor)
            ?? $monitorFullName(optional($bookingUser->courseSubGroup)->monitor);
    }
}
