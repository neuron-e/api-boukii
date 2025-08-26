import type { Meta, StoryObj } from '@storybook/angular';
import { applicationConfig, componentWrapperDecorator } from '@storybook/angular';
import { AuthShellComponent } from './auth-shell.component';
import { TranslationService } from '@core/services/translation.service';
import { TranslatePipe } from '../../../../shared/pipes/translate.pipe';

class MockTranslationService {
  currentLanguage() {
    return 'en';
  }
  setLanguage(_: string) {}
  get(key: string) {
    return key;
  }
}

const lightDecorator = componentWrapperDecorator((story) => {
  document.body.classList.remove('dark');
  localStorage.setItem('theme', 'light');
  return `
    <div style="min-height:100vh;background:var(--bg);padding:1rem;">
      ${story}
    </div>
  `;
});

const darkDecorator = componentWrapperDecorator((story) => {
  document.body.classList.add('dark');
  localStorage.setItem('theme', 'dark');
  return `
    <div class="dark" style="min-height:100vh;background:var(--bg);padding:1rem;">
      ${story}
    </div>
  `;
});

const defaultFeatures = [
  {
    icon: `<svg viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/></svg>`,
    title: 'Complete Suite',
    subtitle: 'Everything you need for your sports school',
  },
  {
    icon: `<svg viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>`,
    title: 'Advanced Analytics',
    subtitle: 'Metrics and reports in real time',
  },
  {
    icon: `<svg viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>`,
    title: 'Total Security',
    subtitle: 'Protected data and controlled access',
  },
];

const meta: Meta<AuthShellComponent> = {
  title: 'Features/Auth/AuthShell',
  component: AuthShellComponent,
  decorators: [
    applicationConfig({
      providers: [{ provide: TranslationService, useClass: MockTranslationService }, TranslatePipe],
    }),
  ],
  parameters: { layout: 'fullscreen' },
  argTypes: {
    brandLine: { control: 'text' },
    title: { control: 'text' },
    subtitle: { control: 'text' },
    features: { control: 'object' },
  },
  args: {
    brandLine: 'Manage your sports school professionally',
    title: 'Sign In',
    subtitle: 'Enter your credentials to continue',
    features: defaultFeatures,
  },
};
export default meta;

type Story = StoryObj<AuthShellComponent>;

const renderTemplate = (content: string) => (args: any) => ({
  props: args,
  template: `
    <auth-shell
      [brandLine]="brandLine"
      [title]="title"
      [subtitle]="subtitle"
      [features]="features">
      ${content}
    </auth-shell>
  `,
});

const loginForm = `
  <form auth-form class="auth-form">
    <label>Email <input type="email" /></label>
    <label>Password <input type="password" /></label>
    <button type="submit">Login</button>
  </form>
`;

const registerForm = `
  <form auth-form class="auth-form">
    <label>Email <input type="email" /></label>
    <label>Password <input type="password" /></label>
    <label>Confirm Password <input type="password" /></label>
    <button type="submit">Register</button>
  </form>
`;

const forgotForm = `
  <form auth-form class="auth-form">
    <label>Email <input type="email" /></label>
    <button type="submit">Send Reset Link</button>
  </form>
`;

const loginFormErrors = `
  <form auth-form class="auth-form ng-invalid">
    <label>
      Email
      <input
        type="email"
        class="ng-invalid ng-touched"
        aria-invalid="true"
        aria-describedby="login-email-error"
      />
      <span id="login-email-error" role="alert">Email is required</span>
    </label>
    <label>
      Password
      <input
        type="password"
        class="ng-invalid ng-touched"
        aria-invalid="true"
        aria-describedby="login-password-error"
      />
      <span id="login-password-error" role="alert">Password is too short</span>
    </label>
    <button type="submit" disabled>Login</button>
  </form>
`;

export const LoginLight: Story = {
  decorators: [lightDecorator],
  render: renderTemplate(loginForm),
};

export const LoginDark: Story = {
  decorators: [darkDecorator],
  render: renderTemplate(loginForm),
};

export const RegisterLight: Story = {
  decorators: [lightDecorator],
  args: { title: 'Create Account', subtitle: 'Join Boukii V5' },
  render: renderTemplate(registerForm),
};

export const RegisterDark: Story = {
  decorators: [darkDecorator],
  args: { title: 'Create Account', subtitle: 'Join Boukii V5' },
  render: renderTemplate(registerForm),
};

export const ForgotLight: Story = {
  decorators: [lightDecorator],
  args: { title: 'Reset Password', subtitle: "We'll send you a recovery link to your email" },
  render: renderTemplate(forgotForm),
};

export const ForgotDark: Story = {
  decorators: [darkDecorator],
  args: { title: 'Reset Password', subtitle: "We'll send you a recovery link to your email" },
  render: renderTemplate(forgotForm),
};

export const WithErrors: Story = {
  decorators: [lightDecorator],
  render: renderTemplate(loginFormErrors),
};
