import type { Meta, StoryObj } from '@storybook/angular';
import { applicationConfig, componentWrapperDecorator } from '@storybook/angular';
import { provideRouter } from '@angular/router';
import { provideAnimations } from '@angular/platform-browser/animations';
import { importProvidersFrom } from '@angular/core';
import { HttpClientModule } from '@angular/common/http';
import { ReactiveFormsModule } from '@angular/forms';

import { ForgotPasswordPage } from './forgot-password.page';
import { TranslatePipe } from '../../../shared/pipes/translate.pipe';

// Mock services
const mockAuthV5Service = {
  isAuthenticated: () => false,
  forgotPassword: (_email: string) => {
    return new Promise((resolve, _reject) => {
      setTimeout(() => {
        // Always resolve for security (don't reveal if email exists)
        resolve({
          success: true,
          message: 'Password reset link sent'
        });
      }, 1000);
    });
  }
};

const mockTranslationService = {
  get: (key: string) => {
    const translations: Record<string, string> = {
      'auth.forgotPassword.welcome.title': 'Recover Your Access',
      'auth.forgotPassword.title': 'Reset Password',
      'auth.forgotPassword.subtitle': 'We\'ll send you a recovery link to your email',
      'auth.forgotPassword.button': 'Send Reset Link',
      'auth.forgotPassword.successTitle': 'Link Sent!',
      'auth.forgotPassword.emailSent': 'If an account with that email exists, we\'ve sent instructions.',
      'auth.forgotPassword.checkSpam': 'If you don\'t see the email, check your spam folder.',
      'auth.forgotPassword.sendAnother': 'Send Another Link',
      'auth.forgotPassword.rememberedPassword': 'Remembered your password?',
      'auth.common.email': 'Email',
      'auth.errors.requiredEmail': 'Email is required',
      'auth.errors.invalidEmail': 'Please enter a valid email',
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

const meta: Meta<ForgotPasswordPage> = {
  title: 'Pages/Auth/ForgotPassword',
  component: ForgotPasswordPage,
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component: 'Forgot password page with email form and success state, following security best practices.',
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
};

export default meta;
type Story = StoryObj<ForgotPasswordPage>;

// Default Light Theme
export const DefaultLight: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Default forgot password page in light theme with email input form.',
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
        story: 'Forgot password page in dark theme with consistent styling.',
      },
    },
  },
};

// Validation Error
export const WithValidationError: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Shows validation error for invalid email format.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const emailInput = canvas.querySelector('#forgotPasswordEmail') as HTMLInputElement;
    const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;

    if (emailInput && submitButton) {
      // Enter invalid email
      emailInput.value = 'invalid-email-format';
      emailInput.dispatchEvent(new Event('input', { bubbles: true }));
      emailInput.blur();

      // Try to submit
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
        story: 'Forgot password form in loading state while sending reset link.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const emailInput = canvas.querySelector('#forgotPasswordEmail') as HTMLInputElement;
    const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;

    if (emailInput && submitButton) {
      // Fill valid email
      emailInput.value = 'user@example.com';
      emailInput.dispatchEvent(new Event('input', { bubbles: true }));

      // Submit to show loading state
      setTimeout(() => {
        submitButton.click();
      }, 500);
    }
  },
};

// Success State
export const SuccessState: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Success state showing confirmation that reset link was sent.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const emailInput = canvas.querySelector('#forgotPasswordEmail') as HTMLInputElement;
    const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;

    if (emailInput && submitButton) {
      // Fill valid email and submit
      emailInput.value = 'user@example.com';
      emailInput.dispatchEvent(new Event('input', { bubbles: true }));
      
      submitButton.click();
      
      // Wait for success state to show
      setTimeout(() => {
        // Success state should be visible now
      }, 1500);
    }
  },
};

// Success State Dark Theme
export const SuccessStateDark: Story = {
  decorators: [themeDecorator('dark')],
  parameters: {
    docs: {
      description: {
        story: 'Success state in dark theme with proper color adaptation.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const emailInput = canvas.querySelector('#forgotPasswordEmail') as HTMLInputElement;
    const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;

    if (emailInput && submitButton) {
      emailInput.value = 'user@example.com';
      emailInput.dispatchEvent(new Event('input', { bubbles: true }));
      submitButton.click();
    }
  },
};

// Empty Email Error
export const EmptyEmailError: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Shows required field validation when attempting to submit empty email.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;

    if (submitButton) {
      // Try to submit without entering email
      setTimeout(() => {
        submitButton.click();
      }, 100);
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
        story: 'Forgot password page optimized for mobile devices.',
      },
    },
  },
};

// Complete Flow Demonstration
export const CompleteFlow: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Demonstrates the complete forgot password flow from form to success state.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const emailInput = canvas.querySelector('#forgotPasswordEmail') as HTMLInputElement;
    const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;

    if (emailInput && submitButton) {
      // Step 1: Fill email
      setTimeout(() => {
        emailInput.value = 'demo@example.com';
        emailInput.dispatchEvent(new Event('input', { bubbles: true }));
      }, 500);

      // Step 2: Submit form
      setTimeout(() => {
        submitButton.click();
      }, 1500);

      // Step 3: Success state will be shown automatically
      // Step 4: After success, show send another option
      setTimeout(() => {
        const sendAnotherButton = canvas.querySelector('button:contains("Send Another Link")') as HTMLButtonElement;
        if (sendAnotherButton) {
          sendAnotherButton.click();
        }
      }, 3500);
    }
  },
};

// Accessibility Focus Test
export const AccessibilityFocus: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Tests keyboard navigation and focus management.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const emailInput = canvas.querySelector('#forgotPasswordEmail') as HTMLInputElement;

    if (emailInput) {
      // Focus the email input
      emailInput.focus();
      
      // Simulate tab navigation
      setTimeout(() => {
        const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;
        submitButton?.focus();
      }, 1000);
      
      setTimeout(() => {
        const backToLoginLink = canvas.querySelector('a[href*="/auth/login"]') as HTMLAnchorElement;
        backToLoginLink?.focus();
      }, 2000);
    }
  },
};