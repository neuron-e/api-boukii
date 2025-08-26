import { Injectable, signal, computed, inject } from '@angular/core';
import { ApiService } from '../services/api.service';
import { ROLE_SUPERADMIN } from '../constants/roles';

export interface User {
  id: number;
  name: string;
  email: string;
  roles?: string[];
  avatar?: string;
  preferences?: {
    language?: string;
    timezone?: string;
  };
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface LoginResponse {
  success: boolean;
  token: string;
  user: User;
  expires_in?: number;
}

// Helper to get token from localStorage
function getStoredToken(): string | null {
  if (typeof localStorage === 'undefined') return null;
  return localStorage.getItem('auth_token');
}

@Injectable({ providedIn: 'root' })
export class AuthStore {
  private readonly api = inject(ApiService);

  // Private signals
  private readonly _user = signal<User | null>(null);
  private readonly _token = signal<string | null>(getStoredToken());
  private readonly _loading = signal(false);
  private readonly _error = signal<string | null>(null);
  private readonly _isInitialized = signal(false);

  // Public readonly signals
  readonly user = this._user.asReadonly();
  readonly token = this._token.asReadonly();
  readonly loading = this._loading.asReadonly();
  readonly error = this._error.asReadonly();
  readonly isInitialized = this._isInitialized.asReadonly();

  // Computed signals
  readonly isAuthenticated = computed(() => !!this._token() && !!this._user());
  readonly isLoading = computed(() => this._loading());
  readonly hasError = computed(() => !!this._error());
  readonly initials = computed(() => {
    const user = this._user();
    if (!user?.name) return '';
    return user.name
      .split(' ')
      .map((part) => part[0])
      .join('')
      .toUpperCase()
      .slice(0, 2); // Max 2 characters
  });
  readonly userRoles = computed(() => this._user()?.roles || []);
  readonly hasRole = computed(() => (role: string) => {
    const roles = this._user()?.roles || [];
    return roles.includes(role);
  });

  // Methods
  /**
   * Sign in with email/password
   */
  async signIn(credentials: LoginCredentials): Promise<void> {
    this._loading.set(true);
    this._error.set(null);

    try {
      const response = await this.api.post<LoginResponse>('/auth/login', credentials);

      if (response.success && response.token && response.user) {
        // Store token in localStorage
        if (typeof localStorage !== 'undefined') {
          localStorage.setItem('auth_token', response.token);
        }

        this._token.set(response.token);
        this._user.set(response.user);
        this._loading.set(false);
        this._error.set(null);
        this._isInitialized.set(true);
      } else {
        throw new Error('Invalid response format');
      }
    } catch (error: unknown) {
      let errorMessage = 'Login failed';

      if (error && typeof error === 'object' && 'status' in error) {
        const httpError = error as { status: number; detail?: string; message?: string };
        if (httpError.status === 401) {
          errorMessage = 'Invalid credentials';
        } else if (httpError.status === 429) {
          errorMessage = 'Too many login attempts. Please try again later.';
        } else if (httpError.detail) {
          errorMessage = httpError.detail;
        }
      } else if (error instanceof Error) {
        errorMessage = error.message;
      }

      this._loading.set(false);
      this._error.set(errorMessage);
      this._token.set(null);
      this._user.set(null);

      throw error;
    }
  }

  /**
   * Load current user profile
   */
  async loadMe(): Promise<void> {
    const token = this._token();
    if (!token) {
      this._isInitialized.set(true);
      return;
    }

    this._loading.set(true);
    this._error.set(null);

    try {
      const user = await this.api.get<User>('/auth/me');

      this._user.set(user);
      this._loading.set(false);
      this._error.set(null);
      this._isInitialized.set(true);
    } catch {
      // If loading user fails, clear the stored token
      if (typeof localStorage !== 'undefined') {
        localStorage.removeItem('auth_token');
      }

      this._user.set(null);
      this._token.set(null);
      this._loading.set(false);
      this._error.set(null); // Don't show error for invalid token
      this._isInitialized.set(true);
    }
  }

  /**
   * Sign out current user
   */
  async signOut(): Promise<void> {
    try {
      // Attempt to notify server (optional)
      await this.api.post('/auth/logout', {}).catch(() => {
        // Ignore logout errors - we'll clear local state anyway
      });
    } finally {
      // Always clear local state
      if (typeof localStorage !== 'undefined') {
        localStorage.removeItem('auth_token');
      }

      this._user.set(null);
      this._token.set(null);
      this._loading.set(false);
      this._error.set(null);
    }
  }

  /**
   * Update user profile
   */
  async updateProfile(updates: Partial<User>): Promise<void> {
    const currentUser = this._user();
    if (!currentUser) return;

    this._loading.set(true);
    this._error.set(null);

    try {
      const updatedUser = await this.api.put<User>('/auth/profile', updates);

      this._user.set(updatedUser);
      this._loading.set(false);
      this._error.set(null);
    } catch (error: unknown) {
      let errorMessage = 'Failed to update profile';

      if (error && typeof error === 'object' && 'detail' in error) {
        const httpError = error as { detail?: string };
        if (httpError.detail) {
          errorMessage = httpError.detail;
        }
      } else if (error instanceof Error) {
        errorMessage = error.message;
      }

      this._loading.set(false);
      this._error.set(errorMessage);

      throw error;
    }
  }

  /**
   * Clear error state
   */
  clearError(): void {
    this._error.set(null);
  }

  /**
   * Check if user has specific permission
   */
  hasPermission(permission: string): boolean {
    const user = this._user();
    if (!user) return false;

    // For now, simple role-based check
    // Can be extended for more granular permissions
    const roles = user.roles || [];

    if (roles.includes('admin')) return true;
    if (roles.includes(ROLE_SUPERADMIN)) return true;

    return roles.includes(permission);
  }

  /**
   * Refresh token (if needed for long-running apps)
   */
  async refreshToken(): Promise<void> {
    const token = this._token();
    if (!token) return;

    try {
      const response = await this.api.post<{ token: string }>('/auth/refresh', {});

      if (response.token) {
        if (typeof localStorage !== 'undefined') {
          localStorage.setItem('auth_token', response.token);
        }

        this._token.set(response.token);
      }
    } catch {
      // If refresh fails, sign out
      this.signOut();
    }
  }
}
