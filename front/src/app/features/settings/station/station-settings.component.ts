import { CommonModule } from '@angular/common';
import { Component, OnInit, inject } from '@angular/core';
import { SettingsService } from '../settings.service';
import { Station } from '../data/stations';

@Component({
  selector: 'app-station-settings',
  standalone: true,
  imports: [CommonModule],
  template: `
    <h2>Estaciones</h2>
    <div *ngFor="let station of stations">
      <label>
        <input
          type="checkbox"
          [checked]="selectedStationIds.includes(station.id)"
          (change)="onToggleStation(station.id, $event.target.checked)"
        />
        {{ station.name }}
      </label>
    </div>
    <button (click)="save()">Guardar</button>
  `
})
export class StationSettingsComponent implements OnInit {
  private readonly settingsService = inject(SettingsService);
  stations: Station[] = [];
  selectedStationIds: number[] = [];

  ngOnInit(): void {
    this.settingsService.getMockStations().subscribe(({ stations, selectedStationIds }) => {
      this.stations = stations;
      this.selectedStationIds = [...selectedStationIds];
    });
  }

  onToggleStation(id: number, checked: boolean): void {
    if (checked) {
      if (!this.selectedStationIds.includes(id)) {
        this.selectedStationIds.push(id);
      }
    } else {
      this.selectedStationIds = this.selectedStationIds.filter(s => s !== id);
    }
  }

  save(): void {
    this.settingsService.saveSelectedStations(this.selectedStationIds);
  }
}
