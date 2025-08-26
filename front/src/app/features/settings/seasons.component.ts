import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SettingsService } from './settings.service';

@Component({
  selector: 'app-seasons',
  standalone: true,
  imports: [CommonModule],
  template: `
    <h2>Seasons</h2>
    <ul>
      <li *ngFor="let season of seasons$ | async">{{ season.name }} - {{ season.year }}</li>
    </ul>
  `
})
export class SeasonsComponent {
  private readonly settingsService = inject(SettingsService);
  readonly seasons$ = this.settingsService.getSeasons();
}
