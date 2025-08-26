import { Injectable, signal, computed, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import {
  RuntimeEnvironment,
  EnvironmentLoadingState,
  EnvironmentType,
  FeatureFlags,
  ConfigurationOverride,
  ConfigurationChangeEvent,
} from '../models/environment.models';
import { ErrorSeverity, ErrorResponse, ErrorContext } from '../models/error.models';
import { environment } from '@environments/environment';

export type EnvName = 'development' | 'staging' | 'test' | 'production';

/**
 * Environment Service with Angular Signals
 * Manages runtime configuration, feature flags, and environment settings
 */
@Injectable({ providedIn: 'root' })
export class EnvironmentService {
  private readonly http = inject(HttpClient);

  private readonly _envName: EnvName = (environment as any).envName ?? 'development';

  // Private signals
  private readonly _environment = signal<RuntimeEnvironment | null>(null);
  private readonly _loadingState = signal<EnvironmentLoadingState>({
    loading: false,
    loaded: false,
    error: null,
    lastUpdated: null,
    source: 'static',
  });
  private readonly _overrides = signal<ConfigurationOverride[]>([]);

  // Public readonly signals
  readonly environment = this._environment.asReadonly();
  readonly loadingState = this._loadingState.asReadonly();
  readonly overrides = this._overrides.asReadonly();

  // Computed signals
  readonly environmentType = computed(() => this._environment()?.type ?? 'development');

  readonly apiBaseUrl = computed(() => this._environment()?.api.baseUrl ?? '');

  readonly features = computed(() => this._environment()?.features ?? this.getDefaultFeatures());

  readonly isLoading = computed(() => this._loadingState().loading);
  readonly hasError = computed(() => !!this._loadingState().error);
  readonly isLoaded = computed(() => this._loadingState().loaded);

  // Configuration change events
  private configurationChanges: ConfigurationChangeEvent[] = [];
  private readonly maxChangeHistorySize = 100;

  // Cache for configuration
  private readonly CONFIG_CACHE_KEY = 'boukii_runtime_config';
  private readonly CONFIG_CACHE_TTL = 5 * 60 * 1000; // 5 minutes

  private initialized = false;

  constructor() {
    this.initializeEnvironment();
  }

  envName(): EnvName {
    return this._envName;
  }

  isDevelopment(): boolean {
    return this._envName === 'development';
  }

  isStaging(): boolean {
    return this._envName === 'staging';
  }

  isTest(): boolean {
    return this._envName === 'test';
  }

  isProduction(): boolean {
    return this._envName === 'production';
  }


  private logInfo(message: string, context?: any): void {
    if (!this.isProduction()) {
      console.log(`[EnvironmentService] ${message}`, context);
    }
  }

  private logWarning(message: string, context?: any): void {
    console.warn(`[EnvironmentService] ${message}`, context);
  }

  private logError(
    message: string,
    error?: any,
    context?: any,
    userFriendlyMessage?: string
  ): void {
    console.error(`[EnvironmentService] ${message}`, { error, ...(context || {}) });
  }

  /**
   * Initialize environment configuration
   */
  private async initializeEnvironment(): Promise<void> {
    this._loadingState.set({
      loading: true,
      loaded: false,
      error: null,
      lastUpdated: null,
      source: 'static',
    });

    try {
      // Try to load from cache first
      const cachedConfig = this.loadFromCache();
      if (cachedConfig) {
        this._environment.set(cachedConfig);
        this._loadingState.set({
          loading: false,
          loaded: true,
          error: null,
          lastUpdated: new Date(),
          source: 'cache',
        });

        this.logInfo('Environment loaded from cache', {
          url: 'environment-service',
          method: 'initializeEnvironment',
          severity: ErrorSeverity.LOW,
          source: 'cache',
        });

        // Try to update from remote in background
        this.loadFromRemote().catch(() => {
          // Ignore errors if cache load succeeded
        });

        return;
      }

      // Load from remote or fall back to static
      await this.loadFromRemote();
    } catch (_error) {
      // Fall back to static configuration
      await this.loadStaticConfiguration();

      this._loadingState.set({
        loading: false,
        loaded: true,
        error: 'Failed to load remote configuration, using fallback',
        lastUpdated: new Date(),
        source: 'fallback',
      });

      this.logWarning('Using fallback environment configuration', {
        url: 'environment-service',
        method: 'initializeEnvironment',
        severity: ErrorSeverity.MEDIUM,
        error: _error instanceof Error ? _error.message : 'Unknown error',
      });
    } finally {
      this.initialized = true;
    }
  }

  /**
   * Load configuration from remote endpoint
   */
  private async loadFromRemote(): Promise<void> {
    try {
      const remoteConfig = await firstValueFrom(
        this.http.get<RuntimeEnvironment>('/assets/config/runtime-config.json')
      );

      this._environment.set(remoteConfig);
      this.saveToCache(remoteConfig);

      this._loadingState.set({
        loading: false,
        loaded: true,
        error: null,
        lastUpdated: new Date(),
        source: 'remote',
      });

      this.logInfo('Environment loaded from remote', {
        url: 'environment-service',
        method: 'loadFromRemote',
        severity: ErrorSeverity.LOW,
        version: remoteConfig.version,
      });
    } catch (_error) {
      throw new Error(
        `Failed to load remote configuration: ${_error instanceof Error ? _error.message : 'Unknown error'}`
      );
    }
  }

  /**
   * Load static fallback configuration
   */
  private async loadStaticConfiguration(): Promise<void> {
    const staticConfig = this.getStaticConfiguration();
    this._environment.set(staticConfig);

    this.logInfo('Environment loaded from static configuration', {
      url: 'environment-service',
      method: 'loadStaticConfiguration',
      severity: ErrorSeverity.LOW,
      type: staticConfig.type,
    });
  }

  /**
   * Get static fallback configuration
   */
  private getStaticConfiguration(): RuntimeEnvironment {
    const isProduction =
      typeof window !== 'undefined' &&
      (window.location.hostname === 'admin.boukii.app' ||
        window.location.hostname.includes('boukii-admin'));

    const isDevelopment =
      typeof window !== 'undefined' &&
      (window.location.hostname === 'localhost' || window.location.hostname.includes('127.0.0.1'));

    const environmentType: EnvironmentType = isProduction
      ? 'production'
      : isDevelopment
        ? 'development'
        : 'staging';

    return {
      name: `Boukii Admin V5 - ${environmentType.charAt(0).toUpperCase() + environmentType.slice(1)}`,
      type: environmentType,
      version: '5.0.0',
      buildDate: new Date().toISOString(),
      description: `Boukii Admin Panel V5 - ${environmentType} environment`,
      production: isProduction,

      api: {
        baseUrl: isProduction
          ? 'https://api.boukii.app'
          : isDevelopment
            ? 'http://localhost:8000'
            : 'https://staging-api.boukii.app',
        timeout: 30000,
        retryAttempts: 3,
        retryDelay: 1000,
        version: 'v5',
        endpoints: {
          auth: '/api/v5/auth',
          users: '/api/v5/users',
          dashboard: '/api/v5/dashboard',
          schools: '/api/v5/schools',
          seasons: '/api/v5/seasons',
          clients: '/api/v5/clients',
          courses: '/api/v5/courses',
          bookings: '/api/v5/bookings',
          monitors: '/api/v5/monitors',
        },
      },

      auth: {
        tokenKey: 'boukii_auth_token',
        refreshTokenKey: 'boukii_refresh_token',
        tokenExpiry: 3600000, // 1 hour
        refreshThreshold: 300000, // 5 minutes
        loginRedirect: '/auth/login',
        logoutRedirect: '/auth/login',
        sessionTimeout: 28800000, // 8 hours
        rememberMeEnabled: true,
      },

      features: this.getDefaultFeatures(),

      monitoring: {
        enabled: !isDevelopment,
        errorReporting: isProduction,
        performanceTracking: isProduction,
        userAnalytics: isProduction,
        logLevel: isDevelopment ? 'debug' : isProduction ? 'warn' : 'info',
        maxLogEntries: 1000,
        retentionDays: 30,
        endpoint: isProduction ? 'https://api.boukii.app/logs' : undefined,
      },

      cache: {
        enabled: true,
        strategies: {
          api: 'memory',
          translations: 'localStorage',
          user: 'localStorage',
          dashboard: 'sessionStorage',
        },
        ttl: {
          default: 300000, // 5 minutes
          user: 3600000, // 1 hour
          translations: 86400000, // 24 hours
          dashboard: 600000, // 10 minutes
          api: 300000, // 5 minutes
        },
        maxSize: {
          memory: 10485760, // 10MB
          localStorage: 5242880, // 5MB
          sessionStorage: 2621440, // 2.5MB
        },
      },

      security: {
        enableCSP: isProduction,
        enableHSTS: isProduction,
        enableXSSProtection: true,
        corsOrigins: isProduction
          ? ['https://boukii.app']
          : ['http://localhost:4200', 'http://api-boukii.test'],
        trustedDomains: ['boukii.app', 'boukii-api.app', 'api-boukii.test'],
        encryptLocalStorage: isProduction,
        sessionSecurity: isProduction ? 'strict' : 'standard',
        passwordPolicy: {
          minLength: 8,
          requireUppercase: true,
          requireLowercase: true,
          requireNumbers: true,
          requireSymbols: false,
        },
      },

      app: {
        title: 'Boukii Admin V5',
        logo: '/assets/images/logo.svg',
        supportEmail: 'support@boukii.app',
        supportPhone: '+34 900 123 456',
        companyName: 'Boukii Technologies',
        privacyPolicyUrl: 'https://boukii.app/privacy',
        termsOfServiceUrl: 'https://boukii.app/terms',
        maxFileUploadSize: 10485760, // 10MB
        allowedFileTypes: ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'],
        defaultLanguage: 'en',
        availableLanguages: ['en', 'es'],
        timezone: 'Europe/Madrid',
        dateFormat: 'dd/MM/yyyy',
        timeFormat: 'HH:mm',
        currency: 'EUR',
      },

      integrations: {
        firebase: isProduction
          ? {
              apiKey: 'your-firebase-api-key',
              authDomain: 'boukii-prod.firebaseapp.com',
              projectId: 'boukii-prod',
              storageBucket: 'boukii-prod.appspot.com',
              messagingSenderId: '123456789',
              appId: '1:123456789:web:abcdef123456',
            }
          : undefined,
        google: {
          analytics: isProduction ? 'G-XXXXXXXXXX' : undefined,
          maps: 'your-google-maps-api-key',
        },
      },
    };
  }

  /**
   * Get default feature flags
   */
  private getDefaultFeatures(): FeatureFlags {
    const isDevelopment = this.environmentType() === 'development';
    const isProduction = this.environmentType() === 'production';

    return {
      // Core features
      darkTheme: true,
      multiLanguage: true,
      notifications: true,
      analytics: isProduction,

      // Dashboard features
      dashboardWidgets: true,
      realtimeUpdates: true,
      exportData: true,
      importData: true,

      // Admin features
      userManagement: true,
      systemSettings: true,
      auditLogs: !isDevelopment,
      backupRestore: isProduction,

      // Experimental features
      experimentalUI: isDevelopment,
      betaFeatures: !isProduction,
      debugMode: isDevelopment,
      performanceMonitoring: !isDevelopment,

      // External integrations
      paymentGateway: isProduction,
      emailService: true,
      smsService: isProduction,
      socialLogin: true,
    };
  }

  /**
   * Check if a feature flag is enabled
   */
  isFeatureEnabled(feature: keyof FeatureFlags): boolean {
    const features = this.features();
    const overrideValue = this.getOverrideValue(`features.${feature}`);

    if (overrideValue !== undefined) {
      return Boolean(overrideValue);
    }

    return features[feature];
  }

  /**
   * Toggle a feature flag (for development/testing)
   */
  toggleFeature(feature: keyof FeatureFlags, value?: boolean): void {
    const currentEnv = this._environment();
    if (!currentEnv) return;

    const newValue = value ?? !currentEnv.features[feature];
    const oldValue = currentEnv.features[feature];

    // Update the environment
    const updatedEnv = {
      ...currentEnv,
      features: {
        ...currentEnv.features,
        [feature]: newValue,
      },
    };

    this._environment.set(updatedEnv);
    this.saveToCache(updatedEnv);

    // Record the change
    this.recordConfigurationChange({
      type: 'feature_flag',
      key: `features.${feature}`,
      oldValue,
      newValue,
      source: 'runtime',
      timestamp: new Date(),
    });

    this.logInfo(`Feature flag ${feature} toggled`, {
      url: 'environment-service',
      method: 'toggleFeature',
      severity: ErrorSeverity.LOW,
      feature,
      oldValue,
      newValue,
    });
  }

  /**
   * Get configuration value with override support
   */
  getConfig<T = unknown>(key: string): T | undefined {
    const overrideValue = this.getOverrideValue(key);
    if (overrideValue !== undefined) {
      return overrideValue as T;
    }

    const env = this._environment();
    if (!env) return undefined;

    return this.getNestedProperty(env, key) as T;
  }

  /**
   * Set configuration override
   */
  setConfigurationOverride(override: Omit<ConfigurationOverride, 'source'>): void {
    const newOverride: ConfigurationOverride = {
      ...override,
      source: 'runtime',
    };

    const currentOverrides = this._overrides();
    const existingIndex = currentOverrides.findIndex((o) => o.key === override.key);

    let updatedOverrides: ConfigurationOverride[];
    if (existingIndex >= 0) {
      updatedOverrides = [...currentOverrides];
      updatedOverrides[existingIndex] = newOverride;
    } else {
      updatedOverrides = [...currentOverrides, newOverride];
    }

    this._overrides.set(updatedOverrides);
    this.saveOverridesToCache(updatedOverrides);

    this.logInfo('Configuration override set', {
      url: 'environment-service',
      method: 'setConfigurationOverride',
      severity: ErrorSeverity.LOW,
      key: override.key,
      value: override.value,
    });
  }

  /**
   * Remove configuration override
   */
  removeConfigurationOverride(key: string): void {
    const currentOverrides = this._overrides();
    const updatedOverrides = currentOverrides.filter((o) => o.key !== key);

    this._overrides.set(updatedOverrides);
    this.saveOverridesToCache(updatedOverrides);

    this.logInfo('Configuration override removed', {
      url: 'environment-service',
      method: 'removeConfigurationOverride',
      severity: ErrorSeverity.LOW,
      key,
    });
  }

  /**
   * Reload configuration from remote
   */
  async reloadConfiguration(): Promise<void> {
    this._loadingState.set({
      ...this._loadingState(),
      loading: true,
    });

    try {
      await this.loadFromRemote();

      this.recordConfigurationChange({
        type: 'full_reload',
        key: 'environment',
        oldValue: null,
        newValue: this._environment(),
        source: 'reload',
        timestamp: new Date(),
      });
    } catch (error) {
      this._loadingState.set({
        ...this._loadingState(),
        loading: false,
        error: error instanceof Error ? error.message : 'Unknown error',
      });

      this.logError(
        'Failed to reload configuration',
        {
          type: 'configuration_error',
          title: 'Configuration Reload Error',
          detail: error instanceof Error ? error.message : 'Unknown error',
          code: 'CONFIG_RELOAD_ERROR',
        },
        {
          url: 'environment-service',
          method: 'reloadConfiguration',
          severity: ErrorSeverity.HIGH,
        }
      );

      throw error;
    }
  }

  /**
   * Get configuration change history
   */
  getConfigurationHistory(): ConfigurationChangeEvent[] {
    return [...this.configurationChanges];
  }

  /**
   * Get override value for a key
   */
  private getOverrideValue(key: string): unknown {
    const overrides = this._overrides();
    const activeOverrides = overrides
      .filter((o) => !o.expiresAt || o.expiresAt > new Date())
      .filter((o) => o.key === key)
      .sort((a, b) => b.priority - a.priority);

    return activeOverrides.length > 0 ? activeOverrides[0].value : undefined;
  }

  /**
   * Get nested property from object using dot notation
   */
  private getNestedProperty(obj: unknown, path: string): unknown {
    return path
      .split('.')
      .reduce(
        (current, key) =>
          current && typeof current === 'object' && key in current
            ? (current as Record<string, unknown>)[key]
            : undefined,
        obj
      );
  }

  /**
   * Record configuration change
   */
  private recordConfigurationChange(change: ConfigurationChangeEvent): void {
    this.configurationChanges.push(change);

    // Keep only the last N changes
    if (this.configurationChanges.length > this.maxChangeHistorySize) {
      this.configurationChanges = this.configurationChanges.slice(-this.maxChangeHistorySize);
    }
  }

  /**
   * Save configuration to cache
   */
  private saveToCache(config: RuntimeEnvironment): void {
    if (typeof localStorage === 'undefined') return;

    try {
      const cacheData = {
        config,
        timestamp: Date.now(),
      };
      localStorage.setItem(this.CONFIG_CACHE_KEY, JSON.stringify(cacheData));
    } catch (error) {
      this.logWarning('Failed to save configuration to cache', {
        url: 'environment-service',
        method: 'saveToCache',
        severity: ErrorSeverity.LOW,
        error: error instanceof Error ? error.message : 'Unknown error',
      });
    }
  }

  /**
   * Load configuration from cache
   */
  private loadFromCache(): RuntimeEnvironment | null {
    if (typeof localStorage === 'undefined') return null;

    try {
      const cached = localStorage.getItem(this.CONFIG_CACHE_KEY);
      if (!cached) return null;

      const cacheData = JSON.parse(cached);
      const age = Date.now() - cacheData.timestamp;

      if (age > this.CONFIG_CACHE_TTL) {
        localStorage.removeItem(this.CONFIG_CACHE_KEY);
        return null;
      }

      return cacheData.config;
    } catch (_error) {
      localStorage.removeItem(this.CONFIG_CACHE_KEY);
      return null;
    }
  }

  /**
   * Save overrides to cache
   */
  private saveOverridesToCache(overrides: ConfigurationOverride[]): void {
    if (typeof localStorage === 'undefined') return;

    try {
      localStorage.setItem(`${this.CONFIG_CACHE_KEY}_overrides`, JSON.stringify(overrides));
    } catch (error) {
      this.logWarning('Failed to save overrides to cache', {
        url: 'environment-service',
        method: 'saveOverridesToCache',
        severity: ErrorSeverity.LOW,
        error: error instanceof Error ? error.message : 'Unknown error',
      });
    }
  }
}
