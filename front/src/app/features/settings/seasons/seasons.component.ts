import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SettingsService } from '../settings.service';
import { SeasonFormComponent } from './season-form.component';
import { Season } from '../data/seasons';

@Component({
  selector: 'app-seasons',
  standalone: true,
  imports: [CommonModule, SeasonFormComponent],
  template: `
    <h2>Temporadas</h2>
    <button (click)="openNew()">Nueva Temporada</button>
    <table *ngIf="seasons().length > 0">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Fecha Inicio</th>
          <th>Fecha Fin</th>
          <th>Activa</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <tr *ngFor="let season of seasons()">
          <td>{{ season.name }}</td>
          <td>{{ season.start_date }}</td>
          <td>{{ season.end_date }}</td>
          <td>
            <input type="checkbox" [checked]="season.is_active" disabled />
          </td>
          <td>
            <button (click)="editSeason(season)">Editar</button>
            <button (click)="activateSeason(season)" [disabled]="season.is_active">Activar</button>
            <button (click)="deactivateSeason(season)" [disabled]="!season.is_active">Desactivar</button>
          </td>
        </tr>
      </tbody>
    </table>
    <app-season-form
      *ngIf="formOpen()"
      [season]="selectedSeason()"
      (save)="saveSeason($event)"
      (cancel)="closeForm()"
    ></app-season-form>
  `
})
export class SeasonsComponent {
  private readonly settingsService = inject(SettingsService);

  seasons = signal<Season[]>([]);
  formOpen = signal(false);
  selectedSeason = signal<Season | null>(null);
  private nextId = 1;

  constructor() {
    this.settingsService.getMockSeasons().subscribe((data) => {
      this.seasons.set(data);
      this.nextId = data.length > 0 ? Math.max(...data.map((s) => s.id)) + 1 : 1;
    });
  }

  openNew(): void {
    this.selectedSeason.set(null);
    this.formOpen.set(true);
  }

  editSeason(season: Season): void {
    this.selectedSeason.set({ ...season });
    this.formOpen.set(true);
  }

  closeForm(): void {
    this.formOpen.set(false);
  }

  saveSeason(season: Season): void {
    const list = this.seasons();
    if (season.id) {
      const index = list.findIndex((s) => s.id === season.id);
      if (index > -1) {
        list[index] = season;
      }
    } else {
      season.id = this.nextId++;
      list.push(season);
    }

    let updated = list;
    if (season.is_active) {
      updated = list.map((s) => ({ ...s, is_active: s.id === season.id }));
    }
    this.seasons.set([...updated]);
    this.closeForm();
  }

  activateSeason(season: Season): void {
    this.seasons.update((list) => list.map((s) => ({ ...s, is_active: s.id === season.id })));
  }

  deactivateSeason(season: Season): void {
    this.seasons.update((list) => list.map((s) => (s.id === season.id ? { ...s, is_active: false } : s)));
  }
}
