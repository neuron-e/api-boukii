import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { EnvironmentService } from '@core/services/environment.service';
import { FeatureFlags } from '@core/models/environment.models';
import { TranslatePipe } from '@shared/pipes/translate.pipe';
import { FeatureFlagDirective } from '@shared/directives/feature-flag.directive';

/**
 * Feature Flag Panel Component
 * Development tool for toggling feature flags
 */
@Component({
  selector: 'app-feature-flag-panel',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe, FeatureFlagDirective],
  template: `
    <div class="feature-flag-panel" *appFeatureFlag="'debugMode'">
      <div class="panel-header">
        <h3 class="panel-title">{{ 'featureFlags.title' | translate }}</h3>
        <button
          class="panel-toggle"
          (click)="togglePanel()"
          [attr.aria-expanded]="isExpanded"
          [title]="isExpanded ? 'Collapse panel' : 'Expand panel'"
        >
          <svg viewBox="0 0 24 24" class="toggle-icon" [class.rotated]="isExpanded">
            <path d="m6 9 6 6 6-6" />
          </svg>
        </button>
      </div>

      <div class="panel-content" [class.expanded]="isExpanded">
        <div class="feature-categories">
          <div class="category" *ngFor="let category of featureCategories">
            <h4 class="category-title">{{ category.title | translate }}</h4>
            <div class="category-description">{{ category.description | translate }}</div>

            <div class="feature-list">
              <div
                class="feature-item"
                *ngFor="let feature of category.features"
                [class.enabled]="isFeatureEnabled(feature.key)"
                [class.disabled]="!isFeatureEnabled(feature.key)"
              >
                <div class="feature-info">
                  <label class="feature-label" [for]="'feature-' + feature.key">
                    <span class="feature-name">{{ feature.name | translate }}</span>
                    <span class="feature-description">{{ feature.description | translate }}</span>
                  </label>
                </div>

                <div class="feature-toggle">
                  <input
                    type="checkbox"
                    [id]="'feature-' + feature.key"
                    [checked]="isFeatureEnabled(feature.key)"
                    (change)="toggleFeature(feature.key, $event)"
                    class="toggle-checkbox"
                  />
                  <div class="toggle-switch">
                    <div class="toggle-slider"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="panel-actions">
          <button class="action-btn secondary" (click)="resetToDefaults()">
            {{ 'featureFlags.resetDefaults' | translate }}
          </button>
          <button class="action-btn primary" (click)="reloadConfiguration()">
            {{ 'featureFlags.reloadConfig' | translate }}
          </button>
        </div>
      </div>
    </div>
  `,
  styles: [
    `
      .feature-flag-panel {
        position: fixed;
        top: 1rem;
        right: 1rem;
        width: 300px;
        max-width: calc(100vw - 2rem);
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        z-index: 100;
        font-size: var(--font-size-sm);
        opacity: 0.9;
      }

      .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: var(--space-4);
        border-bottom: 1px solid var(--color-border-subtle);
        background: var(--color-surface-elevated);
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
      }

      .panel-title {
        margin: 0;
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-semibold);
        color: var(--color-text-primary);
      }

      .panel-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        padding: 0;
        border: none;
        border-radius: var(--radius-md);
        background: transparent;
        color: var(--color-text-secondary);
        cursor: pointer;
        transition: all var(--duration-fast) var(--ease-out);
      }

      .panel-toggle:hover {
        background: var(--color-surface-elevated);
        color: var(--color-text-primary);
      }

      .toggle-icon {
        width: 1rem;
        height: 1rem;
        stroke: currentColor;
        fill: none;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        transition: transform var(--duration-fast) var(--ease-out);
      }

      .toggle-icon.rotated {
        transform: rotate(180deg);
      }

      .panel-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height var(--duration-normal) var(--ease-out);
      }

      .panel-content.expanded {
        max-height: 80vh;
        overflow-y: auto;
      }

      .feature-categories {
        padding: var(--space-4);
        display: flex;
        flex-direction: column;
        gap: var(--space-6);
      }

      .category-title {
        margin: 0 0 var(--space-2) 0;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-semibold);
        color: var(--color-primary);
      }

      .category-description {
        margin-bottom: var(--space-4);
        font-size: var(--font-size-xs);
        color: var(--color-text-tertiary);
        line-height: 1.4;
      }

      .feature-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
      }

      .feature-item {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: var(--space-3);
        padding: var(--space-3);
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        transition: all var(--duration-fast) var(--ease-out);
      }

      .feature-item.enabled {
        background: var(--color-success-50);
        border-color: var(--color-success-200);
      }

      .feature-item.disabled {
        background: var(--color-surface-secondary);
        border-color: var(--color-border-subtle);
      }

      .dark .feature-item.enabled {
        background: var(--color-success-900);
        border-color: var(--color-success-700);
      }

      .feature-info {
        flex: 1;
        min-width: 0;
      }

      .feature-label {
        display: block;
        cursor: pointer;
      }

      .feature-name {
        display: block;
        font-weight: var(--font-weight-medium);
        color: var(--color-text-primary);
        margin-bottom: var(--space-1);
      }

      .feature-description {
        display: block;
        font-size: var(--font-size-xs);
        color: var(--color-text-tertiary);
        line-height: 1.3;
      }

      .feature-toggle {
        position: relative;
        flex-shrink: 0;
      }

      .toggle-checkbox {
        position: absolute;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
      }

      .toggle-switch {
        width: 2.5rem;
        height: 1.25rem;
        background: var(--color-border);
        border-radius: var(--radius-full);
        position: relative;
        transition: background-color var(--duration-fast) var(--ease-out);
      }

      .toggle-checkbox:checked + .toggle-switch {
        background: var(--color-primary);
      }

      .toggle-slider {
        position: absolute;
        top: 2px;
        left: 2px;
        width: 1rem;
        height: 1rem;
        background: white;
        border-radius: var(--radius-full);
        transition: transform var(--duration-fast) var(--ease-out);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      }

      .toggle-checkbox:checked + .toggle-switch .toggle-slider {
        transform: translateX(1.25rem);
      }

      .panel-actions {
        display: flex;
        gap: var(--space-3);
        padding: var(--space-4);
        border-top: 1px solid var(--color-border-subtle);
        background: var(--color-surface-secondary);
        border-radius: 0 0 var(--radius-lg) var(--radius-lg);
      }

      .action-btn {
        flex: 1;
        padding: var(--space-2) var(--space-4);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        cursor: pointer;
        transition: all var(--duration-fast) var(--ease-out);
      }

      .action-btn.primary {
        background: var(--color-primary);
        color: var(--color-text-on-primary);
        border-color: var(--color-primary);
      }

      .action-btn.primary:hover {
        background: var(--color-primary-600);
        border-color: var(--color-primary-600);
      }

      .action-btn.secondary {
        background: var(--color-surface);
        color: var(--color-text-primary);
      }

      .action-btn.secondary:hover {
        background: var(--color-surface-elevated);
      }

      /* Mobile adjustments */
      @media (max-width: 768px) {
        .feature-flag-panel {
          position: static;
          width: 100%;
          margin: 1rem;
          max-width: none;
        }
      }

      /* Accessibility */
      .toggle-checkbox:focus-visible + .toggle-switch {
        outline: 2px solid var(--color-primary-focus);
        outline-offset: 2px;
      }

      @media (prefers-reduced-motion: reduce) {
        .panel-content,
        .toggle-icon,
        .toggle-switch,
        .toggle-slider,
        .feature-item,
        .action-btn {
          transition: none;
        }
      }
    `,
  ],
})
export class FeatureFlagPanelComponent {
  private readonly environmentService = inject(EnvironmentService);

