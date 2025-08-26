import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SettingsService } from './settings.service';

@Component({
  selector: 'app-sports-degrees',
  standalone: true,
  imports: [CommonModule],
  template: `
    <h2>Sports Degrees</h2>
    <ul>
      <li *ngFor="let degree of degrees$ | async">{{ degree.name }}</li>
    </ul>
  `
})
export class SportsDegreesComponent {
  private readonly settingsService = inject(SettingsService);
  readonly degrees$ = this.settingsService.getSportsDegrees();
}
