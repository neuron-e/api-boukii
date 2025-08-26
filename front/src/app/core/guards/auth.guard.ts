import { CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthGuardService } from '../services/auth-guard.service';

/**
 * Basic authentication guard that relies on lightweight services
 * without performing HTTP requests.
 */
export const authGuard: CanActivateFn = (_route, state) => {
  const authHelper = inject(AuthGuardService);
  const router = inject(Router);

  const isAuthenticated = authHelper.isAuthenticated();

  // Allow multi-step auth flow with temporary token
  if (!isAuthenticated) {
    const tempToken = localStorage.getItem('boukii_temp_token');
    const isSchoolSelection = state.url.includes('/select-school');
    const isSeasonSelection = state.url.includes('/select-season');

    if (tempToken && (isSchoolSelection || isSeasonSelection)) {
      return true;
    }

    router.navigate(['/auth/login'], { queryParams: { returnUrl: state.url } });
    return false;
  }

  return true;
};

/**
 * Guard factory for permission-based routes using stored permissions.
 */
export const createPermissionGuard = (requiredPermissions: string[]): CanActivateFn =>
  () => {
    const authHelper = inject(AuthGuardService);
    const router = inject(Router);

    if (!authHelper.isAuthenticated()) {
      router.navigate(['/auth/login']);
      return false;
    }

    const hasPermission = authHelper.hasAnyPermission(requiredPermissions);
    if (!hasPermission) {
      router.navigate(['/unauthorized']);
      return false;
    }

    return true;
  };
