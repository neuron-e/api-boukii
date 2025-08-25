import { Injectable, inject } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { School } from './context.service';
import { SchoolService } from './school.service';

@Injectable({
  providedIn: 'root'
})
export class SessionService {
  private readonly schoolService = inject(SchoolService);

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
      try {
        const school = await firstValueFrom(this.schoolService.getSchoolById(Number(storedId)));
        this.currentSchool$.next(school);
      } catch {
        this.currentSchool$.next(null);
      }
    }
  }
}
