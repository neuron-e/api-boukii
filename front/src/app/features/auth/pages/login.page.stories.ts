import type { Meta, StoryObj } from '@storybook/angular';
import { applicationConfig, componentWrapperDecorator } from '@storybook/angular';
import { provideRouter } from '@angular/router';
import { provideAnimations } from '@angular/platform-browser/animations';
import { importProvidersFrom } from '@angular/core';
import { HttpClientModule } from '@angular/common/http';
import { ReactiveFormsModule } from '@angular/forms';

import { LoginPage } from './login.page';
import { TranslatePipe } from '../../../shared/pipes/translate.pipe';

// Mock services
const mockAuthV5Service = {
  isAuthenticated: () => false,
  checkUser: (credentials: any) => {
    // Simulate API response
    return new Promise((resolve) => {
      setTimeout(() => {
        resolve({
          success: true,
          data: {
            user: { id: 1, name: 'Test User', email: credentials.email },
            schools: [
              { id: 1, name: 'Swimming School A' },
              { id: 2, name: 'Swimming School B' }
            ],
            temp_token: 'mock-temp-token'
          }
        });
      }, 1000);
    });
  }
};

const mockTranslationService = {
  get: (key: string) => {
    const translations: Record<string, string> = {
      'auth.hero.welcomeBack': 'Welcome back',
      'auth.login.title': 'Sign In',
      'auth.login.subtitle': 'Enter your credentials to continue',
      'auth.common.email': 'Email',
      'auth.common.password': 'Password',
      'auth.common.signin': 'Sign in',
      'auth.common.signup': 'Sign up',
      'auth.common.forgot': 'Forgot your password?',
      'auth.common.noAccount': "Don't have an account?",
      'auth.common.showPassword': 'Show password',
      'auth.common.hidePassword': 'Hide password',
      'auth.errors.requiredEmail': 'Email is required',
      'auth.errors.invalidEmail': 'Please enter a valid email',
      'auth.errors.requiredPassword': 'Password is required',
      'auth.hero.feature1': 'Complete Management',
      'auth.hero.feature1Desc': 'Management, bookings and more.',
      'auth.hero.feature2': 'Multi-Season',
      'auth.hero.feature2Desc': 'Organize by seasons.',
      'auth.hero.feature3': 'Professionalization',
      'auth.hero.feature3Desc': 'Professional tools.',
      'common.loading': 'Loading...'
    };
    return translations[key] || key;
  }
};

const mockToastService = {
  success: (message: string) => console.log('Success:', message),
  error: (message: string) => console.log('Error:', message)
};

// Theme decorator
const themeDecorator = (theme: 'light' | 'dark') =>
  componentWrapperDecorator(
    (story) => `
      <div class="${theme === 'dark' ? 'dark' : ''}" style="min-height: 100vh; background: var(--bg);">
        ${story}
      </div>
    `
  );

const meta: Meta<LoginPage> = {
  title: 'Pages/Auth/Login',
  component: LoginPage,
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component: 'Login page with unified AuthLayout, dark/light theme support, and full accessibility.',
      },
    },
  },
  decorators: [
    applicationConfig({
      providers: [
        provideRouter([]),
        provideAnimations(),
        importProvidersFrom(HttpClientModule, ReactiveFormsModule),
        { provide: 'AuthV5Service', useValue: mockAuthV5Service },
        { provide: 'TranslationService', useValue: mockTranslationService },
        { provide: 'ToastService', useValue: mockToastService },
        TranslatePipe
      ],
    }),
  ],
  argTypes: {
    // No direct inputs for this page component
  },
};

export default meta;
type Story = StoryObj<LoginPage>;

// Default Light Theme
export const DefaultLight: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Default login page appearance in light theme with clean, minimal design.',
      },
    },
  },
};

// Default Dark Theme  
export const DefaultDark: Story = {
  decorators: [themeDecorator('dark')],
  parameters: {
    docs: {
      description: {
        story: 'Login page in dark theme - all colors adapt using CSS custom properties.',
      },
    },
  },
};

// Error States
export const WithValidationErrors: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Login page showing validation error states for invalid email and password.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    // Simulate user interaction to show validation errors
    const canvas = canvasElement;
    const emailInput = canvas.querySelector('#loginEmail') as HTMLInputElement;
    const passwordInput = canvas.querySelector('#loginPassword') as HTMLInputElement;
    const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;

    if (emailInput && passwordInput && submitButton) {
      // Enter invalid email
      emailInput.value = 'invalid-email';
      emailInput.dispatchEvent(new Event('input', { bubbles: true }));
      emailInput.blur();

      // Enter short password  
      passwordInput.value = '123';
      passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
      passwordInput.blur();

      // Try to submit to trigger validation
      setTimeout(() => {
        submitButton.click();
      }, 100);
    }
  },
};

// Loading State
export const LoadingState: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Login page in loading state after form submission.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const emailInput = canvas.querySelector('#loginEmail') as HTMLInputElement;
    const passwordInput = canvas.querySelector('#loginPassword') as HTMLInputElement;
    const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;

    if (emailInput && passwordInput && submitButton) {
      // Fill valid form data
      emailInput.value = 'test@example.com';
      emailInput.dispatchEvent(new Event('input', { bubbles: true }));
      
      passwordInput.value = 'password123';
      passwordInput.dispatchEvent(new Event('input', { bubbles: true }));

      // Submit to show loading state
      setTimeout(() => {
        submitButton.click();
      }, 500);
    }
  },
};

// Password Visibility Toggle
export const PasswordToggleDemo: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Demonstrates password visibility toggle functionality with proper aria attributes.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const passwordInput = canvas.querySelector('#loginPassword') as HTMLInputElement;
    const toggleButton = canvas.querySelector('.password-toggle') as HTMLButtonElement;

    if (passwordInput && toggleButton) {
      // Enter password
      passwordInput.value = 'mySecretPassword';
      passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
      
      // Click toggle to show password
      setTimeout(() => {
        toggleButton.click();
      }, 1000);
    }
  },
};

// Mobile View
export const MobileView: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    viewport: {
      defaultViewport: 'mobile1',
    },
    docs: {
      description: {
        story: 'Login page optimized for mobile devices with responsive layout.',
      },
    },
  },
};

// High Contrast / Accessibility
export const HighContrast: Story = {
  decorators: [
    componentWrapperDecorator(
      (story) => `
        <div style="min-height: 100vh; background: var(--bg); filter: contrast(1.5);">
          ${story}
        </div>
      `
    ),
  ],
  parameters: {
    docs: {
      description: {
        story: 'Login page with enhanced contrast for accessibility testing.',
      },
    },
  },
};