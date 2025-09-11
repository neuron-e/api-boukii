import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatTableModule } from '@angular/material/table';
import { MatPaginatorModule } from '@angular/material/paginator';
import { MatSortModule } from '@angular/material/sort';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatChipsModule } from '@angular/material/chips';
import { MatMenuModule } from '@angular/material/menu';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatCardModule } from '@angular/material/card';

import { BookingsService } from './services/bookings.service';
import { Booking } from './models/booking.interface';
import { PageLayoutComponent } from '../../shared/components/layout/page-layout.component';
import { LoaderComponent } from '../../shared/components/ui/loader/loader.component';
import { EmptyStateComponent } from '../../shared/components/ui/empty-state/empty-state.component';
import { JoinPipe } from '../../shared/pipes/join.pipe';

@Component({
  selector: 'app-bookings-list',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    ReactiveFormsModule,
    MatTableModule,
    MatPaginatorModule,
    MatSortModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatChipsModule,
    MatMenuModule,
    MatDatepickerModule,
    MatCardModule,
    PageLayoutComponent,
    LoaderComponent,
    EmptyStateComponent,
    JoinPipe,
  ],
  template: `
    <app-page-layout>
      <div class="page-header">
        <div class="page-title">
          <h1 class="text-2xl font-bold">Reservas</h1>
          <p class="text-gray-600">Gestiona las reservas de cursos y actividades</p>
        </div>
        
        <div class="page-actions">
          <button mat-raised-button color="primary" [routerLink]="'/bookings/create'">
            <mat-icon>add</mat-icon>
            Nueva Reserva
          </button>
        </div>
      </div>

      <!-- Filtros -->
      <mat-card class="mb-6">
        <mat-card-content>
          <form [formGroup]="filterForm" class="filters-grid">
            <mat-form-field>
              <mat-label>Buscar</mat-label>
              <input matInput formControlName="search" placeholder="Cliente, curso, instructor...">
              <mat-icon matSuffix>search</mat-icon>
            </mat-form-field>

            <mat-form-field>
              <mat-label>Estado</mat-label>
              <mat-select formControlName="status" multiple>
                <mat-option value="pending">Pendiente</mat-option>
                <mat-option value="confirmed">Confirmada</mat-option>
                <mat-option value="paid">Pagada</mat-option>
                <mat-option value="cancelled">Cancelada</mat-option>
                <mat-option value="completed">Completada</mat-option>
              </mat-select>
            </mat-form-field>

            <mat-form-field>
              <mat-label>Fecha desde</mat-label>
              <input matInput [matDatepicker]="fromDatePicker" formControlName="dateFrom">
              <mat-datepicker-toggle matIconSuffix [for]="fromDatePicker"></mat-datepicker-toggle>
              <mat-datepicker #fromDatePicker></mat-datepicker>
            </mat-form-field>

            <mat-form-field>
              <mat-label>Fecha hasta</mat-label>
              <input matInput [matDatepicker]="toDatePicker" formControlName="dateTo">
              <mat-datepicker-toggle matIconSuffix [for]="toDatePicker"></mat-datepicker-toggle>
              <mat-datepicker #toDatePicker></mat-datepicker>
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

      <!-- Estadísticas rápidas -->
      <div class="stats-grid mb-6">
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-value">{{ stats.total }}</div>
            <div class="stat-label">Total</div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-value text-green-600">{{ stats.confirmed }}</div>
            <div class="stat-label">Confirmadas</div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-value text-blue-600">{{ stats.pending }}</div>
            <div class="stat-label">Pendientes</div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-value text-orange-600">{{ stats.revenue | currency:'EUR':'symbol':'1.2-2' }}</div>
            <div class="stat-label">Ingresos</div>
          </mat-card-content>
        </mat-card>
      </div>

      <!-- Lista de reservas -->
      <mat-card>
        <mat-card-content>
          <div class="table-container">
            <table mat-table [dataSource]="bookings" class="bookings-table w-full">
              <!-- Columna ID -->
              <ng-container matColumnDef="id">
                <th mat-header-cell *matHeaderCellDef> ID </th>
                <td mat-cell *matCellDef="let booking"> #{{ booking.id }} </td>
              </ng-container>

              <!-- Columna Cliente -->
              <ng-container matColumnDef="client">
                <th mat-header-cell *matHeaderCellDef> Cliente </th>
                <td mat-cell *matCellDef="let booking">
                  <div class="client-info">
                    <div class="font-medium">{{ booking.client.full_name }}</div>
                    <div class="text-sm text-gray-500">{{ booking.client.email }}</div>
                  </div>
                </td>
              </ng-container>

              <!-- Columna Curso -->
              <ng-container matColumnDef="course">
                <th mat-header-cell *matHeaderCellDef> Curso/Actividad </th>
                <td mat-cell *matCellDef="let booking">
                  <div class="course-info">
                    <div class="font-medium">{{ booking.course.title }}</div>
                    <div class="text-sm text-gray-500">{{ booking.course.dates | join:', ' }}</div>
                  </div>
                </td>
              </ng-container>

              <!-- Columna Estado -->
              <ng-container matColumnDef="status">
                <th mat-header-cell *matHeaderCellDef> Estado </th>
                <td mat-cell *matCellDef="let booking">
                  <mat-chip [color]="getStatusColor(booking.status)" selected>
                    {{ getStatusLabel(booking.status) }}
                  </mat-chip>
                </td>
              </ng-container>

              <!-- Columna Precio -->
              <ng-container matColumnDef="price">
                <th mat-header-cell *matHeaderCellDef> Precio </th>
                <td mat-cell *matCellDef="let booking">
                  <div class="price-info">
                    <div class="font-medium">{{ booking.total_price | currency:'EUR':'symbol':'1.2-2' }}</div>
                    <div class="text-sm text-gray-500" *ngIf="booking.discount_amount">
                      <s>{{ booking.original_price | currency:'EUR':'symbol':'1.2-2' }}</s>
                    </div>
                  </div>
                </td>
              </ng-container>

              <!-- Columna Fecha -->
              <ng-container matColumnDef="created_at">
                <th mat-header-cell *matHeaderCellDef> Fecha </th>
                <td mat-cell *matCellDef="let booking">
                  {{ booking.created_at | date:'dd/MM/yyyy HH:mm' }}
                </td>
              </ng-container>

              <!-- Columna Acciones -->
              <ng-container matColumnDef="actions">
                <th mat-header-cell *matHeaderCellDef> Acciones </th>
                <td mat-cell *matCellDef="let booking">
                  <button mat-icon-button [matMenuTriggerFor]="menu">
                    <mat-icon>more_vert</mat-icon>
                  </button>
                  <mat-menu #menu="matMenu">
                    <button mat-menu-item [routerLink]="['/bookings', booking.id]">
                      <mat-icon>visibility</mat-icon>
                      Ver detalles
                    </button>
                    <button mat-menu-item [routerLink]="['/bookings', booking.id, 'edit']">
                      <mat-icon>edit</mat-icon>
                      Editar
                    </button>
                    <button mat-menu-item (click)="confirmBooking(booking)" *ngIf="booking.status === 'pending'">
                      <mat-icon>check</mat-icon>
                      Confirmar
                    </button>
                    <button mat-menu-item (click)="cancelBooking(booking)" *ngIf="booking.status !== 'cancelled'">
                      <mat-icon>cancel</mat-icon>
                      Cancelar
                    </button>
                  </mat-menu>
                </td>
              </ng-container>

              <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
              <tr mat-row *matRowDef="let row; columns: displayedColumns;"></tr>
            </table>
          </div>

          <mat-paginator 
            [pageSizeOptions]="[10, 25, 50, 100]"
            [showFirstLastButtons]="true">
          </mat-paginator>
        </mat-card-content>
      </mat-card>

      <app-empty-state 
        *ngIf="bookings.length === 0 && !loading"
        title="No hay reservas"
        description="Aún no se han creado reservas"
        [showAction]="true"
        actionText="Crear primera reserva"
        (actionClick)="createBooking()">
      </app-empty-state>

      <app-loader *ngIf="loading"></app-loader>
    </app-page-layout>
  `,
  styles: [`
    .page-header {
      @apply flex justify-between items-start mb-6;
    }

    .filters-grid {
      @apply grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4;
    }

    .filter-actions {
      @apply flex gap-2 items-end;
    }

    .stats-grid {
      @apply grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4;
    }

    .stat-card {
      @apply text-center;
    }

    .stat-value {
      @apply text-2xl font-bold;
    }

    .stat-label {
      @apply text-sm text-gray-500;
    }

    .table-container {
      @apply overflow-auto;
    }

    .bookings-table {
      min-width: 800px;
    }

    .client-info, .course-info, .price-info {
      @apply space-y-1;
    }
  `]
})
export class BookingsListPage implements OnInit {
  private router = inject(Router);
  private fb = inject(FormBuilder);
  private bookingsService = inject(BookingsService);

