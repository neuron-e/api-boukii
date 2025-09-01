import { Component, Inject, OnInit, OnDestroy } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { Subject, takeUntil } from 'rxjs';

interface CourseModalData {
  courseId: number;
  courseName: string;
  courseType?: number;
  sport?: string;
  dateRange?: {
    start: string;
    end: string;
  };
}

@Component({
  selector: 'vex-course-statistics-modal',
  templateUrl: './course-statistics-modal.component.html',
  styleUrls: ['./course-statistics-modal.component.scss']
})
export class CourseStatisticsModalComponent {
  // ==================== COMPONENT STATE ====================
  private destroy$ = new Subject<void>();

  constructor(
    public dialogRef: MatDialogRef<CourseStatisticsModalComponent>,
    @Inject(MAT_DIALOG_DATA) public data: CourseModalData
  ) {}

  ngOnInit(): void {
    // Configurar diálogo para que sea responsive
    this.setupResponsiveDialog();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ==================== DIALOG MANAGEMENT ====================

  private setupResponsiveDialog(): void {
    // Ajustar tamaño del diálogo según el viewport
    const updateSize = () => {
      const width = window.innerWidth;
      const height = window.innerHeight;

      if (width <= 768) {
        // Mobile
        this.dialogRef.updateSize('95vw', '90vh');
      } else if (width <= 1024) {
        // Tablet
        this.dialogRef.updateSize('90vw', '85vh');
      } else {
        // Desktop
        this.dialogRef.updateSize('85vw', '80vh');
      }
    };

    // Aplicar tamaño inicial
    updateSize();

    // Escuchar cambios de tamaño de ventana
    window.addEventListener('resize', updateSize);

    // Limpiar listener al destruir
    this.destroy$.subscribe(() => {
      window.removeEventListener('resize', updateSize);
    });
  }

  // ==================== EVENT HANDLERS ====================

  public onClose(): void {
    this.dialogRef.close();
  }

  public onExport(): void {
    this.dialogRef.close({ action: 'export', courseId: this.data.courseId });
  }

  public onRefresh(): void {
    // Este evento será manejado por el componente hijo (course-statistics)
    console.log('Refresh requested for course:', this.data.courseId);
  }

  // ==================== GETTERS FOR TEMPLATE ====================

  get modalTitle(): string {
    return this.data.courseName || `Curso #${this.data.courseId}`;
  }

  get hasDateRange(): boolean {
    return !!(this.data.dateRange?.start && this.data.dateRange?.end);
  }

  get dateRangeText(): string {
    if (!this.hasDateRange) return '';

    const start = new Date(this.data.dateRange!.start).toLocaleDateString('es-ES');
    const end = new Date(this.data.dateRange!.end).toLocaleDateString('es-ES');

    return `${start} - ${end}`;
  }
}
