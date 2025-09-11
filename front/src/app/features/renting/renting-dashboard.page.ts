import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTabsModule } from '@angular/material/tabs';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressBarModule } from '@angular/material/progress-bar';

import { RentingService } from './services/renting.service';
import { PageLayoutComponent } from '../../shared/components/layout/page-layout.component';

@Component({
  selector: 'app-renting-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatTabsModule,
    MatChipsModule,
    MatProgressBarModule,
    PageLayoutComponent,
  ],
  template: `
    <app-page-layout>
      <div class="page-header">
        <div class="page-title">
          <h1 class="text-2xl font-bold">Centro de Alquiler</h1>
          <p class="text-gray-600">Gestión completa de equipamiento y alquileres</p>
        </div>
        
        <div class="page-actions">
          <button mat-raised-button color="accent" [routerLink]="'/renting/bookings/create'">
            <mat-icon>add</mat-icon>
            Nueva Reserva
          </button>
          <button mat-raised-button color="primary" [routerLink]="'/renting/inventory/create'">
            <mat-icon>inventory</mat-icon>
            Añadir Equipo
          </button>
        </div>
      </div>

      <!-- Estadísticas principales -->
      <div class="stats-grid mb-6">
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-blue-600">inventory_2</mat-icon>
              <div>
                <div class="stat-value">{{ stats.totalItems }}</div>
                <div class="stat-label">Total Artículos</div>
                <div class="stat-sublabel">{{ stats.availableItems }} disponibles</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-orange-600">event_available</mat-icon>
              <div>
                <div class="stat-value">{{ stats.activeRentals }}</div>
                <div class="stat-label">Alquileres Activos</div>
                <div class="stat-sublabel">{{ stats.pendingReturns }} pendientes devolución</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-green-600">euro</mat-icon>
              <div>
                <div class="stat-value">{{ stats.monthlyRevenue | currency:'EUR':'symbol':'1.0-0' }}</div>
                <div class="stat-label">Ingresos del Mes</div>
                <div class="stat-sublabel">{{ stats.revenueGrowth > 0 ? '+' : '' }}{{ stats.revenueGrowth }}%</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-red-600">warning</mat-icon>
              <div>
                <div class="stat-value">{{ stats.issuesCount }}</div>
                <div class="stat-label">Incidencias</div>
                <div class="stat-sublabel">{{ stats.maintenanceItems }} en mantenimiento</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
      </div>

      <div class="content-grid">
        <!-- Panel principal -->
        <div class="main-content">
          <mat-tab-group>
            <!-- Tab: Alquileres recientes -->
            <mat-tab label="Alquileres Recientes">
              <div class="tab-content">
                <div class="section-header">
                  <h3>Alquileres de Hoy</h3>
                  <button mat-stroked-button [routerLink]="'/renting/bookings'">
                    Ver todos
                  </button>
                </div>
                
                <div class="rentals-list">
                  <mat-card *ngFor="let rental of recentRentals" class="rental-card">
                    <mat-card-content>
                      <div class="rental-info">
                        <div class="rental-client">
                          <h4>{{ rental.client_name }}</h4>
                          <p class="text-sm text-gray-500">{{ rental.client_email }}</p>
                        </div>
                        
                        <div class="rental-items">
                          <mat-chip-listbox>
                            <mat-chip *ngFor="let item of rental.items">
                              {{ item.name }} ({{ item.size }})
                            </mat-chip>
                          </mat-chip-listbox>
                        </div>
                        
                        <div class="rental-status">
                          <mat-chip [color]="getRentalStatusColor(rental.status)" selected>
                            {{ getRentalStatusLabel(rental.status) }}
                          </mat-chip>
                          <span class="rental-dates">
                            {{ rental.start_date | date:'dd/MM' }} - {{ rental.end_date | date:'dd/MM' }}
                          </span>
                        </div>
                      </div>
                    </mat-card-content>
                  </mat-card>
                </div>
              </div>
            </mat-tab>

            <!-- Tab: Inventario -->
            <mat-tab label="Estado del Inventario">
              <div class="tab-content">
                <div class="section-header">
                  <h3>Ocupación por Categoría</h3>
                  <button mat-stroked-button [routerLink]="'/renting/inventory'">
                    Ver inventario completo
                  </button>
                </div>
                
                <div class="inventory-overview">
                  <mat-card *ngFor="let category of inventoryByCategory" class="inventory-card">
                    <mat-card-content>
                      <div class="inventory-header">
                        <mat-icon [class]="'category-icon text-' + category.color + '-600'">
                          {{ category.icon }}
                        </mat-icon>
                        <h4>{{ category.name }}</h4>
                      </div>
                      
                      <div class="inventory-stats">
                        <div class="stat-row">
                          <span>Total:</span>
                          <span>{{ category.total_items }}</span>
                        </div>
                        <div class="stat-row">
                          <span>Disponible:</span>
                          <span class="text-green-600">{{ category.available_items }}</span>
                        </div>
                        <div class="stat-row">
                          <span>Alquilado:</span>
                          <span class="text-orange-600">{{ category.rented_items }}</span>
                        </div>
                        <div class="stat-row">
                          <span>Mantenimiento:</span>
                          <span class="text-red-600">{{ category.maintenance_items }}</span>
                        </div>
                      </div>
                      
                      <mat-progress-bar 
                        mode="determinate" 
                        [value]="category.occupancy_rate"
                        [color]="category.occupancy_rate > 80 ? 'warn' : 'primary'">
                      </mat-progress-bar>
                      <div class="occupancy-label">
                        {{ category.occupancy_rate }}% ocupación
                      </div>
                    </mat-card-content>
                  </mat-card>
                </div>
              </div>
            </mat-tab>

            <!-- Tab: Devoluciones pendientes -->
            <mat-tab label="Devoluciones Pendientes">
              <div class="tab-content">
                <div class="section-header">
                  <h3>Equipos por Devolver</h3>
                </div>
                
                <div class="returns-list">
                  <mat-card *ngFor="let return of pendingReturns" class="return-card" 
                           [class.overdue]="return.is_overdue">
                    <mat-card-content>
                      <div class="return-info">
                        <div class="return-header">
                          <h4>{{ return.client_name }}</h4>
                          <mat-chip 
                            [color]="return.is_overdue ? 'warn' : 'accent'" 
                            selected>
                            {{ return.is_overdue ? 'VENCIDO' : 'PENDIENTE' }}
                          </mat-chip>
                        </div>
                        
                        <div class="return-items">
                          <span *ngFor="let item of return.items; let last = last">
                            {{ item.name }}{{ !last ? ', ' : '' }}
                          </span>
                        </div>
                        
                        <div class="return-dates">
                          <span>Fecha prevista: {{ return.expected_return | date:'dd/MM/yyyy' }}</span>
                          <span *ngIf="return.is_overdue" class="text-red-600">
                            ({{ return.days_overdue }} días de retraso)
                          </span>
                        </div>
                        
                        <div class="return-actions">
                          <button mat-stroked-button (click)="processReturn(return.id)">
                            <mat-icon>assignment_return</mat-icon>
                            Procesar devolución
                          </button>
                          <button mat-stroked-button color="warn" (click)="contactClient(return.client_id)">
                            <mat-icon>phone</mat-icon>
                            Contactar cliente
                          </button>
                        </div>
                      </div>
                    </mat-card-content>
                  </mat-card>
                </div>
              </div>
            </mat-tab>
          </mat-tab-group>
        </div>

        <!-- Panel lateral -->
        <div class="sidebar">
          <!-- Accesos rápidos -->
          <mat-card class="mb-4">
            <mat-card-header>
              <mat-card-title>Accesos Rápidos</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="quick-actions">
                <button mat-stroked-button class="w-full" [routerLink]="'/renting/bookings/scan'">
                  <mat-icon>qr_code_scanner</mat-icon>
                  Escanear QR
                </button>
                <button mat-stroked-button class="w-full" [routerLink]="'/renting/inventory/check'">
                  <mat-icon>inventory</mat-icon>
                  Control Stock
                </button>
                <button mat-stroked-button class="w-full" [routerLink]="'/renting/maintenance'">
                  <mat-icon>build</mat-icon>
                  Mantenimiento
                </button>
                <button mat-stroked-button class="w-full" [routerLink]="'/renting/reports'">
                  <mat-icon>analytics</mat-icon>
                  Informes
                </button>
              </div>
            </mat-card-content>
          </mat-card>

          <!-- Alertas -->
          <mat-card class="mb-4" *ngIf="alerts.length">
            <mat-card-header>
              <mat-card-title>Alertas</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="alerts-list">
                <div *ngFor="let alert of alerts" class="alert-item" [class]="'alert-' + alert.type">
                  <mat-icon>{{ getAlertIcon(alert.type) }}</mat-icon>
                  <div>
                    <div class="alert-title">{{ alert.title }}</div>
                    <div class="alert-message">{{ alert.message }}</div>
                  </div>
                </div>
              </div>
            </mat-card-content>
          </mat-card>

          <!-- Resumen del día -->
          <mat-card>
            <mat-card-header>
              <mat-card-title>Resumen del Día</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="daily-summary">
                <div class="summary-item">
                  <span class="label">Nuevos alquileres:</span>
                  <span class="value">{{ dailySummary.new_rentals }}</span>
                </div>
                <div class="summary-item">
                  <span class="label">Devoluciones:</span>
                  <span class="value">{{ dailySummary.returns }}</span>
                </div>
                <div class="summary-item">
                  <span class="label">Ingresos:</span>
                  <span class="value">{{ dailySummary.revenue | currency:'EUR':'symbol':'1.0-0' }}</span>
                </div>
              </div>
            </mat-card-content>
          </mat-card>
        </div>
      </div>
    </app-page-layout>
  `,
  styles: [`
    .page-header {
      @apply flex justify-between items-start mb-6;
    }

    .stats-grid {
      @apply grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4;
    }

    .stat-card .stat-content {
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

    .content-grid {
      @apply grid grid-cols-1 xl:grid-cols-3 gap-6;
    }

    .main-content {
      @apply xl:col-span-2;
    }

    .tab-content {
      @apply p-4;
    }

    .section-header {
      @apply flex justify-between items-center mb-4;
    }

    .section-header h3 {
      @apply text-lg font-medium;
    }

    .rentals-list, .returns-list {
      @apply space-y-3;
    }

    .rental-card, .return-card {
      @apply transition-all hover:shadow-md;
    }

    .rental-info {
      @apply space-y-3;
    }

    .rental-client h4 {
      @apply font-medium;
    }

    .rental-status {
      @apply flex items-center justify-between;
    }

    .rental-dates {
      @apply text-sm text-gray-500;
    }

    .inventory-overview {
      @apply grid grid-cols-1 md:grid-cols-2 gap-4;
    }

    .inventory-header {
      @apply flex items-center gap-2 mb-3;
    }

    .inventory-header h4 {
      @apply font-medium;
    }

    .inventory-stats {
      @apply space-y-1 mb-3;
    }

    .stat-row {
      @apply flex justify-between text-sm;
    }

    .occupancy-label {
      @apply text-xs text-center mt-1;
    }

    .return-card.overdue {
      @apply border-l-4 border-red-500;
    }

    .return-info {
      @apply space-y-3;
    }

    .return-header {
      @apply flex justify-between items-center;
    }

    .return-items {
      @apply text-sm text-gray-600;
    }

    .return-dates {
      @apply text-sm;
    }

    .return-actions {
      @apply flex gap-2;
    }

    .quick-actions {
      @apply space-y-2;
    }

    .alerts-list {
      @apply space-y-3;
    }

    .alert-item {
      @apply flex items-start gap-2 p-2 rounded;
    }

    .alert-warning {
      @apply bg-orange-50 text-orange-800;
    }

    .alert-error {
      @apply bg-red-50 text-red-800;
    }

    .alert-info {
      @apply bg-blue-50 text-blue-800;
    }

    .alert-title {
      @apply font-medium text-sm;
    }

    .alert-message {
      @apply text-xs;
    }

    .daily-summary {
      @apply space-y-2;
    }

    .summary-item {
      @apply flex justify-between text-sm;
    }

    .summary-item .label {
      @apply text-gray-600;
    }

    .summary-item .value {
      @apply font-medium;
    }
  `]
})
export class RentingDashboardPage implements OnInit {
  private rentingService = inject(RentingService);

