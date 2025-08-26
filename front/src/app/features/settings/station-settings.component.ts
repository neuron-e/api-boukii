import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SettingsService } from './settings.service';

@Component({
  selector: 'app-station-settings',
  standalone: true,
  imports: [CommonModule],
  template: `
    <h2>Station Settings</h2>
    <ul>
      <li *ngFor="let setting of settings$ | async">{{ setting.key }}: {{ setting.value }}</li>
    </ul>
  `
})
export class StationSettingsComponent {
  private readonly settingsService = inject(SettingsService);
  readonly settings$ = this.settingsService.getStationSettings();
}
