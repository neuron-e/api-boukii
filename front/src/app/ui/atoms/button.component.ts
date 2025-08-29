import { Component, Input, Output, EventEmitter, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md' | 'lg';

@Component({
  selector: 'ui-button',
  standalone: true,
  imports: [CommonModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <button
      [type]="type"
      [disabled]="disabled"
      [class]="buttonClasses"
      (click)="handleClick($event)"
    >
      @if (loading) {
        <svg class="spinner" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2" opacity="0.25"/>
          <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
        </svg>
      }
      @if (iconLeft && !loading) {
        <ng-content select="[slot=icon-left]"></ng-content>
      }
      <span class="button-text">
        <ng-content></ng-content>
      </span>
      @if (iconRight && !loading) {
        <ng-content select="[slot=icon-right]"></ng-content>
      }
    </button>
  `,
  styles: [`
    .btn {
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      padding: var(--space-3) var(--space-4);
      border: 1px solid transparent;
      border-radius: var(--radius-md);
      font-family: var(--font-family-sans);
      font-weight: var(--font-weight-medium);
      line-height: var(--line-height-none);
      cursor: pointer;
      transition: all var(--duration-fast) var(--ease-in-out);
      text-decoration: none;
      user-select: none;
      white-space: nowrap;
      position: relative;
      overflow: hidden;
    }

    .btn:disabled {
      cursor: not-allowed;
      opacity: 0.6;
    }

    .btn:focus-visible {
      outline: 2px solid var(--color-primary-focus);
      outline-offset: 2px;
    }

    /* Sizes */
    .btn-sm {
      padding: var(--space-2) var(--space-3);
      font-size: var(--font-size-sm);
      gap: var(--space-1);
    }

    .btn-md {
      padding: var(--space-3) var(--space-4);
      font-size: var(--font-size-base);
      gap: var(--space-2);
    }

    .btn-lg {
      padding: var(--space-4) var(--space-6);
      font-size: var(--font-size-lg);
      gap: var(--space-2);
    }

    /* Variants */
    .btn-primary {
      background-color: var(--button-primary-bg);
      color: var(--button-primary-text);
      border-color: var(--button-primary-bg);
    }

    .btn-primary:hover:not(:disabled) {
      background-color: var(--button-primary-hover);
      border-color: var(--button-primary-hover);
    }

    .btn-secondary {
      background-color: var(--button-secondary-bg);
      color: var(--button-secondary-text);
      border-color: var(--color-border);
    }

    .btn-secondary:hover:not(:disabled) {
      background-color: var(--button-secondary-hover);
    }

    .btn-outline {
      background-color: transparent;
      color: var(--color-primary);
      border-color: var(--color-primary);
    }

    .btn-outline:hover:not(:disabled) {
      background-color: var(--color-primary);
      color: var(--color-text-on-primary);
    }

    .btn-ghost {
      background-color: transparent;
      color: var(--color-text-secondary);
      border-color: transparent;
    }

    .btn-ghost:hover:not(:disabled) {
      background-color: var(--color-surface-elevated);
      color: var(--color-text-primary);
    }

    .btn-danger {
      background-color: var(--color-error-500);
      color: var(--color-text-inverse, #0f172a);
      border-color: var(--color-error-500);
    }

    .btn-danger:hover:not(:disabled) {
      background-color: color-mix(in srgb, var(--color-error-500) 85%, black);
      border-color: color-mix(in srgb, var(--color-error-500) 85%, black);
    }

    .spinner {
      width: 1em;
      height: 1em;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .button-text {
      display: inline-flex;
      align-items: center;
    }
  `]
})
export class ButtonComponent {
  @Input() public variant: ButtonVariant = 'primary';
  @Input() public size: ButtonSize = 'md';
  @Input() public type: 'button' | 'submit' | 'reset' = 'button';
  @Input() public disabled: boolean = false;
  @Input() public loading: boolean = false;
  @Input() public iconLeft: boolean = false;
  @Input() public iconRight: boolean = false;

  @Output() public click = new EventEmitter<MouseEvent>();

  public get buttonClasses(): string {
    return [
      'btn',
      `btn-${this.variant}`,
      `btn-${this.size}`
    ].join(' ');
  }

  public handleClick(event: MouseEvent): void {
    if (!this.disabled && !this.loading) {
      this.click.emit(event);
    }
  }
}
