import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

interface RentingItem {
  id: number;
  nombre: string;
  tipo: 'skis' | 'boards' | 'helmets';
  disponible: boolean;
  precio: number;
}

@Component({
  selector: 'app-renting',
  standalone: true,
  imports: [CommonModule, FormsModule],
  styleUrl: './renting.component.scss',
  template: `
    <div class="page">
      <div class="page-header">
        <h1>Renting</h1>
        <div class="subtitle">Gestión de material</div>
      </div>

      <div class="filter">
        <label for="tipo-select" class="visually-hidden">Filtrar por tipo</label>
        <select id="tipo-select" [(ngModel)]="filtroTipo">
          <option value="">Todos</option>
          <option value="skis">Skis</option>
          <option value="boards">Boards</option>
          <option value="helmets">Helmets</option>
        </select>
      </div>

      <div class="stack">
        <div class="card" *ngFor="let item of itemsFiltrados()">
          <div class="item-header">
            <strong>{{ item.nombre }}</strong>
            <span class="chip" [ngClass]="item.disponible ? 'chip--green' : 'chip--red'">
              {{ item.disponible ? 'Disponible' : 'No disponible' }}
            </span>
            <span class="chip chip--blue">€{{ item.precio }}</span>
            <button class="btn btn--primary" (click)="abrirFormulario(item.id)">
              Reservar
            </button>
          </div>

          <form *ngIf="item.id === reservaAbierta" class="reserva-form">
            <div class="row gap">
              <input type="date" name="fecha{{ item.id }}" [(ngModel)]="reserva.fecha" />
              <input type="time" name="hora{{ item.id }}" [(ngModel)]="reserva.hora" />
            </div>
            <label>
              <input type="checkbox" name="curso{{ item.id }}" [(ngModel)]="reserva.adjuntar" />
              Adjuntar a curso
            </label>
          </form>
        </div>
      </div>
    </div>
  `
})
export class RentingComponent {
  filtroTipo = '';
  reservaAbierta: number | null = null;
  reserva = { fecha: '', hora: '', adjuntar: false };

  items: RentingItem[] = [
    { id: 1, nombre: 'Ski Atomic', tipo: 'skis', disponible: true, precio: 25 },
    { id: 2, nombre: 'Snowboard Burton', tipo: 'boards', disponible: false, precio: 30 },
    { id: 3, nombre: 'Casco Smith', tipo: 'helmets', disponible: true, precio: 10 },
    { id: 4, nombre: 'Ski Rossignol', tipo: 'skis', disponible: true, precio: 22 },
    { id: 5, nombre: 'Casco Giro', tipo: 'helmets', disponible: false, precio: 12 },
  ];

  itemsFiltrados() {
    return this.filtroTipo ? this.items.filter(i => i.tipo === this.filtroTipo) : this.items;
  }

  abrirFormulario(id: number) {
    this.reservaAbierta = this.reservaAbierta === id ? null : id;
  }
}

