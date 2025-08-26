import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { animate, style, transition, trigger } from '@angular/animations';

interface RentingItem {
  id: number;
  name: string;
  type: 'skis' | 'boards' | 'helmets';
  available: boolean;
  price: number;
  stock: number;
  brand?: string;
  model?: string;
  size?: string;
  condition: 'new' | 'good' | 'fair' | 'needs-repair';
  notes?: string;
}

interface StockMovement {
  id: number;
  equipmentId: number;
  type: 'in' | 'out' | 'adjustment' | 'damaged' | 'repair';
  quantity: number;
  reason: string;
  date: Date;
  user?: string;
}

@Component({
  selector: 'app-stock-manager',
  standalone: true,
  imports: [CommonModule, FormsModule],
  animations: [
    trigger('slideIn', [
      transition(':enter', [
        style({ opacity: 0, transform: 'translateX(100%)' }),
        animate('300ms ease-out', style({ opacity: 1, transform: 'translateX(0)' }))
      ]),
      transition(':leave', [
        animate('300ms ease-in', style({ opacity: 0, transform: 'translateX(100%)' }))
      ])
    ])
  ],
  template: `
    <div class="stock-overlay" @slideIn>
      <div class="stock-container">
        <!-- Header -->
        <header class="stock-header">
          <div class="header-content">
            <h2 class="stock-title">
              <svg class="title-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <polyline points="3.27,6.96 12,12.01 20.73,6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
              </svg>
              Gestión de Stock
            </h2>
            <p class="stock-subtitle">{{ equipment?.name }} - Control de inventario</p>
          </div>
          <button class="close-btn" (click)="onClose()" type="button">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </header>

        <div class="stock-content">
          <!-- Current Stock Overview -->
          <section class="stock-overview">
            <div class="overview-cards">
              <div class="stock-card current-stock">
                <div class="card-icon">
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                  </svg>
                </div>
                <div class="card-info">
                  <h3 class="card-title">Stock Actual</h3>
                  <p class="card-value">{{ equipment?.stock || 0 }}</p>
                  <span class="card-label">unidades disponibles</span>
                </div>
              </div>

              <div class="stock-card availability">
                <div class="card-icon" [class.available]="equipment?.available" [class.unavailable]="!equipment?.available">
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20,6 9,17 4,12" *ngIf="equipment?.available"></polyline>
                    <line x1="18" y1="6" x2="6" y2="18" *ngIf="!equipment?.available"></line>
                    <line x1="6" y1="6" x2="18" y2="18" *ngIf="!equipment?.available"></line>
                  </svg>
                </div>
                <div class="card-info">
                  <h3 class="card-title">Estado</h3>
                  <p class="card-value">{{ equipment?.available ? 'Disponible' : 'No disponible' }}</p>
                  <span class="card-label">para alquiler</span>
                </div>
              </div>

              <div class="stock-card price">
                <div class="card-icon">
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                  </svg>
                </div>
                <div class="card-info">
                  <h3 class="card-title">Precio</h3>
                  <p class="card-value">{{ equipment?.price || 0 }}€</p>
                  <span class="card-label">por día</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Quick Actions -->
          <section class="stock-actions">
            <h3 class="section-title">Acciones Rápidas</h3>
            
            <div class="action-buttons">
              <button class="action-btn stock-in" (click)="showMovementForm('in')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="12" y1="5" x2="12" y2="19"></line>
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Añadir Stock
              </button>
              
              <button class="action-btn stock-out" (click)="showMovementForm('out')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Retirar Stock
              </button>
              
              <button class="action-btn stock-damaged" (click)="showMovementForm('damaged')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                  <line x1="12" y1="9" x2="12" y2="13"></line>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                Marcar Dañado
              </button>
              
              <button class="action-btn stock-repair" (click)="showMovementForm('repair')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
                En Reparación
              </button>
            </div>
          </section>

          <!-- Movement Form -->
          <section class="movement-form" *ngIf="showForm">
            <h3 class="section-title">{{ getMovementTitle() }}</h3>
            
            <form (ngSubmit)="onSubmitMovement()" #movementForm="ngForm">
              <div class="form-row">
                <div class="form-field">
                  <label class="field-label">Cantidad</label>
                  <div class="quantity-controls">
                    <button type="button" class="quantity-btn" (click)="decrementQuantity()" [disabled]="movement.quantity <= 1">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                      </svg>
                    </button>
                    <input
                      type="number"
                      class="field-input quantity-input"
                      [(ngModel)]="movement.quantity"
                      name="quantity"
                      min="1"
                      required
                    >
                    <button type="button" class="quantity-btn" (click)="incrementQuantity()">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                      </svg>
                    </button>
                  </div>
                </div>

                <div class="form-field">
                  <label class="field-label">Motivo/Observaciones *</label>
                  <select
                    class="field-select"
                    [(ngModel)]="movement.reason"
                    name="reason"
                    required
                    *ngIf="getReasonOptions().length > 0"
                  >
                    <option value="">Seleccionar motivo</option>
                    <option *ngFor="let option of getReasonOptions()" [value]="option">{{ option }}</option>
                  </select>
                  <input
                    type="text"
                    class="field-input"
                    [(ngModel)]="movement.reason"
                    name="reason"
                    placeholder="Describe el motivo"
                    required
                    *ngIf="getReasonOptions().length === 0"
                  >
                </div>
              </div>

              <div class="form-field">
                <label class="field-label">Notas adicionales</label>
                <textarea
                  class="field-textarea"
                  [(ngModel)]="movement.notes"
                  name="notes"
                  placeholder="Información adicional sobre el movimiento de stock..."
                  rows="3"
                ></textarea>
              </div>

              <div class="form-actions">
                <button type="button" class="btn btn--secondary" (click)="hideMovementForm()">
                  Cancelar
                </button>
                <button type="submit" class="btn btn--primary" [disabled]="movementForm.invalid || isProcessing">
                  <svg *ngIf="!isProcessing" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20,6 9,17 4,12"></polyline>
                  </svg>
                  <svg *ngIf="isProcessing" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spinner">
                    <path d="M21,12a9,9 0 1,1-6.219-8.56"></path>
                  </svg>
                  {{ isProcessing ? 'Procesando...' : 'Confirmar Movimiento' }}
                </button>
              </div>
            </form>
          </section>

          <!-- Recent Movements -->
          <section class="recent-movements">
            <h3 class="section-title">Movimientos Recientes</h3>
            
            <div class="movements-list" *ngIf="recentMovements.length > 0; else noMovements">
              <div
                class="movement-item"
                *ngFor="let movement of recentMovements"
                [class]="'movement-' + movement.type"
              >
                <div class="movement-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19" *ngIf="movement.type === 'in'"></line>
                    <line x1="5" y1="12" x2="19" y2="12" *ngIf="movement.type === 'in'"></line>
                    <line x1="5" y1="12" x2="19" y2="12" *ngIf="movement.type === 'out'"></line>
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" *ngIf="movement.type === 'damaged'"></path>
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" *ngIf="movement.type === 'repair'"></path>
                  </svg>
                </div>
                <div class="movement-info">
                  <div class="movement-header">
                    <span class="movement-type">{{ getMovementTypeLabel(movement.type) }}</span>
                    <span class="movement-quantity">{{ movement.type === 'out' ? '-' : '+' }}{{ movement.quantity }}</span>
                  </div>
                  <p class="movement-reason">{{ movement.reason }}</p>
                  <span class="movement-date">{{ formatDate(movement.date) }}</span>
                </div>
              </div>
            </div>
            
            <ng-template #noMovements>
              <div class="no-movements">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <circle cx="12" cy="12" r="10"></circle>
                  <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                  <line x1="9" y1="9" x2="9.01" y2="9"></line>
                  <line x1="15" y1="9" x2="15.01" y2="9"></line>
                </svg>
                <p>No hay movimientos registrados</p>
              </div>
            </ng-template>
          </section>
        </div>
      </div>
    </div>
  `,
  styleUrl: './stock-manager.component.scss'
})
export class StockManagerComponent {
  @Input() equipment: RentingItem | null = null;
  @Input() isVisible = false;
  @Output() close = new EventEmitter<void>();
  @Output() stockUpdated = new EventEmitter<{ equipmentId: number; newStock: number }>();

