import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { AuthV5Service } from '../../../core/services/auth-v5.service';
import { TranslationService } from '../../../core/services/translation.service';
import { ToastService } from '../../../core/services/toast.service';
import { TranslatePipe } from '../../../shared/pipes/translate.pipe';
import { AuthShellComponent } from '../ui/auth-shell/auth-shell.component';
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
    <bk-auth-shell
      [titleKey]="'auth.hero.welcomeBack'"
      [subtitleKey]="'auth.login.subtitle'"
      [features]="features">

      <h2 id="loginTitle" class="visually-hidden">{{ 'auth.login.title' | translate }}</h2>

      <div class="card-header">
        <h1 class="card-title">{{ 'auth.login.title' | translate }}</h1>
        <p class="card-subtitle">{{ 'auth.login.subtitle' | translate }}</p>
      </div>

      <form
        [formGroup]="loginForm"
        (ngSubmit)="onSubmit()"
        class="auth-form"
        [attr.aria-labelledby]="'loginTitle'">
          <!-- Email -->
          <ui-text-field
            [label]="'auth.common.email' | translate"
            [placeholder]="'auth.common.email' | translate"
            [errorMessage]="getFieldError('email')"
            type="email"
            autocomplete="email"
            (valueChange)="onFieldChange('email', $event)">
          </ui-text-field>

          <!-- Password -->
          <ui-text-field
            [label]="'auth.common.password' | translate"
            [type]="showPassword() ? 'text' : 'password'"
            [placeholder]="'auth.common.password' | translate"
            [errorMessage]="getFieldError('password')"
            [suffixIcon]="true"
            autocomplete="current-password"
            (valueChange)="onFieldChange('password', $event)">
            <button 
              slot="suffix-icon"
              type="button"
              class="password-toggle"
              [attr.aria-label]="(showPassword() ? 'auth.common.hidePassword' : 'auth.common.showPassword') | translate"
              [attr.aria-pressed]="showPassword()"
              (click)="togglePassword()">
              @if (showPassword()) {
                <i class="i-eye-off"></i>
              } @else {
                <i class="i-eye"></i>
              }
            </button>
          </ui-text-field>

          <button
            type="submit"
            class="btn btn--primary w-100"
            [disabled]="loginForm.invalid || isLoading()"
            [attr.aria-describedby]="statusMessage() ? 'status-message' : null">
            @if (isLoading()) {
              <div class="loading-spinner">
                <div class="spinner"></div>
              </div>
              {{ 'common.loading' | translate }}
            } @else {
              {{ 'auth.common.signin' | translate }}
            }
          </button>

          <div class="links">
            <a routerLink="/auth/forgot-password">{{ 'auth.common.forgot' | translate }}</a>
            <span>·</span>
            <span>{{ 'auth.common.noAccount' | translate }}</span>
            <a routerLink="/auth/register">{{ 'auth.common.signup' | translate }}</a>
          </div>

          <div
            id="status-message"
            class="visually-hidden"
            role="status"
            aria-live="polite"
            [attr.aria-hidden]="!statusMessage()">
            {{ statusMessage() }}
          </div>
        </form>
    </bk-auth-shell>
  `,
  styleUrls: ['./login.page.scss']
})
export class LoginPage implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly authV5 = inject(AuthV5Service);
  private readonly translation = inject(TranslationService);
  private readonly toast = inject(ToastService);

  // Reactive state
  readonly isLoading = signal(false);
  readonly showPassword = signal(false);
  readonly statusMessage = signal('');

  readonly features = [
    { icon: 'i-grid', titleKey: 'auth.hero.feature1', descKey: 'auth.hero.feature1Desc' },
    { icon: 'i-clock', titleKey: 'auth.hero.feature2', descKey: 'auth.hero.feature2Desc' },
    { icon: 'i-trending-up', titleKey: 'auth.hero.feature3', descKey: 'auth.hero.feature3Desc' }
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

  getFieldError(field: string): string {
    const control = this.loginForm.get(field);
    if (control?.invalid && control?.touched) {
      if (control.errors?.['required']) {
        return this.translation.get(`auth.errors.required${field.charAt(0).toUpperCase() + field.slice(1)}`);
      }
      if (control.errors?.['email']) {
        return this.translation.get('auth.errors.invalidEmail');
      }
      if (control.errors?.['minlength']) {
        return this.translation.get('auth.errors.requiredPassword');
      }
    }
    return '';
  }

  isFieldInvalid(field: string): boolean {
    const control = this.loginForm.get(field);
    return !!(control?.invalid && control?.touched);
  }

  onSubmit(): void {
    if (this.loginForm.invalid) {
      this.markFormGroupTouched();
      return;
    }

    this.isLoading.set(true);
    this.statusMessage.set(this.translation.get('auth.login.processing'));
    const { email, password } = this.loginForm.value;

    // Step 1: Check user credentials
    this.authV5.checkUser({ email, password }).subscribe({
      next: (response) => {
        if (!response.success || !response.data) {
          this.handleLoginError('Invalid response from server');
          return;
        }

        const { schools, temp_token } = response.data;

        if (schools.length === 0) {
          this.handleLoginError('No schools available for this user');
        } else if (schools.length === 1) {
          this.handleSingleSchoolUser(schools[0], temp_token);
        } else if (schools.length > 1) {
          this.handleMultiSchoolUser(schools, temp_token);
        } else {
          this.handleLoginError('Invalid school data received');
        }
      },
      error: (error) => {
        this.handleLoginError(error?.message || 'Login failed');
      }
    });
  }

  private handleSingleSchoolUser(school: any, tempToken: string): void {
    this.statusMessage.set(this.translation.get('auth.login.selectingSchool'));
    
    this.authV5.selectSchool(school.id, tempToken).subscribe({
      next: (response) => {
        if (!response.success || !response.data) {
          this.handleLoginError('School selection failed');
          return;
        }

        const { available_seasons, access_token } = response.data;
        
        if (available_seasons && available_seasons.length > 0) {
          const firstSeason = available_seasons[0];
          this.handleSingleSeason(firstSeason, response.data.school.id, access_token);
        } else {
          this.handleLoginError('No seasons available for this school');
        }
      },
      error: (error) => {
        this.handleLoginError(`School selection failed: ${error.message}`);
      }
    });
  }

  private handleMultiSchoolUser(schools: any[], tempToken: string): void {
    localStorage.setItem('boukii_temp_token', tempToken);
    localStorage.setItem('boukii_temp_schools', JSON.stringify(schools));
    
    this.isLoading.set(false);
    this.router.navigate(['/select-school']);
  }

  private handleSingleSeason(season: any, schoolId: number, accessToken: string): void {
    this.statusMessage.set(this.translation.get('auth.login.selectingSeason'));

    this.authV5.selectSeason(season.id, schoolId, accessToken).subscribe({
      next: (_response) => {
        this.isLoading.set(false);
        this.statusMessage.set(this.translation.get('auth.login.success'));
        
        this.toast.success(this.translation.get('auth.login.success'));
        this.router.navigate(['/dashboard']);
      },
      error: (error) => {
        this.handleLoginError(`Season selection failed: ${error.message}`);
      }
    });
  }

  private handleLoginError(message: string): void {
    this.isLoading.set(false);
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