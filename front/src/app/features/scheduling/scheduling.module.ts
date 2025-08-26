import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SchedulingCalendarComponent } from './scheduling-calendar.component';

const routes: Routes = [
  { path: '', component: SchedulingCalendarComponent }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
})
export class SchedulingModule {}
