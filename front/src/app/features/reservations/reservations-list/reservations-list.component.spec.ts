import { ComponentFixture, TestBed } from '@angular/core/testing';
import { expect } from '@jest/globals';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { ReservationsListComponent } from './reservations-list.component';
import { ReservationsMockService } from '../reservations-mock.service';

describe('ReservationsListComponent', () => {
  let component: ReservationsListComponent;
  let fixture: ComponentFixture<ReservationsListComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReservationsListComponent, NoopAnimationsModule],
      providers: [ReservationsMockService]
    }).compileComponents();

    fixture = TestBed.createComponent(ReservationsListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should filter by type and payment', () => {
    component.typeFilter = 'individual';
    component.paidFilter = 'paid';
    expect(component.filteredReservations.every(r => r.type === 'individual' && r.paid)).toBe(true);
  });

  it('should search by client name', () => {
    component.search = 'alice';
    expect(component.filteredReservations.every(r => r.client.toLowerCase().includes('alice') || r.course.toLowerCase().includes('alice'))).toBe(true);
  });
});

