import { Component, inject, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastService } from '@core/services/toast.service';
import { Toast, ToastPosition } from '@core/models/toast.models';

@Component({
  selector: 'app-toast-container',
  standalone: true,
  imports: [CommonModule],
  template: `
    <!-- Toast containers for each position -->
    @for (positionEntry of toastService.toastsByPosition() | keyvalue; track positionEntry.key) {
      @if (positionEntry.value.length > 0) {
        <div
          class="toast-container"
          [attr.data-position]="positionEntry.key"
          [class]="getContainerClasses(positionEntry.key)"
        >
          @for (toast of positionEntry.value; track toast.id) {
            <div
              class="toast"
              [class]="getToastClasses(toast)"
              [attr.data-type]="toast.type"
              [attr.data-dismissing]="toast.dismissing"
              (mouseenter)="onToastHover(toast.id, true)"
              (mouseleave)="onToastHover(toast.id, false)"
              role="alert"
              [attr.aria-live]="toast.type === 'error' ? 'assertive' : 'polite'"
            >
              <!-- Progress bar -->
              @if (toast.showProgress && toast.duration > 0 && !toast.dismissing) {
                <div class="toast-progress">
                  <div
                    class="toast-progress-bar"
                    [class.paused]="toast.paused"
                    [style.animation-duration.ms]="toast.duration"
                  ></div>
                </div>
              }

              <!-- Icon -->
              <div class="toast-icon">
                <svg class="icon" [attr.data-icon]="getIcon(toast)">
                  <use [attr.href]="'#icon-' + getIcon(toast)"></use>
                </svg>
              </div>

              <!-- Content -->
              <div class="toast-content">
                @if (toast.title) {
                  <div class="toast-title">{{ toast.title }}</div>
                }
                <div class="toast-message" [innerHTML]="toast.html ? toast.message : null">
                  @if (!toast.html) {
                    {{ toast.message }}
                  }
                </div>

                <!-- Actions -->
                @if (toast.actions && toast.actions.length > 0) {
                  <div class="toast-actions">
                    @for (action of toast.actions; track action.label) {
                      <button
                        type="button"
                        class="toast-action"
                        [attr.data-style]="action.style || 'secondary'"
                        (click)="handleAction(action.action)"
                      >
                        {{ action.label }}
                      </button>
                    }
                  </div>
                }
              </div>

              <!-- Close button -->
              @if (toast.closeable) {
                <button
                  type="button"
                  class="toast-close"
                  (click)="closeToast(toast.id)"
                  aria-label="Close notification"
                  title="Close"
                >
                  <svg class="icon">
                    <use href="#icon-x"></use>
                  </svg>
                </button>
              }
            </div>
          }
        </div>
      }
    }

    <!-- SVG Icons -->
    <svg style="display: none;">
      <defs>
        <!-- Success -->
        <symbol id="icon-check-circle" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" stroke="currentColor" fill="none" stroke-width="2" />
          <path d="m9 12 2 2 4-4" stroke="currentColor" fill="none" stroke-width="2" />
        </symbol>

        <!-- Error -->
        <symbol id="icon-x-circle" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" stroke="currentColor" fill="none" stroke-width="2" />
          <path d="m15 9-6 6m0-6 6 6" stroke="currentColor" fill="none" stroke-width="2" />
        </symbol>

        <!-- Warning -->
        <symbol id="icon-alert-triangle" viewBox="0 0 24 24">
          <path
            d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"
            stroke="currentColor"
            fill="none"
            stroke-width="2"
          />
          <path d="M12 9v4m0 4h.01" stroke="currentColor" fill="none" stroke-width="2" />
        </symbol>

        <!-- Info -->
        <symbol id="icon-info" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" stroke="currentColor" fill="none" stroke-width="2" />
          <path d="M12 16v-4m0-4h.01" stroke="currentColor" fill="none" stroke-width="2" />
        </symbol>

        <!-- Loading -->
        <symbol id="icon-loader" viewBox="0 0 24 24">
          <circle
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            fill="none"
            stroke-width="2"
            opacity="0.25"
          />
          <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" fill="none" stroke-width="2">
            <animateTransform
              attributeName="transform"
              type="rotate"
              dur="1s"
              values="0 12 12;360 12 12"
              repeatCount="indefinite"
            />
          </path>
        </symbol>

        <!-- Close -->
        <symbol id="icon-x" viewBox="0 0 24 24">
          <path d="m18 6-12 12m0-12 12 12" stroke="currentColor" fill="none" stroke-width="2" />
        </symbol>
      </defs>
    </svg>
  `,
  styles: [
    `
      :host {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
        z-index: 9999;
      }

      .toast-container {
        position: absolute;
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        max-width: 420px;
        padding: var(--space-4);
        pointer-events: none;
      }

      /* Position styles */
      .toast-container[data-position='top-left'] {
        top: 0;
        left: 0;
      }

      .toast-container[data-position='top-right'] {
        top: 0;
        right: 0;
      }

      .toast-container[data-position='top-center'] {
        top: 0;
        left: 50%;
        transform: translateX(-50%);
      }

      .toast-container[data-position='bottom-left'] {
        bottom: 0;
        left: 0;
      }

      .toast-container[data-position='bottom-right'] {
        bottom: 0;
        right: 0;
      }

      .toast-container[data-position='bottom-center'] {
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
      }

      .toast {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: var(--space-3);
        min-width: 320px;
        max-width: 420px;
        padding: var(--space-4);
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        pointer-events: auto;
        transform: translateX(0);
        opacity: 1;
        transition: all var(--duration-normal) var(--ease-out);
      }

      /* Toast animations */
      .toast[data-dismissing='true'] {
        opacity: 0;
        transform: translateX(100%) scale(0.95);
      }

      .toast-container[data-position*='left'] .toast[data-dismissing='true'] {
        transform: translateX(-100%) scale(0.95);
      }

      .toast-container[data-position*='center'] .toast[data-dismissing='true'] {
        transform: translateY(-20px) scale(0.95);
      }

      /* Type-specific styles */
      .toast[data-type='success'] {
        border-left: 4px solid var(--color-success);
      }

      .toast[data-type='error'] {
        border-left: 4px solid var(--color-error);
      }

      .toast[data-type='warning'] {
        border-left: 4px solid var(--color-warning);
      }

      .toast[data-type='info'] {
        border-left: 4px solid var(--color-info);
      }

      /* Progress bar */
      .toast-progress {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--color-border-subtle);
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        overflow: hidden;
      }

      .toast-progress-bar {
        height: 100%;
        background: currentColor;
        width: 100%;
        animation: toast-progress linear forwards;
        transform-origin: left;
      }

      .toast-progress-bar.paused {
        animation-play-state: paused;
      }

      @keyframes toast-progress {
        from {
          transform: scaleX(1);
        }
        to {
          transform: scaleX(0);
        }
      }

      /* Icon */
      .toast-icon {
        flex-shrink: 0;
        width: 1.25rem;
        height: 1.25rem;
        margin-top: 0.125rem;
      }

      .toast[data-type='success'] .toast-icon {
        color: var(--color-success);
      }

      .toast[data-type='error'] .toast-icon {
        color: var(--color-error);
      }

      .toast[data-type='warning'] .toast-icon {
        color: var(--color-warning);
      }

      .toast[data-type='info'] .toast-icon {
        color: var(--color-info);
      }

      .icon {
        width: 100%;
        height: 100%;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
      }

      /* Content */
      .toast-content {
        flex: 1;
        min-width: 0;
      }

      .toast-title {
        font-weight: var(--font-weight-semibold);
        color: var(--color-text-primary);
        margin-bottom: var(--space-1);
        font-size: var(--font-size-sm);
        line-height: 1.4;
      }

      .toast-message {
        color: var(--color-text-secondary);
        font-size: var(--font-size-sm);
        line-height: 1.4;
        word-wrap: break-word;
      }

      /* Actions */
      .toast-actions {
        display: flex;
        gap: var(--space-2);
        margin-top: var(--space-3);
      }

      .toast-action {
        padding: var(--space-1) var(--space-3);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: transparent;
        color: var(--color-text-primary);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        cursor: pointer;
        transition: all var(--duration-fast) var(--ease-out);
      }

      .toast-action:hover {
        background: var(--color-surface-elevated);
      }

      .toast-action[data-style='primary'] {
        background: var(--color-primary);
        border-color: var(--color-primary);
        color: var(--color-text-on-primary);
      }

      .toast-action[data-style='primary']:hover {
        background: var(--color-primary-hover);
        border-color: var(--color-primary-hover);
      }

      .toast-action[data-style='destructive'] {
        background: var(--color-error);
        border-color: var(--color-error);
        color: var(--color-text-on-primary);
      }

      .toast-action[data-style='destructive']:hover {
        opacity: 0.9;
      }

      /* Close button */
      .toast-close {
        flex-shrink: 0;
        width: 1.5rem;
        height: 1.5rem;
        padding: 0;
        border: none;
        background: transparent;
        color: var(--color-text-tertiary);
        cursor: pointer;
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all var(--duration-fast) var(--ease-out);
      }

      .toast-close:hover {
        background: var(--color-surface-elevated);
        color: var(--color-text-secondary);
      }

      .toast-close:focus-visible {
        outline: 2px solid var(--color-primary-focus);
        outline-offset: 2px;
      }

      .toast-close .icon {
        width: 1rem;
        height: 1rem;
      }

      /* Responsive */
      @media (max-width: 640px) {
        .toast-container {
          left: var(--space-4) !important;
          right: var(--space-4) !important;
          max-width: none;
          transform: none !important;
        }

        .toast {
          min-width: auto;
          max-width: none;
        }
      }

      /* Accessibility */
      @media (prefers-reduced-motion: reduce) {
        .toast,
        .toast-progress-bar {
          transition: none;
          animation: none;
        }

        .toast[data-dismissing='true'] {
          display: none;
        }
      }

      /* Dark theme adjustments */
      .dark .toast {
        box-shadow:
          var(--shadow-xl),
          0 0 0 1px rgba(255, 255, 255, 0.05);
      }
    `,
  ],
})
export class ToastComponent implements OnInit, OnDestroy {
  protected readonly toastService = inject(ToastService);

  ngOnInit(): void {
    // Toast component is ready - initialization handled by service
    console.debug('Toast container initialized');
  }

  ngOnDestroy(): void {
    // Clear any remaining toasts when component is destroyed
    this.toastService.clear();
  }

  protected getContainerClasses(position: ToastPosition): string {
    return `toast-container--${position}`;
  }

  protected getToastClasses(toast: Toast): string {
    const classes = ['toast'];

    if (toast.className) {
      classes.push(toast.className);
    }

    return classes.join(' ');
  }

  protected getIcon(toast: Toast): string {
    if (toast.icon) {
      return toast.icon;
    }

    switch (toast.type) {
      case 'success':
        return 'check-circle';
      case 'error':
        return 'x-circle';
      case 'warning':
        return 'alert-triangle';
      case 'info':
        return 'info';
      default:
        return 'info';
    }
  }

  protected closeToast(id: string): void {
    this.toastService.remove(id);
  }

  protected onToastHover(id: string, isHovering: boolean): void {
    if (isHovering) {
      this.toastService.pauseToast(id);
    } else {
      this.toastService.resumeToast(id);
    }
  }

  protected handleAction(action: () => void): void {
    try {
      action();
    } catch (error) {
      console.error('Toast action error:', error);
    }
  }
}
