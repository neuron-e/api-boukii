import { Component, EventEmitter, Input, OnChanges, Output } from '@angular/core';
import { UntypedFormGroup } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';
import { CoursesService } from 'src/service/courses.service';
import { ApiCrudService } from 'src/service/crud.service';
import jsPDF from 'jspdf';
import * as QRCode from 'qrcode';
import { saveAs } from 'file-saver';

@Component({
  selector: 'vex-course-detail-card',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss'],
})
export class CourseDetailCardComponent implements OnChanges {

  @Input() courseFormGroup!: UntypedFormGroup
  @Input() onlyExtras: boolean = false
  @Input() noneExtras: boolean = false
  @Input() detail: boolean = false
  @Input() step: number = 0
  @Input() mode: 'create' | 'update' = "create"
  @Output() close = new EventEmitter()
  @Output() open = new EventEmitter<number>()
  @Output() edit = new EventEmitter<number>()
  showDescription: boolean = false;

  // Propiedades para fechas agrupadas por intervalos
  groupedCourseDates: Map<number, any[]> = new Map();
  groupedCourseDatesPrev: Map<number, any[]> = new Map();
  intervalNames: Map<number, string> = new Map();
  intervalNamesPrev: Map<number, string> = new Map();

  // Mantener el estado de expansión/colapso de cada intervalo
  expandedIntervals: Map<number, boolean> = new Map();
  expandedIntervalsPrev: Map<number, boolean> = new Map();

  Translate: { Code: string, Name: string }[] = [
    { Code: "fr", Name: "French" },
    { Code: "de", Name: "German" },
    { Code: "en", Name: "English" },
    { Code: "it", Name: "Italian" },
    { Code: "es", Name: "Spanish" },
  ]

  constructor(public CoursesService: CoursesService, private translateService: TranslateService, private crudService: ApiCrudService) { }

  find = (array: any[], key: string, value: string) => array.find((a: any) => a[key] === value)
  count = (array: any[], key: string) => Boolean(array.map((a: any) => a[key]).find((a: any) => a))
  DateDiff = (value1: string, value2: string): number => Math.round((new Date(value2).getTime() - new Date(value1).getTime()) / 1000 / 60 / 60 / 24)

  ngOnInit(): void {
    this.processCourseDates();

    // Subscribe to value changes to update sorted dates when necessary
    if (this.courseFormGroup) {
      this.courseFormGroup.controls['course_dates'].valueChanges.subscribe(() => {
        this.processCourseDates();
      });

      this.courseFormGroup.controls['course_dates_prev'].valueChanges.subscribe(() => {
        this.processCourseDates();
      });
    }
  }
  ngOnChanges(): void {
    //if (this.courseFormGroup.controls['id'].value) {
    //  const course_dates_prev = []
    //  for (const [index, value] of this.courseFormGroup.controls['course_dates_prev'].value.entries()) {
    //    if (index !== 0 && course_dates_prev[course_dates_prev.length - 1]["price"] === value["price"] &&
    //      course_dates_prev[course_dates_prev.length - 1]["hour_end"] === value["hour_end"] &&
    //      course_dates_prev[course_dates_prev.length - 1]["hour_start"] === value["hour_start"] &&
    //      new Date(value["date"]).getTime() - new Date(course_dates_prev[course_dates_prev.length - 1]["date_end"]).getTime() === 86400000
    //    ) { } else {
    //      course_dates_prev.push(value)
    //    }
    //    course_dates_prev[course_dates_prev.length - 1].date_end = value.date
    //  }
    //  this.courseFormGroup.patchValue({ course_dates_prev })
    //}
  }

  /**
   * Sort the course dates and update the component properties
   */
  /**
   * Agrupa las fechas del curso por sus intervalos
   */
  private groupCourseDataByIntervals(courseDates: any[]): Map<number, any[]> {
    if (!courseDates || courseDates.length === 0) {
      return new Map();
    }

    // Verificar si las fechas tienen interval_id
    const hasIntervalId = courseDates.some(date => date.hasOwnProperty('interval_id'));

    if (!hasIntervalId) {
      // Si no tienen interval_id, devolver todos en un solo grupo
      const map = new Map<number, any[]>();
      map.set(0, [...courseDates]);
      return map;
    }

    // Agrupar las fechas por interval_id
    const groupedDates = new Map<number, any[]>();

    courseDates.forEach(date => {
      const intervalId = date.interval_id || 0;

      if (!groupedDates.has(intervalId)) {
        groupedDates.set(intervalId, []);

        // Inicializar el estado de expansión para este intervalo (por defecto expandido)
        if (!this.expandedIntervals.has(intervalId)) {
          this.expandedIntervals.set(intervalId, true);
        }
        if (!this.expandedIntervalsPrev.has(intervalId)) {
          this.expandedIntervalsPrev.set(intervalId, true);
        }
      }

      groupedDates.get(intervalId).push(date);
    });

    // Ordenar cada grupo por order
    groupedDates.forEach((dates, intervalId) => {
      groupedDates.set(intervalId, dates.sort((a, b) => {
        return (a.order || 0) - (b.order || 0);
      }));
    });

    return groupedDates;
  }

