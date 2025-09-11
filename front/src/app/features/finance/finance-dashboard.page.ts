import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTabsModule } from '@angular/material/tabs';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { NgChartsModule } from 'ng2-charts';

import { PageLayoutComponent } from '../../shared/components/layout/page-layout.component';

@Component({
  selector: 'app-finance-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatTabsModule,
    MatProgressBarModule,
    NgChartsModule,
    PageLayoutComponent,
  ],
  template: `
    <app-page-layout>
      <div class="page-header">
        <div class="page-title">
          <h1 class="text-2xl font-bold">Panel Financiero</h1>
          <p class="text-gray-600">Gestión financiera y contabilidad</p>
        </div>
        
        <div class="page-actions">
          <button mat-stroked-button [routerLink]="'/finance/reports'">
            <mat-icon>analytics</mat-icon>
            Informes
          </button>
          <button mat-stroked-button [routerLink]="'/finance/invoices/create'">
            <mat-icon>receipt</mat-icon>
            Nueva Factura
          </button>
          <button mat-raised-button color="primary" [routerLink]="'/finance/payments/process'">
            <mat-icon>payment</mat-icon>
            Procesar Pago
          </button>
        </div>
      </div>

      <!-- Resumen financiero -->
      <div class="stats-grid mb-6">
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-green-600">account_balance</mat-icon>
              <div>
                <div class="stat-value">{{ summary.total_revenue | currency:'EUR':'symbol':'1.0-0' }}</div>
                <div class="stat-label">Ingresos Totales</div>
                <div class="stat-sublabel">{{ summary.revenue_growth > 0 ? '+' : '' }}{{ summary.revenue_growth }}% vs mes anterior</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-red-600">money_off</mat-icon>
              <div>
                <div class="stat-value">{{ summary.total_expenses | currency:'EUR':'symbol':'1.0-0' }}</div>
                <div class="stat-label">Gastos Totales</div>
                <div class="stat-sublabel">{{ summary.expense_categories }} categorías</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-blue-600">trending_up</mat-icon>
              <div>
                <div class="stat-value">{{ summary.net_profit | currency:'EUR':'symbol':'1.0-0' }}</div>
                <div class="stat-label">Beneficio Neto</div>
                <div class="stat-sublabel">{{ summary.profit_margin | number:'1.1-1' }}% margen</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-orange-600">schedule</mat-icon>
              <div>
                <div class="stat-value">{{ summary.pending_payments }}</div>
                <div class="stat-label">Pagos Pendientes</div>
                <div class="stat-sublabel">{{ summary.overdue_payments }} vencidos</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
      </div>

      <div class="content-grid">
        <div class="main-content">
          <mat-tab-group>
            <!-- Tab: Resumen de ingresos -->
            <mat-tab label="Ingresos">
              <div class="tab-content">
                <mat-card class="mb-4">
                  <mat-card-header>
                    <mat-card-title>Evolución de Ingresos</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="chart-container">
                      <!-- Chart placeholder -->
                      <div class="chart-placeholder">
                        <mat-icon>show_chart</mat-icon>
                        <p>Gráfico de evolución de ingresos</p>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>

                <mat-card>
                  <mat-card-header>
                    <mat-card-title>Ingresos por Fuente</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="revenue-sources">
                      <div *ngFor="let source of revenueSources" class="source-item">
                        <div class="source-info">
                          <span class="source-name">{{ source.name }}</span>
                          <span class="source-amount">{{ source.amount | currency:'EUR':'symbol':'1.0-0' }}</span>
                        </div>
                        <mat-progress-bar 
                          mode="determinate" 
                          [value]="source.percentage"
                          color="primary">
                        </mat-progress-bar>
                        <span class="percentage">{{ source.percentage }}%</span>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>
              </div>
            </mat-tab>

            <!-- Tab: Gastos -->
            <mat-tab label="Gastos">
              <div class="tab-content">
                <mat-card class="mb-4">
                  <mat-card-header>
                    <mat-card-title>Gastos por Categoría</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="expenses-list">
                      <div *ngFor="let expense of expenseCategories" class="expense-item">
                        <div class="expense-header">
                          <mat-icon>{{ expense.icon }}</mat-icon>
                          <span class="expense-name">{{ expense.name }}</span>
                          <span class="expense-amount">{{ expense.amount | currency:'EUR':'symbol':'1.0-0' }}</span>
                        </div>
                        <mat-progress-bar 
                          mode="determinate" 
                          [value]="expense.percentage"
                          color="warn">
                        </mat-progress-bar>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>
              </div>
            </mat-tab>

            <!-- Tab: Facturas -->
            <mat-tab label="Facturas">
              <div class="tab-content">
                <mat-card>
                  <mat-card-header>
                    <mat-card-title>Facturas Recientes</mat-card-title>
                    <div class="card-actions">
                      <button mat-stroked-button [routerLink]="'/finance/invoices'">
                        Ver todas
                      </button>
                    </div>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="invoices-list">
                      <div *ngFor="let invoice of recentInvoices" class="invoice-item">
                        <div class="invoice-info">
                          <div class="invoice-number">#{{ invoice.number }}</div>
                          <div class="invoice-client">{{ invoice.client_name }}</div>
                          <div class="invoice-date">{{ invoice.date | date:'dd/MM/yyyy' }}</div>
                        </div>
                        <div class="invoice-amount">
                          {{ invoice.amount | currency:'EUR':'symbol':'1.0-0' }}
                        </div>
                        <div class="invoice-status">
                          <span class="status-chip" [class]="'status-' + invoice.status">
                            {{ getInvoiceStatusLabel(invoice.status) }}
                          </span>
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
              <mat-card-title>Acciones Rápidas</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="quick-actions">
                <button mat-stroked-button class="w-full" [routerLink]="'/finance/reconciliation'">
                  <mat-icon>account_balance</mat-icon>
                  Conciliación
                </button>
                <button mat-stroked-button class="w-full" [routerLink]="'/finance/tax-report'">
                  <mat-icon>description</mat-icon>
                  Informe Fiscal
                </button>
                <button mat-stroked-button class="w-full" [routerLink]="'/finance/budget'">
                  <mat-icon>pie_chart</mat-icon>
                  Presupuesto
                </button>
                <button mat-stroked-button class="w-full" [routerLink]="'/finance/export'">
                  <mat-icon>download</mat-icon>
                  Exportar Datos
                </button>
              </div>
            </mat-card-content>
          </mat-card>

          <mat-card class="mb-4">
            <mat-card-header>
              <mat-card-title>Próximos Vencimientos</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="upcoming-payments">
                <div *ngFor="let payment of upcomingPayments" class="payment-item">
                  <div class="payment-info">
                    <div class="payment-description">{{ payment.description }}</div>
                    <div class="payment-date">{{ payment.due_date | date:'dd/MM/yyyy' }}</div>
                  </div>
                  <div class="payment-amount">
                    {{ payment.amount | currency:'EUR':'symbol':'1.0-0' }}
                  </div>
                </div>
              </div>
            </mat-card-content>
          </mat-card>

          <mat-card>
            <mat-card-header>
              <mat-card-title>Ratios Financieros</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="financial-ratios">
                <div class="ratio-item">
                  <span class="ratio-label">Liquidez:</span>
                  <span class="ratio-value">{{ ratios.liquidity_ratio | number:'1.2-2' }}</span>
                </div>
                <div class="ratio-item">
                  <span class="ratio-label">Rentabilidad:</span>
                  <span class="ratio-value">{{ ratios.profitability_ratio | number:'1.1-1' }}%</span>
                </div>
                <div class="ratio-item">
                  <span class="ratio-label">Eficiencia:</span>
                  <span class="ratio-value">{{ ratios.efficiency_ratio | number:'1.1-1' }}%</span>
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

    .content-grid {
      @apply grid grid-cols-1 xl:grid-cols-3 gap-6;
    }

    .main-content {
      @apply xl:col-span-2;
    }

    .tab-content {
      @apply p-4;
    }

    .chart-container {
      @apply h-64 flex items-center justify-center;
    }

    .chart-placeholder {
      @apply text-center text-gray-400;
    }

    .revenue-sources, .expenses-list {
      @apply space-y-4;
    }

    .source-item, .expense-item {
      @apply space-y-2;
    }

    .source-info, .expense-header {
      @apply flex justify-between items-center;
    }

    .source-name, .expense-name {
      @apply font-medium;
    }

    .percentage {
      @apply text-sm text-gray-500;
    }

    .invoices-list {
      @apply space-y-3;
    }

    .invoice-item {
      @apply flex items-center justify-between p-3 border border-gray-200 rounded;
    }

    .invoice-info {
      @apply space-y-1;
    }

    .invoice-number {
      @apply font-medium;
    }

    .invoice-client, .invoice-date {
      @apply text-sm text-gray-500;
    }

    .status-chip {
      @apply px-2 py-1 rounded text-xs font-medium;
    }

    .status-paid {
      @apply bg-green-100 text-green-800;
    }

    .status-pending {
      @apply bg-orange-100 text-orange-800;
    }

    .status-overdue {
      @apply bg-red-100 text-red-800;
    }

    .quick-actions {
      @apply space-y-2;
    }

    .upcoming-payments {
      @apply space-y-3;
    }

    .payment-item {
      @apply flex justify-between;
    }

    .payment-description {
      @apply font-medium text-sm;
    }

    .payment-date {
      @apply text-xs text-gray-500;
    }

    .financial-ratios {
      @apply space-y-2;
    }

    .ratio-item {
      @apply flex justify-between;
    }

    .ratio-label {
      @apply text-sm text-gray-600;
    }

    .ratio-value {
      @apply font-medium;
    }
  `]
})
export class FinanceDashboardPage implements OnInit {

