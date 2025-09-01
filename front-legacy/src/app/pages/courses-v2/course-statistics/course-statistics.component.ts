// =============== COURSE STATISTICS COMPONENT ===============
// Archivo: src/app/components/shared/course-statistics/course-statistics.component.ts

import { Component, Input, OnInit, OnDestroy, ChangeDetectorRef, ViewChild, ElementRef } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { Subject, takeUntil } from 'rxjs';
import Plotly from 'plotly.js-dist-min';
import moment from 'moment';
import {ApiCrudService} from '../../../../service/crud.service';

// ==================== INTERFACES ====================

interface CourseStatistics {
  course_info: {
    id: number;
    name: string;
    type: number;
    sport: string;
    is_flexible: boolean;
  };
  financial_stats: {
    total_revenue: number;
    total_bookings: number;
    total_participants: number;
    average_price_per_participant: number;
    revenue_trend: Array<{
      month: string;
      revenue: number;
      bookings: number;
    }>;
    payment_methods: {
      [key: string]: {
        count: number;
        amount: number;
        percentage: number;
      };
    };
  };
  participant_stats: {
    total_participants: number;
    active_participants: number;
    cancelled_participants: number;
    completion_rate: number;
    bookings_by_date: Array<{
      date: string;
      participants: number;
      revenue: number;
    }>;
    booking_sources: {
      [key: string]: {
        count: number;
        percentage: number;
      };
    };
  };
  performance_stats: {
    occupancy_rate: number;
    average_class_size: number;
    total_sessions: number;
    completion_rate: number;
    popularity_rank: number;
    comparison_with_similar: {
      revenue_vs_average: number;
      participants_vs_average: number;
      price_vs_average: number;
    };
  };
}

@Component({
  selector: 'app-course-statistics',
  templateUrl: './course-statistics.component.html',
  styleUrls: ['./course-statistics.component.scss']
})
export class CourseStatisticsComponent implements OnInit, OnDestroy {

  // ==================== INPUTS ====================
  @Input() courseId!: number;
  @Input() dateRange?: { start: string; end: string };
  @Input() showTitle: boolean = true;
  @Input() compact: boolean = false; // Para modo modal vs página completa

  // ==================== VIEW CHILDREN ====================
  @ViewChild('revenueTrendChart', { static: false }) revenueTrendChartRef!: ElementRef;
  @ViewChild('paymentMethodsChart', { static: false }) paymentMethodsChartRef!: ElementRef;
  @ViewChild('participantsTrendChart', { static: false }) participantsTrendChartRef!: ElementRef;
  @ViewChild('sourcesChart', { static: false }) sourcesChartRef!: ElementRef;

  // ==================== COMPONENT STATE ====================
  loading = false;
  error: string | null = null;
  courseStats: CourseStatistics | null = null;
  private destroy$ = new Subject<void>();

  // ==================== CHART COLORS ====================
  private readonly courseTypeColors = {
    1: '#FAC710', // Colectivo
    2: '#8FD14F', // Privado
    3: '#00beff'  // Actividad
  };

  private readonly chartColors = {
    primary: '#3A57A7',
    secondary: '#FCB859',
    success: '#4CAF50',
    warning: '#FF9800',
    danger: '#F44336',
    info: '#2196F3',
    gradient: ['#3A57A7', '#FCB859', '#4CAF50', '#FF9800', '#F44336', '#2196F3']
  };

