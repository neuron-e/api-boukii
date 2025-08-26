import { CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthV5Service } from '../services/auth-v5.service';
import { LoggingService } from '../services/logging.service';

/**
 * Auth Guard V5 - Protects routes that require authentication
 * Redirects to login if user is not authenticated
 */
export const authV5Guard: CanActivateFn = (route, state) => {
  const authV5 = inject(AuthV5Service);
  const router = inject(Router);
  const logger = inject(LoggingService);

  const isAuthenticated = authV5.isAuthenticated();
  
  // Special case: Allow access to school/season selection during multi-step auth flow
  if (!isAuthenticated) {
    const tempToken = localStorage.getItem('boukii_temp_token');
    const isSchoolSelection = state.url.includes('/select-school');
    const isSeasonSelection = state.url.includes('/select-season');
    
    if (tempToken && (isSchoolSelection || isSeasonSelection)) {
      logger.logInfo('AuthV5Guard: Access granted for selection flow with temp token', {
        route: state.url,
        hasTemp: !!tempToken
      });
      return true;
    }
    
    logger.logInfo('AuthV5Guard: Access denied - not authenticated', {
      route: state.url
    });
    
    router.navigate(['/auth/login'], {
      queryParams: { returnUrl: state.url }
    });
    
    return false;
  }

  logger.logInfo('AuthV5Guard: Access granted', {
    route: state.url,
    userId: authV5.user()?.id
  });

  return true;
};

/**
 * Season Context Guard - Ensures user has school/season context
 * Redirects to school/season selection if context is missing
 */
export const seasonContextGuard: CanActivateFn = (_route, _state) => {
  const authV5 = inject(AuthV5Service);
  const router = inject(Router);
  const logger = inject(LoggingService);

  // First check if authenticated
  if (!authV5.isAuthenticated()) {
    logger.logInfo('SeasonContextGuard: Access denied - not authenticated');
    router.navigate(['/auth/login']);
    return false;
  }

  const context = authV5.getAuthContext();
  
  if (!context) {
    const schools = authV5.schools();
    
    if (schools.length === 0) {
      logger.logWarning('SeasonContextGuard: User has no schools assigned');
      router.navigate(['/no-access']);
      return false;
    }

    if (schools.length === 1) {
      // Auto-select single school and redirect
      logger.logInfo('SeasonContextGuard: Auto-selecting single school');
      authV5.setCurrentSchool(schools[0].id).subscribe();
      return false; // Let the service handle navigation
    }

    // Multiple schools - redirect to selector
    logger.logInfo('SeasonContextGuard: Redirecting to school selection');
    router.navigate(['/select-school']);
    return false;
  }

  logger.logInfo('SeasonContextGuard: Context available', {
    schoolId: context.school_id,
    seasonId: context.season_id
  });

  return true;
};

/**
 * Permission Guard Factory - Creates a guard that checks for specific permissions
 */
export const createPermissionGuard = (requiredPermissions: string[]): CanActivateFn => 
  (_route, _state) => {
    const authV5 = inject(AuthV5Service);
    const router = inject(Router);
    const logger = inject(LoggingService);

    if (!authV5.isAuthenticated()) {
      router.navigate(['/auth/login']);
      return false;
    }

    const hasPermission = authV5.hasAnyPermission(requiredPermissions);
    
    if (!hasPermission) {
      logger.logWarning('PermissionGuard: Access denied - insufficient permissions', {
        required: requiredPermissions,
        userPermissions: authV5.permissions()
      });
      
      router.navigate(['/unauthorized']);
      return false;
    }

    return true;
  };