import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { schoolSelectionGuard, requireCompleteContextGuard } from './core/guards/school-selection.guard';
import { seasonSelectionGuard, requireCompleteAuthGuard } from './core/guards/season-selection.guard';

export const routes: Routes = [
  {
    path: '',
    redirectTo: '/dashboard',
    pathMatch: 'full',
  },
  
  // Auth routes - standalone without shell
  {
    path: 'auth/login',
    loadComponent: () => import('./features/auth/pages/login.page').then(m => m.LoginPage)
  },
  {
    path: 'auth/register',
    loadComponent: () => import('./features/auth/pages/register.page').then(m => m.RegisterPage)
  },
  {
    path: 'auth/forgot-password',
    loadComponent: () => import('./features/auth/pages/forgot-password.page').then(m => m.ForgotPasswordPage)
  },
  
  // Auth context selection routes - standalone without shell
  {
    path: 'select-school',
    loadComponent: () =>
      import('./features/school-selection/select-school.page').then(c => c.SelectSchoolPageComponent),
    canActivate: [authGuard, schoolSelectionGuard]
  },
  {
    path: 'select-season',
    loadComponent: () => 
      import('./features/seasons/select-season.page').then(c => c.SelectSeasonPageComponent),
    canActivate: [authGuard, seasonSelectionGuard]
  },
  
  // Admin routes - wrapped in AppShell
  {
    path: '',
    loadComponent: () => import('./ui/app-shell/app-shell.component').then(c => c.AppShellComponent),
    children: [
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./features/dashboard/dashboard-page.component').then(c => c.DashboardPageComponent),
        canActivate: [requireCompleteAuthGuard]
      },
      {
        path: 'clients',
        canActivate: [requireCompleteAuthGuard],
        children: [
          {
            path: '',
            loadComponent: () =>
              import('./features/clients/clients-list.page').then(c => c.ClientsListPageComponent)
          },
          {
            path: ':id',
            loadComponent: () =>
              import('./features/clients/client-detail.page').then(c => c.ClientDetailPageComponent)
          }
        ]
      },
      {
        path: 'admin/users',
        canActivate: [requireCompleteAuthGuard],
        loadChildren: () => import('./features/admin/users/users.routes').then(m => m.USERS_ROUTES)
      },
      {
        path: 'admin/roles',
        loadComponent: () => import('./features/admin/roles/roles-list.page').then(m => m.RolesListPageComponent),
        canActivate: [requireCompleteAuthGuard]
      },
      {
        path: 'admin/permissions',
        canActivate: [requireCompleteAuthGuard],
        loadChildren: () => import('./features/admin/permissions/permissions.routes').then(m => m.permissionsRoutes)
      },
      {
        path: 'settings',
        loadChildren: () => import('./features/settings').then(m => m.routes)
      }
    ]
  },
  
  // Utility routes - standalone
  {
    path: 'unauthorized',
    loadComponent: () =>
      import('./shared/components/unauthorized/unauthorized.component').then(c => c.UnauthorizedComponent)
  },
  {
    path: 'no-access',
    loadComponent: () =>
      import('./shared/components/no-access/no-access.component').then(c => c.NoAccessComponent)
  },
  {
    path: '**',
    redirectTo: '/auth/login',
  },
];
