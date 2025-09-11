import { Injectable, inject, signal } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, timer, switchMap, startWith, share } from 'rxjs';

interface SystemStats {
  migration_status: {
    total_schools: number;
    migrated_schools: number;
    pending_schools: number;
    migration_percentage: number;
  };
  performance_overview: {
    v4: { request_count: number; avg_response_time: number; };
    v5: { request_count: number; avg_response_time: number; };
  };
  recent_alerts: AlertData[];
  version_distribution: { legacy: number; mixed: number; v5: number; };
  system_health: { status: string; checks: Record<string, string>; };
}

interface AlertData {
  type: string;
  severity: 'critical' | 'warning' | 'info';
  school_id?: number;
  timestamp: string;
  response_time_ms?: number;
  error_count?: number;
}

interface PerformanceComparison {
  v4: VersionStats;
  v5: VersionStats;
  improvement: {
    response_time: { percentage: number; direction: string; };
    error_rate: { percentage: number; direction: string; };
  };
  period: { hours: number; from: string; to: string; module?: string; };
}

interface VersionStats {
  request_count: number;
  avg_response_time: number;
  min_response_time: number;
  max_response_time: number;
  p95_response_time: number;
  error_rate: number;
  errors_count: number;
}

interface PerformanceMetric {
  school_id?: number;
  version: 'v4' | 'v5';
  module: string;
  action: string;
  response_time_ms: number;
  metadata?: Record<string, any>;
}

interface MigrationError {
  school_id: number;
  migration_type: string;
  error_message: string;
  context?: Record<string, any>;
}

@Injectable({
  providedIn: 'root'
})
export class MonitoringService {
  private http = inject(HttpClient);
  
  // Polling observables
  readonly systemStats$ = timer(0, 30000).pipe( // Every 30 seconds
    switchMap(() => this.getSystemStats()),
    share()
  );

  readonly performanceComparison$ = timer(0, 120000).pipe( // Every 2 minutes
    switchMap(() => this.getPerformanceComparison()),
    share()
  );

  // Reactive signals for real-time updates
  readonly isPolling = signal(false);

  /**
   * Get system statistics
   */
  getSystemStats(): Observable<SystemStats> {
    return this.http.get<SystemStats>('/monitoring/system-stats');
  }

  /**
   * Get performance comparison between V4 and V5
   */
  getPerformanceComparison(params?: {
    school_id?: number;
    module?: string;
    hours?: number;
  }): Observable<PerformanceComparison> {
    let httpParams = new HttpParams();
    
    if (params?.school_id) {
      httpParams = httpParams.set('school_id', params.school_id.toString());
    }
    if (params?.module) {
      httpParams = httpParams.set('module', params.module);
    }
    if (params?.hours) {
      httpParams = httpParams.set('hours', params.hours.toString());
    }

    return this.http.get<PerformanceComparison>('/monitoring/performance-comparison', {
      params: httpParams
    });
  }

  /**
   * Get recent alerts with optional filters
   */
  getRecentAlerts(params?: {
    severity?: 'critical' | 'warning' | 'info';
    limit?: number;
    school_id?: number;
  }): Observable<AlertData[]> {
    let httpParams = new HttpParams();
    
    if (params?.severity) {
      httpParams = httpParams.set('severity', params.severity);
    }
    if (params?.limit) {
      httpParams = httpParams.set('limit', params.limit.toString());
    }
    if (params?.school_id) {
      httpParams = httpParams.set('school_id', params.school_id.toString());
    }

    return this.http.get<AlertData[]>('/monitoring/alerts', {
      params: httpParams
    });
  }

  /**
   * Record a performance metric
   */
  recordPerformance(metric: PerformanceMetric): Observable<{ success: boolean }> {
    return this.http.post<{ success: boolean }>('/monitoring/performance', metric);
  }

  /**
   * Record a migration error
   */
  recordMigrationError(error: MigrationError): Observable<{ success: boolean }> {
    return this.http.post<{ success: boolean }>('/monitoring/migration-error', error);
  }

  /**
   * Get metrics for a specific school
   */
  getSchoolMetrics(schoolId: number, params?: {
    module?: string;
    hours?: number;
  }): Observable<{
    school_id: number;
    performance: PerformanceComparison;
    period: { hours: number; module: string; };
  }> {
    let httpParams = new HttpParams();
    
    if (params?.module) {
      httpParams = httpParams.set('module', params.module);
    }
    if (params?.hours) {
      httpParams = httpParams.set('hours', params.hours.toString());
    }

    return this.http.get<{
      school_id: number;
      performance: PerformanceComparison;
      period: { hours: number; module: string; };
    }>(`/monitoring/school/${schoolId}`, {
      params: httpParams
    });
  }

  /**
   * Public health check (no auth required)
   */
  healthCheck(): Observable<{
    status: string;
    timestamp: string;
    checks: Record<string, string>;
  }> {
    return this.http.get<{
      status: string;
      timestamp: string;
      checks: Record<string, string>;
    }>('/monitoring/health');
  }

  /**
   * Clear metrics cache (development only)
   */
  clearMetricsCache(): Observable<{ success: boolean; message: string }> {
    return this.http.delete<{ success: boolean; message: string }>('/monitoring/cache');
  }

  /**
   * Helper method to automatically track page performance
   */
  trackPagePerformance(
    version: 'v4' | 'v5',
    module: string,
    action: string,
    startTime: number,
    schoolId?: number,
    metadata?: Record<string, any>
  ): void {
    const responseTime = Date.now() - startTime;
    
    this.recordPerformance({
      school_id: schoolId,
      version,
      module,
      action,
      response_time_ms: responseTime,
      metadata: {
        ...metadata,
        user_agent: navigator.userAgent,
        timestamp: new Date().toISOString()
      }
    }).subscribe({
      next: () => console.debug(`Performance tracked: ${module}.${action} - ${responseTime}ms`),
      error: (error) => console.warn('Failed to track performance:', error)
    });
  }

  /**
   * Helper method to track errors during migration
   */
  trackMigrationError(
    schoolId: number,
    migrationType: string,
    error: Error,
    context?: Record<string, any>
  ): void {
    this.recordMigrationError({
      school_id: schoolId,
      migration_type: migrationType,
      error_message: error.message,
      context: {
        ...context,
        stack: error.stack,
        timestamp: new Date().toISOString(),
        user_agent: navigator.userAgent
      }
    }).subscribe({
      next: () => console.debug(`Migration error tracked: ${migrationType}`),
      error: (err) => console.warn('Failed to track migration error:', err)
    });
  }

  /**
   * Start polling for real-time updates
   */
  startPolling(): void {
    this.isPolling.set(true);
  }

  /**
   * Stop polling
   */
  stopPolling(): void {
    this.isPolling.set(false);
  }
}