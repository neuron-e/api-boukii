import { Routes } from '@angular/router';
import { authGuard } from '../../../core/guards/auth.guard';
import { schoolSelectionGuard } from '../../../core/guards/school-selection.guard';

export const permissionsRoutes: Routes = [
  {
    path: '',
    canActivate: [authGuard, schoolSelectionGuard],
    children: [
      {
        path: '',
        redirectTo: 'matrix',
        pathMatch: 'full'
      },
      {
        path: 'matrix',
        loadComponent: () => import('./permissions-page.component').then(m => m.PermissionsPageComponent),
        data: {
          title: 'permissions.title',
          breadcrumbs: [
            { label: 'admin.title', route: '/admin' },
            { label: 'permissions.title', route: '/admin/permissions' }
          ]
        }
      }
    ]
  }
];