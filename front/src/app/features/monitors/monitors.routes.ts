import { Routes } from '@angular/router';

export const MONITORS_ROUTES: Routes = [
  {
    path: '',
    loadComponent: () =>
      import('./monitors-list/monitors-list.component').then(
        (m) => m.MonitorsListComponent
      ),
  },
  {
    path: ':id',
    loadComponent: () =>
      import('./monitor-profile/monitor-profile.component').then(
        (m) => m.MonitorProfileComponent
      ),
  },
];
