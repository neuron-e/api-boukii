import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

type BadgeColor = 'blue' | 'green' | 'yellow' | 'red' | 'gray' | 'info' | 'success' | 'warning' | 'error';

@Component({
  selector: 'ui-badge',
  standalone: true,
  imports: [CommonModule],
  template: `
    <span class="ui-badge" [class.pill]="pill" [ngClass]="colorClass">
      <ng-content />
    </span>
  `,
  styles: [
    `
      .ui-badge {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .2rem .55rem;
        border-radius: 6px;
        font-size: var(--font-size-xs, 12px);
        font-weight: 600;
        line-height: 1;
        border: 1px solid var(--color-border);
        background: var(--color-surface);
        color: var(--color-text-secondary);
      }
      .ui-badge.pill { border-radius: 999px; }

      .c-blue { background: color-mix(in srgb, var(--color-ski-blue) 12%, transparent); color: var(--color-ski-blue); border-color: color-mix(in srgb, var(--color-ski-blue) 30%, transparent); }
      .c-green { background: color-mix(in srgb, var(--color-ski-green) 12%, transparent); color: var(--color-ski-green); border-color: color-mix(in srgb, var(--color-ski-green) 30%, transparent); }
      .c-yellow { background: color-mix(in srgb, var(--color-ski-yellow) 12%, transparent); color: var(--color-ski-yellow); border-color: color-mix(in srgb, var(--color-ski-yellow) 30%, transparent); }
      .c-red { background: color-mix(in srgb, var(--color-ski-red) 12%, transparent); color: var(--color-ski-red); border-color: color-mix(in srgb, var(--color-ski-red) 30%, transparent); }
      .c-gray { background: var(--color-surface-elevated); color: var(--color-text-secondary); }
      .c-info { background: color-mix(in srgb, var(--color-ski-info) 12%, transparent); color: var(--color-ski-info); border-color: color-mix(in srgb, var(--color-ski-info) 30%, transparent); }
      .c-success { composes: c-green; }
      .c-warning { composes: c-yellow; }
      .c-error { composes: c-red; }
    `,
  ],
})
export class UIBadgeComponent {
  @Input() color: BadgeColor = 'gray';
  @Input() pill = false;

  get colorClass() {
    return `c-${this.color}`;
  }
}

