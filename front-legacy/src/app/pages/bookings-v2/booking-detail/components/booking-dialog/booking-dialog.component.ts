import {Component, EventEmitter, Inject, Output, ViewChild} from '@angular/core';
import { MAT_DIALOG_DATA } from '@angular/material/dialog';
import {BookingReservationDetailComponent} from '../booking-reservation-detail/booking-reservation-detail.component';

@Component({
  selector: 'booking-detail-dialog',
  templateUrl: './booking-dialog.component.html',
  styleUrls: ['./booking-dialog.component.scss']
})
export class BookingDetailDialogComponent {
  @ViewChild(BookingReservationDetailComponent) reservationDetailComponent: BookingReservationDetailComponent;

  @Output() deleteActivity = new EventEmitter<void>();
  @Output() payActivity = new EventEmitter<void>();
  @Output() closeClick = new EventEmitter<void>();

  ngAfterViewInit() {
    this.reservationDetailComponent.deleteActivity.subscribe(() => {
      this.deleteActivity.emit(); // Lo reemites hacia el `dialogRef.componentInstance`
    });

    this.reservationDetailComponent.payClick.subscribe(() => {
      this.payActivity.emit(); // Lo reemites hacia el `dialogRef.componentInstance`
    });

    this.reservationDetailComponent.closeClick.subscribe(() => {
      this.closeClick.emit(); // También lo reemites
    });
  }

  constructor(@Inject(MAT_DIALOG_DATA) public data: any) { }
}