  /**
   * Obtiene los nombres de los intervalos
   */
  private getIntervalNames(groupedDates: Map<number, any[]>): Map<number, string> {
    const intervalNames = new Map<number, string>();

    groupedDates.forEach((dates, intervalId) => {
      // Si todas las fechas del intervalo tienen el mismo nombre, usarlo
      if (dates.length > 0 && dates[0].interval_name) {
        intervalNames.set(intervalId, dates[0].interval_name);
      } else {
        // De lo contrario, generar un nombre en base a las fechas
        const dateRange = this.formatDateRange(dates);
        intervalNames.set(intervalId, this.translateService.instant('interval', { id: intervalId, range: dateRange }));
      }
    });

    return intervalNames;
  }

  /**
   * Procesa las fechas del curso para agruparlas por intervalos
   */
  private processCourseDates() {
    if (this.courseFormGroup) {
      const courseDates = this.courseFormGroup.controls['course_dates'].value;
      if (courseDates) {
        this.groupedCourseDates = this.groupCourseDataByIntervals(courseDates);
        this.intervalNames = this.getIntervalNames(this.groupedCourseDates);
      }

      const courseDatesPrev = this.courseFormGroup.controls['course_dates_prev'].value;
      if (courseDatesPrev) {
        this.groupedCourseDatesPrev = this.groupCourseDataByIntervals(courseDatesPrev);
        this.intervalNamesPrev = this.getIntervalNames(this.groupedCourseDatesPrev);
      }
    }
  }

  /**
   * Obtiene un array de los IDs de intervalo ordenados
   */
  getOrderedIntervalIds(groupedDates: Map<number, any[]>): number[] {
    return Array.from(groupedDates.keys()).sort((a, b) => a - b);
  }

  /**
   * Formatea un rango de fechas de manera compacta
   */
  formatDateRange(dates: any[]): string {
    if (!dates || dates.length === 0) {
      return '';
    }

    if (dates.length === 1) {
      return this.formatDate(dates[0].date);
    }

    // Ordenar por fecha
    const sortedDates = [...dates].sort((a, b) => {
      return new Date(a.date).getTime() - new Date(b.date).getTime();
    });

    const firstDate = sortedDates[0].date;
    const lastDate = sortedDates[sortedDates.length - 1].date;

    return `${this.formatDate(firstDate)} - ${this.formatDate(lastDate)}`;
  }

  /**
   * Formatea una fecha individual
   */
  formatDate(date: string): string {
    if (!date) return '';
    const d = new Date(date);
    // Formato: dd/mm/yyyy
    return `${d.getDate().toString().padStart(2, '0')}/${(d.getMonth() + 1).toString().padStart(2, '0')}/${d.getFullYear()}`;
  }

  /**
   * Verifica si todas las fechas en un grupo tienen el mismo horario
   */
  hasSameTimeSchedule(dates: any[]): boolean {
    if (!dates || dates.length <= 1) return true;

    const firstStartTime = dates[0].hour_start;
    const firstEndTime = dates[0].hour_end;

    return dates.every(date => date.hour_start === firstStartTime && date.hour_end === firstEndTime);
  }

  /**
   * Obtiene el horario común para un grupo de fechas (si todas tienen el mismo)
   */
  getCommonTimeSchedule(dates: any[]): string {
    if (!dates || dates.length === 0) return '';

    if (this.hasSameTimeSchedule(dates)) {
      return `${dates[0].hour_start}h - ${dates[0].hour_end}h`;
    }

    return this.translateService.instant('variable_schedule');
  }

