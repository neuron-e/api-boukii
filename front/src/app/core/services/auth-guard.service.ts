import { Injectable, inject } from '@angular/core';
import { SessionService } from './session.service';
import { ContextService } from './context.service';

/**
 * Helper service providing authentication checks for route guards
 * without performing any HTTP requests.
 */
@Injectable({ providedIn: 'root' })
export class AuthGuardService {
  private readonly session = inject(SessionService);
  private readonly context = inject(ContextService);

  /**
   * Determine if a valid auth token exists.
   */
  isAuthenticated(): boolean {
    return !!localStorage.getItem('boukii_auth_token');
  }

  /**
   * Retrieve current authentication context if available.
   */
  getAuthContext(): { school_id: number; season_id: number } | null {
    const context = this.context.context();
    if (context.schoolId !== null && context.seasonId !== null) {
      return { school_id: context.schoolId, season_id: context.seasonId };
    }
    return null;
  }

  /**
   * Check if any of the required permissions are present in storage.
   */
  hasAnyPermission(required: string[]): boolean {
    const stored = localStorage.getItem('boukii_permissions');
    const permissions: string[] = stored ? JSON.parse(stored) : [];
    return required.some(p => permissions.includes(p));
  }
}
