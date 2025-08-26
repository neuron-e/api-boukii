import { Component, Inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { Reservation } from './reservations-mock.service';

@Component({
  selector: 'app-reservation-detail',
  standalone: true,
  imports: [CommonModule, MatDialogModule, MatButtonModule],
  template: `
    <h2 mat-dialog-title>Reservation {{ data.id }}</h2>
    <mat-dialog-content class="dialog-content">
      <p><strong>Client:</strong> {{ data.client }}</p>
      <p><strong>Course:</strong> {{ data.course }}</p>
      <p><strong>Date:</strong> {{ data.date }}</p>
      <p><strong>Status:</strong> {{ data.status }}</p>
      <p><strong>Type:</strong> {{ data.type }}</p>
      <p><strong>Monitor:</strong> {{ data.monitor }}</p>
      <p><strong>Price:</strong> {{ data.price | currency:'EUR' }}</p>
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button mat-button mat-dialog-close>Close</button>
    </mat-dialog-actions>
  `,
  styles: [
    `.dialog-content { color: var(--text-1); }`
  ]
})
export class ReservationDetailComponent {
  constructor(@Inject(MAT_DIALOG_DATA) public data: Reservation) {}
}

