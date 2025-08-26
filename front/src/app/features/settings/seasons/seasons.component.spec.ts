import { TestBed } from '@angular/core/testing';
import { SeasonsComponent } from './seasons.component';
import { SettingsService } from '../settings.service';
import { of } from 'rxjs';

describe('SeasonsComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SeasonsComponent],
      providers: [
        {
          provide: SettingsService,
          useValue: {
            getMockSeasons: () => of([]),
          },
        },
      ],
    }).compileComponents();
  });

  it('should create and render title and new button', () => {
    const fixture = TestBed.createComponent(SeasonsComponent);
    fixture.detectChanges();
    const h2: HTMLElement = fixture.nativeElement.querySelector('h2');
    const button: HTMLButtonElement = fixture.nativeElement.querySelector('button');
    expect(h2.textContent).toContain('Temporadas');
    expect(button.textContent).toContain('Nueva Temporada');
  });
});