  /**
   * Obtiene los días de la semana en que ocurren las fechas
   */
  getWeekdaysPattern(dates: any[]): string {
    if (!dates || dates.length === 0) return '';

    const weekdays = ['D', 'L', 'M', 'X', 'J', 'V', 'S'];
    const daysSet = new Set<number>();

    dates.forEach(date => {
      const d = new Date(date.date);
      daysSet.add(d.getDay());
    });

    const daysArray = Array.from(daysSet).sort();

    if (daysArray.length === 7) {
      return this.translateService.instant('all_days_week');
    }

    if (daysArray.length === 5 &&
      daysArray.includes(1) && daysArray.includes(2) &&
      daysArray.includes(3) && daysArray.includes(4) &&
      daysArray.includes(5)) {
      return this.translateService.instant('workdays_only');
    }

    if (daysArray.length === 2 &&
      daysArray.includes(0) && daysArray.includes(6)) {
      return this.translateService.instant('weekends');
    }

    return daysArray.map(day => weekdays[day]).join(', ');
  }

  /**
   * Determina si un intervalo debería tener un resumen compacto
   * basado en el número de fechas y patrones
   */
  shouldShowCompactSummary(dates: any[]): boolean {
    if (!dates || dates.length <= 1) return false;

    // Si hay más de 5 fechas, mostrar resumen
    if (dates.length > 5) return true;

    // Si todas las fechas tienen el mismo horario, mostrar resumen
    if (this.hasSameTimeSchedule(dates)) return true;

    // Si las fechas siguen un patrón semanal (por ejemplo, todos los lunes)
    const weekdayPattern = this.getWeekdaysPattern(dates);
    if (weekdayPattern === 'Lunes a Viernes' ||
      weekdayPattern === 'Fines de semana' ||
      weekdayPattern === 'Todos los días') {
      return true;
    }

    return false;
  }

  /**
   * Alterna el estado de expansión de un intervalo
   */
  toggleInterval(intervalId: number, isPrevious: boolean = false): void {
    if (isPrevious) {
      const currentState = this.expandedIntervalsPrev.get(intervalId) || false;
      this.expandedIntervalsPrev.set(intervalId, !currentState);
    } else {
      const currentState = this.expandedIntervals.get(intervalId) || false;
      this.expandedIntervals.set(intervalId, !currentState);
    }
  }

  /**
   * Verifica si un intervalo está expandido
   */
  isIntervalExpanded(intervalId: number, isPrevious: boolean = false): boolean {
    if (isPrevious) {
      return this.expandedIntervalsPrev.get(intervalId) || false;
    } else {
      return this.expandedIntervals.get(intervalId) || false;
    }
  }

  /**
   * Expande todos los intervalos
   */
  expandAllIntervals(): void {
    this.getOrderedIntervalIds(this.groupedCourseDates).forEach(id => {
      this.expandedIntervals.set(id, true);
    });

    this.getOrderedIntervalIds(this.groupedCourseDatesPrev).forEach(id => {
      this.expandedIntervalsPrev.set(id, true);
    });
  }

  /**
   * Colapsa todos los intervalos
   */
  collapseAllIntervals(): void {
    this.getOrderedIntervalIds(this.groupedCourseDates).forEach(id => {
      this.expandedIntervals.set(id, false);
    });

    this.getOrderedIntervalIds(this.groupedCourseDatesPrev).forEach(id => {
      this.expandedIntervalsPrev.set(id, false);
    });
  }

  /**
   * Cuenta el número de intervalos
   */
  countIntervals(): number {
    return this.groupedCourseDates.size + this.groupedCourseDatesPrev.size;
  }

