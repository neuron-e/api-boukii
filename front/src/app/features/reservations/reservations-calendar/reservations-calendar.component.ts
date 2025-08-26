import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { Reservation } from '../reservations-mock.service';

@Component({
  selector: 'app-reservations-calendar',
  standalone: true,
  imports: [CommonModule, MatDatepickerModule, MatNativeDateModule],
  templateUrl: './reservations-calendar.component.html',
  styleUrl: './reservations-calendar.component.scss'
})
export class ReservationsCalendarComponent {
  @Input() reservations: Reservation[] = [];
  selectedDate = new Date();

  get eventsForSelected(): Reservation[] {
    const dateStr = this.selectedDate.toISOString().slice(0, 10);
    return this.reservations.filter(r => r.date === dateStr);
  }
}

