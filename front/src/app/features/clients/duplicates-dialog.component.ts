import { Component, Inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';

export interface DuplicateClientItem {
  id: number;
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string;
  created_at?: string;
  total_bookings?: number;
  status?: 'active' | 'inactive' | 'blocked' | string;
}

export interface DuplicatesDialogData {
  duplicates: DuplicateClientItem[];
}

@Component({
  selector: 'app-duplicates-dialog',
  standalone: true,
  imports: [CommonModule, MatDialogModule],
  template: `
    <h2 mat-dialog-title>Posibles duplicados</h2>
    <mat-dialog-content class="content">
      <p class="hint">Hemos encontrado clientes similares. Revisa y selecciona una opción.</p>
      <ul class="list">
        <li class="item" *ngFor="let c of data.duplicates">
          <div class="main">
            <div class="name">{{ (c.first_name || '') + ' ' + (c.last_name || '') }}</div>
            <div class="meta">
              <span *ngIf="c.email">{{ c.email }}</span>
              <span *ngIf="c.phone">· {{ c.phone }}</span>
              <span *ngIf="c.total_bookings != null">· {{ c.total_bookings }} reservas</span>
            </div>
          </div>
          <div class="status" [class.active]="c.status==='active'" [class.inactive]="c.status==='inactive'" [class.blocked]="c.status==='blocked'">
            {{ c.status || '-' }}
          </div>
          <div class="actions">
            <button class="btn btn--secondary" (click)="open(c.id)">Abrir</button>
          </div>
        </li>
      </ul>
    </mat-dialog-content>
    <mat-dialog-actions align="end" class="actions-bar">
      <button class="btn" (click)="close()">Editar datos</button>
    </mat-dialog-actions>
  `,
  styles: [`
    h2[mat-dialog-title]{ margin:0; font:600 18px/1.2 var(--font-family-sans); color: var(--color-text-primary); }
    .content{ color: var(--color-text-primary); }
    .hint{ color: var(--color-text-secondary); margin: 4px 0 var(--space-3); }
    .list{ list-style:none; padding:0; margin:0; display:grid; gap:12px; }
    .item{ display:grid; grid-template-columns: 1fr auto auto; align-items:center; gap:12px; padding:12px; border:1px solid var(--color-border); border-radius:12px; }
    .name{ font-weight:600; }
    .meta{ color: var(--color-text-secondary); font-size: var(--text-sm); }
    .status{ font-size: 12px; border:1px solid currentColor; padding:2px 8px; border-radius:999px; text-transform: capitalize; }
    .status.active{ color: var(--color-success-500); }
    .status.inactive{ color: var(--color-warning-600); }
    .status.blocked{ color: var(--color-error-500); }
    .actions-bar{ padding: var(--space-2) 0 var(--space-1); }
    .btn{ height:36px; padding:0 12px; border-radius:8px; border:1px solid var(--color-border); background: var(--color-surface); color: var(--color-text-primary); }
    .btn.btn--secondary:hover{ background: var(--color-surface-elevated); }
  `]
})
export class DuplicatesDialogComponent {
  constructor(
    private readonly dialogRef: MatDialogRef<DuplicatesDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: DuplicatesDialogData
  ) {}

  close(): void { this.dialogRef.close({ action: 'edit' }); }
  open(id: number): void { this.dialogRef.close({ action: 'open', id }); }
}

