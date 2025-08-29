import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

type KpiTone = 'blue' | 'green' | 'purple' | 'yellow' | 'cyan' | 'warning';
type IconPosition = 'right' | 'left' | 'none';

@Component({
  selector: 'ui-kpi-card',
  standalone: true,
  imports: [CommonModule],
  template: `
    <section class="stat-card" [ngClass]="[toneClass, iconPositionClass]">
      <div class="stat-icon" *ngIf="iconPosition !== 'none'">
        <ng-content />
      </div>
      <div class="content">
        <div class="label" *ngIf="!hideLabel">{{ label }}</div>
        <div class="value">{{ value }}</div>
      </div>
    </section>
  `,
  styles: [
    `
      /* Dashboard metric-card visual spec applied generically */
      .stat-card {
        position: relative;
        background: #ffffff;
        border: 1px solid #eee;
        border-radius: 16px;
        padding: 1.25rem 1.5rem; /* 20px 24px */
        box-shadow: 0 1px 6px rgba(0,0,0,0.06);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        min-height: 72px;
      }
      .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

      .content { text-align: left; margin-right: 0; }
      .label { font-size: 0.875rem; color: #718096; font-weight: 500; margin-bottom: 0.5rem; line-height: 1.2; }
      .value { font-size: 2rem; font-weight: 700; color: #2d3748; line-height: 1; }

      .stat-icon {
        width: 48px; height: 48px; border-radius: 50%; background: #f4f4f4;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
      }
      .stat-icon svg { width: 24px; height: 24px; color: #64748b; }

      /* Icon on the right (default) mimics dashboard metric layout */
      .icon-right .stat-icon { position: absolute; top: 1.25rem; right: 1.5rem; }
      .icon-right .content { margin-right: 60px; }

      /* Icon inline on the left */
      .icon-left { display: flex; align-items: center; gap: 1rem; }

      /* Tone colors affect icon color/background */
      .tone-blue .stat-icon { background: color-mix(in srgb, var(--color-ski-blue) 12%, transparent); }
      .tone-blue .stat-icon svg { color: var(--color-ski-blue); }
      .tone-green .stat-icon { background: color-mix(in srgb, var(--color-ski-green) 12%, transparent); }
      .tone-green .stat-icon svg { color: var(--color-ski-green); }
      .tone-purple .stat-icon { background: color-mix(in srgb, var(--color-primary-600, #8b5cf6) 12%, transparent); }
      .tone-purple .stat-icon svg { color: var(--color-primary-600, #8b5cf6); }
      .tone-yellow .stat-icon { background: color-mix(in srgb, var(--color-ski-yellow) 12%, transparent); }
      .tone-yellow .stat-icon svg { color: var(--color-ski-yellow); }
      .tone-cyan .stat-icon { background: color-mix(in srgb, var(--color-ski-info) 12%, transparent); }
      .tone-cyan .stat-icon svg { color: var(--color-ski-info); }
      .tone-warning { border-color: var(--color-ski-yellow); }
    `,
  ],
})
export class UIKpiCardComponent {
  @Input() label = '';
  @Input() value: string | number = '';
  @Input() tone: KpiTone = 'blue';
  @Input() iconPosition: IconPosition = 'right';
  @Input() hideLabel = false;

  get toneClass() { return `tone-${this.tone}`; }
  get iconPositionClass() { return `icon-${this.iconPosition}`; }
}
