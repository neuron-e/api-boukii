import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { expect } from '@jest/globals';
import { MatStepper } from '@angular/material/stepper';

import { ReservationFormComponent } from './reservation-form.component';

describe('ReservationFormComponent', () => {
  let fixture: ComponentFixture<ReservationFormComponent>;
  let component: ReservationFormComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReservationFormComponent, NoopAnimationsModule]
    }).compileComponents();

    fixture = TestBed.createComponent(ReservationFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should move forward and backward through steps', () => {
    component.clientGroup.setValue({ client: 1 });
    fixture.detectChanges();
    const stepper = fixture.debugElement
      .query(By.css('mat-horizontal-stepper'))
      .componentInstance as MatStepper;
    expect(stepper.selectedIndex).toBe(0);
    stepper.next();
    fixture.detectChanges();
    expect(stepper.selectedIndex).toBe(1);
    stepper.previous();
    fixture.detectChanges();
    expect(stepper.selectedIndex).toBe(0);
  });

  it('should show summary with mock data after confirmation', () => {
    component.clientGroup.setValue({ client: 1 });
    component.addParticipant();
    component.addParticipant();
    component.participants.at(0).setValue('John');
    component.participants.at(1).setValue('Jane');
    component.courseGroup.setValue({ sport: 'Tennis', course: 1 });
    component.dateExtrasGroup.setValue({ date: new Date('2025-01-01'), extras: [1, 2] });

    component.confirm();
    fixture.detectChanges();

    const summaryText = fixture.nativeElement.querySelector('.summary').textContent;
    expect(summaryText).toContain('Alice Johnson');
    expect(summaryText).toContain('John, Jane');
    expect(summaryText).toContain('Tennis');
    expect(summaryText).toContain('Beginner');
    expect(summaryText).toContain('Equipment rental');
    expect(summaryText).toContain('Locker');
    expect(summaryText).toContain('65');
  });
});

