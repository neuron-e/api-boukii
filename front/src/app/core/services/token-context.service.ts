import { Injectable, inject } from '@angular/core';
import { SessionService } from './session.service';
import { ContextService } from './context.service';

/**
 * Lightweight service to provide authentication token and context IDs
 * without depending on ApiService.
 */
@Injectable({
  providedIn: 'root'
})
export class TokenContextService {
  private readonly sessionService = inject(SessionService);
  private readonly contextService = inject(ContextService);

  /**
   * Retrieve the stored authentication token.
   */
  getToken(): string | null {
    return localStorage.getItem('boukii_auth_token');
  }

  /**
   * Get current school and season IDs from context/session.
   */
  getAuthContext(): { school_id: number; season_id: number } | null {
    let schoolId = this.contextService.getSelectedSchoolId();
    if (schoolId === null) {
      const currentSchool = this.sessionService.currentSchool$.getValue();
      schoolId = currentSchool ? currentSchool.id : null;
    }
    const seasonId = this.contextService.getSelectedSeasonId();
    if (schoolId !== null && seasonId !== null) {
      return { school_id: schoolId, season_id: seasonId };
    }
    return null;
  }
}
