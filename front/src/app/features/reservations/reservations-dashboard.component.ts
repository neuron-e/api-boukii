import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { ReservationsMockService, Reservation } from './reservations-mock.service';
import { ReservationsListComponent } from './reservations-list/reservations-list.component';
import { ReservationsCalendarComponent } from './reservations-calendar/reservations-calendar.component';

@Component({
  selector: 'app-reservations-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    ReactiveFormsModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatDatepickerModule,
    MatNativeDateModule,
    ReservationsListComponent,
    ReservationsCalendarComponent
  ],
  templateUrl: './reservations-dashboard.component.html',
  styleUrl: './reservations-dashboard.component.scss'
})
export class ReservationsDashboardComponent {
  view: 'list' | 'calendar' = 'list';
  filters: FormGroup;
  reservations: Reservation[];
  stats: { today: number; pending: number; cancelled: number; income: number };

  constructor(private fb: FormBuilder, private service: ReservationsMockService) {
    this.filters = this.fb.group({
      dateFrom: null,
      dateTo: null,
      status: '',
      type: '',
      client: '',
      monitor: ''
    });
    this.reservations = this.service.getReservations();
    this.stats = this.service.getStats();
  }

  get filteredReservations(): Reservation[] {
    const { dateFrom, dateTo, status, type, client, monitor } = this.filters.value;
    return this.reservations.filter(r => {
      const d = new Date(r.date);
      const matchesDate = (!dateFrom || d >= dateFrom) && (!dateTo || d <= dateTo);
      const matchesStatus = !status || r.status === status;
      const matchesType = !type || r.type === type;
      const matchesClient = !client || r.client.toLowerCase().includes(client.toLowerCase());
      const matchesMonitor = !monitor || r.monitor.toLowerCase().includes(monitor.toLowerCase());
      return matchesDate && matchesStatus && matchesType && matchesClient && matchesMonitor;
    });
  }

  toggleView(): void {
    this.view = this.view === 'list' ? 'calendar' : 'list';
  }
}

