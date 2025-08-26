import { Routes } from '@angular/router';
import { SchoolSettingsComponent } from './school-settings.component';
import { SeasonsComponent } from './seasons.component';
import { SportsDegreesComponent } from './sports-degrees.component';
import { StationSettingsComponent } from './station-settings.component';

export const routes: Routes = [
  { path: 'school', component: SchoolSettingsComponent },
  { path: 'seasons', component: SeasonsComponent },
  { path: 'sports-degrees', component: SportsDegreesComponent },
  { path: 'station', component: StationSettingsComponent }
];
