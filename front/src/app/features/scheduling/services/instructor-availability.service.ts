import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';

export interface Instructor {
  id: number;
  name: string;
  avatar: string;
  available: boolean;
}

@Injectable({ providedIn: 'root' })
export class InstructorAvailabilityService {
  getInstructors(): Observable<Instructor[]> {
    // Placeholder data until API integration
    return of([
      {
        id: 1,
        name: 'Alice Johnson',
        avatar: 'https://via.placeholder.com/32',
        available: true,
      },
      {
        id: 2,
        name: 'Bob Smith',
        avatar: 'https://via.placeholder.com/32',
        available: false,
      },
      {
        id: 3,
        name: 'Charlie Brown',
        avatar: 'https://via.placeholder.com/32',
        available: true,
      },
    ]);
  }
}

