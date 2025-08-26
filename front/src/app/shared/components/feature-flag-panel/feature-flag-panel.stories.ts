import type { Meta, StoryObj } from '@storybook/angular';
import { moduleMetadata, applicationConfig } from '@storybook/angular';
import { provideAnimations } from '@angular/platform-browser/animations';
import { provideHttpClient } from '@angular/common/http';
import { FeatureFlagPanelComponent } from './feature-flag-panel.component';

const meta: Meta<FeatureFlagPanelComponent> = {
  title: 'Shared/FeatureFlagPanel',
  component: FeatureFlagPanelComponent,
  decorators: [
    moduleMetadata({
      imports: [FeatureFlagPanelComponent],
    }),
    applicationConfig({
      providers: [provideAnimations(), provideHttpClient()],
    }),
  ],
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component: `
Feature Flag Panel is a development tool for toggling feature flags in real-time.

## Features
- Categorized feature flags (Core, Dashboard, Admin, Experimental)
- Real-time toggle functionality
- Visual indicators for enabled/disabled states
- Configuration reload capabilities
- Collapsible interface
- Development-only visibility (debug mode)

## Usage
\`\`\`html
<!-- Only shown when debugMode feature flag is enabled -->
<app-feature-flag-panel></app-feature-flag-panel>
\`\`\`

## Categories
- **Core Features**: Basic application functionality
- **Dashboard**: Analytics and dashboard features  
- **Administration**: System administration tools
- **Experimental**: Development and debugging features
        `,
      },
    },
  },
  argTypes: {
    // Component manages its own state internally
  },
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<FeatureFlagPanelComponent>;

export const Default: Story = {
  render: () => ({
    template: `
      <div style="position: relative; height: 100vh; background: var(--color-background); padding: 1rem;">
        <div style="max-width: 800px; margin: 0 auto;">
          <h2>Feature Flag Panel Demo</h2>
          <p>The feature flag panel appears in the top-right corner when debug mode is enabled.</p>
          
          <div style="margin: 2rem 0; padding: 1rem; border: 1px solid var(--color-border); border-radius: 8px; background: var(--color-surface);">
            <h3>Sample Application Content</h3>
            <p>This represents your main application content. The feature flag panel floats above it.</p>
            
            <div style="display: grid; gap: 1rem; margin-top: 1rem;">
              <div style="padding: 1rem; background: var(--color-surface-secondary); border-radius: 6px;">
                <h4>Dashboard Widget</h4>
                <p>This widget is controlled by the 'dashboardWidgets' feature flag.</p>
              </div>
              
              <div style="padding: 1rem; background: var(--color-surface-secondary); border-radius: 6px;">
                <h4>Analytics Section</h4>
                <p>This section is controlled by the 'analytics' feature flag.</p>
              </div>
              
              <div style="padding: 1rem; background: var(--color-surface-secondary); border-radius: 6px;">
                <h4>Experimental Feature</h4>
                <p>This feature is controlled by the 'experimentalUI' feature flag.</p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Feature Flag Panel -->
        <app-feature-flag-panel></app-feature-flag-panel>
      </div>
    `,
  }),
  parameters: {
    docs: {
      description: {
        story: 'Default feature flag panel positioned in the top-right corner of the viewport.',
      },
    },
  },
};

export const Expanded: Story = {
  render: () => ({
    template: `
      <div style="position: relative; height: 100vh; background: var(--color-background); padding: 1rem;">
        <div style="max-width: 600px; margin: 0 auto;">
          <h2>Feature Flag Panel - Expanded View</h2>
          <p>Click the toggle icon to expand/collapse the panel and see all available feature flags.</p>
          
          <div style="margin: 2rem 0; padding: 1rem; border: 1px solid var(--color-border); border-radius: 8px; background: var(--color-surface);">
            <h3>Interactive Demo</h3>
            <p>Toggle different feature flags and observe the changes:</p>
            
            <ul style="color: var(--color-text-secondary);">
              <li><strong>Dark Theme</strong>: Changes the color scheme</li>
              <li><strong>Multi Language</strong>: Enables language switching</li>
              <li><strong>Dashboard Widgets</strong>: Shows/hides dashboard components</li>
              <li><strong>Analytics</strong>: Enables tracking and metrics</li>
              <li><strong>Debug Mode</strong>: Shows development tools</li>
            </ul>
          </div>
        </div>
        
        <app-feature-flag-panel></app-feature-flag-panel>
      </div>
    `,
  }),
  parameters: {
    docs: {
      description: {
        story: 'Feature flag panel with expanded view showing all categories and toggles.',
      },
    },
  },
};

