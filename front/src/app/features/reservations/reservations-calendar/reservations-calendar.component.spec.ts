import { ComponentFixture, TestBed } from '@angular/core/testing';
import { expect } from '@jest/globals';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { ReservationsCalendarComponent } from './reservations-calendar.component';

describe('ReservationsCalendarComponent', () => {
  let component: ReservationsCalendarComponent;
  let fixture: ComponentFixture<ReservationsCalendarComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReservationsCalendarComponent, NoopAnimationsModule]
    }).compileComponents();

    fixture = TestBed.createComponent(ReservationsCalendarComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});

