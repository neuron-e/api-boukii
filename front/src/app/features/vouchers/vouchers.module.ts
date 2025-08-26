import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { VouchersListComponent } from './vouchers-list.component';

const routes: Routes = [{ path: '', component: VouchersListComponent }];

@NgModule({
  imports: [RouterModule.forChild(routes)],
})
export class VouchersModule {}

