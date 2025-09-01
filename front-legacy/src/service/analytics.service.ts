import { Injectable } from '@angular/core';
import { HttpClient, HttpParams, HttpErrorResponse } from '@angular/common/http';
import { Observable, BehaviorSubject, throwError, of, timer } from 'rxjs';
import { map, catchError, retry, shareReplay, switchMap, tap } from 'rxjs/operators';
import moment from 'moment';

// ==================== INTERFACES ====================

export interface AnalyticsFilters {
  school_id: number;
  start_date?: string;
  end_date?: string;
  season_id?: number;
  course_type?: number;
  source?: string;
  sport_id?: number;
  only_weekends?: boolean;
  optimization_level?: 'fast' | 'balanced' | 'detailed';
  include_test_detection?: boolean;
  include_payrexx_analysis?: boolean;
}

interface BookingSourceAnalysis {
  source: string;
  count: number;
  revenue: number;
  percentage: number;
  averageValue: number;
}

interface FinancialSummary {
  totalExpectedRevenue: number;
  totalReceivedRevenue: number;
  totalPendingRevenue: number;
  collectionEfficiency: number;
  averageBookingValue: number;
}

interface PaymentMethodBreakdown {
  cash: PaymentMethodData;
  card: PaymentMethodData;
  online: PaymentMethodData;
  vouchers: PaymentMethodData;
  pending: PaymentMethodData;
}

interface PaymentMethodData {
  count: number;
  totalAmount: number;
  averageAmount: number;
  percentage: number;
}

export interface SeasonDashboard {
  seasonInfo: any;
  executiveKpis: ExecutiveKpis;
  bookingSources: BookingSourceAnalysis[];
  paymentMethods: PaymentMethodBreakdown;
  bookingStatusAnalysis: any;
  financialSummary: FinancialSummary;
  performanceMetrics: any;
  testDetectionInfo?: any;
  payrexxAnalysis?: any;
}

export interface ExecutiveKpis {
  totalBookings: number;
  totalClients: number;
  totalParticipants: number;
  revenueExpected: number;
  revenueReceived: number;
  revenuePending: number;
  collectionEfficiency: number;
  consistencyRate: number;
  averageBookingValue: number;
}

export interface RevenueByPeriod {
  periodType: 'daily' | 'weekly' | 'monthly';
  dateRange: { start: string; end: string };
  data: RevenueDataPoint[];
  summary: RevenueSummary;
}

export interface RevenueDataPoint {
  period: string;
  date: string;
  totalBookings: number;
  activeBookings: number;
  cancelledBookings: number;
  expectedRevenue: number;
  receivedRevenue: number;
  pendingRevenue: number;
  collectionRate: number;
}

export interface RevenueSummary {
  totalPeriods: number;
  totalBookings: number;
  totalExpected: number;
  totalReceived: number;
  averageCollectionRate: number;
}

export interface CourseAnalytics {
  courses: CourseAnalyticsData[];
  summary: CourseAnalyticsSummary;
}

export interface CourseAnalyticsData {
  courseId: number;
  courseName: string;
  courseType: number;
  sportName: string;
  basePrice: number;
  totalBookings: number;
  activeBookings: number;
  cancelledBookings: number;
  totalRevenue: number;
  totalPaid: number;
  pendingAmount: number;
  averagePrice: number;
  completionRate: number;
  collectionEfficiency: number;
  paymentMethods: any;
  profitabilityScore: number;
}

export interface CourseAnalyticsSummary {
  totalCourses: number;
  totalRevenue: number;
  averageCompletionRate: number;
  bestPerformingCourse: CourseAnalyticsData;
  mostPopularCourse: CourseAnalyticsData;
}

export interface MonitorAnalytics {
  monitors: MonitorAnalyticsData[];
  summary: MonitorAnalyticsSummary;
}

export interface MonitorAnalyticsData {
  monitorId: number;
  monitorName: string;
  email: string;
  sportsAssigned: string[];
  totalHours: number;
  hoursCollective: number;
  hoursPrivate: number;
  hoursActivities: number;
  hourlyRate: number;
  totalCost: number;
  revenueGenerated: number;
  profitMargin: number;
  efficiencyScore: number;
  satisfactionRate: number;
  bookingsPerHour: number;
  averageRevenuePerHour: number;
}