  summary = {
    total_revenue: 125000,
    revenue_growth: 12.5,
    total_expenses: 85000,
    expense_categories: 8,
    net_profit: 40000,
    profit_margin: 32.0,
    pending_payments: 15,
    overdue_payments: 3
  };

  revenueSources = [
    { name: 'Cursos de Esquí', amount: 45000, percentage: 36 },
    { name: 'Alquiler Material', amount: 35000, percentage: 28 },
    { name: 'Clases Privadas', amount: 25000, percentage: 20 },
    { name: 'Otros Servicios', amount: 20000, percentage: 16 }
  ];

  expenseCategories = [
    { name: 'Salarios Instructores', amount: 35000, percentage: 41, icon: 'group' },
    { name: 'Mantenimiento Material', amount: 15000, percentage: 18, icon: 'build' },
    { name: 'Marketing', amount: 12000, percentage: 14, icon: 'campaign' },
    { name: 'Seguros', amount: 8000, percentage: 9, icon: 'shield' },
    { name: 'Gastos Generales', amount: 15000, percentage: 18, icon: 'receipt_long' }
  ];

  recentInvoices = [
    { number: '2025-001', client_name: 'Juan Pérez', date: new Date(), amount: 350, status: 'paid' },
    { number: '2025-002', client_name: 'María García', date: new Date(), amount: 280, status: 'pending' },
    { number: '2025-003', client_name: 'Carlos Ruiz', date: new Date(), amount: 450, status: 'overdue' }
  ];

  upcomingPayments = [
    { description: 'Nómina Instructores', due_date: new Date(), amount: 15000 },
    { description: 'Seguro Material', due_date: new Date(), amount: 2500 },
    { description: 'Reparación Equipos', due_date: new Date(), amount: 800 }
  ];

  ratios = {
    liquidity_ratio: 2.5,
    profitability_ratio: 32.0,
    efficiency_ratio: 85.5
  };

  ngOnInit() {
    // TODO: Load financial data
  }

  getInvoiceStatusLabel(status: string): string {
    const labels: { [key: string]: string } = {
      paid: 'Pagada',
      pending: 'Pendiente',
      overdue: 'Vencida'
    };
    return labels[status] || status;
  }
}