import type { Meta, StoryObj } from '@storybook/angular';
import { moduleMetadata, applicationConfig } from '@storybook/angular';
import { provideAnimations } from '@angular/platform-browser/animations';
import { provideRouter } from '@angular/router';
import { within, userEvent } from '@storybook/test';
import { signal, computed } from '@angular/core';

import { AppShellComponent } from './app-shell.component';
import { UiStore } from '@core/stores/ui.store';
import { AuthStore } from '@core/stores/auth.store';
import { LoadingStore } from '@core/stores/loading.store';
import { AuthV5Service } from '@core/services/auth-v5.service';
import { TranslationService, SupportedLanguage } from '@core/services/translation.service';
import { EnvironmentService } from '@core/services/environment.service';

class MockTranslationService {
  private langSignal = signal<SupportedLanguage>('es');
  currentLanguage = computed(() => this.langSignal());
  setLanguage(lang: SupportedLanguage) { 
    this.langSignal.set(lang); 
    // Persist to localStorage for the mock
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('language', lang);
    }
  }
  get(key: string) { return key; }
  instant(key: string) { return key; }
}

class MockAuthV5Service {
  user = computed(() => ({ name: 'Usuario Demo', email: 'demo@boukii.com' }));
  permissions = computed(() => ['admin']);
  isAuthenticated() { return true; }
  currentSchool() { return { id: 1, name: 'Escuela Demo' }; }
  currentSchoolIdSignal() { return signal(1); }
  logout() { console.log('Logout called'); }
}

class MockUiStore {
  private themeSignal = signal<'light' | 'dark'>('light');
  sidebarCollapsed = signal(false);
  theme = computed(() => this.themeSignal());
  isDark = computed(() => this.themeSignal() === 'dark');

  initFromStorage() {}
  toggleTheme() {
    const newTheme = this.themeSignal() === 'light' ? 'dark' : 'light';
    this.themeSignal.set(newTheme);
    const isDark = newTheme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    document.body.classList.toggle('dark', isDark);
  }

  toggleSidebar() {
    this.sidebarCollapsed.set(!this.sidebarCollapsed());
  }

  
  initTheme() {
    const isDark = this.themeSignal() === 'dark';
    document.body.classList.toggle('dark', isDark);
  }

  initializeTheme() {
    const isDark = this.themeSignal() === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
  }
}

const meta: Meta<AppShellComponent> = {
  title: 'App/AppShell',
  component: AppShellComponent,
  decorators: [
    moduleMetadata({ imports: [AppShellComponent] }),
    applicationConfig({
      providers: [
        provideAnimations(),
        provideRouter([]),
        { provide: UiStore, useClass: MockUiStore },
        { provide: AuthStore, useValue: { loadMe: () => {} } },
        { provide: LoadingStore, useValue: { isLoading: () => false, longestRunningRequest: () => null } },
        { provide: AuthV5Service, useClass: MockAuthV5Service },
        { provide: TranslationService, useClass: MockTranslationService },
        { provide: EnvironmentService, useValue: { envName: () => 'production', isProduction: () => true } },
      ],
    }),
  ],
  parameters: { 
    layout: 'fullscreen',
    docs: {
      description: {
        component: 'Main application shell with navbar, sidebar, and layout management. Supports theme switching, language selection, and responsive design.'
      }
    }
  },
};
export default meta;

type Story = StoryObj<AppShellComponent>;

// Default expanded state
export const Default: Story = {
  parameters: {
    docs: {
      description: {
        story: 'Default AppShell with expanded sidebar in light theme.'
      }
    }
  }
};

// Collapsed sidebar
export const SidebarCollapsed: Story = {
  name: '🔔 Collapsed badge dot',
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    // Click the sidebar collapse button
    const collapseBtn = canvas.getByLabelText('nav.collapse');
    await userEvent.click(collapseBtn);
  },
  parameters: {
    docs: {
      description: {
        story: 'AppShell with collapsed sidebar showing badge dots instead of numbers.'
      }
    }
  }
};

// Dark theme
export const DarkTheme: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    // Click the theme toggle button
    const themeBtn = canvas.getByTitle('theme.toggle');
    await userEvent.click(themeBtn);
  },
  parameters: {
    docs: {
      description: {
        story: 'AppShell in dark theme mode.'
      }
    }
  }
};

// Dark theme + collapsed sidebar
export const DarkCollapsed: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    // Toggle theme first
    const themeBtn = canvas.getByTitle('theme.toggle');
    await userEvent.click(themeBtn);

    // Then collapse sidebar
    const collapseBtn = canvas.getByLabelText('nav.collapse');
    await userEvent.click(collapseBtn);
  },
  parameters: {
    docs: {
      description: {
        story: 'AppShell with both dark theme and collapsed sidebar.'
      }
    }
  }
};

// All interactions open (dropdowns)
export const AllInteractionsOpen: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const user = userEvent.setup();
    
    // Open language dropdown
    const languageBtn = canvas.getByTitle('language.title');
    await user.click(languageBtn);
    
    // Wait a bit
    await new Promise(resolve => setTimeout(resolve, 100));
    
    // Open notifications dropdown
    const notificationsBtn = canvas.getByTitle('nav.notifications');
    await user.click(notificationsBtn);
    
    // Wait a bit
    await new Promise(resolve => setTimeout(resolve, 100));
    
    // Open user dropdown
    const userBtn = canvasElement.querySelector('.user-trigger') as HTMLElement;
    if (userBtn) {
      await user.click(userBtn);
    }
  },
  parameters: {
    docs: {
      description: {
        story: 'AppShell with all dropdown menus opened to test z-index and accessibility.'
      }
    }
  }
};

// Environment badge (staging)
export const WithEnvironmentBadge: Story = {
  decorators: [
    applicationConfig({
      providers: [
        { provide: EnvironmentService, useValue: { envName: () => 'staging', isProduction: () => false } },
      ],
    }),
  ],
  parameters: {
    docs: {
      description: {
        story: 'AppShell showing environment badge for non-production environments.'
      }
    }
  }
};