  showForm = false;
  isProcessing = false;
  
  movement = {
    type: 'in' as 'in' | 'out' | 'adjustment' | 'damaged' | 'repair',
    quantity: 1,
    reason: '',
    notes: ''
  };

  // Mock recent movements - in a real app, this would come from a service
  recentMovements: StockMovement[] = [
    {
      id: 1,
      equipmentId: 1,
      type: 'in',
      quantity: 5,
      reason: 'Compra nueva',
      date: new Date(2025, 7, 20),
      user: 'Admin'
    },
    {
      id: 2,
      equipmentId: 1,
      type: 'out',
      quantity: 2,
      reason: 'Alquiler cliente',
      date: new Date(2025, 7, 18),
      user: 'Empleado1'
    },
    {
      id: 3,
      equipmentId: 1,
      type: 'damaged',
      quantity: 1,
      reason: 'Daño en uso',
      date: new Date(2025, 7, 15),
      user: 'Empleado2'
    }
  ];

  showMovementForm(type: 'in' | 'out' | 'damaged' | 'repair') {
    this.movement = {
      type,
      quantity: 1,
      reason: '',
      notes: ''
    };
    this.showForm = true;
  }

  hideMovementForm() {
    this.showForm = false;
  }

  incrementQuantity() {
    this.movement.quantity++;
  }

