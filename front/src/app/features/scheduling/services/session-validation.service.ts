import { Injectable } from '@angular/core';

@Injectable({ providedIn: 'root' })
export class SessionValidationService {
  /**
   * Placeholder logic to determine availability.
   * Sessions starting before noon are considered available,
   * others are flagged as conflicts.
   */
  checkAvailability(start: Date, end: Date): 'confirmed' | 'conflict' {
    return start.getHours() < 12 ? 'confirmed' : 'conflict';
  }
}
