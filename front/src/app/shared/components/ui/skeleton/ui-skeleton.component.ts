import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'ui-skeleton',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="ui-skeleton" [style.width.px]="width" [style.height.px]="height"></div>
  `,
  styles: [
    `
      .ui-skeleton {
        border-radius: 8px; background: var(--color-surface-elevated);
        position: relative; overflow: hidden;
      }
      .ui-skeleton::after {
        content: ''; position: absolute; inset: 0; transform: translateX(-100%);
        background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--color-text-tertiary) 8%, transparent), transparent);
        animation: shimmer 1.2s infinite;
      }
      @keyframes shimmer { 100% { transform: translateX(100%); } }
    `,
  ],
})
export class UISkeletonComponent { @Input() width = 120; @Input() height = 16; }

