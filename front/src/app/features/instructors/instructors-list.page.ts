import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatTableModule } from '@angular/material/table';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatChipsModule } from '@angular/material/chips';
import { MatMenuModule } from '@angular/material/menu';
import { MatBadgeModule } from '@angular/material/badge';
import { MatDividerModule } from '@angular/material/divider';

import { InstructorsService } from './services/instructors.service';
import { Instructor } from './models/instructor.interface';
import { PageLayoutComponent } from '../../shared/components/layout/page-layout.component';

@Component({
  selector: 'app-instructors-list',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    ReactiveFormsModule,
    MatTableModule,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatChipsModule,
    MatMenuModule,
    MatBadgeModule,
    MatDividerModule,
    PageLayoutComponent,
  ],
  template: `
    <app-page-layout>
      <div class="page-header">
        <div class="page-title">
          <h1 class="text-2xl font-bold">Instructores</h1>
          <p class="text-gray-600">Gestión del equipo de instructores y monitores</p>
        </div>
        
        <div class="page-actions">
          <button mat-stroked-button [routerLink]="'/instructors/import'">
            <mat-icon>upload</mat-icon>
            Importar
          </button>
          <button mat-raised-button color="primary" [routerLink]="'/instructors/create'">
            <mat-icon>person_add</mat-icon>
            Nuevo Instructor
          </button>
        </div>
      </div>

      <!-- Estadísticas rápidas -->
      <div class="stats-grid mb-6">
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-blue-600">group</mat-icon>
              <div>
                <div class="stat-value">{{ stats.total }}</div>
                <div class="stat-label">Total Instructores</div>
                <div class="stat-sublabel">{{ stats.active }} activos</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-green-600">event_available</mat-icon>
              <div>
                <div class="stat-value">{{ stats.available_today }}</div>
                <div class="stat-label">Disponibles Hoy</div>
                <div class="stat-sublabel">{{ stats.busy_today }} ocupados</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-orange-600">school</mat-icon>
              <div>
                <div class="stat-value">{{ stats.certified_count }}</div>
                <div class="stat-label">Certificados</div>
                <div class="stat-sublabel">{{ stats.pending_certification }} en proceso</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-purple-600">star</mat-icon>
              <div>
                <div class="stat-value">{{ stats.avg_rating | number:'1.1-1' }}</div>
                <div class="stat-label">Valoración Media</div>
                <div class="stat-sublabel">{{ stats.total_reviews }} valoraciones</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
      </div>

      <!-- Filtros -->
      <mat-card class="mb-6">
        <mat-card-content>
          <form [formGroup]="filterForm" class="filters-grid">
            <mat-form-field>
              <mat-label>Buscar</mat-label>
              <input matInput formControlName="search" placeholder="Nombre, email, especialidad...">
              <mat-icon matSuffix>search</mat-icon>
            </mat-form-field>

            <mat-form-field>
              <mat-label>Estado</mat-label>
              <mat-select formControlName="status">
                <mat-option value="">Todos</mat-option>
                <mat-option value="active">Activos</mat-option>
                <mat-option value="inactive">Inactivos</mat-option>
                <mat-option value="on_vacation">De vacaciones</mat-option>
                <mat-option value="suspended">Suspendidos</mat-option>
              </mat-select>
            </mat-form-field>

            <mat-form-field>
              <mat-label>Especialidad</mat-label>
              <mat-select formControlName="specialties" multiple>
                <mat-option value="ski">Esquí</mat-option>
                <mat-option value="snowboard">Snowboard</mat-option>
                <mat-option value="telemark">Telemark</mat-option>
                <mat-option value="cross_country">Esquí de fondo</mat-option>
                <mat-option value="freestyle">Freestyle</mat-option>
              </mat-select>
            </mat-form-field>

            <mat-form-field>
              <mat-label>Nivel de certificación</mat-label>
              <mat-select formControlName="certification_level">
                <mat-option value="">Todos</mat-option>
                <mat-option value="trainee">En formación</mat-option>
                <mat-option value="junior">Junior</mat-option>
                <mat-option value="senior">Senior</mat-option>
                <mat-option value="expert">Experto</mat-option>
                <mat-option value="master">Maestro</mat-option>
              </mat-select>
            </mat-form-field>

            <div class="filter-actions">
              <button mat-button type="button" (click)="clearFilters()">
                Limpiar
              </button>
              <button mat-raised-button color="primary" type="submit">
                Filtrar
              </button>
            </div>
          </form>
        </mat-card-content>
      </mat-card>

      <!-- Vista de tarjetas/tabla -->
      <mat-card>
        <mat-card-content>
          <div class="view-controls mb-4">
            <div class="view-toggles">
              <button mat-icon-button 
                      [color]="viewMode === 'grid' ? 'primary' : ''" 
                      (click)="viewMode = 'grid'">
                <mat-icon>grid_view</mat-icon>
              </button>
              <button mat-icon-button 
                      [color]="viewMode === 'list' ? 'primary' : ''" 
                      (click)="viewMode = 'list'">
                <mat-icon>view_list</mat-icon>
              </button>
            </div>
            
            <div class="results-info">
              {{ instructors.length }} instructores encontrados
            </div>
          </div>

          <!-- Vista de rejilla -->
          <div *ngIf="viewMode === 'grid'" class="instructors-grid">
            <mat-card *ngFor="let instructor of instructors" class="instructor-card">
              <mat-card-header>
                <div mat-card-avatar class="instructor-avatar">
                  <img [src]="instructor.photo_url || '/assets/img/default-avatar.png'" 
                       [alt]="instructor.name">
                </div>
                <mat-card-title>{{ instructor.name }}</mat-card-title>
                <mat-card-subtitle>
                  <mat-chip [color]="getStatusColor(instructor.status)" selected>
                    {{ getStatusLabel(instructor.status) }}
                  </mat-chip>
                </mat-card-subtitle>
              </mat-card-header>
              
              <mat-card-content>
                <div class="instructor-info">
                  <div class="info-row" *ngIf="instructor.email">
                    <mat-icon>email</mat-icon>
                    <span>{{ instructor.email }}</span>
                  </div>
                  
                  <div class="info-row" *ngIf="instructor.phone">
                    <mat-icon>phone</mat-icon>
                    <span>{{ instructor.phone }}</span>
                  </div>
                  
                  <div class="specialties mt-2">
                    <mat-chip-listbox>
                      <mat-chip *ngFor="let specialty of instructor.specialties">
                        {{ specialty }}
                      </mat-chip>
                    </mat-chip-listbox>
                  </div>
                  
                  <div class="rating-info mt-2" *ngIf="instructor.avg_rating">
                    <div class="rating">
                      <mat-icon class="star-icon">star</mat-icon>
                      <span>{{ instructor.avg_rating | number:'1.1-1' }}</span>
                      <span class="text-sm text-gray-500">({{ instructor.total_reviews }})</span>
                    </div>
                  </div>
                  
                  <div class="availability-indicator mt-2">
                    <span class="availability-dot" 
                          [class.available]="instructor.is_available_today"
                          [class.busy]="!instructor.is_available_today">
                    </span>
                    <span class="text-sm">
                      {{ instructor.is_available_today ? 'Disponible hoy' : 'Ocupado hoy' }}
                    </span>
                  </div>
                </div>
              </mat-card-content>
              
              <mat-card-actions>
                <button mat-button [routerLink]="['/instructors', instructor.id]">
                  Ver perfil
                </button>
                <button mat-button [routerLink]="['/instructors', instructor.id, 'edit']">
                  Editar
                </button>
                <button mat-icon-button [matMenuTriggerFor]="instructorMenu">
                  <mat-icon>more_vert</mat-icon>
                </button>
                <mat-menu #instructorMenu="matMenu">
                  <button mat-menu-item (click)="viewSchedule(instructor.id)">
                    <mat-icon>schedule</mat-icon>
                    Ver horario
                  </button>
                  <button mat-menu-item (click)="viewAssignments(instructor.id)">
                    <mat-icon>assignment</mat-icon>
                    Asignaciones
                  </button>
                  <button mat-menu-item (click)="viewPayments(instructor.id)">
                    <mat-icon>payment</mat-icon>
                    Pagos
                  </button>
                  <mat-divider></mat-divider>
                  <button mat-menu-item (click)="toggleStatus(instructor.id)" 
                          [disabled]="instructor.status === 'suspended'">
                    <mat-icon>{{ instructor.status === 'active' ? 'pause' : 'play_arrow' }}</mat-icon>
                    {{ instructor.status === 'active' ? 'Desactivar' : 'Activar' }}
                  </button>
                </mat-menu>
              </mat-card-actions>
            </mat-card>
          </div>

          <!-- Vista de tabla -->
          <div *ngIf="viewMode === 'list'" class="table-container">
            <table mat-table [dataSource]="instructors" class="instructors-table w-full">
              <!-- Avatar y nombre -->
              <ng-container matColumnDef="instructor">
                <th mat-header-cell *matHeaderCellDef> Instructor </th>
                <td mat-cell *matCellDef="let instructor">
                  <div class="instructor-cell">
                    <img [src]="instructor.photo_url || '/assets/img/default-avatar.png'" 
                         [alt]="instructor.full_name"
                         class="avatar-sm">
                    <div>
                      <div class="font-medium">{{ instructor.full_name }}</div>
                      <div class="text-sm text-gray-500">{{ instructor.email }}</div>
                    </div>
                  </div>
                </td>
              </ng-container>

              <!-- Especialidades -->
              <ng-container matColumnDef="specialties">
                <th mat-header-cell *matHeaderCellDef> Especialidades </th>
                <td mat-cell *matCellDef="let instructor">
                  <mat-chip-listbox>
                    <mat-chip *ngFor="let specialty of instructor.specialties?.slice(0, 2)">
                      {{ specialty.name }}
                    </mat-chip>
                    <span *ngIf="instructor.specialties?.length > 2" class="text-xs text-gray-500">
                      +{{ instructor.specialties.length - 2 }} más
                    </span>
                  </mat-chip-listbox>
                </td>
              </ng-container>

              <!-- Estado -->
              <ng-container matColumnDef="status">
                <th mat-header-cell *matHeaderCellDef> Estado </th>
                <td mat-cell *matCellDef="let instructor">
                  <mat-chip [color]="getStatusColor(instructor.status)" selected>
                    {{ getStatusLabel(instructor.status) }}
                  </mat-chip>
                </td>
              </ng-container>

              <!-- Certificación -->
              <ng-container matColumnDef="certification">
                <th mat-header-cell *matHeaderCellDef> Certificación </th>
                <td mat-cell *matCellDef="let instructor">
                  <div class="certification-info">
                    <span class="cert-level">{{ instructor.certification_level }}</span>
                    <mat-icon *ngIf="instructor.certifications_expire_soon" 
                             class="text-orange-500" 
                             matTooltip="Certificación próxima a vencer">
                      warning
                    </mat-icon>
                  </div>
                </td>
              </ng-container>

              <!-- Rating -->
              <ng-container matColumnDef="rating">
                <th mat-header-cell *matHeaderCellDef> Valoración </th>
                <td mat-cell *matCellDef="let instructor">
                  <div class="rating-cell" *ngIf="instructor.avg_rating">
                    <mat-icon class="star-icon">star</mat-icon>
                    <span>{{ instructor.avg_rating | number:'1.1-1' }}</span>
                    <span class="text-sm text-gray-500">({{ instructor.total_reviews }})</span>
                  </div>
                  <span *ngIf="!instructor.avg_rating" class="text-gray-400">Sin valorar</span>
                </td>
              </ng-container>

              <!-- Disponibilidad -->
              <ng-container matColumnDef="availability">
                <th mat-header-cell *matHeaderCellDef> Disponibilidad </th>
                <td mat-cell *matCellDef="let instructor">
                  <div class="availability-cell">
                    <span class="availability-dot" 
                          [class.available]="instructor.is_available_today"
                          [class.busy]="!instructor.is_available_today">
                    </span>
                    <span class="text-sm">
                      {{ instructor.is_available_today ? 'Disponible' : 'Ocupado' }}
                    </span>
                  </div>
                </td>
              </ng-container>

              <!-- Acciones -->
              <ng-container matColumnDef="actions">
                <th mat-header-cell *matHeaderCellDef> Acciones </th>
                <td mat-cell *matCellDef="let instructor">
                  <button mat-icon-button [matMenuTriggerFor]="actionMenu">
                    <mat-icon>more_vert</mat-icon>
                  </button>
                  <mat-menu #actionMenu="matMenu">
                    <button mat-menu-item [routerLink]="['/instructors', instructor.id]">
                      <mat-icon>visibility</mat-icon>
                      Ver perfil
                    </button>
                    <button mat-menu-item [routerLink]="['/instructors', instructor.id, 'edit']">
                      <mat-icon>edit</mat-icon>
                      Editar
                    </button>
                    <button mat-menu-item (click)="viewSchedule(instructor.id)">
                      <mat-icon>schedule</mat-icon>
                      Horario
                    </button>
                    <button mat-menu-item (click)="assignToCourse(instructor.id)">
                      <mat-icon>assignment_add</mat-icon>
                      Asignar curso
                    </button>
                  </mat-menu>
                </td>
              </ng-container>

              <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
              <tr mat-row *matRowDef="let row; columns: displayedColumns;"></tr>
            </table>
          </div>
        </mat-card-content>
      </mat-card>
    </app-page-layout>
  `,
  styles: [`
    .page-header {
      @apply flex justify-between items-start mb-6;
    }

    .stats-grid {
      @apply grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4;
    }

    .stat-content {
      @apply flex items-center gap-4;
    }

    .stat-icon {
      @apply text-3xl;
    }

    .stat-value {
      @apply text-2xl font-bold;
    }

    .stat-label {
      @apply text-sm font-medium text-gray-700;
    }

    .stat-sublabel {
      @apply text-xs text-gray-500;
    }

    .filters-grid {
      @apply grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4;
    }

    .filter-actions {
      @apply flex gap-2 items-end;
    }

    .view-controls {
      @apply flex justify-between items-center;
    }

    .view-toggles {
      @apply flex gap-1;
    }

    .instructors-grid {
      @apply grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4;
    }

    .instructor-card {
      @apply transition-all hover:shadow-md;
    }

    .instructor-avatar img {
      @apply w-10 h-10 rounded-full object-cover;
    }

    .instructor-info {
      @apply space-y-2;
    }

    .info-row {
      @apply flex items-center gap-2 text-sm;
    }

    .rating {
      @apply flex items-center gap-1;
    }

    .star-icon {
      @apply text-yellow-500 text-base;
    }

    .availability-dot {
      @apply inline-block w-2 h-2 rounded-full mr-2;
    }

    .availability-dot.available {
      @apply bg-green-500;
    }

    .availability-dot.busy {
      @apply bg-red-500;
    }

    .table-container {
      @apply overflow-auto;
    }

    .instructors-table {
      min-width: 900px;
    }

    .instructor-cell {
      @apply flex items-center gap-3;
    }

    .avatar-sm {
      @apply w-8 h-8 rounded-full object-cover;
    }

    .certification-info {
      @apply flex items-center gap-2;
    }

    .cert-level {
      @apply capitalize;
    }

    .rating-cell {
      @apply flex items-center gap-1;
    }

    .availability-cell {
      @apply flex items-center;
    }
  `]
})
export class InstructorsListPage implements OnInit {
  private fb = inject(FormBuilder);
  private instructorsService = inject(InstructorsService);