export const DarkTheme: Story = {
  render: () => ({
    template: `
      <div class="dark" style="position: relative; height: 100vh; background: var(--color-background); padding: 1rem;">
        <div style="max-width: 600px; margin: 0 auto;">
          <h2>Feature Flag Panel - Dark Theme</h2>
          <p>The feature flag panel adapts to the current theme automatically.</p>
          
          <div style="margin: 2rem 0; padding: 1rem; border: 1px solid var(--color-border); border-radius: 8px; background: var(--color-surface);">
            <h3>Dark Theme Context</h3>
            <p>Notice how the panel styling adapts to match the dark theme:</p>
            
            <ul style="color: var(--color-text-secondary);">
              <li>Background colors adjust to dark variants</li>
              <li>Text colors maintain proper contrast</li>
              <li>Border colors are theme-appropriate</li>
              <li>Toggle switches use theme colors</li>
            </ul>
          </div>
        </div>
        
        <app-feature-flag-panel></app-feature-flag-panel>
      </div>
    `,
  }),
  parameters: {
    backgrounds: { default: 'dark' },
    docs: {
      description: {
        story: 'Feature flag panel displayed in dark theme context.',
      },
    },
  },
};

export const Mobile: Story = {
  render: () => ({
    template: `
      <div style="position: relative; height: 100vh; background: var(--color-background); padding: 0.5rem;">
        <div style="max-width: 100%;">
          <h2 style="font-size: 1.25rem;">Mobile View</h2>
          <p style="font-size: 0.875rem;">On mobile devices, the feature flag panel adapts its layout and positioning.</p>
          
          <div style="margin: 1rem 0; padding: 1rem; border: 1px solid var(--color-border); border-radius: 8px; background: var(--color-surface);">
            <h3 style="font-size: 1rem;">Mobile Layout</h3>
            <p style="font-size: 0.875rem;">The panel becomes:</p>
            
            <ul style="font-size: 0.875rem; color: var(--color-text-secondary);">
              <li>Full-width on small screens</li>
              <li>Positioned at the top</li>
              <li>Optimized touch targets</li>
              <li>Compact toggle switches</li>
            </ul>
          </div>
        </div>
        
        <app-feature-flag-panel></app-feature-flag-panel>
      </div>
    `,
  }),
  parameters: {
    viewport: {
      defaultViewport: 'mobile',
    },
    docs: {
      description: {
        story: 'Feature flag panel optimized for mobile devices with responsive layout.',
      },
    },
  },
};

export const Integration: Story = {
  render: () => ({
    template: `
      <div style="position: relative; height: 100vh; background: var(--color-background);">
        <!-- Simulated App Header -->
        <header style="background: var(--color-surface-elevated); padding: 1rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
          <h1 style="margin: 0; font-size: 1.25rem;">Boukii Admin V5</h1>
          <div style="display: flex; gap: 1rem; align-items: center;">
            <app-language-selector></app-language-selector>
            <app-theme-toggle></app-theme-toggle>
          </div>
        </header>
        
        <!-- Main Content -->
        <main style="padding: 2rem;">
          <h2>Feature Flag Integration Demo</h2>
          <p>This demo shows how the feature flag panel integrates with other components:</p>
          
          <div style="display: grid; gap: 1.5rem; margin-top: 2rem;">
            <div style="padding: 1.5rem; border: 1px solid var(--color-border); border-radius: 8px; background: var(--color-surface);">
              <h3>Component Integration</h3>
              <p>Feature flags can control the visibility and behavior of components:</p>
              
              <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button style="padding: 0.5rem 1rem; border: 1px solid var(--color-border); border-radius: 6px; background: var(--color-surface); color: var(--color-text-primary);">
                  Feature Button
                </button>
                <button style="padding: 0.5rem 1rem; border: 1px solid var(--color-border); border-radius: 6px; background: var(--color-primary); color: white;">
                  Primary Action
                </button>
              </div>
            </div>
            
            <div style="padding: 1.5rem; border: 1px solid var(--color-border); border-radius: 8px; background: var(--color-surface);">
              <h3>Real-time Updates</h3>
              <p>Changes in the feature flag panel immediately affect the application:</p>
              
              <ul style="color: var(--color-text-secondary);">
                <li>No page reload required</li>
                <li>Instant visual feedback</li>
                <li>Perfect for A/B testing</li>
                <li>Development and QA workflows</li>
              </ul>
            </div>
          </div>
        </main>
        
        <!-- Feature Flag Panel -->
        <app-feature-flag-panel></app-feature-flag-panel>
      </div>
    `,
  }),
  parameters: {
    docs: {
      description: {
        story:
          'Feature flag panel integrated with a full application layout including header and navigation.',
      },
    },
  },
};
