import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ReservationsListComponent } from './reservations-list/reservations-list.component';

const routes: Routes = [{ path: '', component: ReservationsListComponent }];

@NgModule({
  imports: [RouterModule.forChild(routes)]
})
export class ReservationsModule {}

