import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { TokenContextService } from '../services/token-context.service';

/**
 * HTTP Interceptor V5 that adds Authorization header and context headers
 * (X-School-ID, X-Season-ID) to authenticated requests
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const tokenContext = inject(TokenContextService);
  const token = tokenContext.getToken();

  // Skip auth header for certain URLs
  const skipAuthUrls = [
    '/api/v5/auth/login',
    '/api/v5/auth/register',
    '/api/v5/auth/forgot-password',
    '/auth/login',
    '/auth/register',
    '/auth/forgot-password',
    '/auth/reset-password',
    '/assets/',
  ];

  const shouldSkipAuth = skipAuthUrls.some((url) => req.url.includes(url));

  // Only add headers if we have a token and URL requires auth
  if (token && !shouldSkipAuth) {
    const headers: { [key: string]: string } = {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    };

    // Add school/season context headers if available
    const context = tokenContext.getAuthContext();
    if (context) {
      headers['X-School-ID'] = context.school_id.toString();
      headers['X-Season-ID'] = context.season_id.toString();
    }

    const authReq = req.clone({
      setHeaders: headers
    });

    return next(authReq);
  }

  return next(req);
};