export interface MonitorAnalyticsSummary {
  totalMonitors: number;
  totalHoursWorked: number;
  totalCost: number;
  totalRevenueGenerated: number;
  averageEfficiency: number;
  mostEfficientMonitor: MonitorAnalyticsData;
  mostProductiveMonitor: MonitorAnalyticsData;
}

export interface ConversionAnalysis {
  bySource: SourceConversionData[];
  overallMetrics: OverallConversionMetrics;
}

export interface SourceConversionData {
  source: string;
  totalBookings: number;
  completedBookings: number;
  cancelledBookings: number;
  conversionRate: number;
  cancellationRate: number;
  averageTimeToCompletion: number;
}

export interface OverallConversionMetrics {
  totalBookings: number;
  overallConversionRate: number;
  bestPerformingSource: SourceConversionData;
  worstPerformingSource: SourceConversionData;
}

export interface RealtimeMetrics {
  today: RealtimePeriodData;
  thisWeek: RealtimePeriodData;
  thisMonth: RealtimePeriodData;
  lastUpdated: string;
}

export interface RealtimePeriodData {
  newBookings: number;
  revenue: number;
  cancellations: number;
}

export interface AnalyticsPreferences {
  defaultPeriod: 'daily' | 'weekly' | 'monthly';
  defaultDateRange: number; // days
  enableNotifications: boolean;
  enableAutoRefresh: boolean;
  autoRefreshInterval: number; // seconds
  preferredChartTypes: string[];
  enableAdvancedFeatures: boolean;
  customKpis: any[];
}

export interface AnalyticsAlert {
  id: number;
  title: string;
  description: string;
  type: 'revenue' | 'bookings' | 'efficiency' | 'custom';
  condition: string;
  threshold: number;
  isActive: boolean;
  lastTriggered?: string;
}

// ==================== SERVICE ====================

@Injectable({
  providedIn: 'root'
})
export class AnalyticsProfessionalService {

  private readonly baseUrl = '/api/admin';
  private readonly cacheTimeout = 5 * 60 * 1000; // 5 minutos

  // BehaviorSubjects para estado reactivo
  private loadingSubject = new BehaviorSubject<boolean>(false);
  private filtersSubject = new BehaviorSubject<AnalyticsFilters | null>(null);
  private dashboardSubject = new BehaviorSubject<SeasonDashboard | null>(null);
  private realtimeSubject = new BehaviorSubject<RealtimeMetrics | null>(null);
  private preferencesSubject = new BehaviorSubject<AnalyticsPreferences | null>(null);

  // Observables públicos
  public loading$ = this.loadingSubject.asObservable();
  public filters$ = this.filtersSubject.asObservable();
  public dashboard$ = this.dashboardSubject.asObservable();
  public realtime$ = this.realtimeSubject.asObservable();
  public preferences$ = this.preferencesSubject.asObservable();

  // Cache para respuestas
  private cache = new Map<string, { data: any; timestamp: number }>();

  constructor(private http: HttpClient) {
    this.loadPreferences();
    this.startRealtimeUpdates();
  }

  // ==================== MÉTODOS PRINCIPALES ====================

  /**
   * Obtener dashboard de temporada
   */
  getSeasonDashboard(filters: AnalyticsFilters): Observable<SeasonDashboard> {
    this.setLoading(true);
    this.filtersSubject.next(filters);

    const cacheKey = `season-dashboard-${JSON.stringify(filters)}`;
    const cached = this.getFromCache(cacheKey);

    if (cached) {
      this.setLoading(false);
      this.dashboardSubject.next(cached);
      return of(cached);
    }

    return this.http.get<any>(`${this.baseUrl}/finance/season-dashboard`, {
      params: this.buildHttpParams(filters)
    }).pipe(
      map(response => response.data),
      tap(data => {
        this.setToCache(cacheKey, data);
        this.dashboardSubject.next(data);
        this.setLoading(false);
      }),
      catchError(this.handleError.bind(this)),
      shareReplay(1)
    );
  }