  bookings: Booking[] = [];
  loading = false;
  displayedColumns = ['id', 'client', 'course', 'status', 'price', 'created_at', 'actions'];

  stats = {
    total: 0,
    confirmed: 0,
    pending: 0,
    revenue: 0
  };

  filterForm = this.fb.group({
    search: [''],
    status: [[]],
    dateFrom: [''],
    dateTo: ['']
  });

  ngOnInit() {
    this.loadBookings();
    this.loadStats();
  }

  loadBookings() {
    this.loading = true;
    // TODO: Implement with BookingsService
    this.loading = false;
  }

  loadStats() {
    // TODO: Implement stats loading
  }

  clearFilters() {
    this.filterForm.reset();
    this.loadBookings();
  }

  getStatusColor(status: string) {
    const colors: { [key: string]: string } = {
      pending: 'warn',
      confirmed: 'accent',
      paid: 'primary',
      cancelled: '',
      completed: 'primary'
    };
    return colors[status] || '';
  }

  getStatusLabel(status: string) {
    const labels: { [key: string]: string } = {
      pending: 'Pendiente',
      confirmed: 'Confirmada',
      paid: 'Pagada',
      cancelled: 'Cancelada',
      completed: 'Completada'
    };
    return labels[status] || status;
  }

  confirmBooking(booking: Booking) {
    // TODO: Implement confirm booking
  }

  cancelBooking(booking: Booking) {
    // TODO: Implement cancel booking
  }

  createBooking() {
    this.router.navigate(['/bookings/new']);
  }
}