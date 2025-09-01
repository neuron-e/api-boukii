import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { MatDialogModule, MatDialogRef } from '@angular/material/dialog';

export interface CreateClientFormData {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
}

@Component({
  selector: 'app-create-client-dialog',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, MatDialogModule],
  template: `
    <h2 mat-dialog-title>Nuevo cliente</h2>
    <form [formGroup]="form" (ngSubmit)="submit()" mat-dialog-content class="dialog-content">
      <div class="field col">
        <label for="first_name">Nombre</label>
        <input id="first_name" type="text" formControlName="first_name" [class.is-invalid]="submitted && form.get('first_name')?.invalid" />
        <small class="error" *ngIf="submitted && form.get('first_name')?.invalid">El nombre es obligatorio</small>
      </div>
      <div class="field col">
        <label for="last_name">Apellidos</label>
        <input id="last_name" type="text" formControlName="last_name" [class.is-invalid]="submitted && form.get('last_name')?.invalid" />
        <small class="error" *ngIf="submitted && form.get('last_name')?.invalid">Los apellidos son obligatorios</small>
      </div>
      <div class="field col">
        <label for="email">Email</label>
        <input id="email" type="email" formControlName="email" [class.is-invalid]="submitted && form.get('email')?.invalid" />
        <small class="error" *ngIf="submitted && form.get('email')?.errors?.['required']">El email es obligatorio</small>
        <small class="error" *ngIf="submitted && form.get('email')?.errors?.['email']">Introduce un email válido</small>
      </div>
      <div class="field col">
        <label for="phone">Teléfono (opcional)</label>
        <input id="phone" type="tel" formControlName="phone" />
      </div>

      <div class="field col">
        <label for="status">Estado</label>
        <select id="status" formControlName="status">
          <option value="active">Activo</option>
          <option value="inactive">Inactivo</option>
          <option value="blocked">Bloqueado</option>
        </select>
      </div>
      <div class="field col">
        <label for="type">Tipo</label>
        <select id="type" formControlName="type">
          <option value="nuevo">Nuevo</option>
          <option value="habitual">Habitual</option>
          <option value="vip">VIP</option>
        </select>
      </div>

      <div class="field col-full">
        <label for="notes">Notas</label>
        <textarea id="notes" formControlName="notes" rows="3"></textarea>
      </div>
      <div class="field toggle col-full">
        <label>
          <input type="checkbox" formControlName="sendWelcome" /> Enviar email de bienvenida
        </label>
      </div>
    </form>
    <div mat-dialog-actions align="end" class="dialog-actions">
      <button type="button" class="btn" mat-dialog-close>Cancelar</button>
      <button type="button" class="btn btn--secondary" (click)="reset()">Limpiar</button>
      <button type="submit" class="btn btn--primary" (click)="submit()">Crear</button>
    </div>
  `,
  styles: [
    `
      h2[mat-dialog-title] { margin: 0; font: 600 18px/1.2 var(--font-family-sans); color: var(--color-text-primary); }
      .dialog-content { display: grid; gap: var(--space-4); padding-top: var(--space-2); color: var(--color-text-primary); grid-template-columns: 1fr 1fr; }
      .field { display: grid; gap: 6px; }
      .col { grid-column: span 1; }
      .col-full { grid-column: 1 / -1; }
      .field label { font: 500 14px/1 var(--font-family-sans); color: var(--color-text-primary); }
      .field input {
        height: var(--ctrl-h); padding: 0 12px; border-radius: var(--ctrl-radius);
        border: 1px solid var(--color-border); background: var(--color-surface);
        color: var(--color-text-primary);
      }
      .field select {
        height: var(--ctrl-h); padding: 0 12px; border-radius: var(--ctrl-radius);
        border: 1px solid var(--color-border); background: var(--color-surface);
        color: var(--color-text-primary);
      }
      .field textarea {
        padding: 8px 12px; border-radius: var(--ctrl-radius);
        border: 1px solid var(--color-border); background: var(--color-surface);
        color: var(--color-text-primary); resize: vertical;
      }
      .field.toggle { align-items: center; }
      .field.toggle label { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }
      .field input:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 14%, transparent); }
      .field input.is-invalid { border-color: var(--color-error-500, #ef4444); }
      .field input.is-invalid:focus { box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-error-500, #ef4444) 14%, transparent); }
      .error { color: var(--color-error-500, #ef4444); font-size: 12px; }
      .dialog-actions { padding: var(--space-2) 0 var(--space-1); }
      .btn { height: 36px; padding: 0 12px; border-radius: 8px; border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text-primary); }
      .btn.btn--primary { background: var(--color-primary); color: var(--color-text-on-primary); border-color: transparent; }
      .btn.btn--primary:hover { background: var(--color-primary-hover); }
      .btn.btn--secondary:hover { background: var(--color-surface-elevated); }
    `,
  ],
})
export class CreateClientDialogComponent {
  private readonly fb = inject(FormBuilder);
  private readonly dialogRef = inject(MatDialogRef<CreateClientDialogComponent>);

  submitted = false;
  form = this.fb.group({
    first_name: ['', Validators.required],
    last_name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    phone: [''],
    status: ['active'],
    type: ['nuevo'],
    notes: [''],
    sendWelcome: [true],
  });

  reset() { this.form.reset(); this.submitted = false; }

  submit() {
    this.submitted = true;
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.dialogRef.close(this.form.value as CreateClientFormData);
  }
}
