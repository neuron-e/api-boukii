import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

interface RentingItem {
  id: number;
  name: string;
  type: 'skis' | 'boards' | 'helmets';
  available: boolean;
  price: number;
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
        <div class="subtitle">Equipment management</div>
      </div>

      <div class="filter">
        <label for="type-select" class="visually-hidden">Filter by type</label>
        <select id="type-select" [(ngModel)]="typeFilter">
          <option value="">All</option>
          <option value="skis">Skis</option>
          <option value="boards">Boards</option>
          <option value="helmets">Helmets</option>
        </select>
      </div>

      <div class="stack">
        <div class="card" *ngFor="let item of filteredItems()">
          <div class="item-header">
            <strong>{{ item.name }}</strong>
            <span class="chip" [ngClass]="item.available ? 'chip--green' : 'chip--red'">
              {{ item.available ? 'Available' : 'Not available' }}
            </span>
            <span class="chip chip--blue">€{{ item.price }}</span>
            <button class="btn btn--primary" (click)="toggleForm(item.id)">
              Reserve
            </button>
          </div>

          <form *ngIf="item.id === openReservationId" class="reservation-form">
            <div class="row gap">
              <input type="date" name="date{{ item.id }}" [(ngModel)]="reservation.date" />
              <input type="time" name="time{{ item.id }}" [(ngModel)]="reservation.time" />
            </div>
            <label>
              <input type="checkbox" name="course{{ item.id }}" [(ngModel)]="reservation.attachToCourse" />
              Attach to course
            </label>
          </form>
        </div>
      </div>
    </div>
  `
})
export class RentingComponent {
  typeFilter = '';
  openReservationId: number | null = null;
  reservation = { date: '', time: '', attachToCourse: false };

  items: RentingItem[] = [
    { id: 1, name: 'Atomic Ski', type: 'skis', available: true, price: 25 },
    { id: 2, name: 'Burton Snowboard', type: 'boards', available: false, price: 30 },
    { id: 3, name: 'Smith Helmet', type: 'helmets', available: true, price: 10 },
    { id: 4, name: 'Rossignol Ski', type: 'skis', available: true, price: 22 },
    { id: 5, name: 'Giro Helmet', type: 'helmets', available: false, price: 12 },
  ];

  filteredItems() {
    return this.typeFilter ? this.items.filter(i => i.type === this.typeFilter) : this.items;
  }

  toggleForm(id: number) {
    this.openReservationId = this.openReservationId === id ? null : id;
  }
}
