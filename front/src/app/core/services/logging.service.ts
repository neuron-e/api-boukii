import { Injectable, inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { ApiService } from './api.service';
import { ConfigService } from './config.service';
import type { ErrorContext, ErrorResponse } from '../models/error.models';

export type LogLevel = 'debug' | 'info' | 'warn' | 'error' | 'none';

export interface LogContext {
  [key: string]: unknown;
}

export interface LogPayload {
  level: LogLevel;
  message: string;
  context?: LogContext;
  timestamp: string;
  env: string;
  appVersion?: string;
  url?: string;
  userId?: string | number;
  schoolId?: string | number;
  seasonId?: string | number;
  ua?: string;
}

@Injectable({ providedIn: 'root' })
export class LoggingService {
  private readonly api = inject(ApiService);
  private readonly config = inject(ConfigService);
  private readonly platformId = inject(PLATFORM_ID);

  private envName = 'unknown';
  private production = false;

  private currentLogLevel: LogLevel = 'info';

  configureEnvironment(envName: string, isProduction: boolean): void {
    this.envName = envName;
    this.production = isProduction;
  }

  log(level: LogLevel, message: string, context: LogContext = {}): void {
    const envName = this.envName;
    const appVersion = this.config.getAppVersion?.();

    const payload: LogPayload = {
      level,
      message,
      context: Object.keys(context).length ? context : undefined,
      timestamp: new Date().toISOString(),
      env: envName,
      appVersion: appVersion || undefined,
      url: isPlatformBrowser(this.platformId) ? window.location.href : undefined,
      ua: isPlatformBrowser(this.platformId) ? navigator.userAgent : undefined,
    };

    const runtime = this.config.getRuntimeConfig?.();
    const loggingCfg = (runtime as any)?.logging || {};

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'X-Env': envName,
    };
    if (appVersion) {
      headers['X-App-Version'] = appVersion;
    }

    const endpoint = loggingCfg.endpoint || '/logs';
    const shouldSend =
      (this.production && loggingCfg.enabled !== false) ||
      (!this.production && loggingCfg.forceNetworkInDev === true);

    if (shouldSend) {
      void this.api
        .postWithHeaders(endpoint, payload, headers)
        .catch((err) => {
          if (!this.production) {
            console.error('Failed to send log', err);
          }
        });
    }

    if (!this.production || loggingCfg.enabled === false) {
      const fn = (console as any)[level] || console.log;
      fn(payload);
    }
  }

  debug(message: string, context?: LogContext): void {
    this.log('debug', message, context);
  }

  info(message: string, context?: LogContext): void {
    this.log('info', message, context);
  }

  warn(message: string, context?: LogContext): void {
    this.log('warn', message, context);
  }

  error(message: string, context?: LogContext): void {
    this.log('error', message, context);
  }

  // Legacy wrapper methods
  logInfo(message: string, context?: LogContext): void {
    this.info(message, context);
  }

  logWarning(message: string, context?: LogContext): void {
    this.warn(message, context);
  }

  logError(
    message: string,
    error?: ErrorResponse,
    context?: Partial<ErrorContext>,
    userFriendlyMessage?: string
  ): void {
    const ctx: LogContext = { ...(context || {}) };
    if (error) {
      (ctx as any).error = error;
    }
    if (userFriendlyMessage) {
      (ctx as any).userFriendlyMessage = userFriendlyMessage;
    }
    this.error(message, ctx);
  }

  setLogLevel(level: LogLevel): void {
    this.currentLogLevel = level;
  }

  getLogLevel(): LogLevel {
    return this.currentLogLevel;
  }
}

