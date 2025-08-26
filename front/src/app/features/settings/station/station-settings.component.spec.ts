import { TestBed } from '@angular/core/testing';
import { StationSettingsComponent } from './station-settings.component';
import { SettingsService } from '../settings.service';
import { of } from 'rxjs';

describe('StationSettingsComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StationSettingsComponent],
      providers: [
        {
          provide: SettingsService,
          useValue: {
            getMockStations: () => of({ stations: [], selectedStationIds: [] }),
            saveSelectedStations: jest.fn(),
          },
        },
      ],
    }).compileComponents();
  });

  it('should create and render title and button', () => {
    const fixture = TestBed.createComponent(StationSettingsComponent);
    fixture.detectChanges();
    const h2: HTMLElement = fixture.nativeElement.querySelector('h2');
    const button: HTMLButtonElement = fixture.nativeElement.querySelector('button');
    expect(h2.textContent).toContain('Estaciones');
    expect(button.textContent).toContain('Guardar');
  });
});
