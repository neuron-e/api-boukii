import { ComponentFixture, TestBed } from '@angular/core/testing';
import { expect } from '@jest/globals';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { ReservationsDashboardComponent } from './reservations-dashboard.component';
import { ReservationsMockService } from './reservations-mock.service';
import { RouterTestingModule } from '@angular/router/testing';

describe('ReservationsDashboardComponent', () => {
  let component: ReservationsDashboardComponent;
  let fixture: ComponentFixture<ReservationsDashboardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReservationsDashboardComponent, NoopAnimationsModule, RouterTestingModule],
      providers: [ReservationsMockService]
    }).compileComponents();

    fixture = TestBed.createComponent(ReservationsDashboardComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});

