import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { ConfigService } from '../services/config.service';

/**
 * HTTP Interceptor that adds base URL to relative API requests
 * This ensures all services work consistently regardless of whether they use
 * ApiService or HttpClient directly
 */
export const baseUrlInterceptor: HttpInterceptorFn = (req, next) => {
  const config = inject(ConfigService);

  // Skip if URL is already absolute or is an asset
  if (req.url.startsWith('http') || req.url.startsWith('/assets/')) {
    return next(req);
  }

  // Skip if URL starts with specific prefixes that shouldn't be modified
  if (req.url.startsWith('/api/v5/api/') || req.url.includes('://')) {
    return next(req);
  }

  // Add base URL for relative API paths
  const baseUrl = config.getApiBaseUrl();
  const fullUrl = `${baseUrl}${req.url.startsWith('/') ? req.url : '/' + req.url}`;

  const modifiedReq = req.clone({
    url: fullUrl
  });

  return next(modifiedReq);
};