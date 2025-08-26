import type { Meta, StoryObj } from '@storybook/angular';
import { applicationConfig, componentWrapperDecorator } from '@storybook/angular';
import { provideRouter } from '@angular/router';
import { provideAnimations } from '@angular/platform-browser/animations';
import { importProvidersFrom } from '@angular/core';
import { HttpClientModule } from '@angular/common/http';
import { ReactiveFormsModule } from '@angular/forms';

import { RegisterPage } from './register.page';
import { TranslatePipe } from '../../../shared/pipes/translate.pipe';

// Mock services
const mockAuthV5Service = {
  isAuthenticated: () => false,
  register: (data: any) => {
    return new Promise((resolve, reject) => {
      setTimeout(() => {
        if (data.email === 'existing@example.com') {
          reject({ message: 'Email already exists' });
        } else {
          resolve({
            success: true,
            message: 'Account created successfully'
          });
        }
      }, 1000);
    });
  }
};

const mockTranslationService = {
  get: (key: string) => {
    const translations: Record<string, string> = {
      'auth.hero.join': 'Join Boukii V5',
      'auth.register.title': 'Create Account',
      'auth.register.subtitle': 'Join Boukii V5',
      'auth.register.confirmPassword': 'Confirm password',
      'auth.common.email': 'Email',
      'auth.common.password': 'Password',
      'auth.common.signup': 'Sign up',
      'auth.common.signin': 'Sign in',
      'auth.common.haveAccount': 'Already have an account?',
      'auth.common.showPassword': 'Show password',
      'auth.common.hidePassword': 'Hide password',
      'auth.name.label': 'Full Name',
      'auth.name.placeholder': 'Enter your full name',
      'auth.errors.requiredName': 'Name is required',
      'auth.errors.requiredEmail': 'Email is required',
      'auth.errors.invalidEmail': 'Please enter a valid email',
      'auth.errors.requiredPassword': 'Password is required',
      'auth.errors.passwordsNoMatch': "Passwords don't match",
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

const meta: Meta<RegisterPage> = {
  title: 'Pages/Auth/Register',
  component: RegisterPage,
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component: 'Registration page with form validation, password confirmation, and unified AuthLayout.',
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
type Story = StoryObj<RegisterPage>;

// Default Light Theme
export const DefaultLight: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Default registration page in light theme with all form fields.',
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
        story: 'Registration page in dark theme with automatic color adaptation.',
      },
    },
  },
};

// Validation Errors
export const WithValidationErrors: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Registration form showing various validation error states.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const nameInput = canvas.querySelector('#registerName') as HTMLInputElement;
    const emailInput = canvas.querySelector('#registerEmail') as HTMLInputElement;
    const passwordInput = canvas.querySelector('#registerPassword') as HTMLInputElement;
    const confirmPasswordInput = canvas.querySelector('#registerConfirmPassword') as HTMLInputElement;
    const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;

    if (nameInput && emailInput && passwordInput && confirmPasswordInput && submitButton) {
      // Enter invalid data
      nameInput.value = 'A'; // Too short
      nameInput.dispatchEvent(new Event('input', { bubbles: true }));
      nameInput.blur();

      emailInput.value = 'invalid-email';
      emailInput.dispatchEvent(new Event('input', { bubbles: true }));
      emailInput.blur();

      passwordInput.value = '123'; // Too short
      passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
      passwordInput.blur();

      confirmPasswordInput.value = '456'; // Doesn't match
      confirmPasswordInput.dispatchEvent(new Event('input', { bubbles: true }));
      confirmPasswordInput.blur();

      // Submit to trigger validation
      setTimeout(() => {
        submitButton.click();
      }, 100);
    }
  },
};

// Password Mismatch
export const PasswordMismatch: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Demonstrates password confirmation validation when passwords do not match.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const passwordInput = canvas.querySelector('#registerPassword') as HTMLInputElement;
    const confirmPasswordInput = canvas.querySelector('#registerConfirmPassword') as HTMLInputElement;

    if (passwordInput && confirmPasswordInput) {
      // Enter valid password
      passwordInput.value = 'password123';
      passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
      passwordInput.blur();

      // Enter different confirmation
      confirmPasswordInput.value = 'differentPassword';
      confirmPasswordInput.dispatchEvent(new Event('input', { bubbles: true }));
      confirmPasswordInput.blur();
    }
  },
};

