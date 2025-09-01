import { ComponentFixture, TestBed } from '@angular/core/testing';

import { BookingDialogComponent } from './booking-dialog.component';

describe('BookingDialogComponent', () => {
  let component: BookingDialogComponent;
  let fixture: ComponentFixture<BookingDialogComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [BookingDialogComponent]
    });
    fixture = TestBed.createComponent(BookingDialogComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