  protected isExpanded = false;

  protected readonly featureCategories = [
    {
      title: 'featureFlags.categories.core.title',
      description: 'featureFlags.categories.core.description',
      features: [
        {
          key: 'darkTheme' as keyof FeatureFlags,
          name: 'featureFlags.features.darkTheme.name',
          description: 'featureFlags.features.darkTheme.description',
        },
        {
          key: 'multiLanguage' as keyof FeatureFlags,
          name: 'featureFlags.features.multiLanguage.name',
          description: 'featureFlags.features.multiLanguage.description',
        },
        {
          key: 'notifications' as keyof FeatureFlags,
          name: 'featureFlags.features.notifications.name',
          description: 'featureFlags.features.notifications.description',
        },
        {
          key: 'analytics' as keyof FeatureFlags,
          name: 'featureFlags.features.analytics.name',
          description: 'featureFlags.features.analytics.description',
        },
      ],
    },
    {
      title: 'featureFlags.categories.dashboard.title',
      description: 'featureFlags.categories.dashboard.description',
      features: [
        {
          key: 'dashboardWidgets' as keyof FeatureFlags,
          name: 'featureFlags.features.dashboardWidgets.name',
          description: 'featureFlags.features.dashboardWidgets.description',
        },
        {
          key: 'realtimeUpdates' as keyof FeatureFlags,
          name: 'featureFlags.features.realtimeUpdates.name',
          description: 'featureFlags.features.realtimeUpdates.description',
        },
        {
          key: 'exportData' as keyof FeatureFlags,
          name: 'featureFlags.features.exportData.name',
          description: 'featureFlags.features.exportData.description',
        },
        {
          key: 'importData' as keyof FeatureFlags,
          name: 'featureFlags.features.importData.name',
          description: 'featureFlags.features.importData.description',
        },
      ],
    },
    {
      title: 'featureFlags.categories.admin.title',
      description: 'featureFlags.categories.admin.description',
      features: [
        {
          key: 'userManagement' as keyof FeatureFlags,
          name: 'featureFlags.features.userManagement.name',
          description: 'featureFlags.features.userManagement.description',
        },
        {
          key: 'systemSettings' as keyof FeatureFlags,
          name: 'featureFlags.features.systemSettings.name',
          description: 'featureFlags.features.systemSettings.description',
        },
        {
          key: 'auditLogs' as keyof FeatureFlags,
          name: 'featureFlags.features.auditLogs.name',
          description: 'featureFlags.features.auditLogs.description',
        },
        {
          key: 'backupRestore' as keyof FeatureFlags,
          name: 'featureFlags.features.backupRestore.name',
          description: 'featureFlags.features.backupRestore.description',
        },
      ],
    },
    {
      title: 'featureFlags.categories.experimental.title',
      description: 'featureFlags.categories.experimental.description',
      features: [
        {
          key: 'experimentalUI' as keyof FeatureFlags,
          name: 'featureFlags.features.experimentalUI.name',
          description: 'featureFlags.features.experimentalUI.description',
        },
        {
          key: 'betaFeatures' as keyof FeatureFlags,
          name: 'featureFlags.features.betaFeatures.name',
          description: 'featureFlags.features.betaFeatures.description',
        },
        {
          key: 'debugMode' as keyof FeatureFlags,
          name: 'featureFlags.features.debugMode.name',
          description: 'featureFlags.features.debugMode.description',
        },
        {
          key: 'performanceMonitoring' as keyof FeatureFlags,
          name: 'featureFlags.features.performanceMonitoring.name',
          description: 'featureFlags.features.performanceMonitoring.description',
        },
      ],
    },
  ];

  protected togglePanel(): void {
    this.isExpanded = !this.isExpanded;
  }

  protected isFeatureEnabled(feature: keyof FeatureFlags): boolean {
    return this.environmentService.isFeatureEnabled(feature);
  }

  protected toggleFeature(feature: keyof FeatureFlags, event: Event): void {
    const checkbox = event.target as HTMLInputElement;
    this.environmentService.toggleFeature(feature, checkbox.checked);
  }

  protected resetToDefaults(): void {
    // This would reset all feature flags to their default values
    // Implementation depends on your requirements
    console.log('Reset to defaults - implementation needed');
  }

  protected async reloadConfiguration(): Promise<void> {
    try {
      await this.environmentService.reloadConfiguration();
    } catch (error) {
      console.error('Failed to reload configuration:', error);
    }
  }
}