  decrementQuantity() {
    if (this.movement.quantity > 1) {
      this.movement.quantity--;
    }
  }

  getMovementTitle(): string {
    const titles: Record<string, string> = {
      in: 'Añadir Stock',
      out: 'Retirar Stock',
      damaged: 'Marcar como Dañado',
      repair: 'Enviar a Reparación',
      adjustment: 'Ajuste de Stock'
    };
    return titles[this.movement.type] || 'Movimiento de Stock';
  }

  getReasonOptions(): string[] {
    const options: Record<string, string[]> = {
      in: ['Compra nueva', 'Devolución cliente', 'Reparación completada', 'Ajuste inventario'],
      out: ['Alquiler cliente', 'Venta', 'Pérdida', 'Transferencia'],
      damaged: ['Daño en uso', 'Desgaste normal', 'Accidente', 'Defecto fabricación'],
      repair: ['Mantenimiento preventivo', 'Reparación menor', 'Reparación mayor', 'Revisión técnica'],
      adjustment: ['Inventario físico', 'Corrección sistema', 'Ajuste manual']
    };
    return options[this.movement.type] || [];
  }

  getMovementTypeLabel(type: string): string {
    const labels: Record<string, string> = {
      in: 'Entrada',
      out: 'Salida',
      damaged: 'Dañado',
      repair: 'Reparación',
      adjustment: 'Ajuste'
    };
    return labels[type] || type;
  }

  formatDate(date: Date): string {
    return new Intl.DateTimeFormat('es-ES', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }).format(date);
  }

  onSubmitMovement() {
    if (this.isProcessing || !this.equipment) return;
    
    this.isProcessing = true;
    
    // Simulate API call
    setTimeout(() => {
      let newStock = this.equipment!.stock;
      
      switch (this.movement.type) {
        case 'in':
          newStock += this.movement.quantity;
          break;
        case 'out':
        case 'damaged':
        case 'repair':
          newStock = Math.max(0, newStock - this.movement.quantity);
          break;
      }
      
      // Add movement to recent movements
      this.recentMovements.unshift({
        id: Date.now(),
        equipmentId: this.equipment!.id,
        type: this.movement.type,
        quantity: this.movement.quantity,
        reason: this.movement.reason,
        date: new Date(),
        user: 'Usuario Actual'
      });
      
      // Keep only last 5 movements
      this.recentMovements = this.recentMovements.slice(0, 5);
      
      this.stockUpdated.emit({
        equipmentId: this.equipment!.id,
        newStock
      });
      
      this.isProcessing = false;
      this.hideMovementForm();
    }, 1000);
  }

  onClose() {
    this.close.emit();
  }
}