  instructors: Instructor[] = [];
  viewMode: 'grid' | 'list' = 'grid';
  displayedColumns = ['instructor', 'specialties', 'status', 'certification', 'rating', 'availability', 'actions'];

  stats = {
    total: 0,
    active: 0,
    available_today: 0,
    busy_today: 0,
    certified_count: 0,
    pending_certification: 0,
    avg_rating: 0,
    total_reviews: 0
  };

  filterForm = this.fb.group({
    search: [''],
    status: [''],
    specialties: [[]],
    certification_level: ['']
  });

  ngOnInit() {
    this.loadInstructors();
    this.loadStats();
  }

  loadInstructors() {
    // TODO: Implement with InstructorsService
  }

  loadStats() {
    // TODO: Implement stats loading
  }

  clearFilters() {
    this.filterForm.reset();
    this.loadInstructors();
  }

  getStatusColor(status: string) {
    const colors: { [key: string]: string } = {
      active: 'primary',
      inactive: '',
      on_vacation: 'accent',
      suspended: 'warn'
    };
    return colors[status] || '';
  }

  getStatusLabel(status: string) {
    const labels: { [key: string]: string } = {
      active: 'Activo',
      inactive: 'Inactivo',
      on_vacation: 'De vacaciones',
      suspended: 'Suspendido'
    };
    return labels[status] || status;
  }

  viewSchedule(instructorId: number) {
    // TODO: Open schedule modal or navigate
  }

  viewAssignments(instructorId: number) {
    // TODO: Open assignments view
  }

  viewPayments(instructorId: number) {
    // TODO: Open payments view
  }

  toggleStatus(instructorId: number) {
    // TODO: Implement status toggle
  }

  assignToCourse(instructorId: number) {
    // TODO: Open course assignment modal
  }
}