  /**
   * Obtener análisis de ingresos por período
   */
  getRevenueByPeriod(filters: AnalyticsFilters & { period: 'daily' | 'weekly' | 'monthly' }): Observable<RevenueByPeriod> {
    this.setLoading(true);

    const cacheKey = `revenue-by-period-${JSON.stringify(filters)}`;
    const cached = this.getFromCache(cacheKey);

    if (cached) {
      this.setLoading(false);
      return of(cached);
    }

    return this.http.get<any>(`${this.baseUrl}/analytics/revenue-by-period`, {
      params: this.buildHttpParams(filters)
    }).pipe(
      map(response => response.data),
      tap(data => {
        this.setToCache(cacheKey, data);
        this.setLoading(false);
      }),
      catchError(this.handleError.bind(this)),
      shareReplay(1)
    );
  }

  /**
   * Obtener análisis detallado de cursos
   */
  getDetailedCourseAnalytics(filters: AnalyticsFilters): Observable<CourseAnalytics> {
    this.setLoading(true);

    return this.http.get<any>(`${this.baseUrl}/analytics/courses-detailed`, {
      params: this.buildHttpParams(filters)
    }).pipe(
      map(response => response.data),
      tap(() => this.setLoading(false)),
      catchError(this.handleError.bind(this)),
      shareReplay(1)
    );
  }

  /**
   * Obtener análisis de eficiencia de monitores
   */
  getMonitorEfficiencyAnalytics(filters: AnalyticsFilters): Observable<MonitorAnalytics> {
    this.setLoading(true);

    return this.http.get<any>(`${this.baseUrl}/analytics/monitors-efficiency`, {
      params: this.buildHttpParams(filters)
    }).pipe(
      map(response => response.data),
      tap(() => this.setLoading(false)),
      catchError(this.handleError.bind(this)),
      shareReplay(1)
    );
  }

  /**
   * Obtener análisis de conversión
   */
  getConversionAnalysis(filters: AnalyticsFilters): Observable<ConversionAnalysis> {
    this.setLoading(true);

    return this.http.get<any>(`${this.baseUrl}/analytics/conversion-analysis`, {
      params: this.buildHttpParams(filters)
    }).pipe(
      map(response => response.data),
      tap(() => this.setLoading(false)),
      catchError(this.handleError.bind(this)),
      shareReplay(1)
    );
  }

  /**
   * Obtener tendencias y predicciones
   */
  getTrendsAndPredictions(filters: AnalyticsFilters & {
    analysisMonths?: number;
    predictionMonths?: number;
  }): Observable<any> {
    this.setLoading(true);

    return this.http.get<any>(`${this.baseUrl}/analytics/trends-prediction`, {
      params: this.buildHttpParams(filters)
    }).pipe(
      map(response => response.data),
      tap(() => this.setLoading(false)),
      catchError(this.handleError.bind(this)),
      shareReplay(1)
    );
  }

  /**
   * Obtener métricas en tiempo real
   */
  getRealtimeMetrics(schoolId: number): Observable<RealtimeMetrics> {
    return this.http.get<any>(`${this.baseUrl}/analytics/realtime-metrics`, {
      params: { school_id: schoolId.toString() }
    }).pipe(
      map(response => response.data),
      tap(data => this.realtimeSubject.next(data)),
      catchError(this.handleError.bind(this))
    );
  }

  // ==================== MÉTODOS ESPECÍFICOS DE MONITORES ====================

