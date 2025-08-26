import type { Meta, StoryObj } from '@storybook/angular';
import { moduleMetadata, applicationConfig } from '@storybook/angular';
import { provideAnimations } from '@angular/platform-browser/animations';
import { ThemeToggleComponent } from './theme-toggle.component';

const meta: Meta<ThemeToggleComponent> = {
  title: 'UI/ThemeToggle',
  component: ThemeToggleComponent,
  decorators: [
    moduleMetadata({
      imports: [ThemeToggleComponent],
    }),
    applicationConfig({
      providers: [provideAnimations()],
    }),
  ],
  parameters: {
    layout: 'centered',
    docs: {
      description: {
        component: `
Theme Toggle Component allows users to switch between light and dark themes.

## Features
- Light/Dark theme options
- Dropdown menu with theme selection
- Quick toggle between light and dark
- Smooth transitions
- Accessible keyboard navigation

## Usage
\`\`\`html
<app-theme-toggle></app-theme-toggle>
\`\`\`
        `,
      },
    },
  },
  argTypes: {
    // No direct inputs as this component manages its own state
  },
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<ThemeToggleComponent>;

export const Default: Story = {
  render: () => ({
    template: `
      <div class="story-container">
        <div class="component-showcase">
          <h3>Theme Toggle</h3>
          <p>Click to change themes</p>
          <app-theme-toggle></app-theme-toggle>
        </div>
      </div>
    `,
  }),
  parameters: {
    docs: {
      description: {
        story: 'Default theme toggle with dropdown menu for theme selection.',
      },
    },
  },
};

export const LightTheme: Story = {
  render: () => ({
    template: `
      <div class="story-container">
        <div class="component-showcase">
          <h3>Light Theme</h3>
          <p>Theme toggle in light mode</p>
          <app-theme-toggle></app-theme-toggle>
        </div>
      </div>
    `,
  }),
  parameters: {
    backgrounds: { default: 'light' },
    docs: {
      description: {
        story: 'Theme toggle displayed in light theme context.',
      },
    },
  },
};

export const DarkTheme: Story = {
  render: () => ({
    template: `
      <div class="story-container dark">
        <div class="component-showcase">
          <h3>Dark Theme</h3>
          <p>Theme toggle in dark mode</p>
          <app-theme-toggle></app-theme-toggle>
        </div>
      </div>
    `,
  }),
  parameters: {
    backgrounds: { default: 'dark' },
    docs: {
      description: {
        story: 'Theme toggle displayed in dark theme context.',
      },
    },
  },
};

export const Interactive: Story = {
  render: () => ({
    template: `
      <div class="story-container">
        <div class="component-showcase">
          <h3>Interactive Demo</h3>
          <p>Try switching themes and see the changes in real-time</p>
          <div style="display: flex; gap: 1rem; align-items: center;">
            <app-theme-toggle></app-theme-toggle>
            <span>Current theme will be applied globally</span>
          </div>
          
          <div style="margin-top: 2rem; padding: 1rem; border: 1px solid var(--color-border); border-radius: 8px; background: var(--color-surface);">
            <h4>Theme-aware content</h4>
            <p>This content adapts to the selected theme:</p>
            <ul>
              <li>Background: <code>var(--color-surface)</code></li>
              <li>Text: <code>var(--color-text-primary)</code></li>
              <li>Border: <code>var(--color-border)</code></li>
            </ul>
          </div>
        </div>
      </div>
    `,
  }),
  parameters: {
    docs: {
      description: {
        story: 'Interactive demo showing how theme changes affect the entire interface.',
      },
    },
  },
};

export const Playground: Story = {
  render: () => ({
    template: `
      <div class="story-container">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
          <div class="component-showcase">
            <h4>Default State</h4>
            <app-theme-toggle></app-theme-toggle>
          </div>
          
          <div class="component-showcase">
            <h4>With Custom Container</h4>
            <div style="padding: 1rem; background: var(--color-surface-elevated); border-radius: 8px;">
              <app-theme-toggle></app-theme-toggle>
            </div>
          </div>
          
          <div class="component-showcase">
            <h4>Multiple Instances</h4>
            <div style="display: flex; gap: 1rem;">
              <app-theme-toggle></app-theme-toggle>
              <app-theme-toggle></app-theme-toggle>
            </div>
            <p style="font-size: 0.875rem; color: var(--color-text-secondary); margin-top: 0.5rem;">
              Multiple instances share the same theme state
            </p>
          </div>
        </div>
      </div>
    `,
  }),
  parameters: {
    docs: {
      description: {
        story: 'Playground with different usage scenarios and configurations.',
      },
    },
  },
};