  /**
   * Obtiene el nombre del mes a partir de la fecha
   */
  getMonthName(date: string): string {
    if (!date) return '';

    const months = [
      'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    const d = new Date(date);
    return months[d.getMonth()];
  }

  // Mapas para controlar la visualización detallada de fechas
  detailedDatesVisible: Map<number, boolean> = new Map();
  detailedDatesPrevVisible: Map<number, boolean> = new Map();

  /**
   * Alterna la visibilidad de todas las fechas detalladas para un intervalo
   */
  toggleDetailedDates(intervalId: number): void {
    const currentState = this.detailedDatesVisible.get(intervalId) || false;
    this.detailedDatesVisible.set(intervalId, !currentState);
  }

  /**
   * Alterna la visibilidad de todas las fechas detalladas previas para un intervalo
   */
  toggleDetailedDatesPrev(intervalId: number): void {
    const currentState = this.detailedDatesPrevVisible.get(intervalId) || false;
    this.detailedDatesPrevVisible.set(intervalId, !currentState);
  }

  /**
   * Verifica si se deben mostrar las fechas detalladas para un intervalo
   */
  showDetailedDates(intervalId: number): boolean {
    return this.detailedDatesVisible.get(intervalId) || false;
  }

  /**
   * Verifica si se deben mostrar las fechas detalladas previas para un intervalo
   */
  showDetailedDatesPrev(intervalId: number): boolean {
    return this.detailedDatesPrevVisible.get(intervalId) || false;
  }

  hexToRgb(hex: string) {
    const rgb = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return rgb ? {
      r: parseInt(rgb[1], 16),
      g: parseInt(rgb[2], 16),
      b: parseInt(rgb[3], 16)
    } : null;
  }

  toggleDescription() {
    this.showDescription = !this.showDescription;
  }

  export(id: any) {
    this.crudService.getFile('/admin/courses/' + id + '/export/' + this.translateService.currentLang)
      .subscribe(async (data) => {
        const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        saveAs(blob, `course_details_${id}.xlsx`);
      }, error => console.error('Error al descargar el archivo:', error));
  }

  exportQR(id: any) {

    this.crudService.get('/admin/clients/course/' + id)
      .subscribe(async (data) => {
        const clientsData = data.data;
        if (clientsData && clientsData.length) {
          const doc = new jsPDF();
          const pageWidth = doc.internal.pageSize.getWidth();
          const colWidth = pageWidth / 2;
          const lineHeight = 6;
          const qrSize = 48;
          let y = 10;
          for (let i = 0; i < clientsData.length; i++) {
            const client = clientsData[i];
            const isLeftColumn = i % 2 === 0;
            const baseX = isLeftColumn ? 10 : colWidth + 6;
            const qrX = baseX + 48;
            let y_text = y;
            const maxWidthText = 48;
            doc.setTextColor(70, 70, 70);
            doc.setFontSize(16);
            doc.setFont('helvetica', 'bold');
            let lines = doc.splitTextToSize(`${client.client?.first_name} ${client.client?.last_name}`, maxWidthText);
            doc.text(lines, baseX, y_text);
            y_text += (lines.length + 0.4) * lineHeight;
            if (client.client?.phone || client.client?.telephone) {
              let clientPhone = '';
              if (client.client?.phone) { clientPhone = client.client.phone; }
              else { clientPhone = client.client.telephone; }
              doc.setFontSize(14);
              doc.setFont('helvetica', 'normal');
              lines = doc.splitTextToSize(`${clientPhone}`, maxWidthText);
              doc.text(lines, baseX, y_text);
              y_text += lines.length * lineHeight;
            }
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            lines = doc.splitTextToSize(`${client.course?.name}`, maxWidthText);
            doc.text(lines, baseX, y_text);
            y_text += (lines.length * lineHeight) - 2;
            if (client.monitor) {
              doc.setFontSize(8);
              lines = doc.splitTextToSize(`Professeur - niveau`, maxWidthText);
              doc.text(lines, baseX, y_text);
              y_text += (lines.length * lineHeight) - 2;
              doc.setFontSize(11);
              doc.setFont('helvetica', 'bold');
              lines = doc.splitTextToSize(`${client.monitor?.first_name} ${client.monitor?.last_name}`, maxWidthText);
              doc.text(lines, baseX, y_text);
              y_text += (lines.length * lineHeight) + 3;
            }
            else y_text += 6;
            if (client.degree) {
              const rgbColor = this.hexToRgb(client.degree.color);
              doc.setFillColor(rgbColor.r, rgbColor.g, rgbColor.b);
              doc.setTextColor(255, 255, 255);
              doc.setFontSize(9);
              doc.setFont('helvetica', 'normal');
              const text = `${client.degree?.annotation} - ${client.degree?.name}`;
              lines = doc.splitTextToSize(text, maxWidthText);
              const textBoxHeight = (lines.length + 0.5) * lineHeight;
              doc.rect(baseX, y_text - lineHeight, maxWidthText, textBoxHeight, 'F');
              doc.text(lines, baseX + 1.5, y_text);
              doc.setTextColor(70, 70, 70);
              y_text += textBoxHeight;
            }
            const qrData = await QRCode.toDataURL(client.client.id.toString());
            doc.addImage(qrData, 'JPEG', qrX, y - 10, qrSize, qrSize);
            if (!isLeftColumn || i === clientsData.length - 1) y += qrSize + lineHeight * 4;
            if (y >= doc.internal.pageSize.getHeight() - 20) {
              doc.addPage();
              y = 10;
            }
          }
          doc.save('clients.pdf');
        }
      })
  }
}
