import { ComponentFixture, TestBed } from '@angular/core/testing';
import { expect } from '@jest/globals';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { RouterTestingModule } from '@angular/router/testing';
import { MatDialogModule } from '@angular/material/dialog';
import { ReservationsListComponent } from './reservations-list.component';
import { Reservation } from '../reservations-mock.service';

describe('ReservationsListComponent', () => {
  let component: ReservationsListComponent;
  let fixture: ComponentFixture<ReservationsListComponent>;

  const mockReservations: Reservation[] = [
    {
      id: 1,
      client: 'Test User',
      course: 'Yoga',
      date: '2025-08-20',
      status: 'confirmed',
      type: 'course',
      price: 20,
      monitor: 'Ana'
    }
  ];

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReservationsListComponent, NoopAnimationsModule, RouterTestingModule, MatDialogModule]
    }).compileComponents();

    fixture = TestBed.createComponent(ReservationsListComponent);
    component = fixture.componentInstance;
    component.reservations = mockReservations;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should render reservation cards', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelectorAll('.reservation-card').length).toBe(1);
  });
});

