import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { School } from './context.service';

@Injectable({
  providedIn: 'root'
})
export class SessionService {

  /**
   * BehaviorSubject that emits the current school or null when none selected
   */
  readonly currentSchool$ = new BehaviorSubject<School | null>(null);

  /**
   * Select a school and persist it
   */
  selectSchool(school: School): void {
    this.currentSchool$.next(school);
    localStorage.setItem('boukiiSchoolId', school.id.toString());
  }

  /**
   * Load stored school from localStorage and initialize the BehaviorSubject
   */
  async loadStoredSchool(): Promise<void> {
    const storedId = localStorage.getItem('boukiiSchoolId');
    if (storedId) {
      this.currentSchool$.next({ id: Number(storedId) } as School);
    }
  }
}
