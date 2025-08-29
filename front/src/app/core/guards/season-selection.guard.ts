import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, of, switchMap } from 'rxjs';
import { AuthV5Service } from '@core/services/auth-v5.service';
import { ContextService } from '@core/services/context.service';

/**
 * Guard for /select-season route
 * Logic:
 * - Requires user to be authenticated and have school selected
 * - If user has 1 active season -> auto-select and redirect to dashboard
 * - If user has >1 active seasons -> allow access to select-season page
 * - If user has 0 active seasons -> redirect to dashboard with warning
 */
export const seasonSelectionGuard: CanActivateFn = () => {
  const auth = inject(AuthV5Service);
  const router = inject(Router);
  const contextService = inject(ContextService);

  // First check if user is authenticated
  if (!auth.isAuthenticated()) {
    console.log('📅 SeasonSelectionGuard: Not authenticated, redirecting to login');
    router.navigate(['/auth/login']);
    return false;
  }

  // Check if user has selected a school
  const schoolId = contextService.getSelectedSchoolId();
  if (!schoolId) {
    console.log('📅 SeasonSelectionGuard: No school selected, redirecting to school selection');
    router.navigate(['/select-school']);
    return false;
  }

  // Check if user already has complete context
  if (contextService.hasCompleteContext()) {
    console.log('📅 SeasonSelectionGuard: Complete context exists, redirecting to dashboard');
    router.navigate(['/dashboard']);
    return false;
  }

  // Get seasons for the selected school
  return auth.getSeasons(schoolId).pipe(
    switchMap(async (seasons) => {
      console.log('📅 SeasonSelectionGuard: Found seasons:', seasons?.length || 0);

      if (!seasons || seasons.length === 0) {
        // No seasons available - redirect to dashboard with message
        console.log('📅 SeasonSelectionGuard: No seasons found, redirecting to dashboard');
        router.navigate(['/dashboard'], { 
          queryParams: { warning: 'no-seasons' } 
        });
        return false;
      }

      // Filter active seasons
      const activeSeasons = seasons.filter(s => s.is_active !== false && s.active !== false);
      
      if (activeSeasons.length === 0) {
        // No active seasons - redirect to dashboard with message
        console.log('📅 SeasonSelectionGuard: No active seasons found, redirecting to dashboard');
        router.navigate(['/dashboard'], { 
          queryParams: { warning: 'no-active-seasons' } 
        });
        return false;
      }

      if (activeSeasons.length === 1) {
        // Only one active season - auto-select
        console.log('📅 SeasonSelectionGuard: Single active season found, auto-selecting');
        try {
          // Use the auth service to select the season
          const season = activeSeasons[0];
          // Ensure AuthV5Service has the same school context as ContextService
          await auth.setCurrentSchool(schoolId).toPromise();
          await auth.selectSeason(season.id).toPromise();
          
          // Update context
          contextService.setSelectedSeason({
            id: season.id,
            name: season.name,
            slug: season.slug,
            is_current: season.is_current
          });

          router.navigate(['/dashboard']);
          return false;
        } catch (error) {
          console.error('📅 SeasonSelectionGuard: Failed to auto-select season:', error);
          // Continue to season selection page if auto-selection fails
          return true;
        }
      }

      // Multiple active seasons - show selection page
      console.log('📅 SeasonSelectionGuard: Multiple active seasons found, showing selection page');
      return true;
    }),
    catchError((error) => {
      console.error('📅 SeasonSelectionGuard: Error loading seasons:', error);
      
      // On API error, still show the selection page
      // This allows user to see error states and retry
      return of(true);
    })
  );
};

/**
 * Guard that ensures user has selected a season before accessing certain routes
 */
export const requireSeasonGuard: CanActivateFn = () => {
  const router = inject(Router);
  const contextService = inject(ContextService);
  const auth = inject(AuthV5Service);

  // First check if user is authenticated
  if (!auth.isAuthenticated()) {
    console.log('📅 RequireSeasonGuard: Not authenticated, redirecting to login');
    router.navigate(['/auth/login']);
    return false;
  }

  // Check if school is selected
  if (!contextService.hasSchoolSelected()) {
    console.log('📅 RequireSeasonGuard: No school selected, redirecting to school selection');
    router.navigate(['/select-school']);
    return false;
  }

  // Check if season is selected
  if (!contextService.hasCompleteContext()) {
    console.log('📅 RequireSeasonGuard: No season selected, redirecting to season selection');
    router.navigate(['/select-season']);
    return false;
  }

  return true;
};

/**
 * Combined guard that ensures complete authentication context
 * Use this for routes that require full auth + school + season context
 */
export const requireCompleteAuthGuard: CanActivateFn = () => {
  const router = inject(Router);
  const contextService = inject(ContextService);
  const auth = inject(AuthV5Service);

  console.log('🔐 RequireCompleteAuthGuard: Checking complete authentication context');

  // Step 1: Check authentication
  if (!auth.isAuthenticated()) {
    console.log('🔐 RequireCompleteAuthGuard: Not authenticated, redirecting to login');
    router.navigate(['/auth/login']);
    return false;
  }

  // Step 2: Check school selection
  if (!contextService.hasSchoolSelected()) {
    console.log('🔐 RequireCompleteAuthGuard: No school selected, redirecting to school selection');
    router.navigate(['/select-school']);
    return false;
  }

  // Step 3: Check season selection
  if (!contextService.hasCompleteContext()) {
    console.log('🔐 RequireCompleteAuthGuard: No season selected, redirecting to season selection');
    router.navigate(['/select-season']);
    return false;
  }

  console.log('🔐 RequireCompleteAuthGuard: Complete context validated, allowing access');
  return true;
};
