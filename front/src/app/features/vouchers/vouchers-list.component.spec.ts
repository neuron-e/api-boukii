import { ComponentFixture, TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { MatDialog } from '@angular/material/dialog';
import { VouchersListComponent } from './vouchers-list.component';

describe('VouchersListComponent', () => {
  let component: VouchersListComponent;
  let fixture: ComponentFixture<VouchersListComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [VouchersListComponent],
      providers: [
        {
          provide: MatDialog,
          useValue: { open: () => ({ afterClosed: () => of(undefined) }) },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(VouchersListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create with 8 vouchers', () => {
    expect(component).toBeTruthy();
    expect(component.vouchers.length).toBe(8);
  });

  it('should add voucher', () => {
    const initial = component.vouchers.length;
    component.addVoucher({ type: 'gift', value: 25, expiration: '2025-06-01' });
    expect(component.vouchers.length).toBe(initial + 1);
    expect(component.vouchers[initial].type).toBe('gift');
  });
});

