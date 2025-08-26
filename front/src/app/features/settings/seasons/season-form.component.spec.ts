import { TestBed } from '@angular/core/testing';
import { SeasonFormComponent } from './season-form.component';

describe('SeasonFormComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SeasonFormComponent],
    }).compileComponents();
  });

  it('should create and render title and save button', () => {
    const fixture = TestBed.createComponent(SeasonFormComponent);
    fixture.detectChanges();
    const h2: HTMLElement = fixture.nativeElement.querySelector('h2');
    const button: HTMLButtonElement = fixture.nativeElement.querySelector('button[type="submit"]');
    expect(h2.textContent).toContain('Nueva Temporada');
    expect(button.textContent).toContain('Guardar');
  });
});
