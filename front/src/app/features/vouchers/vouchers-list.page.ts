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
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatDividerModule } from '@angular/material/divider';

import { PageLayoutComponent } from '../../shared/components/layout/page-layout.component';

@Component({
  selector: 'app-vouchers-list',
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
    MatProgressBarModule,
    MatDividerModule,
    PageLayoutComponent,
  ],
  template: `
    <app-page-layout>
      <div class="page-header">
        <div class="page-title">
          <h1 class="text-2xl font-bold">Gestión de Vouchers</h1>
          <p class="text-gray-600">Cupones de descuento y promociones</p>
        </div>
        
        <div class="page-actions">
          <button mat-stroked-button [routerLink]="'/vouchers/analytics'">
            <mat-icon>analytics</mat-icon>
            Análisis
          </button>
          <button mat-stroked-button [routerLink]="'/vouchers/bulk-create'">
            <mat-icon>add_box</mat-icon>
            Crear Masivos
          </button>
          <button mat-raised-button color="primary" [routerLink]="'/vouchers/create'">
            <mat-icon>local_offer</mat-icon>
            Nuevo Voucher
          </button>
        </div>
      </div>

      <!-- Estadísticas de vouchers -->
      <div class="stats-grid mb-6">
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-green-600">local_offer</mat-icon>
              <div>
                <div class="stat-value">{{ stats.total_vouchers }}</div>
                <div class="stat-label">Total Vouchers</div>
                <div class="stat-sublabel">{{ stats.active_vouchers }} activos</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-blue-600">redeem</mat-icon>
              <div>
                <div class="stat-value">{{ stats.redeemed_count }}</div>
                <div class="stat-label">Canjeados</div>
                <div class="stat-sublabel">{{ stats.redemption_rate }}% tasa uso</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-orange-600">euro</mat-icon>
              <div>
                <div class="stat-value">{{ stats.total_discount | currency:'EUR':'symbol':'1.0-0' }}</div>
                <div class="stat-label">Descuento Total</div>
                <div class="stat-sublabel">{{ stats.avg_discount | currency:'EUR':'symbol':'1.0-0' }} promedio</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-red-600">schedule</mat-icon>
              <div>
                <div class="stat-value">{{ stats.expiring_soon }}</div>
                <div class="stat-label">Próximos a Vencer</div>
                <div class="stat-sublabel">En los próximos 30 días</div>
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
              <input matInput formControlName="search" placeholder="Código, nombre, descripción...">
              <mat-icon matSuffix>search</mat-icon>
            </mat-form-field>

            <mat-form-field>
              <mat-label>Estado</mat-label>
              <mat-select formControlName="status">
                <mat-option value="">Todos</mat-option>
                <mat-option value="active">Activos</mat-option>
                <mat-option value="inactive">Inactivos</mat-option>
                <mat-option value="expired">Expirados</mat-option>
                <mat-option value="used_up">Agotados</mat-option>
              </mat-select>
            </mat-form-field>

            <mat-form-field>
              <mat-label>Tipo de Descuento</mat-label>
              <mat-select formControlName="discount_type">
                <mat-option value="">Todos</mat-option>
                <mat-option value="percentage">Porcentaje</mat-option>
                <mat-option value="fixed">Cantidad Fija</mat-option>
                <mat-option value="free_item">Artículo Gratis</mat-option>
                <mat-option value="free_shipping">Envío Gratis</mat-option>
              </mat-select>
            </mat-form-field>

            <mat-form-field>
              <mat-label>Categoría</mat-label>
              <mat-select formControlName="category">
                <mat-option value="">Todas</mat-option>
                <mat-option value="courses">Cursos</mat-option>
                <mat-option value="equipment">Material</mat-option>
                <mat-option value="private">Clases Privadas</mat-option>
                <mat-option value="general">General</mat-option>
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

      <!-- Lista de vouchers -->
      <mat-card>
        <mat-card-content>
          <div class="table-container">
            <table mat-table [dataSource]="vouchers" class="vouchers-table w-full">
              <!-- Columna Código -->
              <ng-container matColumnDef="code">
                <th mat-header-cell *matHeaderCellDef> Código </th>
                <td mat-cell *matCellDef="let voucher">
                  <div class="code-cell">
                    <span class="voucher-code">{{ voucher.code }}</span>
                    <button mat-icon-button (click)="copyCode(voucher.code)" matTooltip="Copiar código">
                      <mat-icon class="text-sm">content_copy</mat-icon>
                    </button>
                  </div>
                </td>
              </ng-container>

              <!-- Columna Nombre -->
              <ng-container matColumnDef="name">
                <th mat-header-cell *matHeaderCellDef> Nombre </th>
                <td mat-cell *matCellDef="let voucher">
                  <div class="name-cell">
                    <div class="voucher-name">{{ voucher.name }}</div>
                    <div class="voucher-description">{{ voucher.description }}</div>
                  </div>
                </td>
              </ng-container>

              <!-- Columna Tipo y Valor -->
              <ng-container matColumnDef="discount">
                <th mat-header-cell *matHeaderCellDef> Descuento </th>
                <td mat-cell *matCellDef="let voucher">
                  <div class="discount-cell">
                    <div class="discount-value">
                      <ng-container [ngSwitch]="voucher.discount_type">
                        <span *ngSwitchCase="'percentage'">{{ voucher.discount_value }}%</span>
                        <span *ngSwitchCase="'fixed'">{{ voucher.discount_value | currency:'EUR':'symbol':'1.0-0' }}</span>
                        <span *ngSwitchDefault>{{ getDiscountLabel(voucher.discount_type) }}</span>
                      </ng-container>
                    </div>
                    <div class="discount-type">{{ getDiscountTypeLabel(voucher.discount_type) }}</div>
                  </div>
                </td>
              </ng-container>

              <!-- Columna Uso -->
              <ng-container matColumnDef="usage">
                <th mat-header-cell *matHeaderCellDef> Uso </th>
                <td mat-cell *matCellDef="let voucher">
                  <div class="usage-cell">
                    <div class="usage-stats">
                      {{ voucher.used_count }} / {{ voucher.usage_limit || '∞' }}
                    </div>
                    <mat-progress-bar 
                      *ngIf="voucher.usage_limit"
                      mode="determinate" 
                      [value]="(voucher.used_count / voucher.usage_limit) * 100"
                      [color]="getUsageColor(voucher.used_count, voucher.usage_limit)">
                    </mat-progress-bar>
                  </div>
                </td>
              </ng-container>

              <!-- Columna Validez -->
              <ng-container matColumnDef="validity">
                <th mat-header-cell *matHeaderCellDef> Validez </th>
                <td mat-cell *matCellDef="let voucher">
                  <div class="validity-cell">
                    <div class="validity-dates">
                      <div>Desde: {{ voucher.valid_from | date:'dd/MM/yyyy' }}</div>
                      <div *ngIf="voucher.valid_until">
                        Hasta: {{ voucher.valid_until | date:'dd/MM/yyyy' }}
                      </div>
                      <div *ngIf="!voucher.valid_until">Sin expiración</div>
                    </div>
                    <div class="validity-status" [class]="getValidityStatusClass(voucher)">
                      {{ getValidityStatusLabel(voucher) }}
                    </div>
                  </div>
                </td>
              </ng-container>

              <!-- Columna Estado -->
              <ng-container matColumnDef="status">
                <th mat-header-cell *matHeaderCellDef> Estado </th>
                <td mat-cell *matCellDef="let voucher">
                  <mat-chip [color]="getStatusColor(voucher.status)" selected>
                    {{ getStatusLabel(voucher.status) }}
                  </mat-chip>
                </td>
              </ng-container>

              <!-- Columna Acciones -->
              <ng-container matColumnDef="actions">
                <th mat-header-cell *matHeaderCellDef> Acciones </th>
                <td mat-cell *matCellDef="let voucher">
                  <button mat-icon-button [matMenuTriggerFor]="menu">
                    <mat-icon>more_vert</mat-icon>
                  </button>
                  <mat-menu #menu="matMenu">
                    <button mat-menu-item [routerLink]="['/vouchers', voucher.id]">
                      <mat-icon>visibility</mat-icon>
                      Ver detalles
                    </button>
                    <button mat-menu-item [routerLink]="['/vouchers', voucher.id, 'edit']">
                      <mat-icon>edit</mat-icon>
                      Editar
                    </button>
                    <button mat-menu-item (click)="duplicateVoucher(voucher.id)">
                      <mat-icon>content_copy</mat-icon>
                      Duplicar
                    </button>
                    <mat-divider></mat-divider>
                    <button mat-menu-item (click)="toggleStatus(voucher)" 
                            [disabled]="voucher.status === 'expired'">
                      <mat-icon>{{ voucher.status === 'active' ? 'pause' : 'play_arrow' }}</mat-icon>
                      {{ voucher.status === 'active' ? 'Desactivar' : 'Activar' }}
                    </button>
                    <button mat-menu-item (click)="viewUsageHistory(voucher.id)">
                      <mat-icon>history</mat-icon>
                      Historial de uso
                    </button>
                    <button mat-menu-item (click)="generateQRCode(voucher.code)">
                      <mat-icon>qr_code</mat-icon>
                      Generar QR
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

    .table-container {
      @apply overflow-auto;
    }

    .vouchers-table {
      min-width: 1000px;
    }

    .code-cell {
      @apply flex items-center gap-2;
    }

    .voucher-code {
      @apply font-mono font-bold text-blue-600;
    }

    .name-cell {
      @apply space-y-1;
    }

    .voucher-name {
      @apply font-medium;
    }

    .voucher-description {
      @apply text-sm text-gray-500;
    }

    .discount-cell {
      @apply text-center;
    }

    .discount-value {
      @apply font-bold text-lg text-green-600;
    }

    .discount-type {
      @apply text-xs text-gray-500;
    }

    .usage-cell {
      @apply space-y-1;
    }

    .usage-stats {
      @apply text-sm font-medium;
    }

    .validity-cell {
      @apply space-y-1;
    }

    .validity-dates {
      @apply text-xs;
    }

    .validity-status {
      @apply text-xs font-medium;
    }

    .validity-status.valid {
      @apply text-green-600;
    }

    .validity-status.expiring {
      @apply text-orange-600;
    }

    .validity-status.expired {
      @apply text-red-600;
    }
  `]
})
export class VouchersListPage implements OnInit {
  private fb = inject(FormBuilder);

