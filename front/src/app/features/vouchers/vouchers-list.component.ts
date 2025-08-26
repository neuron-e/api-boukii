import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { CreateVoucherDialogComponent, CreateVoucherFormData } from './create-voucher-dialog.component';

export interface Voucher {
  code: string;
  type: 'purchase' | 'gift' | 'discount';
  client: string;
  value: number;
  status: 'active' | 'used' | 'expired';
  expiration: string;
}

@Component({
  selector: 'app-vouchers-list',
  standalone: true,
  imports: [CommonModule, MatTableModule, MatButtonModule, MatDialogModule],
  templateUrl: './vouchers-list.component.html',
  styleUrls: ['./vouchers-list.component.scss'],
})
export class VouchersListComponent {
  vouchers: Voucher[] = [
    { code: 'VCH-001', type: 'purchase', client: 'Alice', value: 50, status: 'active', expiration: '2025-12-31' },
    { code: 'VCH-002', type: 'gift', client: 'Bob', value: 30, status: 'active', expiration: '2024-10-15' },
    { code: 'VCH-003', type: 'discount', client: 'Charlie', value: 15, status: 'used', expiration: '2024-08-01' },
    { code: 'VCH-004', type: 'purchase', client: 'Diana', value: 100, status: 'active', expiration: '2025-05-20' },
    { code: 'VCH-005', type: 'gift', client: 'Eve', value: 25, status: 'expired', expiration: '2023-12-31' },
    { code: 'VCH-006', type: 'discount', client: 'Frank', value: 20, status: 'active', expiration: '2024-09-30' },
    { code: 'VCH-007', type: 'purchase', client: 'Grace', value: 40, status: 'used', expiration: '2024-07-15' },
    { code: 'VCH-008', type: 'gift', client: 'Henry', value: 35, status: 'active', expiration: '2025-02-28' },
  ];

  displayedColumns = ['code', 'type', 'client', 'value', 'status'];

  constructor(private dialog: MatDialog) {}

  openCreateDialog(): void {
    const dialogRef = this.dialog.open(CreateVoucherDialogComponent);
    dialogRef.afterClosed().subscribe((data?: CreateVoucherFormData) => {
      if (data) {
        this.addVoucher(data);
      }
    });
  }

  addVoucher(data: CreateVoucherFormData): void {
    const newVoucher: Voucher = {
      code: `VCH-${(this.vouchers.length + 1).toString().padStart(3, '0')}`,
      type: data.type,
      value: data.value,
      expiration: data.expiration,
      client: 'New Client',
      status: 'active',
    };
    this.vouchers = [...this.vouchers, newVoucher];
  }
}

