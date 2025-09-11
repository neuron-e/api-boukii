import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { interval, switchMap, startWith } from 'rxjs';

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
  period: { hours: number; from: string; to: string; };
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

@Component({
  selector: 'app-monitoring-dashboard',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="monitoring-dashboard">
      <div class="dashboard-header">
        <h1>📊 Boukii V5 Monitoring Dashboard</h1>
        <div class="last-updated">
          Última actualización: {{ lastUpdated() | date:'medium' }}
        </div>
      </div>

      <!-- System Health Status -->
      <div class="status-grid">
        <div class="status-card" [class]="'status-' + systemHealth()?.status">
          <h3>🏥 Estado del Sistema</h3>
          <div class="status-indicator">
            <span class="status-badge" [class]="'badge-' + systemHealth()?.status">
              {{ getStatusText(systemHealth()?.status) }}
            </span>
          </div>
          <div class="health-checks" *ngIf="systemHealth()?.checks">
            <div *ngFor="let check of getHealthChecks()" class="health-check">
              <span class="check-name">{{ check.name }}:</span>
              <span class="check-status" [class]="'status-' + check.status">
                {{ getCheckStatusIcon(check.status) }} {{ check.status }}
              </span>
            </div>
          </div>
        </div>

        <!-- Migration Progress -->
        <div class="status-card">
          <h3>🚀 Progreso de Migración</h3>
          <div class="progress-stats" *ngIf="migrationStatus()">
            <div class="progress-bar-container">
              <div class="progress-bar" 
                   [style.width.%]="migrationStatus()!.migration_percentage">
              </div>
              <span class="progress-text">
                {{ migrationStatus()!.migration_percentage }}%
              </span>
            </div>
            <div class="migration-numbers">
              <div class="stat">
                <span class="number">{{ migrationStatus()!.migrated_schools }}</span>
                <span class="label">Migradas</span>
              </div>
              <div class="stat">
                <span class="number">{{ migrationStatus()!.pending_schools }}</span>
                <span class="label">Pendientes</span>
              </div>
              <div class="stat">
                <span class="number">{{ migrationStatus()!.total_schools }}</span>
                <span class="label">Total</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Version Distribution -->
        <div class="status-card">
          <h3>📈 Distribución de Versiones</h3>
          <div class="version-chart" *ngIf="versionDistribution()">
            <div class="version-item">
              <span class="version-label">Legacy (V4):</span>
              <span class="version-count">{{ versionDistribution()!.legacy }}</span>
            </div>
            <div class="version-item">
              <span class="version-label">Mixto:</span>
              <span class="version-count">{{ versionDistribution()!.mixed }}</span>
            </div>
            <div class="version-item">
              <span class="version-label">V5 Completo:</span>
              <span class="version-count">{{ versionDistribution()!.v5 }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Performance Comparison -->
      <div class="performance-section" *ngIf="performanceData()">
        <h2>⚡ Comparativa de Performance V4 vs V5</h2>
        <div class="performance-cards">
          <div class="perf-card v4">
            <h3>V4 (Legacy)</h3>
            <div class="perf-stats">
              <div class="perf-stat">
                <span class="stat-value">{{ performanceData()!.v4.request_count | number }}</span>
                <span class="stat-label">Requests</span>
              </div>
              <div class="perf-stat">
                <span class="stat-value">{{ performanceData()!.v4.avg_response_time | number:'1.0-0' }}ms</span>
                <span class="stat-label">Tiempo Promedio</span>
              </div>
              <div class="perf-stat">
                <span class="stat-value">{{ performanceData()!.v4.error_rate | number:'1.1-1' }}%</span>
                <span class="stat-label">Tasa de Error</span>
              </div>
            </div>
          </div>

          <div class="perf-card v5">
            <h3>V5 (Nuevo)</h3>
            <div class="perf-stats">
              <div class="perf-stat">
                <span class="stat-value">{{ performanceData()!.v5.request_count | number }}</span>
                <span class="stat-label">Requests</span>
              </div>
              <div class="perf-stat">
                <span class="stat-value">{{ performanceData()!.v5.avg_response_time | number:'1.0-0' }}ms</span>
                <span class="stat-label">Tiempo Promedio</span>
              </div>
              <div class="perf-stat">
                <span class="stat-value">{{ performanceData()!.v5.error_rate | number:'1.1-1' }}%</span>
                <span class="stat-label">Tasa de Error</span>
              </div>
            </div>
          </div>

          <div class="perf-card improvement">
            <h3>📊 Mejoras</h3>
            <div class="improvement-stats">
              <div class="improvement-stat">
                <span class="improvement-label">Tiempo de Respuesta:</span>
                <span class="improvement-value" [class]="'improvement-' + performanceData()!.improvement.response_time.direction">
                  {{ getImprovementIcon(performanceData()!.improvement.response_time.direction) }}
                  {{ performanceData()!.improvement.response_time.percentage | number:'1.1-1' }}%
                </span>
              </div>
              <div class="improvement-stat">
                <span class="improvement-label">Tasa de Error:</span>
                <span class="improvement-value" [class]="'improvement-' + performanceData()!.improvement.error_rate.direction">
                  {{ getImprovementIcon(performanceData()!.improvement.error_rate.direction) }}
                  {{ performanceData()!.improvement.error_rate.percentage | number:'1.1-1' }}%
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Alerts -->
      <div class="alerts-section">
        <h2>🚨 Alertas Recientes</h2>
        <div class="alerts-container" *ngIf="recentAlerts().length > 0; else noAlerts">
          <div *ngFor="let alert of recentAlerts().slice(0, 10)" 
               class="alert-card" 
               [class]="'alert-' + alert.severity">
            <div class="alert-header">
              <span class="alert-icon">{{ getAlertIcon(alert.severity) }}</span>
              <span class="alert-type">{{ formatAlertType(alert.type) }}</span>
              <span class="alert-time">{{ alert.timestamp | date:'short' }}</span>
            </div>
            <div class="alert-details">
              <div *ngIf="alert.school_id" class="alert-detail">
                <strong>Escuela:</strong> {{ alert.school_id }}
              </div>
              <div *ngIf="alert.response_time_ms" class="alert-detail">
                <strong>Tiempo:</strong> {{ alert.response_time_ms }}ms
              </div>
              <div *ngIf="alert.error_count" class="alert-detail">
                <strong>Errores:</strong> {{ alert.error_count }}
              </div>
            </div>
          </div>
        </div>
        
        <ng-template #noAlerts>
          <div class="no-alerts">
            <p>✅ No hay alertas recientes. ¡El sistema funciona correctamente!</p>
          </div>
        </ng-template>
      </div>
    </div>
  `,
  styles: [`
    .monitoring-dashboard {
      padding: 20px;
      max-width: 1400px;
      margin: 0 auto;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .dashboard-header {
      text-align: center;
      margin-bottom: 30px;
    }

    .dashboard-header h1 {
      color: #2c3e50;
      font-size: 2.5rem;
      margin-bottom: 10px;
    }

    .last-updated {
      color: #7f8c8d;
      font-size: 0.9rem;
    }

    .status-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
      margin-bottom: 40px;
    }

    .status-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      border-left: 4px solid #3498db;
    }

    .status-healthy { border-left-color: #27ae60; }
    .status-degraded { border-left-color: #f39c12; }
    .status-unhealthy { border-left-color: #e74c3c; }

    .status-card h3 {
      margin: 0 0 15px 0;
      color: #2c3e50;
      font-size: 1.2rem;
    }

    .status-indicator {
      margin-bottom: 15px;
    }

    .status-badge {
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.9rem;
      text-transform: uppercase;
    }

    .badge-healthy { background: #d5f4e6; color: #27ae60; }
    .badge-degraded { background: #fef9e7; color: #f39c12; }
    .badge-unhealthy { background: #fadbd8; color: #e74c3c; }

    .health-checks {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .health-check {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.9rem;
    }

    .check-name {
      color: #7f8c8d;
    }

    .check-status {
      font-weight: 600;
    }

    .check-status.status-ok { color: #27ae60; }
    .check-status.status-warning { color: #f39c12; }
    .check-status.status-error { color: #e74c3c; }

    .progress-bar-container {
      position: relative;
      background: #ecf0f1;
      border-radius: 10px;
      height: 20px;
      margin-bottom: 15px;
    }

    .progress-bar {
      background: linear-gradient(90deg, #3498db, #2ecc71);
      height: 100%;
      border-radius: 10px;
      transition: width 0.3s ease;
    }

    .progress-text {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      color: #2c3e50;
      font-weight: 600;
      font-size: 0.9rem;
    }

    .migration-numbers {
      display: flex;
      justify-content: space-around;
    }

    .stat {
      text-align: center;
    }

    .stat .number {
      display: block;
      font-size: 1.8rem;
      font-weight: 700;
      color: #2c3e50;
    }

    .stat .label {
      color: #7f8c8d;
      font-size: 0.8rem;
    }

    .version-chart {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .version-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid #ecf0f1;
    }

    .version-label {
      color: #7f8c8d;
    }

    .version-count {
      font-weight: 600;
      color: #2c3e50;
    }

    .performance-section {
      margin-bottom: 40px;
    }

    .performance-section h2 {
      color: #2c3e50;
      margin-bottom: 20px;
      text-align: center;
    }

    .performance-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
    }

    .perf-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .perf-card.v4 { border-left: 4px solid #e74c3c; }
    .perf-card.v5 { border-left: 4px solid #27ae60; }
    .perf-card.improvement { border-left: 4px solid #9b59b6; }

    .perf-card h3 {
      margin: 0 0 15px 0;
      color: #2c3e50;
    }

    .perf-stats {
      display: flex;
      justify-content: space-around;
      gap: 15px;
    }

    .perf-stat {
      text-align: center;
    }

    .stat-value {
      display: block;
      font-size: 1.5rem;
      font-weight: 700;
      color: #2c3e50;
    }

    .stat-label {
      color: #7f8c8d;
      font-size: 0.8rem;
    }

    .improvement-stats {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    .improvement-stat {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .improvement-label {
      color: #7f8c8d;
    }

    .improvement-value {
      font-weight: 600;
      padding: 4px 8px;
      border-radius: 4px;
    }

    .improvement-improved {
      background: #d5f4e6;
      color: #27ae60;
    }

    .improvement-degraded {
      background: #fadbd8;
      color: #e74c3c;
    }

    .improvement-neutral {
      background: #f8f9fa;
      color: #6c757d;
    }

    .alerts-section {
      margin-bottom: 40px;
    }

    .alerts-section h2 {
      color: #2c3e50;
      margin-bottom: 20px;
      text-align: center;
    }

    .alerts-container {
      display: grid;
      gap: 15px;
    }

    .alert-card {
      background: white;
      border-radius: 8px;
      padding: 15px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .alert-critical { border-left: 4px solid #e74c3c; }
    .alert-warning { border-left: 4px solid #f39c12; }
    .alert-info { border-left: 4px solid #3498db; }

    .alert-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .alert-icon {
      font-size: 1.2rem;
      margin-right: 8px;
    }

    .alert-type {
      font-weight: 600;
      color: #2c3e50;
      flex: 1;
    }

    .alert-time {
      color: #7f8c8d;
      font-size: 0.9rem;
    }

    .alert-details {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }

    .alert-detail {
      color: #7f8c8d;
      font-size: 0.9rem;
    }

    .no-alerts {
      text-align: center;
      padding: 40px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .no-alerts p {
      margin: 0;
      color: #27ae60;
      font-size: 1.1rem;
    }

    @media (max-width: 768px) {
      .dashboard-header h1 {
        font-size: 2rem;
      }

      .status-grid {
        grid-template-columns: 1fr;
      }

      .performance-cards {
        grid-template-columns: 1fr;
      }

      .perf-stats {
        flex-direction: column;
        gap: 10px;
      }

      .improvement-stats {
        gap: 10px;
      }
    }
  `]
})
export class MonitoringDashboardComponent implements OnInit {
  private http = inject(HttpClient);
  
  // Signals for reactive data
  readonly systemStats = signal<SystemStats | null>(null);
  readonly performanceData = signal<PerformanceComparison | null>(null);
  readonly lastUpdated = signal<Date>(new Date());
  readonly loading = signal<boolean>(true);
  readonly error = signal<string | null>(null);

  // Computed properties for easy template access
  readonly systemHealth = computed(() => this.systemStats()?.system_health);
  readonly migrationStatus = computed(() => this.systemStats()?.migration_status);
  readonly versionDistribution = computed(() => this.systemStats()?.version_distribution);
  readonly recentAlerts = computed(() => this.systemStats()?.recent_alerts || []);

  ngOnInit() {
    this.startPolling();
    this.loadInitialData();
  }

  private startPolling() {
    // Poll every 30 seconds
    interval(30000).pipe(
      startWith(0),
      switchMap(() => this.loadSystemStats())
    ).subscribe();

    // Load performance data every 2 minutes
    interval(120000).pipe(
      startWith(0),
      switchMap(() => this.loadPerformanceData())
    ).subscribe();
  }

  private loadInitialData() {
    this.loading.set(true);
    Promise.all([
      this.loadSystemStats().toPromise(),
      this.loadPerformanceData().toPromise()
    ]).finally(() => {
      this.loading.set(false);
    });
  }

  private loadSystemStats() {
    return this.http.get<SystemStats>('/monitoring/system-stats').pipe(
      switchMap(stats => {
        this.systemStats.set(stats);
        this.lastUpdated.set(new Date());
        this.error.set(null);
        return [];
      })
    );
  }

  private loadPerformanceData() {
    return this.http.get<PerformanceComparison>('/monitoring/performance-comparison').pipe(
      switchMap(data => {
        this.performanceData.set(data);
        return [];
      })
    );
  }

  getStatusText(status?: string): string {
    const statusMap: Record<string, string> = {
      'healthy': 'Saludable',
      'degraded': 'Degradado',
      'unhealthy': 'Con Problemas'
    };
    return statusMap[status || 'healthy'] || 'Desconocido';
  }

  getHealthChecks() {
    const checks = this.systemHealth()?.checks || {};
    return Object.entries(checks).map(([name, status]) => ({
      name: this.formatCheckName(name),
      status
    }));
  }

  private formatCheckName(name: string): string {
    const nameMap: Record<string, string> = {
      'database': 'Base de Datos',
      'redis': 'Redis',
      'migration_errors': 'Errores de Migración'
    };
    return nameMap[name] || name;
  }

  getCheckStatusIcon(status: string): string {
    const iconMap: Record<string, string> = {
      'ok': '✅',
      'warning': '⚠️',
      'error': '❌'
    };
    return iconMap[status] || '❓';
  }

  getAlertIcon(severity: string): string {
    const iconMap: Record<string, string> = {
      'critical': '🚨',
      'warning': '⚠️',
      'info': 'ℹ️'
    };
    return iconMap[severity] || 'ℹ️';
  }

  formatAlertType(type: string): string {
    const typeMap: Record<string, string> = {
      'performance_degradation': 'Degradación de Performance',
      'critical_migration_errors': 'Errores Críticos de Migración'
    };
    return typeMap[type] || type.replace('_', ' ');
  }

  getImprovementIcon(direction: string): string {
    const iconMap: Record<string, string> = {
      'improved': '📈',
      'degraded': '📉',
      'neutral': '➡️'
    };
    return iconMap[direction] || '➡️';
  }
}