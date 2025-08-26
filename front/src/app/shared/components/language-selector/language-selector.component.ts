import { Component, inject, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TranslationService, SupportedLanguage } from '@core/services/translation.service';

@Component({
  selector: 'app-language-selector',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="language-selector" [class.open]="isDropdownOpen">
      <!-- Current language button -->
      <button
        class="language-button"
        (click)="toggleDropdown()"
        [attr.aria-expanded]="isDropdownOpen"
        [attr.aria-label]="
          'Select language. Current: ' + translationService.currentLanguageConfig().nativeName
        "
        aria-haspopup="true"
        [disabled]="translationService.isLoading()"
      >
        <span class="flag" [attr.aria-hidden]="true">
          {{ translationService.currentLanguageConfig().flag }}
        </span>
        <span class="language-name">
          {{ translationService.currentLanguageConfig().nativeName }}
        </span>

        <!-- Loading spinner -->
        @if (translationService.isLoading()) {
          <svg class="loading-icon" viewBox="0 0 24 24" aria-hidden="true">
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
          </svg>
        } @else {
          <!-- Chevron -->
          <svg class="chevron-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path
              d="m6 9 6 6 6-6"
              stroke="currentColor"
              fill="none"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        }
      </button>

      <!-- Language dropdown -->
      <div
        class="language-dropdown"
        [attr.aria-hidden]="!isDropdownOpen"
        role="menu"
        [attr.aria-label]="'Language selection menu'"
      >
        @for (language of translationService.availableLanguages(); track language.code) {
          <button
            class="language-option"
            [class.active]="language.code === translationService.currentLanguage()"
            (click)="selectLanguage(language.code)"
            role="menuitem"
            [attr.aria-label]="'Switch to ' + language.nativeName"
          >
            <span class="flag" [attr.aria-hidden]="true">{{ language.flag }}</span>
            <div class="language-info">
              <span class="native-name">{{ language.nativeName }}</span>
              <span class="english-name">{{ language.name }}</span>
            </div>

            <!-- Active indicator -->
            @if (language.code === translationService.currentLanguage()) {
              <svg class="check-icon" viewBox="0 0 24 24" aria-hidden="true">
                <polyline
                  points="20,6 9,17 4,12"
                  stroke="currentColor"
                  fill="none"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            }
          </button>
        }
      </div>

      <!-- Error state -->
      @if (translationService.hasError()) {
        <div class="error-message" role="alert">
          Translation loading failed
          <button
            class="retry-button"
            (click)="retryLoad()"
            [attr.aria-label]="'Retry loading translations'"
          >
            Retry
          </button>
        </div>
      }
    </div>

    <!-- Backdrop (for mobile) -->
    @if (isDropdownOpen) {
      <div class="backdrop" (click)="closeDropdown()" [attr.aria-hidden]="true"></div>
    }
  `,
  styles: [
    `
      .language-selector {
        position: relative;
        display: inline-block;
      }

      .language-button {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-2) var(--space-3);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        color: var(--color-text-primary);
        font-size: var(--font-size-sm);
        cursor: pointer;
        transition: all var(--duration-fast) var(--ease-out);
        min-width: 5rem;
      }

      .language-button:hover {
        background: var(--color-surface-elevated);
        border-color: var(--color-border-strong);
      }

      .language-button:focus-visible {
        outline: 2px solid var(--color-primary-focus);
        outline-offset: 2px;
      }

      .language-button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }

      .flag {
        font-size: 1.125rem;
        line-height: 1;
        flex-shrink: 0;
      }

      .language-name {
        font-weight: var(--font-weight-medium);
        white-space: nowrap;
      }

      .loading-icon,
      .chevron-icon {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
        transition: transform var(--duration-fast) var(--ease-out);
      }

      .language-selector.open .chevron-icon {
        transform: rotate(180deg);
      }

      .language-dropdown {
        position: absolute;
        top: calc(100% + var(--space-2));
        right: 0;
        min-width: 12rem;
        max-width: 16rem;
        padding: var(--space-2);
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        z-index: var(--z-dropdown);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: all var(--duration-normal) var(--ease-out);
      }

      .language-selector.open .language-dropdown {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
      }

      .language-option {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        width: 100%;
        padding: var(--space-3);
        border: none;
        border-radius: var(--radius-md);
        background: transparent;
        color: var(--color-text-primary);
        text-align: left;
        cursor: pointer;
        transition: background-color var(--duration-fast) var(--ease-out);
      }

      .language-option:hover {
        background: var(--color-surface-elevated);
      }

      .language-option.active {
        background: var(--color-primary-50);
        color: var(--color-primary-700);
      }

      .dark .language-option.active {
        background: var(--color-primary-900);
        color: var(--color-primary-200);
      }

      .language-info {
        flex: 1;
        min-width: 0;
      }

      .native-name {
        display: block;
        font-weight: var(--font-weight-medium);
        font-size: var(--font-size-sm);
        line-height: 1.2;
      }

      .english-name {
        display: block;
        font-size: var(--font-size-xs);
        color: var(--color-text-tertiary);
        line-height: 1.2;
        margin-top: 1px;
      }

      .check-icon {
        width: 1rem;
        height: 1rem;
        color: var(--color-primary);
        flex-shrink: 0;
      }

      .error-message {
        position: absolute;
        top: calc(100% + var(--space-2));
        right: 0;
        padding: var(--space-3);
        background: var(--color-error-50);
        border: 1px solid var(--color-error-200);
        border-radius: var(--radius-md);
        color: var(--color-error-700);
        font-size: var(--font-size-xs);
        z-index: var(--z-dropdown);
        white-space: nowrap;
      }

      .dark .error-message {
        background: var(--color-error-900);
        border-color: var(--color-error-700);
        color: var(--color-error-200);
      }

      .retry-button {
        margin-left: var(--space-2);
        padding: var(--space-1) var(--space-2);
        background: var(--color-error);
        color: white;
        border: none;
        border-radius: var(--radius-sm);
        font-size: var(--font-size-xs);
        cursor: pointer;
        transition: opacity var(--duration-fast) var(--ease-out);
      }

      .retry-button:hover {
        opacity: 0.9;
      }

      .backdrop {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: transparent;
        z-index: calc(var(--z-dropdown) - 1);
      }

      /* Responsive design */
      @media (max-width: 768px) {
        .language-dropdown {
          position: fixed;
          top: auto;
          bottom: var(--space-4);
          left: var(--space-4);
          right: var(--space-4);
          min-width: auto;
          max-width: none;
        }

        .language-selector.open .language-dropdown {
          transform: translateY(0);
        }

        .language-button {
          padding: var(--space-2);
          min-width: auto;
        }

        .language-name {
          display: none;
        }
      }

      /* Accessibility - reduced motion */
      @media (prefers-reduced-motion: reduce) {
        .language-dropdown,
        .chevron-icon,
        .language-option {
          transition: none;
        }
      }

      /* High contrast mode */
      @media (prefers-contrast: high) {
        .language-button,
        .language-dropdown {
          border-width: 2px;
        }

        .language-option.active {
          outline: 2px solid currentColor;
          outline-offset: -2px;
        }
      }

      /* Focus management */
      .language-option:focus-visible {
        outline: 2px solid var(--color-primary-focus);
        outline-offset: -2px;
        background: var(--color-surface-elevated);
      }
    `,
  ],
})
export class LanguageSelectorComponent implements OnInit, OnDestroy {
  protected readonly translationService = inject(TranslationService);

  protected isDropdownOpen = false;
  private documentClickListener?: (event: Event) => void;

  ngOnInit(): void {
    this.setupDocumentClickListener();
  }

  ngOnDestroy(): void {
    this.cleanupDocumentClickListener();
  }

  protected toggleDropdown(): void {
    if (this.translationService.isLoading()) {
      return; // Don't open while loading
    }

    this.isDropdownOpen = !this.isDropdownOpen;
  }

  protected closeDropdown(): void {
    this.isDropdownOpen = false;
  }

  protected async selectLanguage(language: SupportedLanguage): Promise<void> {
    if (language === this.translationService.currentLanguage()) {
      this.closeDropdown();
      return;
    }

    try {
      await this.translationService.setLanguage(language);
      this.closeDropdown();
    } catch (error) {
      console.error('Failed to change language:', error);
      // Error will be shown via the error state in template
    }
  }

  protected async retryLoad(): Promise<void> {
    try {
      await this.translationService.reloadTranslations();
    } catch (error) {
      console.error('Failed to retry loading translations:', error);
    }
  }

  private setupDocumentClickListener(): void {
    this.documentClickListener = (event: Event) => {
      const target = event.target as HTMLElement;
      if (!target.closest('.language-selector')) {
        this.closeDropdown();
      }
    };

    if (typeof document !== 'undefined') {
      document.addEventListener('click', this.documentClickListener);

      // Close on escape key
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && this.isDropdownOpen) {
          this.closeDropdown();
        }
      });
    }
  }

  private cleanupDocumentClickListener(): void {
    if (this.documentClickListener && typeof document !== 'undefined') {
      document.removeEventListener('click', this.documentClickListener);
    }
  }
}
