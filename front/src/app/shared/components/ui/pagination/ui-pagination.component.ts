import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'ui-pagination',
  standalone: true,
  imports: [CommonModule],
  template: `
    <nav class="ui-pagination" role="navigation" aria-label="pagination">
      <button class="page-btn" [disabled]="page<=1" (click)="go(page-1)">Anterior</button>
      <span class="page-info">Página {{ page }} de {{ totalPages }}</span>
      <button class="page-btn" [disabled]="page>=totalPages" (click)="go(page+1)">Siguiente</button>
    </nav>
  `,
  styles: [
    `
      .ui-pagination { display: flex; align-items: center; gap: var(--space-3); }
      .page-btn {
        height: 32px; padding: 0 var(--space-3); border-radius: 8px;
        border: 1px solid var(--color-border); background: var(--color-surface);
        color: var(--color-text-primary); cursor: pointer;
      }
      .page-btn:disabled { opacity: .5; cursor: not-allowed; }
      .page-info { color: var(--color-text-secondary); font-size: var(--text-sm); }
    `,
  ],
})
export class UIPaginationComponent {
  @Input() page = 1;
  @Input() pageSize = 10;
  @Input() total = 0;
  @Output() pageChange = new EventEmitter<number>();
  get totalPages(): number { return Math.max(1, Math.ceil(this.total / this.pageSize)); }
  go(p: number) { this.page = Math.min(this.totalPages, Math.max(1, p)); this.pageChange.emit(this.page); }
}

