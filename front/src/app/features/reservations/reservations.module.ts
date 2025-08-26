import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ReservationsDashboardComponent } from './reservations-dashboard.component';
import { ReservationFormComponent } from './reservation-form/reservation-form.component';

const routes: Routes = [
  { path: '', component: ReservationsDashboardComponent },
  { path: 'new', component: ReservationFormComponent },
  { path: ':id/edit', component: ReservationFormComponent }
];

@NgModule({
  imports: [RouterModule.forChild(routes)]
})
export class ReservationsModule {}

