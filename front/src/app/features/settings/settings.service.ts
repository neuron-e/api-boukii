import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { SCHOOL_SETTINGS, SchoolSetting } from './data/school-settings';
import { MOCK_SEASONS, Season } from './data/seasons';
import { SPORTS_DEGREES, SportsDegree } from './data/sports-degrees';
import { STATION_SETTINGS, StationSetting } from './data/station-settings';

export interface Sport {
  id: number;
  name: string;
}

const MOCK_SPORTS: Sport[] = [
  { id: 1, name: 'Surf' },
  { id: 2, name: 'Kitesurf' },
  { id: 3, name: 'Windsurf' }
];

const MOCK_SELECTED_SPORT_IDS: number[] = [1, 3];

interface School {
  name: string;
  email: string;
  contact_phone: string;
  address: string;
  logo: string;
}

const MOCK_SCHOOL: School = {
  name: 'Surf School',
  email: 'info@surfschool.com',
  contact_phone: '+1 234 567 890',
  address: 'Beach Avenue 123',
  logo: 'assets/logo.png'
};

@Injectable({ providedIn: 'root' })
export class SettingsService {
  private selectedSportsIds = [...MOCK_SELECTED_SPORT_IDS];

  getAllSports(): Observable<Sport[]> {
    return of(MOCK_SPORTS);
  }

  getSelectedSportsIds(): Observable<number[]> {
    return of(this.selectedSportsIds);
  }

  saveSelectedSports(ids: number[]): void {
    this.selectedSportsIds = ids;
    console.log('Selected sports saved', ids);
  }

  getSchoolSettings(): Observable<SchoolSetting[]> {
    return of(SCHOOL_SETTINGS);
  }

  getMockSeasons(): Observable<Season[]> {
    return of(MOCK_SEASONS);
  }

  getMockDegrees(): Observable<SportsDegree[]> {
    return of(SPORTS_DEGREES);
  }

  getStationSettings(): Observable<StationSetting[]> {
    return of(STATION_SETTINGS);
  }

  getMockSchool(): Observable<School> {
    return of(MOCK_SCHOOL);
  }
}
