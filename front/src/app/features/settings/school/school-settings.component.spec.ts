import { TestBed } from '@angular/core/testing';
import { SchoolSettingsComponent } from './school-settings.component';
import { SettingsService } from '../settings.service';
import { TranslatePipe } from '@shared/pipes/translate.pipe';
import { Pipe, PipeTransform } from '@angular/core';
import { of } from 'rxjs';

@Pipe({ name: 'translate' })
class TranslatePipeStub implements PipeTransform {
  transform(value: string): string {
    return value;
  }
}

describe('SchoolSettingsComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SchoolSettingsComponent],
      providers: [
        { provide: SettingsService, useValue: { getMockSchool: () => of({ name: '', email: '', contact_phone: '', address: '', logo: '' }) } },
        { provide: TranslatePipe, useClass: TranslatePipeStub },
      ],
    }).compileComponents();
  });

  it('should create and render title and save button', () => {
    const fixture = TestBed.createComponent(SchoolSettingsComponent);
    fixture.detectChanges();
    const h2: HTMLElement = fixture.nativeElement.querySelector('h2');
    const button: HTMLButtonElement = fixture.nativeElement.querySelector('button');
    expect(h2.textContent).toContain('settings.school.title');
    expect(button.textContent).toContain('common.save');
  });
});
