import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormControl } from '@angular/forms';

interface Course {
  id: number;
  name: string;
  type: 'ski' | 'snow';
  level: 'beginner' | 'intermediate' | 'advanced';
  price: number;
  duration: string;
  instructor: string;
  capacity: number;
  reservations: number;
  status: 'active' | 'paused' | 'inactive';
}

@Component({
  selector: 'app-courses-list',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule],
  templateUrl: './courses-list.component.html',
  styleUrls: ['./courses-list.component.scss'],
})
export class CoursesListComponent {
  /** Controls */
  searchControl = new FormControl('');
  typeFilter = '';
  levelFilter = '';
  statusFilter = '';
  viewMode: 'cards' | 'table' = 'cards';

  /** Loading & error states */
  loading = false;
  error: string | null = null;

  /** Sample data */
  courses: Course[] = [
    {
      id: 1,
      name: 'Ski Basics',
      type: 'ski',
      level: 'beginner',
      price: 120,
      duration: '2h',
      instructor: 'Luis Pérez',
      capacity: 8,
      reservations: 5,
      status: 'active',
    },
    {
      id: 2,
      name: 'Snowboard Freestyle',
      type: 'snow',
      level: 'advanced',
      price: 200,
      duration: '3h',
      instructor: 'Marta García',
      capacity: 5,
      reservations: 2,
      status: 'paused',
    },
    {
      id: 3,
      name: 'Ski Intermedio',
      type: 'ski',
      level: 'intermediate',
      price: 150,
      duration: '4h',
      instructor: 'Carlos Ruiz',
      capacity: 10,
      reservations: 10,
      status: 'inactive',
    },
  ];

  filteredCourses: Course[] = [...this.courses];
  skeletonCourses = Array(6);

  constructor() {
    this.searchControl.valueChanges.subscribe(() => this.applyFilters());
    this.applyFilters();
  }

  applyFilters(): void {
    const term = this.searchControl.value?.toLowerCase() ?? '';
    this.filteredCourses = this.courses.filter((course) => {
      const matchesSearch = course.name.toLowerCase().includes(term);
      const matchesType = !this.typeFilter || course.type === this.typeFilter;
      const matchesLevel = !this.levelFilter || course.level === this.levelFilter;
      const matchesStatus = !this.statusFilter || course.status === this.statusFilter;
      return matchesSearch && matchesType && matchesLevel && matchesStatus;
    });
  }

  /** Actions */
  createCourse(): void {
    // TODO: Implement navigation to course creation
  }

  exportCourses(): void {
    const headers = [
      'ID',
      'Nombre',
      'Tipo',
      'Nivel',
      'Precio',
      'Duración',
      'Monitor',
      'Capacidad',
      'Reservas',
      'Estado',
    ];
    const rows = this.filteredCourses.map((c) => [
      c.id,
      c.name,
      this.getTypeLabel(c.type),
      this.getLevelLabel(c.level),
      c.price,
      c.duration,
      c.instructor,
      c.capacity,
      c.reservations,
      this.getStatusLabel(c.status),
    ]);
    const csvRows = [headers, ...rows]
      .map((row) => row.map((f) => `"${f}"`).join(','))
      .join('\n');
    const blob = new Blob([csvRows], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `cursos-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  }

  viewDetails(_course: Course): void {
    // TODO: Implement navigation to details
  }

  editCourse(_course: Course): void {
    // TODO: Implement edit course
  }

  manageReservations(_course: Course): void {
    // TODO: Implement reservations management
  }

  trackByCourseId(index: number, course: Course): number {
    return course.id;
  }

  getLevelLabel(level: Course['level']): string {
    switch (level) {
      case 'beginner':
        return 'Principiante';
      case 'intermediate':
        return 'Intermedio';
      default:
        return 'Avanzado';
    }
  }

  getStatusLabel(status: Course['status']): string {
    switch (status) {
      case 'active':
        return 'Activo';
      case 'paused':
        return 'En pausa';
      default:
        return 'Inactivo';
    }
  }

  getTypeLabel(type: Course['type']): string {
    return type === 'ski' ? 'Esquí' : 'Snow';
  }

  getLevelClass(level: Course['level']): string {
    return {
      beginner: 'badge--beginner',
      intermediate: 'badge--intermediate',
      advanced: 'badge--advanced',
    }[level];
  }

  getStatusClass(status: Course['status']): string {
    return {
      active: 'status--active',
      paused: 'status--paused',
      inactive: 'status--inactive',
    }[status];
  }
}

