import { Component, Inject } from '@angular/core';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';

@Component({
  selector: 'app-booking-list-dialog',
  template: `
    <div class="dialog-header">
      <h2 mat-dialog-title>{{ data.title }}</h2>
      <button mat-icon-button mat-dialog-close class="close-button">
        <mat-icon>close</mat-icon>
      </button>
    </div>

    <mat-dialog-content class="dialog-content">
      <!-- Filtro de búsqueda -->
      <div class="search-container">
        <mat-form-field appearance="outline" class="search-field">
          <mat-label>Buscar reserva</mat-label>
          <input matInput [(ngModel)]="searchTerm" (input)="applyFilter()"
                 placeholder="ID, nombre o email">
          <mat-icon matSuffix>search</mat-icon>
        </mat-form-field>
      </div>

      <!-- Tabla de reservas -->
      <div class="table-container">
        <table mat-table [dataSource]="filteredBookings" class="bookings-table">

          <!-- Booking ID -->
          <ng-container matColumnDef="id">
            <th mat-header-cell *matHeaderCellDef>ID</th>
            <td mat-cell *matCellDef="let booking">
              <strong>#{{ booking.id }}</strong>
            </td>
          </ng-container>

          <!-- Cliente -->
          <ng-container matColumnDef="client">
            <th mat-header-cell *matHeaderCellDef>Cliente</th>
            <td mat-cell *matCellDef="let booking">
              <div class="client-info">
                <div class="client-name">{{ booking.client_name }}</div>
                <div class="client-email">{{ booking.client_email }}</div>
              </div>
            </td>
          </ng-container>

          <!-- Fecha -->
          <ng-container matColumnDef="date">
            <th mat-header-cell *matHeaderCellDef>Fecha</th>
            <td mat-cell *matCellDef="let booking">
              {{ formatDate(booking.booking_date) }}
            </td>
          </ng-container>

          <!-- Importe Total -->
          <ng-container matColumnDef="amount">
            <th mat-header-cell *matHeaderCellDef>Importe Total</th>
            <td mat-cell *matCellDef="let booking">
              <span class="amount">
                {{ formatCurrency(booking.amount) }}
              </span>
            </td>
          </ng-container>

          <!-- Importe Pendiente (solo para pending) -->
          <ng-container matColumnDef="pending_amount">
            <th mat-header-cell *matHeaderCellDef>Pendiente</th>
            <td mat-cell *matCellDef="let booking">
              <span class="amount pending">
                {{ formatCurrency(booking.pending_amount) }}
              </span>
            </td>
          </ng-container>

          <!-- Importe Recibido (solo para pending) -->
          <ng-container matColumnDef="received_amount">
            <th mat-header-cell *matHeaderCellDef>Recibido</th>
            <td mat-cell *matCellDef="let booking">
              <span class="amount received">
                {{ formatCurrency(booking.received_amount) }}
              </span>
            </td>
          </ng-container>

          <!-- Estado -->
          <ng-container matColumnDef="status">
            <th mat-header-cell *matHeaderCellDef>Estado</th>
            <td mat-cell *matCellDef="let booking">
              <mat-chip class="status-chip" [ngClass]="getStatusClass(booking.status)"
                        [class.has-issues]="booking.has_issues">
                {{ getStatusName(booking.status) }}
              </mat-chip>
            </td>
          </ng-container>

          <!-- Acciones -->
          <ng-container matColumnDef="actions">
            <th mat-header-cell *matHeaderCellDef>Acciones</th>
            <td mat-cell *matCellDef="let booking">
              <button mat-icon-button [matMenuTriggerFor]="actionMenu">
                <mat-icon>more_vert</mat-icon>
              </button>
              <mat-menu #actionMenu="matMenu">
                <button mat-menu-item (click)="viewDetails(booking)">
                  <mat-icon>visibility</mat-icon>
                  Ver detalles
                </button>
                <button mat-menu-item (click)="exportBooking(booking)">
                  <mat-icon>download</mat-icon>
                  Exportar
                </button>
                <button mat-menu-item (click)="copyBookingId(booking)"
                        *ngIf="booking.id">
                  <mat-icon>content_copy</mat-icon>
                  Copiar ID
                </button>
              </mat-menu>
            </td>
          </ng-container>

          <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
          <tr mat-row *matRowDef="let row; columns: displayedColumns;"
              class="booking-row"
              [class.high-priority]="isHighPriority(row)"
              [class.medium-priority]="isMediumPriority(row)"></tr>
        </table>

        <!-- No data -->
        <div class="no-data" *ngIf="filteredBookings.length === 0">
          <mat-icon>inbox</mat-icon>
          <p>No se encontraron reservas</p>
          <p class="no-data-subtitle" *ngIf="searchTerm">
            Intenta cambiar los criterios de búsqueda
          </p>
        </div>
      </div>
    </mat-dialog-content>

    <mat-dialog-actions class="dialog-actions">
      <div class="summary">
        <span class="total-count">{{ filteredBookings.length }} reservas</span>
        <span *ngIf="data.type === 'pending'" class="total-amount">
          • {{ formatCurrency(getTotalAmount()) }} total pendiente
        </span>
        <span *ngIf="data.type === 'cancelled'" class="total-amount">
          • {{ formatCurrency(getTotalAmount()) }} total cancelado
        </span>
        <span *ngIf="getHighPriorityCount() > 0" class="priority-warning">
          • {{ getHighPriorityCount() }} alta prioridad
        </span>
      </div>
      <div class="actions">
        <button mat-button mat-dialog-close>Cerrar</button>
        <button mat-raised-button color="primary" (click)="exportAll()"
                *ngIf="filteredBookings.length > 0">
          <mat-icon>download</mat-icon>
          Exportar Todo
        </button>
      </div>
    </mat-dialog-actions>
  `,
  styles: [`
    .dialog-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      padding: 0 4px;
    }

    .close-button {
      color: #666;
    }

    .dialog-content {
      max-height: 46vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      min-height: 400px;
    }

    .search-container {
      margin-bottom: 16px;
      padding: 0 4px;
    }

    .search-field {
      width: 300px;
      max-width: 100%;
    }

    .table-container {
      flex: 1;
      overflow: auto;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
    }

    .bookings-table {
      width: 100%;
      min-width: 600px;
    }

    .mat-header-cell {
      background-color: #f8f9fa;
      color: #333;
      font-weight: 600;
      font-size: 0.875rem;
      padding: 12px 8px;
      border-bottom: 2px solid #e0e0e0;
    }

    .mat-cell {
      padding: 12px 8px;
      border-bottom: 1px solid #f0f0f0;
    }

    .client-info {
      .client-name {
        font-weight: 500;
        color: #333;
        margin-bottom: 2px;
      }
      .client-email {
        font-size: 0.875rem;
        color: #666;
      }
    }

    .amount {
      font-weight: 500;
      font-family: 'Roboto Mono', monospace;

      &.pending {
        color: #f57c00;
        font-weight: 600;
      }

      &.received {
        color: #2e7d32;
        font-weight: 600;
      }
    }

    .status-chip {
      font-size: 0.75rem;
      min-height: 24px;
      position: relative;

      &.status-active {
        background: #e8f5e8;
        color: #2e7d32;
      }

      &.status-cancelled {
        background: #ffebee;
        color: #c62828;
      }

      &.status-partial {
        background: #fff3e0;
        color: #f57c00;
      }

      &.has-issues::after {
        content: '⚠️';
        position: absolute;
        top: -2px;
        right: -2px;
        font-size: 10px;
      }
    }

    .booking-row {
      transition: background-color 0.2s ease;

      &:hover {
        background-color: rgba(63, 81, 181, 0.05);
        cursor: pointer;
      }

      &.high-priority {
        border-left: 3px solid #f44336;
      }

      &.medium-priority {
        border-left: 3px solid #ff9800;
      }
    }

    .no-data {
      text-align: center;
      padding: 60px 40px;
      color: #999;

      mat-icon {
        font-size: 64px;
        width: 64px;
        height: 64px;
        margin-bottom: 16px;
        opacity: 0.5;
      }

      p {
        margin: 8px 0;
        font-size: 1rem;
      }

      .no-data-subtitle {
        font-size: 0.875rem;
        color: #bbb;
      }
    }

    .dialog-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 24px;
      border-top: 1px solid #e0e0e0;
      background-color: #fafafa;
    }

    .summary {
      color: #666;
      font-size: 0.875rem;

      .total-count {
        font-weight: 600;
        color: #333;
      }

      .total-amount {
        color: #f57c00;
        font-weight: 500;
        margin-left: 8px;
      }

      .priority-warning {
        color: #f44336;
        font-weight: 600;
        margin-left: 8px;
      }
    }

    .actions {
      display: flex;
      gap: 8px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .search-field {
        width: 100%;
      }

      .dialog-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;

        .summary {
          text-align: center;
        }

        .actions {
          justify-content: center;
        }
      }

      .mat-header-cell,
      .mat-cell {
        padding: 8px 4px;
        font-size: 0.8rem;
      }
    }
  `]
})
export class BookingListModalComponent {
  searchTerm = '';
  filteredBookings: any[] = [];
  displayedColumns: string[] = [];

