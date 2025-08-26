import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, of, switchMap } from 'rxjs';
import { AuthV5Service } from '@core/services/auth-v5.service';
import { SchoolService } from '@core/services/school.service';
import { ContextService } from '@core/services/context.service';

/**
 * Guard for /select-school route
 * Logic:
 * - If user has 1 school -> set context and redirect to /select-season
 * - If user has >1 school -> allow access to select-school page
 * - If user has 0 schools -> redirect to dashboard (should not happen normally)
 */
export const schoolSelectionGuard: CanActivateFn = () => {
  const auth = inject(AuthV5Service);
  const router = inject(Router);
  const schoolService = inject(SchoolService);
  const contextService = inject(ContextService);

  // Check if user is authenticated OR has temp token for school selection
  const tempToken = localStorage.getItem('boukii_temp_token');
  const tempSchools = localStorage.getItem('boukii_temp_schools');
  
  if (!auth.isAuthenticated() && !tempToken) {
    console.log('🔒 SchoolSelectionGuard: No auth and no temp token, redirecting to login');
    router.navigate(['/auth/login']);
    return false;
  }

  // If we have temp token and schools, we're in multi-school selection flow
  if (tempToken && tempSchools) {
    console.log('🎭 SchoolSelectionGuard: Multi-school flow detected, allowing access');
    return true;
  }

  // Check if user already has a school selected
  if (contextService.hasSchoolSelected()) {
    router.navigate(['/select-season']);
    return false;
  }

  // Get user's schools using /me/schools?all=true and apply logic
  return schoolService.getAllMySchools().pipe(
    switchMap(async (schools) => {
      console.log('🏫 SchoolSelectionGuard: Found schools:', schools.length);

      if (schools.length === 0) {
        // No schools available - redirect to dashboard
        console.log('🏫 SchoolSelectionGuard: No schools found, redirecting to dashboard');
        router.navigate(['/dashboard']);
        return false;
      }

      if (schools.length === 1) {
        // Only one school - auto-select and redirect to season selection
        console.log('🏫 SchoolSelectionGuard: Single school found, auto-selecting and redirecting');
        try {
          await contextService.setSchool(schools[0].id);
          router.navigate(['/select-season']);
          return false;
        } catch (error) {
          console.error('🏫 SchoolSelectionGuard: Failed to set school context:', error);
          // Continue to school selection page if context setting fails
          return true;
        }
      }

      // Multiple schools - show selection page
      console.log('🏫 SchoolSelectionGuard: Multiple schools found, showing selection page');
      return true;
    }),
    catchError((error) => {
      console.error('🏫 SchoolSelectionGuard: Error loading schools:', error);
      // On error, redirect to dashboard
      router.navigate(['/dashboard']);
      return of(false);
    })
  );
};

/**
 * Guard that ensures user has selected a school before accessing certain routes
 */
export const requireSchoolGuard: CanActivateFn = () => {
  const router = inject(Router);
  const contextService = inject(ContextService);
  const auth = inject(AuthV5Service);

  // First check if user is authenticated
  if (!auth.isAuthenticated()) {
    router.navigate(['/auth/login']);
    return false;
  }

  // Check if school is selected
  if (!contextService.hasSchoolSelected()) {
    router.navigate(['/select-school']);
    return false;
  }

  return true;
};

/**
 * Guard that ensures user has complete context (school + season) before accessing certain routes
 */
export const requireCompleteContextGuard: CanActivateFn = () => {
  const router = inject(Router);
  const contextService = inject(ContextService);
  const auth = inject(AuthV5Service);

  // First check if user is authenticated
  if (!auth.isAuthenticated()) {
    router.navigate(['/auth/login']);
    return false;
  }

  // Check if school is selected
  if (!contextService.hasSchoolSelected()) {
    router.navigate(['/select-school']);
    return false;
  }

  // Check if complete context exists
  if (!contextService.hasCompleteContext()) {
    router.navigate(['/select-season']);
    return false;
  }

  return true;
};