  constructor(
    private apiService: ApiCrudService,
    private translateService: TranslateService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    if (this.courseId) {
      this.loadCourseStatistics();
    }
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ==================== DATA LOADING ====================

  private async loadCourseStatistics(): Promise<void> {
    if (!this.courseId) {
      this.error = 'ID de curso no proporcionado';
      return;
    }

    this.loading = true;
    this.error = null;

    try {
      // Construir parámetros de consulta
      const params = this.buildQueryParams();

      // Cargar estadísticas del curso
      const response = await this.apiService.get(
        `/admin/finance/courses/${this.courseId}/statistics`,
        [],
        params
      ).toPromise();

      if (response.success && response.data) {
        this.courseStats = response.data;
        this.cdr.detectChanges();

        // Crear gráficos después de que los datos estén listos
        setTimeout(() => this.createAllCharts(), 100);
      } else {
        this.error = 'No se pudieron cargar las estadísticas del curso';
      }

    } catch (error) {
      console.error('Error loading course statistics:', error);
      this.error = 'Error cargando estadísticas: ' + (error as any)?.message;
    } finally {
      this.loading = false;
      this.cdr.detectChanges();
    }
  }

  private buildQueryParams(): any {
    const params: any = {};

    if (this.dateRange) {
      params.start_date = this.dateRange.start;
      params.end_date = this.dateRange.end;
    }

    return params;
  }

  // ==================== CHART CREATION ====================

  private createAllCharts(): void {
    if (!this.courseStats) return;

    try {
      this.createRevenueTrendChart();
      this.createPaymentMethodsChart();
      this.createParticipantsTrendChart();
      this.createSourcesChart();
    } catch (error) {
      console.error('Error creating charts:', error);
    }
  }

  private createRevenueTrendChart(): void {
    if (!this.revenueTrendChartRef?.nativeElement || !this.courseStats) return;

    const trendData = this.courseStats.financial_stats.revenue_trend || [];

    const revenueTrace = {
      x: trendData.map(d => this.formatDateWithMonthName(d.month)),
      y: trendData.map(d => d.revenue),
      type: 'scatter',
      mode: 'lines+markers',
      name: 'Ingresos',
      line: { color: this.getCourseTypeColor(), width: 3 },
      marker: { color: this.getCourseTypeColor(), size: 6 }
    };

    const bookingsTrace = {
      x: trendData.map(d => this.formatDateWithMonthName(d.month)),
      y: trendData.map(d => d.bookings),
      type: 'scatter',
      mode: 'lines+markers',
      name: 'Reservas',
      yaxis: 'y2',
      line: { color: this.chartColors.secondary, width: 2 },
      marker: { color: this.chartColors.secondary, size: 4 }
    };

    const layout = {
      title: false,
      xaxis: { title: 'Período', tickangle: -45 },
      yaxis: { title: 'Ingresos (CHF)', side: 'left' },
      yaxis2: { title: 'Número de Reservas', side: 'right', overlaying: 'y' },
      margin: { l: 60, r: 60, t: 20, b: 80 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)',
      showlegend: true,
      height: this.compact ? 250 : 300
    };

    Plotly.newPlot(this.revenueTrendChartRef.nativeElement, [revenueTrace, bookingsTrace], layout, { responsive: true });
  }

  private createPaymentMethodsChart(): void {
    if (!this.paymentMethodsChartRef?.nativeElement || !this.courseStats) return;

    const paymentMethods = this.courseStats.financial_stats.payment_methods || {};
    const methods = Object.keys(paymentMethods);

    if (methods.length === 0) return;

    const trace = {
      values: methods.map(method => paymentMethods[method].amount),
      labels: methods.map(method => this.translatePaymentMethod(method)),
      type: 'pie',
      marker: { colors: this.chartColors.gradient },
      textinfo: 'label+percent',
      textposition: 'outside'
    };

    const layout = {
      title: false,
      margin: { l: 20, r: 20, t: 20, b: 20 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)',
      height: this.compact ? 250 : 300
    };

    Plotly.newPlot(this.paymentMethodsChartRef.nativeElement, [trace], layout, { responsive: true });
  }

  private createParticipantsTrendChart(): void {
    if (!this.participantsTrendChartRef?.nativeElement || !this.courseStats) return;

    const bookingsData = this.courseStats.participant_stats.bookings_by_date || [];

    const trace = {
      x: bookingsData.map(d => moment(d.date).format('DD/MM')),
      y: bookingsData.map(d => d.participants),
      type: 'bar',
      marker: { color: this.getCourseTypeColor() },
      name: 'Participantes por Día'
    };

    const layout = {
      title: false,
      xaxis: { title: 'Fecha' },
      yaxis: { title: 'Participantes' },
      margin: { l: 60, r: 20, t: 20, b: 60 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)',
      height: this.compact ? 250 : 300
    };

    Plotly.newPlot(this.participantsTrendChartRef.nativeElement, [trace], layout, { responsive: true });
  }

  private createSourcesChart(): void {
    if (!this.sourcesChartRef?.nativeElement || !this.courseStats) return;

    const sources = this.courseStats.participant_stats.booking_sources || {};
    const sourceNames = Object.keys(sources);

    if (sourceNames.length === 0) return;

    const trace = {
      values: sourceNames.map(source => sources[source].count),
      labels: sourceNames.map(source => this.translateSource(source)),
      type: 'pie',
      marker: { colors: this.chartColors.gradient },
      textinfo: 'label+percent'
    };

    const layout = {
      title: false,
      margin: { l: 20, r: 20, t: 20, b: 20 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)',
      height: this.compact ? 250 : 300
    };

    Plotly.newPlot(this.sourcesChartRef.nativeElement, [trace], layout, { responsive: true });
  }

  // ==================== UTILITY METHODS ====================

  private getCourseTypeColor(): string {
    const courseType = this.courseStats?.course_info?.type || 1;
    return this.courseTypeColors[courseType as keyof typeof this.courseTypeColors] || this.chartColors.primary;
  }

  private formatDateWithMonthName(dateString: string): string {
    if (!dateString) return '';

    try {
      const date = moment(dateString);
      const monthKey = date.format('MMMM').toLowerCase();
      const year = date.format('YYYY');
      const translatedMonth = this.translateService.instant(`months.${monthKey}`);

      return `${translatedMonth} ${year}`;
    } catch (error) {
      return dateString;
    }
  }

  private translatePaymentMethod(method: string): string {
    const translations: { [key: string]: string } = {
      'cash': 'Efectivo',
      'card': 'Tarjeta',
      'online': 'Online',
      'voucher': 'Voucher',
      'bank_transfer': 'Transferencia'
    };
    return translations[method] || method;
  }

  private translateSource(source: string): string {
    const translations: { [key: string]: string } = {
      'web': 'Web',
      'app': 'Aplicación',
      'phone': 'Teléfono',
      'walk_in': 'Presencial',
      'partner': 'Partner'
    };
    return translations[source] || source;
  }

  // ==================== GETTERS FOR TEMPLATE ====================

  get courseTypeColor(): string {
    return this.getCourseTypeColor();
  }

  get courseTypeName(): string {
    const type = this.courseStats?.course_info?.type;
    switch (type) {
      case 1: return 'Colectivo';
      case 2: return 'Privado';
      case 3: return 'Actividad';
      default: return 'Desconocido';
    }
  }

  get performanceIndicator(): 'excellent' | 'good' | 'average' | 'poor' {
    const occupancy = this.courseStats?.performance_stats?.occupancy_rate || 0;
    if (occupancy >= 90) return 'excellent';
    if (occupancy >= 70) return 'good';
    if (occupancy >= 50) return 'average';
    return 'poor';
  }

  // ==================== PUBLIC METHODS ====================

  public refreshData(): void {
    this.loadCourseStatistics();
  }

  public exportStatistics(): void {
    // TODO: Implementar exportación de estadísticas
    console.log('Exporting course statistics...');
  }
}