  constructor(
    public dialogRef: MatDialogRef<BookingListModalComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any
  ) {
    this.filteredBookings = [...(this.data.bookings || [])];
    this.setupColumns();
  }

  // 🔧 CONFIGURAR COLUMNAS SEGÚN TIPO
  private setupColumns(): void {
    const baseColumns = ['id', 'client', 'date'];

    if (this.data.type === 'pending') {
      this.displayedColumns = [
        ...baseColumns,
        'amount',
        'received_amount',
        'pending_amount',
        'status',
        'actions'
      ];
    } else if (this.data.type === 'cancelled') {
      this.displayedColumns = [
        ...baseColumns,
        'amount',
        'status',
        'actions'
      ];
    } else {
      // Tipo por defecto
      this.displayedColumns = [
        ...baseColumns,
        'amount',
        'status',
        'actions'
      ];
    }
  }

  // 🔍 APLICAR FILTRO DE BÚSQUEDA
  applyFilter(): void {
    if (!this.searchTerm.trim()) {
      this.filteredBookings = [...this.data.bookings];
      return;
    }

    const term = this.searchTerm.toLowerCase().trim();
    this.filteredBookings = this.data.bookings.filter((booking: any) => {
      return (
        booking.client_name?.toLowerCase().includes(term) ||
        booking.client_email?.toLowerCase().includes(term) ||
        booking.id?.toString().includes(term) ||
        booking.booking_date?.includes(term)
      );
    });
  }

