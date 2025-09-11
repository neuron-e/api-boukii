import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTabsModule } from '@angular/material/tabs';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatChipsModule } from '@angular/material/chips';
import { NgChartsModule } from 'ng2-charts';

import { PageLayoutComponent } from '../../shared/components/layout/page-layout.component';

@Component({
  selector: 'app-analytics-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    ReactiveFormsModule,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatTabsModule,
    MatFormFieldModule,
    MatSelectModule,
    MatDatepickerModule,
    MatChipsModule,
    NgChartsModule,
    PageLayoutComponent,
  ],
  template: `
    <app-page-layout>
      <div class="page-header">
        <div class="page-title">
          <h1 class="text-2xl font-bold">Analytics & Informes</h1>
          <p class="text-gray-600">Análisis avanzado del rendimiento de la escuela</p>
        </div>
        
        <div class="page-actions">
          <button mat-stroked-button [routerLink]="'/analytics/reports'">
            <mat-icon>description</mat-icon>
            Crear Informe
          </button>
          <button mat-raised-button color="primary" (click)="exportDashboard()">
            <mat-icon>download</mat-icon>
            Exportar Dashboard
          </button>
        </div>
      </div>

      <!-- Filtros globales -->
      <mat-card class="mb-6">
        <mat-card-content>
          <form [formGroup]="filterForm" class="filters-row">
            <mat-form-field>
              <mat-label>Período</mat-label>
              <mat-select formControlName="period">
                <mat-option value="7d">Últimos 7 días</mat-option>
                <mat-option value="30d">Últimos 30 días</mat-option>
                <mat-option value="3m">Últimos 3 meses</mat-option>
                <mat-option value="6m">Últimos 6 meses</mat-option>
                <mat-option value="1y">Último año</mat-option>
                <mat-option value="custom">Personalizado</mat-option>
              </mat-select>
            </mat-form-field>

            <mat-form-field *ngIf="filterForm.get('period')?.value === 'custom'">
              <mat-label>Fecha inicio</mat-label>
              <input matInput [matDatepicker]="startPicker" formControlName="startDate">
              <mat-datepicker-toggle matIconSuffix [for]="startPicker"></mat-datepicker-toggle>
              <mat-datepicker #startPicker></mat-datepicker>
            </mat-form-field>

            <mat-form-field *ngIf="filterForm.get('period')?.value === 'custom'">
              <mat-label>Fecha fin</mat-label>
              <input matInput [matDatepicker]="endPicker" formControlName="endDate">
              <mat-datepicker-toggle matIconSuffix [for]="endPicker"></mat-datepicker-toggle>
              <mat-datepicker #endPicker></mat-datepicker>
            </mat-form-field>

            <mat-form-field>
              <mat-label>Comparar con</mat-label>
              <mat-select formControlName="comparison">
                <mat-option value="">Sin comparación</mat-option>
                <mat-option value="previous">Período anterior</mat-option>
                <mat-option value="last_year">Mismo período año anterior</mat-option>
              </mat-select>
            </mat-form-field>

            <button mat-raised-button color="primary" type="submit">
              Actualizar
            </button>
          </form>
        </mat-card-content>
      </mat-card>

      <!-- KPIs principales -->
      <div class="kpis-grid mb-6">
        <mat-card class="kpi-card">
          <mat-card-content>
            <div class="kpi-content">
              <mat-icon class="kpi-icon text-blue-600">people</mat-icon>
              <div>
                <div class="kpi-value">{{ kpis.total_students }}</div>
                <div class="kpi-label">Estudiantes</div>
                <div class="kpi-change" [class.positive]="kpis.students_growth > 0" [class.negative]="kpis.students_growth < 0">
                  <mat-icon>{{ kpis.students_growth > 0 ? 'trending_up' : 'trending_down' }}</mat-icon>
                  {{ kpis.students_growth }}%
                </div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>

        <mat-card class="kpi-card">
          <mat-card-content>
            <div class="kpi-content">
              <mat-icon class="kpi-icon text-green-600">euro</mat-icon>
              <div>
                <div class="kpi-value">{{ kpis.revenue | currency:'EUR':'symbol':'1.0-0' }}</div>
                <div class="kpi-label">Ingresos</div>
                <div class="kpi-change" [class.positive]="kpis.revenue_growth > 0" [class.negative]="kpis.revenue_growth < 0">
                  <mat-icon>{{ kpis.revenue_growth > 0 ? 'trending_up' : 'trending_down' }}</mat-icon>
                  {{ kpis.revenue_growth }}%
                </div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>

        <mat-card class="kpi-card">
          <mat-card-content>
            <div class="kpi-content">
              <mat-icon class="kpi-icon text-orange-600">event_available</mat-icon>
              <div>
                <div class="kpi-value">{{ kpis.occupancy_rate }}%</div>
                <div class="kpi-label">Ocupación</div>
                <div class="kpi-change" [class.positive]="kpis.occupancy_growth > 0" [class.negative]="kpis.occupancy_growth < 0">
                  <mat-icon>{{ kpis.occupancy_growth > 0 ? 'trending_up' : 'trending_down' }}</mat-icon>
                  {{ kpis.occupancy_growth }}%
                </div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>

        <mat-card class="kpi-card">
          <mat-card-content>
            <div class="kpi-content">
              <mat-icon class="kpi-icon text-purple-600">star</mat-icon>
              <div>
                <div class="kpi-value">{{ kpis.satisfaction_score | number:'1.1-1' }}</div>
                <div class="kpi-label">Satisfacción</div>
                <div class="kpi-change" [class.positive]="kpis.satisfaction_growth > 0" [class.negative]="kpis.satisfaction_growth < 0">
                  <mat-icon>{{ kpis.satisfaction_growth > 0 ? 'trending_up' : 'trending_down' }}</mat-icon>
                  {{ kpis.satisfaction_growth }}%
                </div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
      </div>

      <div class="content-grid">
        <div class="charts-section">
          <mat-tab-group>
            <!-- Tab: Tendencias -->
            <mat-tab label="Tendencias">
              <div class="tab-content">
                <mat-card class="mb-4">
                  <mat-card-header>
                    <mat-card-title>Evolución de Estudiantes</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="chart-container">
                      <div class="chart-placeholder">
                        <mat-icon>show_chart</mat-icon>
                        <p>Gráfico de evolución de estudiantes</p>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>

                <mat-card>
                  <mat-card-header>
                    <mat-card-title>Ingresos vs Gastos</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="chart-container">
                      <div class="chart-placeholder">
                        <mat-icon>bar_chart</mat-icon>
                        <p>Gráfico de ingresos vs gastos</p>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>
              </div>
            </mat-tab>

            <!-- Tab: Rendimiento -->
            <mat-tab label="Rendimiento">
              <div class="tab-content">
                <div class="performance-grid">
                  <mat-card>
                    <mat-card-header>
                      <mat-card-title>Cursos más Populares</mat-card-title>
                    </mat-card-header>
                    <mat-card-content>
                      <div class="courses-ranking">
                        <div *ngFor="let course of popularCourses; let i = index" class="course-item">
                          <div class="course-rank">{{ i + 1 }}</div>
                          <div class="course-info">
                            <div class="course-name">{{ course.name }}</div>
                            <div class="course-stats">
                              {{ course.bookings }} reservas • {{ course.revenue | currency:'EUR':'symbol':'1.0-0' }}
                            </div>
                          </div>
                          <div class="course-score">{{ course.satisfaction | number:'1.1-1' }}</div>
                        </div>
                      </div>
                    </mat-card-content>
                  </mat-card>

                  <mat-card>
                    <mat-card-header>
                      <mat-card-title>Mejor Instructores</mat-card-title>
                    </mat-card-header>
                    <mat-card-content>
                      <div class="instructors-ranking">
                        <div *ngFor="let instructor of topInstructors; let i = index" class="instructor-item">
                          <div class="instructor-rank">{{ i + 1 }}</div>
                          <div class="instructor-info">
                            <div class="instructor-name">{{ instructor.name }}</div>
                            <div class="instructor-stats">
                              {{ instructor.classes }} clases • {{ instructor.rating | number:'1.1-1' }}★
                            </div>
                          </div>
                          <div class="instructor-revenue">{{ instructor.revenue | currency:'EUR':'symbol':'1.0-0' }}</div>
                        </div>
                      </div>
                    </mat-card-content>
                  </mat-card>
                </div>
              </div>
            </mat-tab>

            <!-- Tab: Clientes -->
            <mat-tab label="Análisis de Clientes">
              <div class="tab-content">
                <div class="clients-analysis-grid">
                  <mat-card>
                    <mat-card-header>
                      <mat-card-title>Segmentación de Clientes</mat-card-title>
                    </mat-card-header>
                    <mat-card-content>
                      <div class="segments-list">
                        <div *ngFor="let segment of clientSegments" class="segment-item">
                          <div class="segment-info">
                            <div class="segment-name">{{ segment.name }}</div>
                            <div class="segment-description">{{ segment.description }}</div>
                          </div>
                          <div class="segment-stats">
                            <div class="segment-count">{{ segment.count }} clientes</div>
                            <div class="segment-value">{{ segment.avg_value | currency:'EUR':'symbol':'1.0-0' }} promedio</div>
                          </div>
                        </div>
                      </div>
                    </mat-card-content>
                  </mat-card>

                  <mat-card>
                    <mat-card-header>
                      <mat-card-title>Retención de Clientes</mat-card-title>
                    </mat-card-header>
                    <mat-card-content>
                      <div class="retention-metrics">
                        <div class="metric-item">
                          <span class="metric-label">Tasa de retención:</span>
                          <span class="metric-value">{{ retention.rate }}%</span>
                        </div>
                        <div class="metric-item">
                          <span class="metric-label">Clientes recurrentes:</span>
                          <span class="metric-value">{{ retention.recurring_clients }}</span>
                        </div>
                        <div class="metric-item">
                          <span class="metric-label">Churn rate:</span>
                          <span class="metric-value">{{ retention.churn_rate }}%</span>
                        </div>
                      </div>
                    </mat-card-content>
                  </mat-card>
                </div>
              </div>
            </mat-tab>

            <!-- Tab: Predicciones -->
            <mat-tab label="Predicciones">
              <div class="tab-content">
                <mat-card class="mb-4">
                  <mat-card-header>
                    <mat-card-title>Forecasting de Ingresos</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="forecast-info">
                      <div class="forecast-item">
                        <div class="forecast-period">Próximo mes</div>
                        <div class="forecast-value">{{ forecasts.next_month_revenue | currency:'EUR':'symbol':'1.0-0' }}</div>
                        <div class="forecast-confidence">{{ forecasts.confidence }}% confianza</div>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>

                <mat-card>
                  <mat-card-header>
                    <mat-card-title>Recomendaciones</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="recommendations-list">
                      <div *ngFor="let recommendation of recommendations" class="recommendation-item">
                        <mat-icon class="recommendation-icon" [class]="'text-' + recommendation.priority + '-600'">
                          {{ recommendation.icon }}
                        </mat-icon>
                        <div>
                          <div class="recommendation-title">{{ recommendation.title }}</div>
                          <div class="recommendation-description">{{ recommendation.description }}</div>
                        </div>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>
              </div>
            </mat-tab>
          </mat-tab-group>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
          <mat-card class="mb-4">
            <mat-card-header>
              <mat-card-title>Informes Rápidos</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="quick-reports">
                <button mat-stroked-button class="w-full" (click)="generateReport('daily')">
                  <mat-icon>today</mat-icon>
                  Informe Diario
                </button>
                <button mat-stroked-button class="w-full" (click)="generateReport('weekly')">
                  <mat-icon>date_range</mat-icon>
                  Informe Semanal
                </button>
                <button mat-stroked-button class="w-full" (click)="generateReport('monthly')">
                  <mat-icon>calendar_month</mat-icon>
                  Informe Mensual
                </button>
                <button mat-stroked-button class="w-full" (click)="generateReport('seasonal')">
                  <mat-icon>ac_unit</mat-icon>
                  Informe Temporada
                </button>
              </div>
            </mat-card-content>
          </mat-card>

          <mat-card class="mb-4">
            <mat-card-header>
              <mat-card-title>Alertas</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="alerts-list">
                <div *ngFor="let alert of alerts" class="alert-item" [class]="'alert-' + alert.type">
                  <mat-icon>{{ alert.icon }}</mat-icon>
                  <div>
                    <div class="alert-message">{{ alert.message }}</div>
                    <div class="alert-timestamp">{{ alert.created_at | date:'dd/MM HH:mm' }}</div>
                  </div>
                </div>
              </div>
            </mat-card-content>
          </mat-card>

          <mat-card>
            <mat-card-header>
              <mat-card-title>Comparativa Temporal</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="temporal-comparison">
                <div class="comparison-item">
                  <span class="comparison-label">vs Mes anterior:</span>
                  <span class="comparison-value positive">+15.2%</span>
                </div>
                <div class="comparison-item">
                  <span class="comparison-label">vs Año anterior:</span>
                  <span class="comparison-value positive">+28.7%</span>
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

    .filters-row {
      @apply flex gap-4 items-end flex-wrap;
    }

    .kpis-grid {
      @apply grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4;
    }

    .kpi-content {
      @apply flex items-center gap-4;
    }

    .kpi-icon {
      @apply text-3xl;
    }

    .kpi-value {
      @apply text-2xl font-bold;
    }

    .kpi-label {
      @apply text-sm font-medium text-gray-700;
    }

    .kpi-change {
      @apply flex items-center gap-1 text-xs;
    }

    .kpi-change.positive {
      @apply text-green-600;
    }

    .kpi-change.negative {
      @apply text-red-600;
    }

    .content-grid {
      @apply grid grid-cols-1 xl:grid-cols-3 gap-6;
    }

    .charts-section {
      @apply xl:col-span-2;
    }

    .tab-content {
      @apply p-4;
    }

    .chart-container {
      @apply h-64 flex items-center justify-center bg-gray-50 rounded;
    }

    .chart-placeholder {
      @apply text-center text-gray-400;
    }

    .performance-grid, .clients-analysis-grid {
      @apply grid grid-cols-1 gap-4;
    }

    .courses-ranking, .instructors-ranking {
      @apply space-y-3;
    }

    .course-item, .instructor-item {
      @apply flex items-center gap-3 p-2 border-b border-gray-100 last:border-0;
    }

    .course-rank, .instructor-rank {
      @apply w-6 h-6 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center text-sm font-medium;
    }

    .course-info, .instructor-info {
      @apply flex-1;
    }

    .course-name, .instructor-name {
      @apply font-medium;
    }

    .course-stats, .instructor-stats {
      @apply text-sm text-gray-500;
    }

    .segments-list {
      @apply space-y-4;
    }

    .segment-item {
      @apply flex justify-between items-start;
    }

    .segment-description {
      @apply text-sm text-gray-500;
    }

    .segment-stats {
      @apply text-right;
    }

    .retention-metrics {
      @apply space-y-3;
    }

    .metric-item {
      @apply flex justify-between;
    }

    .forecast-info {
      @apply text-center p-4;
    }

    .forecast-value {
      @apply text-3xl font-bold text-blue-600;
    }

    .forecast-confidence {
      @apply text-sm text-gray-500;
    }

    .recommendations-list {
      @apply space-y-3;
    }

    .recommendation-item {
      @apply flex gap-3;
    }

    .recommendation-title {
      @apply font-medium;
    }

    .recommendation-description {
      @apply text-sm text-gray-500;
    }

    .quick-reports {
      @apply space-y-2;
    }

    .alerts-list {
      @apply space-y-3;
    }

    .alert-item {
      @apply flex gap-2 p-2 rounded;
    }

    .alert-warning {
      @apply bg-orange-50 text-orange-800;
    }

    .alert-info {
      @apply bg-blue-50 text-blue-800;
    }

    .alert-message {
      @apply text-sm font-medium;
    }

    .alert-timestamp {
      @apply text-xs opacity-75;
    }

    .temporal-comparison {
      @apply space-y-2;
    }

    .comparison-item {
      @apply flex justify-between;
    }

    .comparison-value.positive {
      @apply text-green-600 font-medium;
    }
  `]
})
export class AnalyticsDashboardPage implements OnInit {
  private fb = inject(FormBuilder);

