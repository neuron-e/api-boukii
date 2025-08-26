import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { animate, style, transition, trigger } from '@angular/animations';

interface RentingItem {
  id?: number;
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

@Component({
  selector: 'app-equipment-form',
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
    <div class="form-overlay" @slideIn>
      <div class="form-container">
        <!-- Header -->
        <header class="form-header">
          <div class="header-content">
            <h2 class="form-title">
              <svg class="title-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
              {{ isEditMode ? 'Editar Equipamiento' : 'Nuevo Equipamiento' }}
            </h2>
            <p class="form-subtitle">
              {{ isEditMode ? 'Modifica los datos del equipamiento' : 'Añade nuevo material de alquiler al inventario' }}
            </p>
          </div>
          <button class="close-btn" (click)="onCancel()" type="button">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </header>

        <!-- Form -->
        <form class="equipment-form" (ngSubmit)="onSubmit()" #equipmentForm="ngForm">
          <!-- Basic Information -->
          <section class="form-section">
            <h3 class="section-title">Información Básica</h3>
            
            <div class="form-row">
              <div class="form-field">
                <label class="field-label" for="name">Nombre del Equipamiento *</label>
                <input
                  id="name"
                  type="text"
                  class="field-input"
                  [(ngModel)]="equipment.name"
                  name="name"
                  placeholder="Ej: Atomic Redster X9"
                  required
                  #nameField="ngModel"
                >
                <div class="field-error" *ngIf="nameField.invalid && nameField.touched">
                  El nombre es obligatorio
                </div>
              </div>

              <div class="form-field">
                <label class="field-label" for="type">Tipo de Equipamiento *</label>
                <select
                  id="type"
                  class="field-select"
                  [(ngModel)]="equipment.type"
                  name="type"
                  required
                  #typeField="ngModel"
                >
                  <option value="">Seleccionar tipo</option>
                  <option value="skis">Esquís</option>
                  <option value="boards">Tablas de Snow</option>
                  <option value="helmets">Cascos</option>
                </select>
                <div class="field-error" *ngIf="typeField.invalid && typeField.touched">
                  El tipo es obligatorio
                </div>
              </div>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label class="field-label" for="brand">Marca</label>
                <input
                  id="brand"
                  type="text"
                  class="field-input"
                  [(ngModel)]="equipment.brand"
                  name="brand"
                  placeholder="Ej: Atomic, Burton, Smith"
                >
              </div>

              <div class="form-field">
                <label class="field-label" for="model">Modelo</label>
                <input
                  id="model"
                  type="text"
                  class="field-input"
                  [(ngModel)]="equipment.model"
                  name="model"
                  placeholder="Ej: Redster X9, Custom X"
                >
              </div>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label class="field-label" for="size">Talla/Tamaño</label>
                <input
                  id="size"
                  type="text"
                  class="field-input"
                  [(ngModel)]="equipment.size"
                  name="size"
                  placeholder="Ej: 170cm, M, L/XL"
                >
              </div>

              <div class="form-field">
                <label class="field-label" for="condition">Estado *</label>
                <select
                  id="condition"
                  class="field-select"
                  [(ngModel)]="equipment.condition"
                  name="condition"
                  required
                  #conditionField="ngModel"
                >
                  <option value="">Seleccionar estado</option>
                  <option value="new">Nuevo</option>
                  <option value="good">Buen estado</option>
                  <option value="fair">Estado regular</option>
                  <option value="needs-repair">Necesita reparación</option>
                </select>
                <div class="field-error" *ngIf="conditionField.invalid && conditionField.touched">
                  El estado es obligatorio
                </div>
              </div>
            </div>
          </section>

          <!-- Pricing & Stock -->
          <section class="form-section">
            <h3 class="section-title">Precio y Stock</h3>
            
            <div class="form-row">
              <div class="form-field">
                <label class="field-label" for="price">Precio por día (€) *</label>
                <div class="input-with-addon">
                  <input
                    id="price"
                    type="number"
                    class="field-input"
                    [(ngModel)]="equipment.price"
                    name="price"
                    placeholder="0"
                    min="0"
                    step="0.01"
                    required
                    #priceField="ngModel"
                  >
                  <span class="input-addon">€/día</span>
                </div>
                <div class="field-error" *ngIf="priceField.invalid && priceField.touched">
                  El precio es obligatorio y debe ser mayor a 0
                </div>
              </div>

              <div class="form-field">
                <label class="field-label" for="stock">Cantidad en Stock *</label>
                <div class="stock-controls">
                  <button type="button" class="stock-btn" (click)="decrementStock()" [disabled]="(equipment.stock || 0) <= 0">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                  </button>
                  <input
                    id="stock"
                    type="number"
                    class="field-input stock-input"
                    [(ngModel)]="equipment.stock"
                    name="stock"
                    min="0"
                    required
                    #stockField="ngModel"
                  >
                  <button type="button" class="stock-btn" (click)="incrementStock()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="12" y1="5" x2="12" y2="19"></line>
                      <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                  </button>
                </div>
                <div class="field-error" *ngIf="stockField.invalid && stockField.touched">
                  El stock es obligatorio
                </div>
              </div>
            </div>

            <div class="form-field">
              <label class="checkbox-field">
                <input
                  type="checkbox"
                  class="checkbox-input"
                  [(ngModel)]="equipment.available"
                  name="available"
                >
                <span class="checkbox-label">Disponible para alquiler</span>
              </label>
              <small class="field-help">
                Si está deshabilitado, el equipamiento no aparecerá en la lista de alquiler
              </small>
            </div>
          </section>

          <!-- Additional Notes -->
          <section class="form-section">
            <h3 class="section-title">Notas Adicionales</h3>
            
            <div class="form-field">
              <label class="field-label" for="notes">Notas y observaciones</label>
              <textarea
                id="notes"
                class="field-textarea"
                [(ngModel)]="equipment.notes"
                name="notes"
                placeholder="Observaciones sobre el estado, características especiales, etc."
                rows="4"
              ></textarea>
            </div>
          </section>

          <!-- Form Actions -->
          <div class="form-actions">
            <button type="button" class="btn btn--secondary" (click)="onCancel()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
              Cancelar
            </button>
            
            <button type="submit" class="btn btn--primary" [disabled]="equipmentForm.invalid || isSubmitting">
              <svg *ngIf="!isSubmitting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20,6 9,17 4,12"></polyline>
              </svg>
              <svg *ngIf="isSubmitting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spinner">
                <path d="M21,12a9,9 0 1,1-6.219-8.56"></path>
              </svg>
              {{ isSubmitting ? 'Guardando...' : (isEditMode ? 'Actualizar' : 'Crear Equipamiento') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  `,
  styleUrl: './equipment-form.component.scss'
})
export class EquipmentFormComponent {
  @Input() isVisible = false;
  @Input() equipment: Partial<RentingItem> = {};
  @Input() isEditMode = false;
  @Output() save = new EventEmitter<RentingItem>();
  @Output() cancel = new EventEmitter<void>();

  isSubmitting = false;

  constructor() {
    this.resetForm();
  }

  ngOnInit() {
    if (!this.equipment.id) {
      this.resetForm();
    }
  }

  resetForm() {
    this.equipment = {
      name: '',
      type: 'skis' as const,
      available: true,
      price: 0,
      stock: 1,
      brand: '',
      model: '',
      size: '',
      condition: 'good' as const,
      notes: ''
    };
  }

  incrementStock() {
    this.equipment.stock = (this.equipment.stock || 0) + 1;
  }

  decrementStock() {
    if ((this.equipment.stock || 0) > 0) {
      this.equipment.stock = (this.equipment.stock || 0) - 1;
    }
  }

  onSubmit() {
    if (this.isSubmitting) return;
    
    this.isSubmitting = true;
    
    // Simulate API call
    setTimeout(() => {
      this.save.emit(this.equipment as RentingItem);
      this.isSubmitting = false;
      if (!this.isEditMode) {
        this.resetForm();
      }
    }, 1000);
  }

  onCancel() {
    this.cancel.emit();
    if (!this.isEditMode) {
      this.resetForm();
    }
  }
}