import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';

export interface CreateVoucherFormData {
  type: 'purchase' | 'gift' | 'discount';
  value: number;
  expiration: string;
}

@Component({
  selector: 'app-create-voucher-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
  ],
  template: `
    <h2 mat-dialog-title>Create Voucher</h2>
    <form [formGroup]="form" (ngSubmit)="submit()" mat-dialog-content class="dialog-content">
      <mat-form-field>
        <mat-label>Type</mat-label>
        <mat-select formControlName="type" required>
          <mat-option value="purchase">Purchase</mat-option>
          <mat-option value="gift">Gift</mat-option>
          <mat-option value="discount">Discount</mat-option>
        </mat-select>
      </mat-form-field>
      <mat-form-field>
        <mat-label>Value</mat-label>
        <input matInput type="number" formControlName="value" required />
      </mat-form-field>
      <mat-form-field>
        <mat-label>Expiration</mat-label>
        <input matInput type="date" formControlName="expiration" required />
      </mat-form-field>
      <div mat-dialog-actions align="end">
        <button mat-button type="button" mat-dialog-close>Cancel</button>
        <button mat-raised-button color="primary" type="submit" [disabled]="form.invalid">Create</button>
      </div>
    </form>
  `,
  styles: [
    `.dialog-content { display: flex; flex-direction: column; gap: var(--space-4); color: var(--text-1); }`
  ],
})
export class CreateVoucherDialogComponent {
  form = this.fb.group({
    type: ['purchase', Validators.required],
    value: [0, Validators.required],
    expiration: ['', Validators.required],
  });

  constructor(private fb: FormBuilder, private dialogRef: MatDialogRef<CreateVoucherDialogComponent>) {}

  submit(): void {
    if (this.form.valid) {
      this.dialogRef.close(this.form.value as CreateVoucherFormData);
    }
  }
}

