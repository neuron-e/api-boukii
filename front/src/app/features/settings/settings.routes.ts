import { Routes } from '@angular/router';
import { SchoolSettingsComponent } from './school/school-settings.component';
import { SeasonsComponent } from './seasons/seasons.component';
import { SportsDegreesComponent } from './sports-degrees/sports-degrees.component';
import { StationSettingsComponent } from './station/station-settings.component';

export const routes: Routes = [
  { path: 'school', component: SchoolSettingsComponent },
  { path: 'seasons', component: SeasonsComponent },
  { path: 'sports-degrees', component: SportsDegreesComponent },
  { path: 'station', component: StationSettingsComponent }
];
