import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { AuthV5Service } from '../../../core/services/auth-v5.service';
import { TranslationService } from '../../../core/services/translation.service';
import { ToastService } from '../../../core/services/toast.service';
import { AuthErrorHandlerService } from '../../../core/services/auth-error-handler.service';
import { TranslatePipe } from '../../../shared/pipes/translate.pipe';
import { AuthShellComponent } from '@features/auth/ui/auth-shell/auth-shell.component';
import { TextFieldComponent } from '../../../ui/atoms/text-field.component';

@Component({
  selector: 'app-login-page',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    RouterLink,
    TranslatePipe,
    AuthShellComponent,
    TextFieldComponent
  ],
  template: `
    <auth-shell
      [brandLine]="t('auth.brandLine')"
      [title]="t(pageTitleKey)"
      [subtitle]="t(pageSubtitleKey)"
      [features]="features"
    >
      <ng-container auth-form>
        <form role="form" data-testid="auth-form" data-cy="login-form" [formGroup]="loginForm" (ngSubmit)="onSubmit()" novalidate>
          <label class="field">
            <span>{{ 'auth.common.email' | translate }}</span>
            <input
              id="loginEmail"
              data-testid="email"
              data-cy="email-input"
              type="email"
              formControlName="email"
              autocomplete="username"
              [class.is-invalid]="isFieldInvalid('email')"
              [attr.aria-invalid]="isFieldInvalid('email') ? 'true' : 'false'"
              [attr.aria-describedby]="isFieldInvalid('email') ? 'email-error' : null" />
            <p class="field-error" [id]="'email-error'" data-cy="email-error" role="alert" *ngIf="isFieldInvalid('email')">
              {{ getFieldError('email') }}
            </p>
          </label>

          <label class="field">
            <span>{{ 'auth.common.password' | translate }}</span>
            <div class="password-wrapper">
              <input
                id="loginPassword"
                data-testid="password"
                data-cy="password-input"
                [type]="showPassword() ? 'text' : 'password'"
                formControlName="password"
                autocomplete="current-password"
                [class.is-invalid]="isFieldInvalid('password')"
                [attr.aria-invalid]="isFieldInvalid('password') ? 'true' : 'false'"
                [attr.aria-describedby]="isFieldInvalid('password') ? 'password-error' : null" />
              <button
                type="button"
                class="password-toggle"
                data-testid="toggle-password"
                [attr.aria-label]="(showPassword() ? 'auth.common.hidePassword' : 'auth.common.showPassword') | translate"
                [attr.aria-pressed]="showPassword()"
                (click)="togglePassword()">
                @if (showPassword()) {
                  <i class="i-eye-off"></i>
                } @else {
                  <i class="i-eye"></i>
                }
              </button>
            </div>
            <p class="field-error" [id]="'password-error'" data-cy="password-error" role="alert" *ngIf="isFieldInvalid('password')">
              {{ getFieldError('password') }}
            </p>
          </label>

          @if (statusMessage()) {
            <div class="status-message" role="status" aria-live="polite">
              {{ statusMessage() }}
            </div>
          }

            <div class="card-actions">
              <button
                class="btn btn--primary"
                type="submit"
                data-testid="submit"
                data-cy="login-button"
                [disabled]="loginForm.invalid || isSubmitting()"
                [attr.aria-busy]="isSubmitting() ? 'true' : null">
                @if (isSubmitting()) {
                  <div class="loading-spinner"></div>
                  {{ 'common.loading' | translate }}
                } @else {
                  {{ 'auth.login.cta' | translate }}
                }
              </button>
            </div>
        </form>
      </ng-container>
    </auth-shell>
  `,
  styleUrls: ['./login.page.scss']
})
export class LoginPage implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly authV5 = inject(AuthV5Service);
  private readonly translationService = inject(TranslationService);
  private readonly toast = inject(ToastService);
  private readonly errorHandler = inject(AuthErrorHandlerService);

  t = (k: string) => this.translationService.instant(k);
  pageTitleKey = 'auth.login.title';
  pageSubtitleKey = 'auth.login.subtitle';

  // Reactive state
  readonly isSubmitting = signal(false);
  readonly showPassword = signal(false);
  readonly statusMessage = signal('');

  readonly features = [
    {
      icon: 'i-rows',
      title: this.t('auth.features.suite.title'),
      subtitle: this.t('auth.features.suite.subtitle')
    },
    {
      icon: 'i-season',
      title: this.t('auth.features.multiseason.title'),
      subtitle: this.t('auth.features.multiseason.subtitle')
    },
    {
      icon: 'i-shield',
      title: this.t('auth.features.pro.title'),
      subtitle: this.t('auth.features.pro.subtitle')
    }
  ];

  loginForm!: FormGroup;

  ngOnInit(): void {
    this.initializeForm();

    // Redirect if already authenticated
    if (this.authV5.isAuthenticated()) {
      this.router.navigate(['/dashboard']);
    }
  }

  private initializeForm(): void {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(6)]]
    });
  }

  onFieldChange(field: string, value: string): void {
    this.loginForm.patchValue({ [field]: value });
    this.loginForm.get(field)?.markAsTouched();
  }

  togglePassword(): void {
    this.showPassword.set(!this.showPassword());
  }

  isFieldInvalid(field: string): boolean {
    const control = this.loginForm.get(field);
    return !!(control?.invalid && (control?.dirty || control?.touched));
  }

  getFieldError(field: string): string {
    const control = this.loginForm.get(field);
    if (control?.invalid && (control?.dirty || control?.touched)) {
      if (control.errors?.['required']) {
        return this.translationService.get(`auth.errors.required${field.charAt(0).toUpperCase() + field.slice(1)}`);
      }
      if (control.errors?.['email']) {
        return this.translationService.get('auth.errors.invalidEmail');
      }
      if (control.errors?.['minlength']) {
        return this.translationService.get('auth.errors.requiredPassword');
      }
    }
    return '';
  }

  onSubmit(): void {
    if (this.loginForm.invalid) {
      this.markFormGroupTouched();
      return;
    }

    this.isSubmitting.set(true);
    this.statusMessage.set(this.translationService.get('auth.login.processing'));
    const { email, password } = this.loginForm.value;

    // Step 1: Check user credentials
    this.authV5.checkUser({ email, password }).subscribe({
      next: (response) => {
        console.log('🔍 Login response received:', response);
        
        if (!response.success || !response.data) {
          console.error('❌ Invalid response:', response);
          this.handleLoginError('Invalid response from server');
          return;
        }

        const { user, schools, temp_token } = response.data;
        console.log('📊 Response data:', { user, schools: schools?.length, temp_token: temp_token?.substring(0, 20) + '...' });

        if (user?.type === 'monitor') {
          console.log('👨‍🏫 Monitor user detected, redirecting to teacher app');
          this.handleLoginError('Please use the teacher app');
          this.router.navigate(['/teach']);
          return;
        }

        if (user?.type === 'client') {
          console.log('👤 Client user detected, redirecting to client app');
          this.handleLoginError('Please use the client app');
          this.router.navigate(['/client']);
          return;
        }

        // Check if schools exists and is an array
        if (!schools || !Array.isArray(schools)) {
          console.error('❌ Schools is not an array:', schools);
          this.handleLoginError('Invalid school data received from server');
          return;
        }

        console.log('🏫 Schools array length:', schools.length);

        if (schools.length === 0) {
          console.log('❌ No schools available');
          this.handleLoginError('No schools available for this user');
        } else if (schools.length === 1) {
          console.log('🎯 Single school user, auto-selecting:', schools[0].name);
          this.handleSingleSchoolUser(schools[0], temp_token);
        } else if (schools.length > 1) {
          console.log('🏫 Multi-school user, showing selection page. Schools:', schools.map(s => s.name));
          this.handleMultiSchoolUser(schools, temp_token);
        } else {
          console.error('❌ Unexpected schools length logic error');
          this.handleLoginError('Invalid school data received');
        }
      },
      error: (error) => {
        const authError = this.errorHandler.handleLoginError(error);
        this.isSubmitting.set(false);
        this.statusMessage.set(authError.message);
      }
    });
  }

  private handleSingleSchoolUser(school: any, tempToken: string): void {
    this.statusMessage.set(this.translationService.get('auth.login.selectingSchool'));

    this.authV5.selectSchool(school.id, tempToken).subscribe({
      next: (response) => {
        if (!response.success || !response.data) {
          this.handleLoginError('School selection failed');
          return;
        }

        // The API returns user, school, season and final access token
        console.log('✅ School selection successful:', response.data);

        // Complete the login flow
        this.isSubmitting.set(false);
        this.statusMessage.set(this.translationService.get('auth.login.success'));
        this.toast.success(this.translationService.get('auth.login.success'));

        // Navigate to dashboard
        this.router.navigate(['/dashboard']);
      },
      error: (error) => {
        const authError = this.errorHandler.handleSchoolSelectionError(error);
        this.isSubmitting.set(false);
        this.statusMessage.set(authError.message);
      }
    });
  }

  private handleMultiSchoolUser(schools: any[], tempToken: string): void {
    console.log('🎭 handleMultiSchoolUser called with:', { schoolCount: schools.length, hasToken: !!tempToken });
    
    // Store temporary token and schools for the selection process
    localStorage.setItem('boukii_temp_token', tempToken);
    localStorage.setItem('boukii_temp_schools', JSON.stringify(schools));
    console.log('💾 Stored in localStorage:', { token: tempToken?.substring(0, 20) + '...', schoolsStored: schools.length });

    // Set schools in auth service to make them available
    this.authV5.schoolsSignal.set(schools);
    console.log('🔄 Set schools in auth service signal');
    
    this.isSubmitting.set(false);
    this.statusMessage.set(this.translationService.get('auth.login.redirectingToSchools'));
    console.log('📝 Updated status message and cleared submitting state');
    
    // Navigate to school selection
    console.log('🧭 Attempting navigation to /select-school');
    this.router.navigate(['/select-school']).then(success => {
      console.log('🧭 Navigation result:', success ? '✅ SUCCESS' : '❌ FAILED');
    });
  }


  private handleLoginError(message: string): void {
    this.isSubmitting.set(false);
    this.statusMessage.set(message);
    this.toast.error(message);
  }

  private markFormGroupTouched(): void {
    Object.keys(this.loginForm.controls).forEach(key => {
      const control = this.loginForm.get(key);
      control?.markAsTouched();
    });
  }
}