  /**
   * Obtener análisis diario de un monitor específico
   */
  getMonitorDailyAnalytics(monitorId: number, filters: Partial<AnalyticsFilters>): Observable<any> {
    return this.http.get<any>(`${this.baseUrl}/analytics/monitors/${monitorId}/daily`, {
      params: this.buildHttpParams(filters)
    }).pipe(
      map(response => response.data),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Obtener rendimiento de un monitor específico
   */
  getMonitorPerformance(monitorId: number, filters: Partial<AnalyticsFilters>): Observable<any> {
    return this.http.get<any>(`${this.baseUrl}/analytics/monitors/${monitorId}/performance`, {
      params: this.buildHttpParams(filters)
    }).pipe(
      map(response => response.data),
      catchError(this.handleError.bind(this))
    );
  }

  // ==================== MÉTODOS DE EXPORTACIÓN ====================

  /**
   * Exportar dashboard completo
   */
  exportDashboard(filters: AnalyticsFilters, format: 'csv' | 'pdf' | 'excel'): Observable<any> {
    this.setLoading(true);

    const params = this.buildHttpParams({ ...filters, format });

    return this.http.get<any>(`${this.baseUrl}/finance/export-dashboard`, { params }).pipe(
      tap(() => this.setLoading(false)),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Exportar reporte ejecutivo
   */
  exportExecutiveSummary(filters: AnalyticsFilters, format: 'pdf' | 'excel'): Observable<any> {
    this.setLoading(true);

    return this.http.post<any>(`${this.baseUrl}/analytics/export/executive-summary`, {
      ...filters,
      format
    }).pipe(
      tap(() => this.setLoading(false)),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Exportar análisis detallado
   */
  exportDetailedAnalysis(filters: AnalyticsFilters, sections: string[], format: 'excel' | 'pdf'): Observable<any> {
    this.setLoading(true);

    return this.http.post<any>(`${this.baseUrl}/analytics/export/detailed-analysis`, {
      ...filters,
      sections,
      format
    }).pipe(
      tap(() => this.setLoading(false)),
      catchError(this.handleError.bind(this))
    );
  }

  // ==================== MÉTODOS DE PREFERENCIAS ====================

  /**
   * Obtener preferencias de analytics
   */
  getPreferences(): Observable<AnalyticsPreferences> {
    return this.http.get<any>(`${this.baseUrl}/analytics/preferences`).pipe(
      map(response => response.data),
      tap(preferences => this.preferencesSubject.next(preferences)),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Guardar preferencias de analytics
   */
  savePreferences(preferences: AnalyticsPreferences): Observable<any> {
    return this.http.post<any>(`${this.baseUrl}/analytics/preferences`, preferences).pipe(
      tap(() => this.preferencesSubject.next(preferences)),
      catchError(this.handleError.bind(this))
    );
  }

  // ==================== MÉTODOS DE ALERTAS ====================

  /**
   * Obtener alertas de analytics
   */
  getAlerts(): Observable<AnalyticsAlert[]> {
    return this.http.get<any>(`${this.baseUrl}/analytics/alerts`).pipe(
      map(response => response.data),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Crear nueva alerta
   */
  createAlert(alert: Omit<AnalyticsAlert, 'id'>): Observable<AnalyticsAlert> {
    return this.http.post<any>(`${this.baseUrl}/analytics/alerts`, alert).pipe(
      map(response => response.data),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Eliminar alerta
   */
  deleteAlert(alertId: number): Observable<any> {
    return this.http.delete<any>(`${this.baseUrl}/analytics/alerts/${alertId}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ==================== MÉTODOS DE COMPARACIÓN ====================

  /**
   * Comparar dos períodos
   */
  comparePeriods(period1: AnalyticsFilters, period2: AnalyticsFilters): Observable<any> {
    return this.http.post<any>(`${this.baseUrl}/analytics/compare-periods`, {
      period1,
      period2
    }).pipe(
      map(response => response.data),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Obtener datos de benchmark
   */
  getBenchmarkData(schoolId: number): Observable<any> {
    return this.http.get<any>(`${this.baseUrl}/analytics/benchmarks`, {
      params: { school_id: schoolId.toString() }
    }).pipe(
      map(response => response.data),
      catchError(this.handleError.bind(this))
    );
  }

  // ==================== MÉTODOS DE UTILIDAD ====================

  /**
   * Limpiar cache
   */
  clearCache(): void {
    this.cache.clear();

    // También llamar al endpoint de limpieza del servidor
    this.http.delete(`${this.baseUrl}/analytics/cache/clear`).pipe(
      catchError(this.handleError.bind(this))
    ).subscribe();
  }

  /**
   * Obtener estado del cache
   */
  getCacheStatus(): Observable<any> {
    return this.http.get<any>(`${this.baseUrl}/analytics/cache/status`).pipe(
      map(response => response.data),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Descargar archivo
   */
  downloadFile(filename: string): void {
    window.open(`${this.baseUrl}/finance/download-export/${filename}`, '_blank');
  }

  /**
   * Refrescar datos
   */
  refreshData(): void {
    this.clearCache();
    const currentFilters = this.filtersSubject.value;

    if (currentFilters) {
      this.getSeasonDashboard(currentFilters).subscribe();
    }
  }

  // ==================== MÉTODOS PRIVADOS ====================

  private setLoading(loading: boolean): void {
    this.loadingSubject.next(loading);
  }

  private buildHttpParams(obj: any): HttpParams {
    let params = new HttpParams();

    Object.keys(obj).forEach(key => {
      if (obj[key] !== null && obj[key] !== undefined && obj[key] !== '') {
        params = params.set(key, obj[key].toString());
      }
    });

    return params;
  }

  private handleError(error: HttpErrorResponse): Observable<never> {
    this.setLoading(false);

    let errorMessage = 'Error desconocido';

    if (error.error instanceof ErrorEvent) {
      // Error del lado del cliente
      errorMessage = `Error: ${error.error.message}`;
    } else {
      // Error del lado del servidor
      errorMessage = `Código de error: ${error.status}, mensaje: ${error.message}`;

      if (error.error && error.error.message) {
        errorMessage = error.error.message;
      }
    }

    console.error('Error en AnalyticsService:', errorMessage);
    return throwError(() => new Error(errorMessage));
  }

  private getFromCache(key: string): any {
    const cached = this.cache.get(key);

    if (cached && (Date.now() - cached.timestamp) < this.cacheTimeout) {
      return cached.data;
    }

    return null;
  }

  private setToCache(key: string, data: any): void {
    this.cache.set(key, {
      data,
      timestamp: Date.now()
    });
  }

  private loadPreferences(): void {
    this.getPreferences().subscribe({
      next: (preferences) => {
        // Preferencias cargadas exitosamente
      },
      error: (error) => {
        // Usar preferencias por defecto
        const defaultPreferences: AnalyticsPreferences = {
          defaultPeriod: 'monthly',
          defaultDateRange: 90,
          enableNotifications: true,
          enableAutoRefresh: false,
          autoRefreshInterval: 300,
          preferredChartTypes: ['bar', 'line', 'pie'],
          enableAdvancedFeatures: true,
          customKpis: []
        };

        this.preferencesSubject.next(defaultPreferences);
      }
    });
  }

  private startRealtimeUpdates(): void {
    // Actualizar métricas en tiempo real cada 30 segundos si está habilitado
    this.preferences$.subscribe(preferences => {
      if (preferences?.enableAutoRefresh) {
        timer(0, preferences.autoRefreshInterval * 1000).pipe(
          switchMap(() => {
            const filters = this.filtersSubject.value;
            if (filters?.school_id) {
              return this.getRealtimeMetrics(filters.school_id);
            }
            return of(null);
          })
        ).subscribe();
      }
    });
  }

  // ==================== MÉTODOS DE FORMATEO ====================

  /**
   * Formatear moneda
   */
  formatCurrency(amount: number, currency: string = 'CHF'): string {
    return new Intl.NumberFormat('es-ES', {
      style: 'currency',
      currency: currency
    }).format(amount);
  }

  /**
   * Formatear porcentaje
   */
  formatPercentage(value: number): string {
    return `${value.toFixed(1)}%`;
  }

  /**
   * Formatear fecha para filtros
   */
  formatDateForFilter(date: Date | moment.Moment): string {
    return moment(date).format('YYYY-MM-DD');
  }

  /**
   * Obtener rango de fechas por defecto
   */
  getDefaultDateRange(): { start: string; end: string } {
    const preferences = this.preferencesSubject.value;
    const days = preferences?.defaultDateRange || 90;

    return {
      start: moment().subtract(days, 'days').format('YYYY-MM-DD'),
      end: moment().format('YYYY-MM-DD')
    };
  }
}