  vouchers: any[] = [];
  displayedColumns = ['code', 'name', 'discount', 'usage', 'validity', 'status', 'actions'];

  stats = {
    total_vouchers: 248,
    active_vouchers: 186,
    redeemed_count: 1456,
    redemption_rate: 68,
    total_discount: 45600,
    avg_discount: 31,
    expiring_soon: 12
  };

  filterForm = this.fb.group({
    search: [''],
    status: [''],
    discount_type: [''],
    category: ['']
  });

  ngOnInit() {
    this.loadVouchers();
  }

  loadVouchers() {
    // TODO: Implement with VouchersService
    this.vouchers = [
      {
        id: 1,
        code: 'WINTER2025',
        name: 'Promoción Invierno',
        description: '20% descuento en cursos de esquí',
        discount_type: 'percentage',
        discount_value: 20,
        used_count: 45,
        usage_limit: 100,
        valid_from: new Date('2025-01-01'),
        valid_until: new Date('2025-03-31'),
        status: 'active'
      },
      {
        id: 2,
        code: 'WELCOME50',
        name: 'Bienvenida',
        description: '50€ descuento primera reserva',
        discount_type: 'fixed',
        discount_value: 50,
        used_count: 89,
        usage_limit: null,
        valid_from: new Date('2025-01-01'),
        valid_until: null,
        status: 'active'
      }
    ];
  }

