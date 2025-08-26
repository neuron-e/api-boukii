import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { SCHOOL_SETTINGS, SchoolSetting } from './data/school-settings';
import { SEASONS, Season } from './data/seasons';
import { SPORTS_DEGREES, SportsDegree } from './data/sports-degrees';
import { STATION_SETTINGS, StationSetting } from './data/station-settings';

@Injectable({ providedIn: 'root' })
export class SettingsService {
  getSchoolSettings(): Observable<SchoolSetting[]> {
    return of(SCHOOL_SETTINGS);
  }

  getSeasons(): Observable<Season[]> {
    return of(SEASONS);
  }

  getSportsDegrees(): Observable<SportsDegree[]> {
    return of(SPORTS_DEGREES);
  }

  getStationSettings(): Observable<StationSetting[]> {
    return of(STATION_SETTINGS);
  }
}