  // 📅 FORMATEAR FECHA
  formatDate(dateString: string): string {
    if (!dateString) return 'N/A';

    try {
      const date = new Date(dateString);
      return date.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      });
    } catch (error) {
      return dateString;
    }
  }

  // 💰 FORMATEAR CURRENCY
  formatCurrency(amount: number | null | undefined): string {
    if (amount === null || amount === undefined || isNaN(amount)) {
      return '0,00 €';
    }

    return new Intl.NumberFormat('es-ES', {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  }

  // 🏷️ OBTENER NOMBRE DEL ESTADO
  getStatusName(status: string): string {
    const statusMap: { [key: string]: string } = {
      // ✅ NUEVOS ESTADOS REALES
      'active': 'Activa',
      'partial_cancel': 'Parcialmente Cancelada',
      'total_cancel': 'Cancelada Totalmente',
      'finished': 'Finalizada',

      // ✅ MANTENER COMPATIBILIDAD CON ESTADOS ANTIGUOS
      'cancelled': 'Cancelada',
      'partial': 'Parcial',
      '1': 'Activa',
      '2': 'Cancelada',
      '3': 'Parcial'
    };

    return statusMap[status] || status || 'Desconocido';
  }

  // 🎨 OBTENER CLASE CSS DEL ESTADO
  getStatusClass(status: string): string {
    const statusClassMap: { [key: string]: string } = {
      // ✅ NUEVOS ESTADOS REALES
      'active': 'status-active',
      'partial_cancel': 'status-partial',
      'total_cancel': 'status-cancelled',
      'finished': 'status-finished',

      // ✅ MANTENER COMPATIBILIDAD
      'cancelled': 'status-cancelled',
      'partial': 'status-partial',
      '1': 'status-active',
      '2': 'status-cancelled',
      '3': 'status-partial'
    };

    return statusClassMap[status] || 'status-unknown';
  }

  // 🔢 CALCULAR TOTAL
  getTotalAmount(): number {
    return this.filteredBookings.reduce((sum, booking) => {
      let amount = 0;

      if (this.data.type === 'pending') {
        amount = booking.pending_amount || 0;
      } else {
        amount = booking.amount || 0;
      }

      return sum + amount;
    }, 0);
  }

  // ⚠️ VERIFICAR SI ES ALTA PRIORIDAD
  isHighPriority(booking: any): boolean {
    if (this.data.type === 'pending') {
      return (booking.pending_amount || 0) > 100;
    }
    return booking.has_issues || false;
  }

  // ⚡ VERIFICAR SI ES PRIORIDAD MEDIA
  isMediumPriority(booking: any): boolean {
    if (this.data.type === 'pending') {
      const pending = booking.pending_amount || 0;
      return pending > 50 && pending <= 100;
    }
    return false;
  }

  // 📊 CONTAR RESERVAS DE ALTA PRIORIDAD
  getHighPriorityCount(): number {
    return this.filteredBookings.filter(booking => this.isHighPriority(booking)).length;
  }

  // 📤 EXPORTAR TODAS LAS RESERVAS
  exportAll(): void {
    console.log('Exportando todas las reservas del modal:', this.filteredBookings.length);
    this.dialogRef.close({
      action: 'export',
      data: this.filteredBookings,
      type: this.data.type,
      title: this.data.title
    });
  }

  // 👁️ VER DETALLES DE RESERVA
  viewDetails(booking: any): void {
    console.log('Ver detalles de reserva:', booking.id);
    this.dialogRef.close({
      action: 'view_details',
      booking: booking
    });
  }

  // 📤 EXPORTAR RESERVA INDIVIDUAL
  exportBooking(booking: any): void {
    console.log('Exportar reserva individual:', booking.id);
    this.dialogRef.close({
      action: 'export_single',
      booking: booking,
      type: this.data.type
    });
  }

  // 📋 COPIAR ID DE RESERVA
  copyBookingId(booking: any): void {
    if (booking.id && navigator.clipboard) {
      navigator.clipboard.writeText(booking.id.toString()).then(() => {
        console.log('ID de reserva copiado:', booking.id);
        // Aquí podrías mostrar un snackbar o toast
      }).catch(err => {
        console.error('Error copiando ID:', err);
      });
    }
  }

  // 🔄 REFRESCAR DATOS
  refreshData(): void {
    // Método para refrescar los datos si es necesario
    this.applyFilter();
  }
}