  clearFilters() {
    this.filterForm.reset();
    this.loadVouchers();
  }

  getStatusColor(status: string) {
    const colors: { [key: string]: string } = {
      active: 'primary',
      inactive: '',
      expired: 'warn',
      used_up: 'accent'
    };
    return colors[status] || '';
  }

  getStatusLabel(status: string) {
    const labels: { [key: string]: string } = {
      active: 'Activo',
      inactive: 'Inactivo',
      expired: 'Expirado',
      used_up: 'Agotado'
    };
    return labels[status] || status;
  }

  getDiscountTypeLabel(type: string) {
    const labels: { [key: string]: string } = {
      percentage: 'Porcentaje',
      fixed: 'Cantidad Fija',
      free_item: 'Artículo Gratis',
      free_shipping: 'Envío Gratis'
    };
    return labels[type] || type;
  }

  getDiscountLabel(type: string) {
    const labels: { [key: string]: string } = {
      free_item: 'Gratis',
      free_shipping: 'Envío Gratis'
    };
    return labels[type] || '';
  }

  getUsageColor(used: number, limit: number) {
    if (!limit) return 'primary';
    const percentage = (used / limit) * 100;
    if (percentage >= 90) return 'warn';
    if (percentage >= 70) return 'accent';
    return 'primary';
  }

  getValidityStatusClass(voucher: any) {
    if (voucher.valid_until && new Date(voucher.valid_until) < new Date()) {
      return 'expired';
    }
    if (voucher.valid_until && new Date(voucher.valid_until) < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)) {
      return 'expiring';
    }
    return 'valid';
  }

  getValidityStatusLabel(voucher: any) {
    if (voucher.valid_until && new Date(voucher.valid_until) < new Date()) {
      return 'Expirado';
    }
    if (voucher.valid_until && new Date(voucher.valid_until) < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)) {
      return 'Próximo a vencer';
    }
    return 'Válido';
  }

  copyCode(code: string) {
    navigator.clipboard.writeText(code);
    // TODO: Show snackbar confirmation
  }

  duplicateVoucher(id: number) {
    // TODO: Navigate to create with pre-filled data
  }

  toggleStatus(voucher: any) {
    // TODO: Toggle voucher status
  }

  viewUsageHistory(id: number) {
    // TODO: Open usage history modal
  }

  generateQRCode(code: string) {
    // TODO: Generate QR code for voucher
  }
}