<?php

namespace App\Exports;

use App\Models\Course;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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
            // Privados: no hay grupos; usar bookingUsers por fecha
            $course->load([
                'courseDates.bookingUsers.client',
                'courseDates.bookingUsers.monitor',
                'courseDates.bookingUsers.booking.clientMain',
                'courseDates.bookingUsers.bookingUserExtras.courseExtra',
            ]);
        }

        // Retorna la vista con los datos
        return view('exports.course_details', compact('course'));
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
}
