import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SettingsService } from './settings.service';

@Component({
  selector: 'app-school-settings',
  standalone: true,
  imports: [CommonModule],
  template: `
    <h2>School Settings</h2>
    <ul>
      <li *ngFor="let setting of settings$ | async">{{ setting.name }}: {{ setting.value }}</li>
    </ul>
  `
})
export class SchoolSettingsComponent {
  private readonly settingsService = inject(SettingsService);
  readonly settings$ = this.settingsService.getSchoolSettings();
}
