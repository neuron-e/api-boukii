# 🔒 Security Guide - Boukii Admin V5

> **Comprehensive security documentation for enterprise-grade protection**

## 📋 Table of Contents

- [Security Overview](#security-overview)
- [Authentication & Authorization](#authentication--authorization)
- [Data Protection](#data-protection)
- [Network Security](#network-security)
- [Input Validation](#input-validation)
- [Error Handling](#error-handling)
- [Security Headers](#security-headers)
- [Dependency Security](#dependency-security)
- [Monitoring & Logging](#monitoring--logging)
- [Security Best Practices](#security-best-practices)

---

## Security Overview

Boukii Admin V5 implements a **defense-in-depth** security strategy with multiple layers of protection to ensure enterprise-grade security for sensitive administrative operations.

### 🎯 Security Principles

1. **Zero Trust Architecture** - Never trust, always verify
2. **Principle of Least Privilege** - Minimal access rights
3. **Defense in Depth** - Multiple security layers
4. **Secure by Default** - Security built-in from start
5. **Privacy by Design** - Data protection embedded
6. **Fail Securely** - Secure failure states

### 🔍 Security Scope

| Layer              | Security Measures                            |
| ------------------ | -------------------------------------------- |
| **Frontend**       | Input validation, XSS prevention, CSP        |
| **API**            | Authentication, authorization, rate limiting |
| **Transport**      | HTTPS, HSTS, certificate pinning             |
| **Data**           | Encryption at rest, PII protection           |
| **Infrastructure** | WAF, DDoS protection, monitoring             |

---

## Authentication & Authorization

### 🔐 JWT Token Security

**Token Structure**

```typescript
interface AuthTokens {
  accessToken: string; // Short-lived (15 minutes)
  refreshToken: string; // Long-lived (7 days)
  idToken?: string; // User identity claims
}

interface JWTPayload {
  sub: string; // User ID
  email: string; // User email
  roles: Role[]; // User roles
  permissions: Permission[]; // User permissions
  iat: number; // Issued at
  exp: number; // Expires at
  aud: string; // Audience
  iss: string; // Issuer
}
```

**Secure Token Storage**

```typescript
@Injectable()
export class SecureTokenService {
  private readonly ACCESS_TOKEN_KEY = 'boukii_access_token';
  private readonly REFRESH_TOKEN_KEY = 'boukii_refresh_token';

  public setTokens(tokens: AuthTokens): void {
    // Store in httpOnly cookies for production
    if (environment.production) {
      this.cookieService.set(this.ACCESS_TOKEN_KEY, tokens.accessToken, {
        httpOnly: true,
        secure: true,
        sameSite: 'Strict',
        maxAge: 900, // 15 minutes
      });
    } else {
      // Use localStorage for development only
      this.storage.setItem(this.ACCESS_TOKEN_KEY, tokens.accessToken);
    }
  }

  public getAccessToken(): string | null {
    return environment.production
      ? this.cookieService.get(this.ACCESS_TOKEN_KEY)
      : this.storage.getItem(this.ACCESS_TOKEN_KEY);
  }

  public clearTokens(): void {
    if (environment.production) {
      this.cookieService.delete(this.ACCESS_TOKEN_KEY);
      this.cookieService.delete(this.REFRESH_TOKEN_KEY);
    } else {
      this.storage.removeItem(this.ACCESS_TOKEN_KEY);
      this.storage.removeItem(this.REFRESH_TOKEN_KEY);
    }
  }
}
```

**Token Refresh Strategy**

```typescript
@Injectable()
export class TokenRefreshInterceptor implements HttpInterceptor {
  private isRefreshing = false;
  private refreshTokenSubject = new BehaviorSubject<string | null>(null);

  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    const token = this.tokenService.getAccessToken();

    if (token && this.tokenService.isTokenValid(token)) {
      req = this.addTokenToRequest(req, token);
    }

    return next.handle(req).pipe(
      catchError((error) => {
        if (error.status === 401 && token) {
          return this.handle401Error(req, next);
        }
        return throwError(error);
      })
    );
  }

  private handle401Error(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    if (!this.isRefreshing) {
      this.isRefreshing = true;
      this.refreshTokenSubject.next(null);

      return this.authService.refreshToken().pipe(
        switchMap((tokens: AuthTokens) => {
          this.isRefreshing = false;
          this.refreshTokenSubject.next(tokens.accessToken);
          return next.handle(this.addTokenToRequest(req, tokens.accessToken));
        }),
        catchError((error) => {
          this.isRefreshing = false;
          this.authService.logout();
          return throwError(error);
        })
      );
    } else {
      return this.refreshTokenSubject.pipe(
        filter((token) => token !== null),
        take(1),
        switchMap((token) => next.handle(this.addTokenToRequest(req, token!)))
      );
    }
  }
}
```

### 🛡️ Role-Based Access Control (RBAC)

**Permission System**

```typescript
export enum Permission {
  // User management
  USERS_READ = 'users:read',
  USERS_WRITE = 'users:write',
  USERS_DELETE = 'users:delete',

  // Booking management
  BOOKINGS_READ = 'bookings:read',
  BOOKINGS_WRITE = 'bookings:write',
  BOOKINGS_DELETE = 'bookings:delete',

  // System administration
  SYSTEM_CONFIG = 'system:config',
  SYSTEM_LOGS = 'system:logs',
  SYSTEM_MONITOR = 'system:monitor',
}

export interface Role {
  id: string;
  name: string;
  description: string;
  permissions: Permission[];
  isSystemRole: boolean;
}

export const SYSTEM_ROLES: Record<string, Role> = {
  SUPER_ADMIN: {
    id: 'superadmin',
    name: 'Super Administrator',
    description: 'Full system access',
    permissions: Object.values(Permission),
    isSystemRole: true,
  },
  ADMIN: {
    id: 'admin',
    name: 'Administrator',
    description: 'Administrative access',
    permissions: [
      Permission.USERS_READ,
      Permission.USERS_WRITE,
      Permission.BOOKINGS_READ,
      Permission.BOOKINGS_WRITE,
      Permission.SYSTEM_LOGS,
    ],
    isSystemRole: true,
  },
  USER: {
    id: 'user',
    name: 'User',
    description: 'Basic user access',
    permissions: [Permission.BOOKINGS_READ],
    isSystemRole: true,
  },
};
```

**Permission Guards**

```typescript
@Injectable()
export class PermissionGuard implements CanActivate {
  constructor(
    private authStore: AuthStore,
    private permissionService: PermissionService,
    private router: Router,
    private logger: LoggingService
  ) {}

  canActivate(route: ActivatedRouteSnapshot): boolean {
    const user = this.authStore.user();

    if (!user) {
      this.logger.warn('Unauthorized access attempt', { route: route.url });
      this.router.navigate(['/auth/login']);
      return false;
    }

    const requiredPermissions = route.data['permissions'] as Permission[];
    if (!requiredPermissions?.length) {
      return true;
    }

    const hasAllPermissions = requiredPermissions.every((permission) =>
      this.permissionService.hasPermission(user, permission)
    );

    if (!hasAllPermissions) {
      this.logger.warn('Insufficient permissions', {
        userId: user.id,
        required: requiredPermissions,
        userPermissions: this.permissionService.getUserPermissions(user),
      });
      this.router.navigate(['/access-denied']);
      return false;
    }

    this.logger.info('Access granted', {
      userId: user.id,
      route: route.url,
      permissions: requiredPermissions,
    });

    return true;
  }
}
```

**Permission Service**

```typescript
@Injectable()
export class PermissionService {
  public hasPermission(user: User, permission: Permission): boolean {
    return user.roles.some((role) => role.permissions.includes(permission));
  }

  public hasAnyPermission(user: User, permissions: Permission[]): boolean {
    return permissions.some((permission) => this.hasPermission(user, permission));
  }

  public hasAllPermissions(user: User, permissions: Permission[]): boolean {
    return permissions.every((permission) => this.hasPermission(user, permission));
  }

  public getUserPermissions(user: User): Permission[] {
    return user.roles.reduce((acc, role) => {
      return [...acc, ...role.permissions];
    }, [] as Permission[]);
  }

  public canAccessResource(user: User, resource: string, action: string): boolean {
    const permission = `${resource}:${action}` as Permission;
    return this.hasPermission(user, permission);
  }
}
```

---

## Data Protection

### 🔐 Encryption Standards

**Data Encryption**

```typescript
@Injectable()
export class EncryptionService {
  private readonly algorithm = 'AES-256-GCM';
  private readonly keyLength = 32; // 256 bits
  private readonly ivLength = 16; // 128 bits

  public async encrypt(data: string, key: string): Promise<EncryptedData> {
    const iv = crypto.getRandomValues(new Uint8Array(this.ivLength));
    const encodedData = new TextEncoder().encode(data);
    const encodedKey = await this.deriveKey(key);

    const encryptedData = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      encodedKey,
      encodedData
    );

    return {
      data: Array.from(new Uint8Array(encryptedData)),
      iv: Array.from(iv),
      algorithm: this.algorithm,
    };
  }

  public async decrypt(encryptedData: EncryptedData, key: string): Promise<string> {
    const encodedKey = await this.deriveKey(key);
    const iv = new Uint8Array(encryptedData.iv);
    const data = new Uint8Array(encryptedData.data);

    const decryptedData = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, encodedKey, data);

    return new TextDecoder().decode(decryptedData);
  }

  private async deriveKey(password: string): Promise<CryptoKey> {
    const encoder = new TextEncoder();
    const keyMaterial = await crypto.subtle.importKey(
      'raw',
      encoder.encode(password),
      'PBKDF2',
      false,
      ['deriveBits', 'deriveKey']
    );

    return crypto.subtle.deriveKey(
      {
        name: 'PBKDF2',
        salt: encoder.encode('boukii-salt'), // Use dynamic salt in production
        iterations: 100000,
        hash: 'SHA-256',
      },
      keyMaterial,
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt', 'decrypt']
    );
  }
}
```

**PII Data Handling**

```typescript
export interface PIIField {
  field: string;
  category: 'sensitive' | 'personal' | 'financial';
  encryption: boolean;
  retention: number; // days
}

export const PII_FIELDS: PIIField[] = [
  { field: 'email', category: 'personal', encryption: false, retention: 2555 }, // 7 years
  { field: 'phone', category: 'personal', encryption: true, retention: 2555 },
  { field: 'address', category: 'personal', encryption: true, retention: 2555 },
  { field: 'paymentInfo', category: 'financial', encryption: true, retention: 365 },
  { field: 'ssn', category: 'sensitive', encryption: true, retention: 365 },
];

@Injectable()
export class PIIService {
  constructor(private encryptionService: EncryptionService) {}

  public async sanitizeForLogging(data: any): Promise<any> {
    const sanitized = { ...data };

    for (const piiField of PII_FIELDS) {
      if (sanitized[piiField.field]) {
        if (piiField.category === 'sensitive') {
          delete sanitized[piiField.field];
        } else {
          sanitized[piiField.field] = this.maskField(sanitized[piiField.field], piiField.category);
        }
      }
    }

    return sanitized;
  }

  private maskField(value: string, category: PIIField['category']): string {
    switch (category) {
      case 'personal':
        return value.substring(0, 2) + '*'.repeat(value.length - 2);
      case 'financial':
        return '*'.repeat(value.length - 4) + value.substring(value.length - 4);
      default:
        return '***';
    }
  }
}
```

---

## Network Security

### 🔒 HTTPS & Security Headers

**Security Headers Configuration**

```typescript
export const SECURITY_HEADERS = {
  // Content Security Policy
  'Content-Security-Policy': [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // Minimize inline scripts
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob: https:",
    "font-src 'self' data:",
    "connect-src 'self' " + environment.apiUrl,
    "media-src 'self'",
    "object-src 'none'",
    "child-src 'none'",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self'",
  ].join('; '),

  // XSS Protection
  'X-XSS-Protection': '1; mode=block',
  'X-Content-Type-Options': 'nosniff',
  'X-Frame-Options': 'DENY',

  // HTTPS Enforcement
  'Strict-Transport-Security': 'max-age=31536000; includeSubDomains; preload',

  // Referrer Policy
  'Referrer-Policy': 'strict-origin-when-cross-origin',

  // Permissions Policy
  'Permissions-Policy': [
    'camera=()',
    'microphone=()',
    'geolocation=()',
    'payment=()',
    'usb=()',
  ].join(', '),
};

@Injectable()
export class SecurityHeadersInterceptor implements HttpInterceptor {
  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    // Add security headers to outgoing requests if needed
    let secureReq = req;

    // Add CSRF token for state-changing operations
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(req.method)) {
      const csrfToken = this.getCsrfToken();
      if (csrfToken) {
        secureReq = req.clone({
          setHeaders: {
            'X-CSRF-TOKEN': csrfToken,
          },
        });
      }
    }

    return next.handle(secureReq);
  }

  private getCsrfToken(): string | null {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null;
  }
}
```

### 🌐 CORS Configuration

**CORS Security**

```typescript
export const CORS_CONFIG = {
  origin: ['https://admin.boukii.app', 'https://staging.boukii-admin.app'],
  methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
  credentials: true,
  maxAge: 86400, // 24 hours
};
```

---

## Input Validation

### ✅ Frontend Validation

**Form Validation**

```typescript
export class SecureValidators {
  static email(control: AbstractControl): ValidationErrors | null {
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    if (!control.value) {
      return null;
    }

    if (!emailRegex.test(control.value)) {
      return { invalidEmail: true };
    }

    // Check for common email injection patterns
    if (this.containsInjectionPatterns(control.value)) {
      return { potentialInjection: true };
    }

    return null;
  }

  static password(control: AbstractControl): ValidationErrors | null {
    if (!control.value) {
      return null;
    }

    const password = control.value;
    const errors: ValidationErrors = {};

    // Minimum length
    if (password.length < 12) {
      errors['minLength'] = { requiredLength: 12, actualLength: password.length };
    }

    // Require uppercase
    if (!/[A-Z]/.test(password)) {
      errors['requireUppercase'] = true;
    }

    // Require lowercase
    if (!/[a-z]/.test(password)) {
      errors['requireLowercase'] = true;
    }

    // Require digits
    if (!/\d/.test(password)) {
      errors['requireDigits'] = true;
    }

    // Require special characters
    if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
      errors['requireSpecialChar'] = true;
    }

    // Check against common passwords
    if (this.isCommonPassword(password)) {
      errors['commonPassword'] = true;
    }

    return Object.keys(errors).length > 0 ? errors : null;
  }

  static noScriptTags(control: AbstractControl): ValidationErrors | null {
    if (!control.value) {
      return null;
    }

    const scriptRegex = /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi;
    if (scriptRegex.test(control.value)) {
      return { containsScript: true };
    }

    return null;
  }

  private static containsInjectionPatterns(value: string): boolean {
    const injectionPatterns = [
      /<script/i,
      /javascript:/i,
      /on\w+\s*=/i,
      /'.*OR.*'/i,
      /".*OR.*"/i,
      /UNION.*SELECT/i,
    ];

    return injectionPatterns.some((pattern) => pattern.test(value));
  }

  private static isCommonPassword(password: string): boolean {
    const commonPasswords = ['password', '123456789', 'qwerty123', 'admin123', 'password123'];

    return commonPasswords.includes(password.toLowerCase());
  }
}
```

**Input Sanitization**

```typescript
@Injectable()
export class SanitizationService {
  constructor(private sanitizer: DomSanitizer) {}

  public sanitizeHtml(html: string): SafeHtml {
    return this.sanitizer.sanitize(SecurityContext.HTML, html) || '';
  }

  public sanitizeUrl(url: string): SafeUrl {
    return this.sanitizer.sanitize(SecurityContext.URL, url) || '';
  }

  public sanitizeInput(input: string): string {
    return input
      .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
      .replace(/javascript:/gi, '')
      .replace(/on\w+\s*=/gi, '')
      .trim();
  }

  public validateAndSanitizeFormData(formData: any): any {
    const sanitized: any = {};

    for (const [key, value] of Object.entries(formData)) {
      if (typeof value === 'string') {
        sanitized[key] = this.sanitizeInput(value);
      } else if (Array.isArray(value)) {
        sanitized[key] = value.map((item) =>
          typeof item === 'string' ? this.sanitizeInput(item) : item
        );
      } else {
        sanitized[key] = value;
      }
    }

    return sanitized;
  }
}
```

---

## Error Handling

### 🚨 Secure Error Handling

**Error Response Sanitization**

```typescript
@Injectable()
export class ErrorSanitizationService {
  public sanitizeError(error: any): ErrorResponse {
    // Never expose internal error details in production
    if (environment.production) {
      return this.getGenericErrorResponse(error);
    }

    // In development, provide more details but still sanitize
    return this.sanitizeDevError(error);
  }

  private getGenericErrorResponse(error: any): ErrorResponse {
    const statusCode = error.status || 500;

    const errorMap: Record<number, string> = {
      400: 'Invalid request. Please check your input and try again.',
      401: 'Authentication required. Please log in.',
      403: 'Access denied. You do not have permission to perform this action.',
      404: 'The requested resource was not found.',
      429: 'Too many requests. Please try again later.',
      500: 'An internal error occurred. Please try again later.',
      503: 'Service temporarily unavailable. Please try again later.',
    };

    return {
      type: 'https://boukii.app/errors/generic',
      title: 'Request Failed',
      status: statusCode,
      detail: errorMap[statusCode] || 'An unexpected error occurred.',
      instance: this.generateErrorId(),
      timestamp: new Date().toISOString(),
    };
  }

  private sanitizeDevError(error: any): ErrorResponse {
    return {
      type: 'https://boukii.app/errors/development',
      title: error.name || 'Development Error',
      status: error.status || 500,
      detail: this.sanitizeErrorMessage(error.message),
      instance: this.generateErrorId(),
      timestamp: new Date().toISOString(),
      // Include sanitized stack trace in development
      stack: environment.production ? undefined : this.sanitizeStackTrace(error.stack),
    };
  }

  private sanitizeErrorMessage(message: string): string {
    // Remove sensitive information from error messages
    return message
      .replace(/password=\w+/gi, 'password=***')
      .replace(/token=[\w.-]+/gi, 'token=***')
      .replace(/key=\w+/gi, 'key=***')
      .replace(/secret=\w+/gi, 'secret=***')
      .replace(/\/api\/v\d+\/\w+/gi, '/api/***');
  }

  private sanitizeStackTrace(stack?: string): string[] | undefined {
    if (!stack) return undefined;

    return stack
      .split('\n')
      .filter((line) => !line.includes('node_modules'))
      .filter((line) => !line.includes('password'))
      .slice(0, 10); // Limit stack trace length
  }

  private generateErrorId(): string {
    return 'err-' + Math.random().toString(36).substr(2, 9);
  }
}
```

**Security Incident Logging**

```typescript
@Injectable()
export class SecurityLogger {
  constructor(private logger: LoggingService) {}

  public logSecurityEvent(event: SecurityEvent): void {
    const logEntry = {
      type: 'SECURITY_EVENT',
      event: event.type,
      severity: event.severity,
      userId: event.userId,
      sessionId: event.sessionId,
      ipAddress: this.getClientIP(),
      userAgent: navigator.userAgent,
      timestamp: new Date().toISOString(),
      details: this.sanitizeEventDetails(event.details),
    };

    // Log based on severity
    switch (event.severity) {
      case 'critical':
        this.logger.error('Critical security event', logEntry);
        this.triggerSecurityAlert(logEntry);
        break;
      case 'high':
        this.logger.warn('High severity security event', logEntry);
        break;
      case 'medium':
        this.logger.info('Medium severity security event', logEntry);
        break;
      default:
        this.logger.debug('Low severity security event', logEntry);
    }
  }

  public logFailedAuthentication(attempt: FailedAuthAttempt): void {
    this.logSecurityEvent({
      type: SecurityEventType.FAILED_AUTHENTICATION,
      severity: 'medium',
      userId: attempt.email,
      details: {
        attemptCount: attempt.attemptCount,
        lastAttempt: attempt.timestamp,
        reason: attempt.reason,
      },
    });
  }

  public logSuspiciousActivity(activity: SuspiciousActivity): void {
    this.logSecurityEvent({
      type: SecurityEventType.SUSPICIOUS_ACTIVITY,
      severity: 'high',
      userId: activity.userId,
      details: {
        activityType: activity.type,
        description: activity.description,
        riskScore: activity.riskScore,
      },
    });
  }

  private sanitizeEventDetails(details: any): any {
    // Remove sensitive information from event details
    const sanitized = { ...details };
    delete sanitized.password;
    delete sanitized.token;
    delete sanitized.secret;
    return sanitized;
  }

  private triggerSecurityAlert(event: any): void {
    // Trigger real-time security alerts for critical events
    console.error('CRITICAL SECURITY EVENT:', event);
    // In production, this would send alerts to security team
  }
}
```

---

## Security Headers

### 🛡️ Content Security Policy

**CSP Implementation**

```typescript
export class CSPService {
  public static generateCSP(environment: Environment): string {
    const baseCSP = {
      'default-src': ["'self'"],
      'script-src': [
        "'self'",
        "'unsafe-inline'", // Minimize usage
        "'unsafe-eval'", // Avoid if possible
      ],
      'style-src': ["'self'", "'unsafe-inline'", 'fonts.googleapis.com'],
      'img-src': ["'self'", 'data:', 'blob:', 'https:'],
      'font-src': ["'self'", 'fonts.gstatic.com'],
      'connect-src': [
        "'self'",
        environment.apiUrl,
        'wss:', // For WebSocket connections
      ],
      'media-src': ["'self'"],
      'object-src': ["'none'"],
      'child-src': ["'none'"],
      'frame-ancestors': ["'none'"],
      'base-uri': ["'self'"],
      'form-action': ["'self'"],
      'manifest-src': ["'self'"],
    };

    // Add report-uri in production
    if (environment.production && environment.cspReportUrl) {
      baseCSP['report-uri'] = [environment.cspReportUrl];
    }

    return Object.entries(baseCSP)
      .map(([directive, sources]) => `${directive} ${sources.join(' ')}`)
      .join('; ');
  }
}
```

---

## Dependency Security

### 📦 Vulnerability Management

**Automated Security Scanning**

```yaml
# .github/workflows/security-scan.yml
name: Security Scan
on:
  schedule:
    - cron: '0 2 * * *' # Daily at 2 AM
  push:
    branches: [main, develop]

jobs:
  security-audit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Run npm audit
        run: |
          npm audit --audit-level=moderate
          npm audit --json > audit-results.json

      - name: Check for high/critical vulnerabilities
        run: |
          CRITICAL=$(cat audit-results.json | jq '.metadata.vulnerabilities.critical')
          HIGH=$(cat audit-results.json | jq '.metadata.vulnerabilities.high')

          if [ "$CRITICAL" -gt 0 ] || [ "$HIGH" -gt 0 ]; then
            echo "Critical or high vulnerabilities found!"
            exit 1
          fi
```

**Dependency Policy**

```typescript
export const DEPENDENCY_POLICY = {
  // Allowed package sources
  allowedRegistries: ['https://registry.npmjs.org/', 'https://npm.pkg.github.com/'],

  // Package vulnerability thresholds
  vulnerabilityThresholds: {
    critical: 0, // No critical vulnerabilities allowed
    high: 0, // No high vulnerabilities allowed
    moderate: 5, // Max 5 moderate vulnerabilities
    low: 20, // Max 20 low vulnerabilities
  },

  // License whitelist
  allowedLicenses: ['MIT', 'Apache-2.0', 'BSD-2-Clause', 'BSD-3-Clause', 'ISC'],

  // Blocked packages (security concerns)
  blockedPackages: [
    'event-stream', // Known security issue
    'flatmap-stream', // Known security issue
  ],
};
```

---

## Monitoring & Logging

### 📊 Security Monitoring

**Security Event Tracking**

```typescript
export enum SecurityEventType {
  FAILED_AUTHENTICATION = 'failed_authentication',
  SUSPICIOUS_ACTIVITY = 'suspicious_activity',
  UNAUTHORIZED_ACCESS = 'unauthorized_access',
  DATA_BREACH_ATTEMPT = 'data_breach_attempt',
  INJECTION_ATTEMPT = 'injection_attempt',
  PRIVILEGE_ESCALATION = 'privilege_escalation',
}

@Injectable()
export class SecurityMonitoringService {
  private securityMetrics = {
    failedLogins: 0,
    suspiciousActivities: 0,
    blockedRequests: 0,
    lastSecurityEvent: null as Date | null,
  };

  public trackSecurityMetric(eventType: SecurityEventType, metadata?: any): void {
    const event = {
      type: eventType,
      timestamp: new Date(),
      userId: this.authStore.user()?.id,
      sessionId: this.sessionService.getSessionId(),
      metadata: metadata || {},
    };

    // Update metrics
    this.updateSecurityMetrics(eventType);

    // Log event
    this.securityLogger.logSecurityEvent(event);

    // Check for attack patterns
    this.analyzeAttackPatterns(event);

    // Trigger real-time monitoring
    this.triggerRealTimeAlert(event);
  }

  private analyzeAttackPatterns(event: SecurityEvent): void {
    // Detect brute force attacks
    if (event.type === SecurityEventType.FAILED_AUTHENTICATION) {
      this.detectBruteForceAttack(event);
    }

    // Detect injection attempts
    if (event.type === SecurityEventType.INJECTION_ATTEMPT) {
      this.detectInjectionPattern(event);
    }

    // Detect privilege escalation
    if (event.type === SecurityEventType.PRIVILEGE_ESCALATION) {
      this.detectPrivilegeEscalation(event);
    }
  }

  private detectBruteForceAttack(event: SecurityEvent): void {
    const recentFailures = this.getRecentFailedLogins(event.userId, 15); // 15 minutes

    if (recentFailures.length >= 5) {
      this.triggerSecurityAlert({
        type: 'BRUTE_FORCE_DETECTED',
        severity: 'critical',
        userId: event.userId,
        details: {
          attemptCount: recentFailures.length,
          timeWindow: '15 minutes',
        },
      });

      // Temporarily lock account
      this.lockAccount(event.userId, 30); // 30 minutes
    }
  }
}
```

---

## Security Best Practices

### ✅ Implementation Checklist

#### **Authentication & Authorization**

- [ ] Implement strong password policies (12+ chars, complexity)
- [ ] Use JWT with short expiration times (15 minutes)
- [ ] Implement refresh token rotation
- [ ] Store tokens securely (httpOnly cookies in production)
- [ ] Implement account lockout after failed attempts
- [ ] Use role-based access control (RBAC)
- [ ] Implement permission-based guards
- [ ] Log all authentication events

#### **Data Protection**

- [ ] Encrypt sensitive data at rest
- [ ] Use HTTPS for all communications
- [ ] Implement proper key management
- [ ] Sanitize all user inputs
- [ ] Validate data on both client and server
- [ ] Implement proper session management
- [ ] Use secure random number generation
- [ ] Implement data retention policies

#### **Network Security**

- [ ] Configure Content Security Policy (CSP)
- [ ] Implement HTTPS Strict Transport Security (HSTS)
- [ ] Set secure HTTP headers
- [ ] Configure CORS properly
- [ ] Implement rate limiting
- [ ] Use CSRF protection
- [ ] Validate SSL certificates
- [ ] Monitor network traffic

#### **Error Handling**

- [ ] Sanitize error messages in production
- [ ] Implement proper logging without sensitive data
- [ ] Use generic error responses
- [ ] Implement security incident logging
- [ ] Monitor for suspicious patterns
- [ ] Implement alerting for critical events
- [ ] Create incident response procedures
- [ ] Regular security testing

#### **Dependency Management**

- [ ] Regularly audit dependencies for vulnerabilities
- [ ] Use automated dependency updates
- [ ] Implement dependency vulnerability scanning
- [ ] Monitor license compliance
- [ ] Use package lock files
- [ ] Verify package integrity
- [ ] Remove unused dependencies
- [ ] Monitor for malicious packages

### 🚨 Security Incident Response

**Incident Response Plan**

```typescript
export class SecurityIncidentResponse {
  public handleSecurityIncident(incident: SecurityIncident): void {
    // 1. Immediate Response
    this.immediateResponse(incident);

    // 2. Assessment
    this.assessIncident(incident);

    // 3. Containment
    this.containIncident(incident);

    // 4. Investigation
    this.investigateIncident(incident);

    // 5. Recovery
    this.recoverFromIncident(incident);

    // 6. Lessons Learned
    this.documentLessonsLearned(incident);
  }

  private immediateResponse(incident: SecurityIncident): void {
    // Log incident
    this.logger.critical('Security incident detected', incident);

    // Notify security team
    this.notifySecurityTeam(incident);

    // If critical, implement emergency measures
    if (incident.severity === 'critical') {
      this.implementEmergencyMeasures(incident);
    }
  }

  private implementEmergencyMeasures(incident: SecurityIncident): void {
    switch (incident.type) {
      case 'data_breach':
        this.lockAllUserAccounts();
        this.disableDataExports();
        break;
      case 'system_compromise':
        this.enableMaintenanceMode();
        this.invalidateAllTokens();
        break;
      case 'ddos_attack':
        this.enableRateLimiting();
        this.blockSuspiciousIPs();
        break;
    }
  }
}
```

---

## 🔍 Security Testing

### Automated Security Testing

```bash
# Security testing commands
npm run security:audit          # Dependency vulnerability scan
npm run security:lint           # Security-focused linting
npm run security:test           # Security unit tests
npm run security:integration    # Security integration tests
npm run security:e2e            # Security end-to-end tests
```

### Manual Security Testing

- **Input validation testing**
- **Authentication bypass testing**
- **Authorization testing**
- **Session management testing**
- **CSRF testing**
- **XSS testing**
- **SQL injection testing**
- **File upload testing**

---

_Last updated: 2025-08-16_

_For security concerns or questions, contact the security team immediately._

**🚨 Security Hotline: security@boukii.app**
