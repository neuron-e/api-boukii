import { Component, inject, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { UiStore, type Theme } from '@core/stores/ui.store';
import { TranslatePipe } from '@shared/pipes/translate.pipe';

@Component({
  selector: 'app-theme-toggle',
  standalone: true,
  imports: [CommonModule, TranslatePipe],
  template: `
    <div class="theme-toggle-container">
      <!-- Toggle rápido light/dark -->
      <button
        class="theme-toggle-btn quick-toggle"
        data-testid="theme-toggle"
        (click)="quickToggle()"
        [attr.aria-label]="
          'theme.switchTo'
            | translate
              : {
                  theme:
                    ui.effectiveTheme() === 'light'
                      ? ('theme.dark' | translate)
                      : ('theme.light' | translate),
                }
        "
        [title]="
          'theme.switchTo'
            | translate
              : {
                  theme:
                    ui.effectiveTheme() === 'light'
                      ? ('theme.dark' | translate)
                      : ('theme.light' | translate),
                }
        "
      >
        <svg
          class="theme-icon"
          [class.active]="ui.effectiveTheme() === 'light'"
          viewBox="0 0 24 24"
        >
          <!-- Sun icon -->
          <circle cx="12" cy="12" r="4" />
          <path d="m12 2 0 2" />
          <path d="m12 20 0 2" />
          <path d="m4.93 4.93 1.41 1.41" />
          <path d="m17.66 17.66 1.41 1.41" />
          <path d="m2 12 2 0" />
          <path d="m20 12 2 0" />
          <path d="m6.34 17.66-1.41 1.41" />
          <path d="m19.07 4.93-1.41 1.41" />
        </svg>

        <svg class="theme-icon" [class.active]="ui.effectiveTheme() === 'dark'" viewBox="0 0 24 24">
          <!-- Moon icon -->
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
        </svg>
      </button>

      <!-- Dropdown con todas las opciones -->
      <div class="theme-dropdown" [class.open]="isDropdownOpen">
        <button
          class="theme-toggle-btn dropdown-trigger"
          (click)="toggleDropdown()"
          [attr.aria-expanded]="isDropdownOpen"
          aria-haspopup="true"
          [title]="'theme.options' | translate"
        >
          <svg viewBox="0 0 24 24" class="chevron-icon">
            <path d="m6 9 6 6 6-6" />
          </svg>
        </button>

        <div class="dropdown-menu" [attr.aria-hidden]="!isDropdownOpen">
          <button
            *ngFor="let option of getThemeOptions()"
            class="dropdown-item"
            [class.active]="ui.theme() === option.value"
            (click)="selectTheme(option.value)"
          >
            <svg class="option-icon" viewBox="0 0 24 24">
              <g [innerHTML]="option.icon"></g>
            </svg>
            <span class="option-label">{{ option.label | translate }}</span>
            <svg *ngIf="ui.theme() === option.value" class="check-icon" viewBox="0 0 24 24">
              <polyline points="20,6 9,17 4,12" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  `,
  styles: [
    `
      .theme-toggle-container {
        position: relative;
        display: flex;
        align-items: center;
        gap: var(--space-1);
      }

      .theme-toggle-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        padding: var(--space-2);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        color: var(--color-text-secondary);
        cursor: pointer;
        transition: all var(--duration-fast) var(--ease-out);
      }

      .theme-toggle-btn:hover {
        background: var(--color-surface-elevated);
        border-color: var(--color-border-strong);
        color: var(--color-text-primary);
      }

      .theme-toggle-btn:focus-visible {
        outline: 2px solid var(--color-primary-focus);
        outline-offset: 2px;
      }

      .quick-toggle {
        position: relative;
      }

      .theme-icon {
        width: 1.25rem;
        height: 1.25rem;
        stroke: currentColor;
        fill: none;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        position: absolute;
        transition: all var(--duration-normal) var(--ease-out);
        opacity: 0;
        transform: scale(0.8) rotate(-90deg);
      }

      .theme-icon.active {
        opacity: 1;
        transform: scale(1) rotate(0deg);
      }

      .chevron-icon {
        width: 1rem;
        height: 1rem;
        stroke: currentColor;
        fill: none;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        transition: transform var(--duration-fast) var(--ease-out);
      }

      .dropdown-trigger .chevron-icon {
        transform: rotate(0deg);
      }

      .dropdown-trigger[aria-expanded='true'] .chevron-icon {
        transform: rotate(180deg);
      }

      .theme-dropdown {
        position: relative;
      }

      .dropdown-menu {
        position: absolute;
        top: calc(100% + var(--space-2));
        right: 0;
        min-width: 12rem;
        padding: var(--space-2);
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        z-index: var(--z-dropdown);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: all var(--duration-normal) var(--ease-out);
      }

      .theme-dropdown.open .dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
      }

      .dropdown-item {
        display: flex;
        align-items: center;
        width: 100%;
        padding: var(--space-3) var(--space-4);
        border: none;
        border-radius: var(--radius-md);
        background: transparent;
        color: var(--color-text-primary);
        text-align: left;
        cursor: pointer;
        transition: background-color var(--duration-fast) var(--ease-out);
        gap: var(--space-3);
      }

      .dropdown-item:hover {
        background: var(--color-surface-elevated);
      }

      .dropdown-item.active {
        background: var(--color-primary-50);
        color: var(--color-primary-700);
      }

      .dark .dropdown-item.active {
        background: var(--color-primary-900);
        color: var(--color-primary-200);
      }

      .option-icon,
      .check-icon {
        width: 1.125rem;
        height: 1.125rem;
        stroke: currentColor;
        fill: none;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        flex-shrink: 0;
      }

      .option-label {
        flex: 1;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
      }

      .check-icon {
        color: var(--color-primary);
      }

      /* Responsive adjustments */
      @media (max-width: 640px) {
        .theme-toggle-container {
          gap: 0;
        }

        .dropdown-trigger {
          display: none;
        }
      }

      /* Animaciones para reduced motion */
      @media (prefers-reduced-motion: reduce) {
        .theme-icon,
        .chevron-icon,
        .dropdown-menu,
        .dropdown-item {
          transition: none;
        }
      }
    `,
  ],
})
export class ThemeToggleComponent implements OnInit, OnDestroy {
  protected readonly ui = inject(UiStore);

  protected isDropdownOpen = false;

  protected getThemeOptions() {
    return [
      {
        value: 'light' as const,
        label: 'theme.light',
        icon: '<circle cx="12" cy="12" r="4"/><path d="m12 2 0 2"/><path d="m12 20 0 2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="m2 12 2 0"/><path d="m20 12 2 0"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
      },
      {
        value: 'dark' as const,
        label: 'theme.dark',
        icon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
      },
    ] as const;
  }

  protected quickToggle(): void {
    this.ui.toggleTheme();
    this.closeDropdown();
  }

  protected toggleDropdown(): void {
    this.isDropdownOpen = !this.isDropdownOpen;
  }

  protected selectTheme(theme: Theme): void {
    this.ui.setTheme(theme);
    this.closeDropdown();
  }

  private closeDropdown(): void {
    this.isDropdownOpen = false;
  }

  // Cerrar dropdown al hacer clic fuera
  protected onDocumentClick = (event: MouseEvent): void => {
    const target = event.target as HTMLElement;
    if (!target.closest('.theme-toggle-container')) {
      this.closeDropdown();
    }
  };

  ngOnInit(): void {
    if (typeof document !== 'undefined') {
      document.addEventListener('click', this.onDocumentClick);
    }
  }

  ngOnDestroy(): void {
    if (typeof document !== 'undefined') {
      document.removeEventListener('click', this.onDocumentClick);
    }
  }
}