  filterForm = this.fb.group({
    period: ['30d'],
    startDate: [''],
    endDate: [''],
    comparison: ['previous']
  });

  kpis = {
    total_students: 1250,
    students_growth: 12.5,
    revenue: 85000,
    revenue_growth: 18.2,
    occupancy_rate: 78,
    occupancy_growth: -2.1,
    satisfaction_score: 4.6,
    satisfaction_growth: 5.3
  };

  popularCourses = [
    { name: 'Esquí Principiantes', bookings: 156, revenue: 23400, satisfaction: 4.7 },
    { name: 'Snowboard Intermedio', bookings: 124, revenue: 18600, satisfaction: 4.5 },
    { name: 'Clases Privadas', bookings: 89, revenue: 26700, satisfaction: 4.9 }
  ];

  topInstructors = [
    { name: 'Carlos Mendez', classes: 89, rating: 4.8, revenue: 12400 },
    { name: 'Ana García', classes: 76, rating: 4.7, revenue: 10800 },
    { name: 'Luis Torres', classes: 68, rating: 4.6, revenue: 9500 }
  ];

  clientSegments = [
    { name: 'VIP', description: 'Clientes premium', count: 45, avg_value: 850 },
    { name: 'Recurrentes', description: 'Clientes habituales', count: 320, avg_value: 420 },
    { name: 'Nuevos', description: 'Primeras reservas', count: 180, avg_value: 280 }
  ];

  retention = {
    rate: 72,
    recurring_clients: 385,
    churn_rate: 18
  };

  forecasts = {
    next_month_revenue: 92000,
    confidence: 87
  };

  recommendations = [
    {
      title: 'Optimizar precios de temporada baja',
      description: 'Considera reducir precios un 15% en enero',
      icon: 'trending_down',
      priority: 'orange'
    },
    {
      title: 'Ampliar clases de principiantes',
      description: 'Alta demanda detectada',
      icon: 'trending_up',
      priority: 'green'
    }
  ];

  alerts = [
    {
      type: 'warning',
      icon: 'warning',
      message: 'Ocupación baja en próxima semana',
      created_at: new Date()
    },
    {
      type: 'info',
      icon: 'info',
      message: 'Nuevo record de satisfacción alcanzado',
      created_at: new Date()
    }
  ];

  ngOnInit() {
    // TODO: Load analytics data
  }

  exportDashboard() {
    // TODO: Export dashboard functionality
  }

  generateReport(type: string) {
    // TODO: Generate specific report type
  }
}