  stats = {
    totalItems: 0,
    availableItems: 0,
    activeRentals: 0,
    pendingReturns: 0,
    monthlyRevenue: 0,
    revenueGrowth: 0,
    issuesCount: 0,
    maintenanceItems: 0
  };

  recentRentals: any[] = [];
  pendingReturns: any[] = [];
  inventoryByCategory: any[] = [];
  alerts: any[] = [];
  dailySummary = {
    new_rentals: 0,
    returns: 0,
    revenue: 0
  };

  ngOnInit() {
    this.loadDashboardData();
  }

  loadDashboardData() {
    // TODO: Load data using RentingService
  }

  getRentalStatusColor(status: string) {
    const colors: { [key: string]: string } = {
      'active': 'primary',
      'overdue': 'warn',
      'returned': 'accent',
      'cancelled': ''
    };
    return colors[status] || '';
  }

  getRentalStatusLabel(status: string) {
    const labels: { [key: string]: string } = {
      'active': 'Activo',
      'overdue': 'Vencido',
      'returned': 'Devuelto',
      'cancelled': 'Cancelado'
    };
    return labels[status] || status;
  }

  getAlertIcon(type: string) {
    const icons: { [key: string]: string } = {
      'warning': 'warning',
      'error': 'error',
      'info': 'info'
    };
    return icons[type] || 'notifications';
  }

  processReturn(rentalId: number) {
    // TODO: Open return processing modal
  }

  contactClient(clientId: number) {
    // TODO: Open communication modal
  }
}