// Loading State
export const LoadingState: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Registration form in loading state during account creation.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const nameInput = canvas.querySelector('#registerName') as HTMLInputElement;
    const emailInput = canvas.querySelector('#registerEmail') as HTMLInputElement;
    const passwordInput = canvas.querySelector('#registerPassword') as HTMLInputElement;
    const confirmPasswordInput = canvas.querySelector('#registerConfirmPassword') as HTMLInputElement;
    const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;

    if (nameInput && emailInput && passwordInput && confirmPasswordInput && submitButton) {
      // Fill valid form data
      nameInput.value = 'John Doe';
      nameInput.dispatchEvent(new Event('input', { bubbles: true }));
      
      emailInput.value = 'john@example.com';
      emailInput.dispatchEvent(new Event('input', { bubbles: true }));
      
      passwordInput.value = 'securePassword123';
      passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
      
      confirmPasswordInput.value = 'securePassword123';
      confirmPasswordInput.dispatchEvent(new Event('input', { bubbles: true }));

      // Submit to show loading state
      setTimeout(() => {
        submitButton.click();
      }, 500);
    }
  },
};

// Password Visibility Demo
export const PasswordVisibilityDemo: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Demonstrates independent password visibility toggles for both password fields.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const passwordInput = canvas.querySelector('#registerPassword') as HTMLInputElement;
    const confirmPasswordInput = canvas.querySelector('#registerConfirmPassword') as HTMLInputElement;
    const passwordToggle = passwordInput?.parentElement?.querySelector('button') as HTMLButtonElement;
    const confirmToggle = confirmPasswordInput?.parentElement?.querySelector('button') as HTMLButtonElement;

    if (passwordInput && confirmPasswordInput) {
      // Enter passwords
      passwordInput.value = 'mySecretPassword';
      passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
      
      confirmPasswordInput.value = 'mySecretPassword';
      confirmPasswordInput.dispatchEvent(new Event('input', { bubbles: true }));
      
      // Toggle password visibility
      setTimeout(() => {
        passwordToggle?.click();
      }, 1000);
      
      setTimeout(() => {
        confirmToggle?.click();
      }, 2000);
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
        story: 'Registration page optimized for mobile devices.',
      },
    },
  },
};

// Successful Registration Flow
export const SuccessfulRegistration: Story = {
  decorators: [themeDecorator('light')],
  parameters: {
    docs: {
      description: {
        story: 'Complete registration flow with valid data and successful submission.',
      },
    },
  },
  play: async ({ canvasElement }) => {
    const canvas = canvasElement;
    const nameInput = canvas.querySelector('#registerName') as HTMLInputElement;
    const emailInput = canvas.querySelector('#registerEmail') as HTMLInputElement;
    const passwordInput = canvas.querySelector('#registerPassword') as HTMLInputElement;
    const confirmPasswordInput = canvas.querySelector('#registerConfirmPassword') as HTMLInputElement;
    const submitButton = canvas.querySelector('button[type="submit"]') as HTMLButtonElement;

    if (nameInput && emailInput && passwordInput && confirmPasswordInput && submitButton) {
      // Fill valid form data
      nameInput.value = 'Jane Smith';
      nameInput.dispatchEvent(new Event('input', { bubbles: true }));
      
      emailInput.value = 'jane@example.com';
      emailInput.dispatchEvent(new Event('input', { bubbles: true }));
      
      passwordInput.value = 'strongPassword123!';
      passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
      
      confirmPasswordInput.value = 'strongPassword123!';
      confirmPasswordInput.dispatchEvent(new Event('input', { bubbles: true }));

      // Submit successful registration
      setTimeout(() => {
        submitButton.click();
      }, 500);
    }
  },
};