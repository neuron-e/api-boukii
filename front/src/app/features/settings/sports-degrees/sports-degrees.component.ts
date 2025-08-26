import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SettingsService, Sport } from '../settings.service';
import { SportsDegree } from '../data/sports-degrees';

@Component({
  selector: 'app-sports-degrees',
  standalone: true,
  imports: [CommonModule],
  template: `
    <h2>Sports</h2>
    <div *ngFor="let sport of allSports">
      <label>
        <input
          type="checkbox"
          [checked]="selectedSportsIds.includes(sport.id)"
          (change)="onToggleSport(sport.id, ($event.target as HTMLInputElement).checked)"
        />
        {{ sport.name }}
      </label>
    </div>

    <h3>Available Degrees</h3>
    <ul>
      <li *ngFor="let degree of degrees">{{ degree.name }}</li>
    </ul>

    <button (click)="save()">Guardar</button>
  `
})
export class SportsDegreesComponent implements OnInit {
  private readonly settingsService = inject(SettingsService);

  allSports: Sport[] = [];
  selectedSportsIds: number[] = [];
  degrees: SportsDegree[] = [];

  ngOnInit(): void {
    this.settingsService.getAllSports().subscribe(sports => (this.allSports = sports));
    this.settingsService
      .getSelectedSportsIds()
      .subscribe(ids => (this.selectedSportsIds = [...ids]));
    this.settingsService.getMockDegrees().subscribe(deg => (this.degrees = deg));
  }

  onToggleSport(id: number, checked: boolean): void {
    if (checked) {
      if (!this.selectedSportsIds.includes(id)) {
        this.selectedSportsIds.push(id);
      }
    } else {
      this.selectedSportsIds = this.selectedSportsIds.filter(s => s !== id);
    }
  }

  save(): void {
    this.settingsService.saveSelectedSports(this.selectedSportsIds);
  }
}
