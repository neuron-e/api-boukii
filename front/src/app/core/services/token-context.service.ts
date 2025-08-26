import { Injectable, inject } from '@angular/core';
import { SessionService } from './session.service';

/**
 * Lightweight service to provide authentication token and context IDs
 * without depending on ApiService.
 */
@Injectable({
  providedIn: 'root'
})
export class TokenContextService {
  private readonly sessionService = inject(SessionService);

  /**
   * Retrieve the stored authentication token.
   */
  getToken(): string | null {
    return localStorage.getItem('boukii_auth_token');
  }

  /**
   * Get current school and season IDs from localStorage.
   */
  getAuthContext(): { school_id: number; season_id: number } | null {
    const schoolId = localStorage.getItem('context_schoolId');
    const seasonId = localStorage.getItem('context_seasonId');
    
    if (schoolId && seasonId) {
      return { 
        school_id: parseInt(schoolId, 10), 
        season_id: parseInt(seasonId, 10) 
      };
    }
    
    // Fallback to session service
    const currentSchool = this.sessionService.currentSchool$.getValue();
    
    if (currentSchool) {
      return {
        school_id: currentSchool.id,
        season_id: currentSchool.id // Temporary fallback
      };
    }
    
    return null;
  }
}
