import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

type CardVariant = 'default' | 'elevated' | 'outlined';

@Component({
  selector: 'ui-card',
  standalone: true,
  imports: [CommonModule],
  template: `
    <section class="ui-card" [class.elevated]="variant === 'elevated'" [class.outlined]="variant === 'outlined'">
      <ng-content />
    </section>
  `,
  styles: [
    `
      .ui-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        padding: var(--space-4);
      }
      .ui-card.outlined { box-shadow: none; }
      .ui-card.elevated { box-shadow: var(--shadow-md); }
    `,
  ],
})
export class UICardComponent { @Input() variant: CardVariant = 'default'; }

