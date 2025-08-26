import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { MatTabsModule } from '@angular/material/tabs';
import { MatTableModule } from '@angular/material/table';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatInputModule } from '@angular/material/input';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { Reservation, ReservationsMockService, ReservationStatus } from '../reservations-mock.service';
import { ReservationDetailComponent } from '../reservation-detail.component';

@Component({
  selector: 'app-reservations-list',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    FormsModule,
    MatTabsModule,
    MatTableModule,
    MatFormFieldModule,
    MatSelectModule,
    MatInputModule,
    MatButtonModule,
    MatDialogModule
  ],
  templateUrl: './reservations-list.component.html',
  styleUrl: './reservations-list.component.scss'
})
export class ReservationsListComponent {
  reservations: Reservation[] = [];
  courses: string[] = [];

  currentTab: ReservationStatus | 'all' = 'active';
  typeFilter = '';
  courseFilter = '';
  paidFilter = '';
  search = '';

  displayedColumns = ['id', 'client', 'course', 'date', 'status', 'actions'];

  constructor(private service: ReservationsMockService, private dialog: MatDialog) {
    this.reservations = this.service.getReservations();
    this.courses = Array.from(new Set(this.reservations.map(r => r.course)));
  }

  get filteredReservations(): Reservation[] {
    return this.reservations.filter(r => {
      const matchesStatus = this.currentTab === 'all' || r.status === this.currentTab;
      const matchesType = !this.typeFilter || r.type === this.typeFilter;
      const matchesCourse = !this.courseFilter || r.course === this.courseFilter;
      const matchesPaid = !this.paidFilter || r.paid === (this.paidFilter === 'paid');
      const term = this.search.toLowerCase();
      const matchesSearch = !term || r.client.toLowerCase().includes(term) || r.course.toLowerCase().includes(term);
      return matchesStatus && matchesType && matchesCourse && matchesPaid && matchesSearch;
    });
  }

  onTabChange(index: number): void {
    const map: (ReservationStatus | 'all')[] = ['active', 'completed', 'cancelled', 'all'];
    this.currentTab = map[index];
  }

  openDetail(reservation: Reservation): void {
    this.dialog.open(ReservationDetailComponent, { data: reservation });
  }
}

