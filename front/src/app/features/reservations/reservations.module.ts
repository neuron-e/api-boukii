import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ReservationsListComponent } from './reservations-list/reservations-list.component';
import { ReservationFormComponent } from './reservation-form/reservation-form.component';

const routes: Routes = [
  { path: '', component: ReservationsListComponent },
  { path: 'new', component: ReservationFormComponent }
];

@NgModule({
  imports: [RouterModule.forChild(routes)]
})
export class ReservationsModule {}

