import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { Reservation, ReservationStatus } from '../reservations-mock.service';
import { ReservationDetailComponent } from '../reservation-detail.component';

@Component({
  selector: 'app-reservations-list',
  standalone: true,
  imports: [CommonModule, RouterModule, MatCardModule, MatButtonModule, MatDialogModule],
  templateUrl: './reservations-list.component.html',
  styleUrl: './reservations-list.component.scss'
})
export class ReservationsListComponent {
  @Input() reservations: Reservation[] = [];

  constructor(private dialog: MatDialog) {}

  openDetail(reservation: Reservation): void {
    this.dialog.open(ReservationDetailComponent, { data: reservation });
  }

  confirm(reservation: Reservation): void {
    // TODO: Implement confirmation logic
  }

  cancel(reservation: Reservation): void {
    // TODO: Implement cancellation logic
  }

  statusLabel(status: ReservationStatus): string {
    switch (status) {
      case 'confirmed':
        return 'Confirmada';
      case 'pending':
        return 'Pendiente';
      case 'cancelled':
        return 'Cancelada';
      case 'completed':
        return 'Completada';
      default:
        return status;
    }
  }
}

