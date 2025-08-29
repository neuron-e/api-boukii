import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost';
type ButtonSize = 'sm' | 'md' | 'lg';

@Component({
  selector: 'ui-button',
  standalone: true,
  imports: [CommonModule],
  template: `
    <button
      class="ui-btn"
      [class.ui-btn--primary]="variant === 'primary'"
      [class.ui-btn--secondary]="variant === 'secondary'"
      [class.ui-btn--outline]="variant === 'outline'"
      [class.ui-btn--ghost]="variant === 'ghost'"
      [class.ui-btn--sm]="size === 'sm'"
      [class.ui-btn--lg]="size === 'lg'"
      [disabled]="disabled"
      [attr.type]="type"
    >
      <ng-content />
    </button>
  `,
  styles: [
    `
      .ui-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        height: 36px;
        padding: 0 var(--space-4);
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        background: var(--color-surface);
        color: var(--color-text-primary);
        font-weight: var(--font-weight-medium);
        font-size: var(--font-size-sm, 14px);
        cursor: pointer;
        transition: background-color var(--duration-fast) var(--ease-out),
          color var(--duration-fast) var(--ease-out),
          border-color var(--duration-fast) var(--ease-out);
      }
      .ui-btn:hover { background: var(--color-surface-elevated); }
      .ui-btn:focus-visible { outline: 2px solid var(--color-primary-focus); outline-offset: 2px; }
      .ui-btn[disabled] { opacity: .6; cursor: not-allowed; }

      .ui-btn--primary { background: var(--button-primary-bg); color: var(--button-primary-text); border-color: transparent; }
      .ui-btn--primary:hover { background: var(--color-primary-hover); }

      .ui-btn--secondary { background: var(--button-secondary-bg); color: var(--button-secondary-text); }

      .ui-btn--outline { background: transparent; color: var(--color-primary); border-color: var(--color-primary); }
      .ui-btn--outline:hover { background: color-mix(in srgb, var(--color-primary) 10%, transparent); }

      .ui-btn--ghost { background: transparent; color: var(--color-text-primary); border-color: transparent; }
      .ui-btn--ghost:hover { background: var(--color-surface-elevated); }

      .ui-btn--sm { height: 32px; padding: 0 var(--space-3); font-size: var(--font-size-xs, 12px); }
      .ui-btn--lg { height: 40px; padding: 0 var(--space-5); }
    `,
  ],
})
export class UIButtonComponent {
  @Input() variant: ButtonVariant = 'secondary';
  @Input() size: ButtonSize = 'md';
  @Input() disabled = false;
  @Input() type: 'button' | 'submit' | 'reset' = 'button';
}

