import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { animate, state, style, transition, trigger } from '@angular/animations';
import { EquipmentFormComponent } from './equipment-form/equipment-form.component';
import { StockManagerComponent } from './stock-manager/stock-manager.component';

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

@Component({
  selector: 'app-renting',
  standalone: true,
  imports: [CommonModule, FormsModule, EquipmentFormComponent, StockManagerComponent],
  styleUrl: './renting.component.scss',
  animations: [
    trigger('slideDown', [
      transition(':enter', [
        style({ opacity: 0, transform: 'translateY(-10px)' }),
        animate('200ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
      ])
    ])
  ],
  template: `
    <div class="renting-page">
      <!-- Header Section -->
      <header class="page-header">
        <div class="header-content">
          <h1 class="page-title">
            <svg class="title-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
              <line x1="8" y1="21" x2="16" y2="21"></line>
              <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
            Material de Alquiler
          </h1>
          <p class="page-subtitle">Gestión profesional del equipamiento deportivo</p>
        </div>
        <div class="header-actions">
          <button class="action-btn action-btn--secondary" (click)="openEquipmentForm()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2v20M2 12h20"></path>
            </svg>
            Nuevo Equipamiento
          </button>
          <button class="action-btn action-btn--primary" (click)="exportInventory()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 11H5a2 2 0 0 0-2 2v3c0 .55.45 1 1 1h4v-6zM20 15V9a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6"></path>
            </svg>
            Exportar Inventario
          </button>
        </div>
      </header>

      <!-- Filters and Search -->
      <section class="filters-section">
        <div class="search-box">
          <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
          <input 
            type="text" 
            class="search-input" 
            placeholder="Buscar equipamiento..." 
            [(ngModel)]="searchQuery"
          >
        </div>
        
        <div class="filter-chips">
          <button 
            class="filter-chip" 
            [class.active]="typeFilter === ''"
            (click)="setFilter('')"
          >
            <span class="chip-count">{{ items.length }}</span>
            Todos
          </button>
          <button 
            class="filter-chip" 
            [class.active]="typeFilter === 'skis'"
            (click)="setFilter('skis')"
          >
            <span class="chip-count">{{ getCountByType('skis') }}</span>
            Esquís
          </button>
          <button 
            class="filter-chip" 
            [class.active]="typeFilter === 'boards'"
            (click)="setFilter('boards')"
          >
            <span class="chip-count">{{ getCountByType('boards') }}</span>
            Tablas
          </button>
          <button 
            class="filter-chip" 
            [class.active]="typeFilter === 'helmets'"
            (click)="setFilter('helmets')"
          >
            <span class="chip-count">{{ getCountByType('helmets') }}</span>
            Cascos
          </button>
        </div>

        <div class="view-toggle">
          <button 
            class="toggle-btn" 
            [class.active]="viewMode === 'grid'"
            (click)="viewMode = 'grid'"
            title="Vista en rejilla"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
          </button>
          <button 
            class="toggle-btn" 
            [class.active]="viewMode === 'list'"
            (click)="viewMode = 'list'"
            title="Vista en lista"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="8" y1="6" x2="21" y2="6"></line>
              <line x1="8" y1="12" x2="21" y2="12"></line>
              <line x1="8" y1="18" x2="21" y2="18"></line>
              <line x1="3" y1="6" x2="3.01" y2="6"></line>
              <line x1="3" y1="12" x2="3.01" y2="12"></line>
              <line x1="3" y1="18" x2="3.01" y2="18"></line>
            </svg>
          </button>
        </div>
      </section>

      <!-- Equipment Grid -->
      <main class="equipment-grid" [class.grid-view]="viewMode === 'grid'" [class.list-view]="viewMode === 'list'">
        <article 
          class="equipment-card" 
          *ngFor="let item of filteredItems(); trackBy: trackByItemId"
          [class.unavailable]="!item.available"
        >
          <!-- Equipment Image/Icon -->
          <div class="equipment-visual">
            <div class="equipment-icon" [attr.data-type]="item.type">
              <svg *ngIf="item.type === 'skis'" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                <path d="M2 2h2v18L2 22l1-1v-1h1v-2H3V4h1V2H2zm18 0h2v2h-1v14h1v2h-1l-2-2V2zm-8 6l-2 2v6l2-2V8zm4 0v6l2 2V8l-2-2z"/>
              </svg>
              <svg *ngIf="item.type === 'boards'" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C8 2 5 5 5 9v6c0 4 3 7 7 7s7-3 7-7V9c0-4-3-7-7-7zm3 13c0 1.7-1.3 3-3 3s-3-1.3-3-3V9c0-1.7 1.3-3 3-3s3 1.3 3 3v6z"/>
              </svg>
              <svg *ngIf="item.type === 'helmets'" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 1C7.6 1 4 4.6 4 9c0 .6.1 1.2.2 1.8L3 12v7h2v-3h2v3h2v-3h6v3h2v-3h2v3h2v-7l-1.2-1.2c.1-.6.2-1.2.2-1.8 0-4.4-3.6-8-8-8z"/>
              </svg>
            </div>
            <span class="availability-indicator" [class.available]="item.available" [class.unavailable]="!item.available">
              {{ item.available ? 'Disponible' : 'No disponible' }}
            </span>
          </div>

          <!-- Equipment Info -->
          <div class="equipment-info">
            <h3 class="equipment-name">{{ item.name }}</h3>
            <div class="equipment-meta">
              <span class="equipment-type">{{ getTypeDisplayName(item.type) }}</span>
              <span class="equipment-price">{{ item.price }}€/día</span>
            </div>
          </div>

          <!-- Equipment Actions -->
          <div class="equipment-actions">
            <button 
              class="action-button action-button--reserve" 
              [disabled]="!item.available"
              (click)="toggleForm(item.id)"
              [class.active]="openReservationId === item.id"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              {{ openReservationId === item.id ? 'Cancelar' : 'Reservar' }}
            </button>
            <button class="action-button action-button--details" (click)="openStockManager(item)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
              </svg>
            </button>
          </div>

          <!-- Reservation Form -->
          <div class="reservation-form" *ngIf="openReservationId === item.id" [@slideDown]>
            <div class="form-header">
              <h4>Reservar {{ item.name }}</h4>
              <span class="form-price">{{ item.price }}€/día</span>
            </div>
            
            <div class="form-body">
              <div class="form-row">
                <div class="form-field">
                  <label class="field-label">Fecha inicio</label>
                  <input 
                    type="date" 
                    class="field-input" 
                    [(ngModel)]="reservation.date"
                    [min]="getTodayDate()"
                  >
                </div>
                <div class="form-field">
                  <label class="field-label">Hora recogida</label>
                  <input 
                    type="time" 
                    class="field-input" 
                    [(ngModel)]="reservation.time"
                  >
                </div>
              </div>
              
              <div class="form-field">
                <label class="checkbox-field">
                  <input 
                    type="checkbox" 
                    class="checkbox-input" 
                    [(ngModel)]="reservation.attachToCourse"
                  >
                  <span class="checkbox-label">Vincular con curso activo</span>
                </label>
              </div>
              
              <div class="form-actions">
                <button 
                  class="form-button form-button--cancel" 
                  (click)="toggleForm(item.id)"
                >
                  Cancelar
                </button>
                <button 
                  class="form-button form-button--confirm"
                  (click)="confirmReservation(item)"
                >
                  Confirmar Reserva
                </button>
              </div>
            </div>
          </div>
        </article>
      </main>

      <!-- Empty State -->
      <div class="empty-state" *ngIf="filteredItems().length === 0">
        <div class="empty-icon">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
        </div>
        <h3 class="empty-title">No se encontró equipamiento</h3>
        <p class="empty-message">
          {{ searchQuery ? 'Intenta con otros términos de búsqueda' : 'No hay equipamiento disponible en esta categoría' }}
        </p>
      </div>
    </div>

    <!-- Equipment Form Modal -->
    <app-equipment-form
      *ngIf="showEquipmentForm"
      [isVisible]="showEquipmentForm"
      [equipment]="selectedEquipment"
      [isEditMode]="isEditMode"
      (save)="onEquipmentSave($event)"
      (cancel)="closeEquipmentForm()"
    ></app-equipment-form>

    <!-- Stock Manager Modal -->
    <app-stock-manager
      *ngIf="showStockManager"
      [isVisible]="showStockManager"
      [equipment]="selectedEquipmentForStock"
      (close)="closeStockManager()"
      (stockUpdated)="onStockUpdated($event)"
    ></app-stock-manager>
  `
})
export class RentingComponent {
  typeFilter = '';
  searchQuery = '';
  viewMode: 'grid' | 'list' = 'grid';
  openReservationId: number | null = null;
  reservation = { date: '', time: '', attachToCourse: false };
  
  // Equipment Form
  showEquipmentForm = false;
  selectedEquipment: Partial<RentingItem> = {};
  isEditMode = false;
  
  // Stock Manager
  showStockManager = false;
  selectedEquipmentForStock: RentingItem | null = null;

  items: RentingItem[] = [
    { id: 1, name: 'Atomic Redster X9', type: 'skis', available: true, price: 35, stock: 5, brand: 'Atomic', model: 'Redster X9', size: '170cm', condition: 'new', notes: 'Esquí de competición de alta gama' },
    { id: 2, name: 'Burton Custom X', type: 'boards', available: false, price: 40, stock: 0, brand: 'Burton', model: 'Custom X', size: '158cm', condition: 'good', notes: 'Tabla freestyle versátil' },
    { id: 3, name: 'Smith Vantage MIPS', type: 'helmets', available: true, price: 15, stock: 8, brand: 'Smith', model: 'Vantage MIPS', size: 'L', condition: 'new', notes: 'Casco con tecnología MIPS' },
    { id: 4, name: 'Rossignol Experience 88', type: 'skis', available: true, price: 32, stock: 3, brand: 'Rossignol', model: 'Experience 88', size: '165cm', condition: 'good', notes: 'Esquí all-mountain polivalente' },
    { id: 5, name: 'Giro Range MIPS', type: 'helmets', available: false, price: 18, stock: 0, brand: 'Giro', model: 'Range MIPS', size: 'M', condition: 'needs-repair', notes: 'Necesita cambio de espuma interior' },
    { id: 6, name: 'K2 Mindbender 85', type: 'skis', available: true, price: 28, stock: 7, brand: 'K2', model: 'Mindbender 85', size: '160cm', condition: 'good', notes: 'Esquí freeride ligero' },
    { id: 7, name: 'Lib Tech Skate Banana', type: 'boards', available: true, price: 38, stock: 4, brand: 'Lib Tech', model: 'Skate Banana', size: '155cm', condition: 'fair', notes: 'Tabla con perfil híbrido' },
    { id: 8, name: 'POC Obex SPIN', type: 'helmets', available: true, price: 20, stock: 6, brand: 'POC', model: 'Obex SPIN', size: 'XL', condition: 'new', notes: 'Casco con sistema SPIN' },
    { id: 9, name: 'Salomon QST 92', type: 'skis', available: false, price: 30, stock: 1, brand: 'Salomon', model: 'QST 92', size: '175cm', condition: 'fair', notes: 'En revisión técnica' },
    { id: 10, name: 'Jones Mountain Twin', type: 'boards', available: true, price: 42, stock: 2, brand: 'Jones', model: 'Mountain Twin', size: '162cm', condition: 'good', notes: 'Tabla twin-tip mountain' },
  ];

  filteredItems() {
    let filtered = this.items;
    
    // Filter by type
    if (this.typeFilter) {
      filtered = filtered.filter(i => i.type === this.typeFilter);
    }
    
    // Filter by search query
    if (this.searchQuery.trim()) {
      const query = this.searchQuery.toLowerCase().trim();
      filtered = filtered.filter(i => 
        i.name.toLowerCase().includes(query) ||
        this.getTypeDisplayName(i.type).toLowerCase().includes(query)
      );
    }
    
    return filtered;
  }

  getCountByType(type: string): number {
    return this.items.filter(i => i.type === type).length;
  }

  getTypeDisplayName(type: string): string {
    const typeNames = {
      'skis': 'Esquís',
      'boards': 'Tablas de Snow',
      'helmets': 'Cascos'
    };
    return typeNames[type as keyof typeof typeNames] || type;
  }

  setFilter(filter: string) {
    this.typeFilter = filter;
  }

  toggleForm(id: number) {
    this.openReservationId = this.openReservationId === id ? null : id;
    if (this.openReservationId === id) {
      // Reset form when opening
      this.reservation = { date: '', time: '09:00', attachToCourse: false };
    }
  }

  confirmReservation(item: RentingItem) {
    if (!this.reservation.date || !this.reservation.time) {
      alert('Por favor, completa todos los campos obligatorios.');
      return;
    }
    
    // Here you would typically send the reservation to a service
    console.log('Reserva confirmada:', {
      item: item.name,
      ...this.reservation
    });
    
    alert(`Reserva confirmada para ${item.name}. Te esperamos el ${this.reservation.date} a las ${this.reservation.time}.`);
    this.toggleForm(item.id);
  }

  getTodayDate(): string {
    return new Date().toISOString().split('T')[0];
  }

  trackByItemId(index: number, item: RentingItem): number {
    return item.id;
  }

  // Equipment Form Methods
  openEquipmentForm() {
    this.selectedEquipment = {};
    this.isEditMode = false;
    this.showEquipmentForm = true;
  }

  editEquipment(item: RentingItem) {
    this.selectedEquipment = { ...item };
    this.isEditMode = true;
    this.showEquipmentForm = true;
  }

  closeEquipmentForm() {
    this.showEquipmentForm = false;
    this.selectedEquipment = {};
    this.isEditMode = false;
  }

  onEquipmentSave(equipment: Partial<RentingItem>) {
    if (this.isEditMode && equipment.id) {
      // Update existing equipment
      const index = this.items.findIndex(item => item.id === equipment.id);
      if (index !== -1) {
        this.items[index] = { ...equipment } as RentingItem;
      }
    } else {
      // Add new equipment
      const newId = Math.max(...this.items.map(i => i.id), 0) + 1;
      const newEquipment: RentingItem = {
        id: newId,
        name: equipment.name || '',
        type: equipment.type || 'skis',
        available: equipment.available || false,
        price: equipment.price || 0,
        stock: equipment.stock || 0,
        brand: equipment.brand,
        model: equipment.model,
        size: equipment.size,
        condition: equipment.condition || 'good',
        notes: equipment.notes
      };
      this.items.push(newEquipment);
    }
    this.closeEquipmentForm();
  }

  // Stock Manager Methods
  openStockManager(item: RentingItem) {
    this.selectedEquipmentForStock = item;
    this.showStockManager = true;
  }

  closeStockManager() {
    this.showStockManager = false;
    this.selectedEquipmentForStock = null;
  }

  onStockUpdated(event: { equipmentId: number; newStock: number }) {
    const item = this.items.find(i => i.id === event.equipmentId);
    if (item) {
      item.stock = event.newStock;
      // Update availability based on stock
      item.available = event.newStock > 0;
    }
  }

  // Export functionality
  exportInventory() {
    const csvContent = this.generateCSVContent();
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `inventario-equipamiento-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  }

  private generateCSVContent(): string {
    const headers = ['ID', 'Nombre', 'Tipo', 'Marca', 'Modelo', 'Tamaño', 'Estado', 'Stock', 'Precio', 'Disponible', 'Notas'];
    const rows = this.items.map(item => [
      item.id,
      item.name,
      this.getTypeDisplayName(item.type),
      item.brand || '',
      item.model || '',
      item.size || '',
      this.getConditionDisplayName(item.condition),
      item.stock,
      item.price,
      item.available ? 'Sí' : 'No',
      item.notes || ''
    ]);

    const csvRows = [headers, ...rows].map(row => 
      row.map(field => `"${field}"`).join(',')
    );

    return csvRows.join('\n');
  }

  private getConditionDisplayName(condition: string): string {
    const conditionNames = {
      'new': 'Nuevo',
      'good': 'Buen estado',
      'fair': 'Estado regular',
      'needs-repair': 'Necesita reparación'
    };
    return conditionNames[condition as keyof typeof conditionNames] || condition;
  }
}
