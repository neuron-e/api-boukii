import { TestBed } from '@angular/core/testing';
import { SportsDegreesComponent } from './sports-degrees.component';
import { SettingsService } from '../settings.service';
import { of } from 'rxjs';

describe('SportsDegreesComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SportsDegreesComponent],
      providers: [
        {
          provide: SettingsService,
          useValue: {
            getAllSports: () => of([]),
            getSelectedSportsIds: () => of([]),
            getMockDegrees: () => of([]),
            saveSelectedSports: jest.fn(),
          },
        },
      ],
    }).compileComponents();
  });

  it('should create and render titles and button', () => {
    const fixture = TestBed.createComponent(SportsDegreesComponent);
    fixture.detectChanges();
    const h2: HTMLElement = fixture.nativeElement.querySelector('h2');
    const h3: HTMLElement = fixture.nativeElement.querySelector('h3');
    const button: HTMLButtonElement = fixture.nativeElement.querySelector('button');
    expect(h2.textContent).toContain('Sports');
    expect(h3.textContent).toContain('Available Degrees');
    expect(button.textContent).toContain('Guardar');
  });
});
