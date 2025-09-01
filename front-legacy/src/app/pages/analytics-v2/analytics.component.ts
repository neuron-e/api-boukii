import {AfterViewInit, ChangeDetectorRef, Component, ElementRef, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {FormBuilder, FormControl, FormGroup} from '@angular/forms';
import {Router} from '@angular/router';
import {TranslateService} from '@ngx-translate/core';
import {debounceTime, distinctUntilChanged, Subject, takeUntil} from 'rxjs';
import {MatTabChangeEvent} from '@angular/material/tabs';
import {MatTableDataSource} from '@angular/material/table';
import {animate, state, style, transition, trigger} from '@angular/animations';
import moment from 'moment';
import Plotly from 'plotly.js-dist-min';

import {ApiCrudService} from '../../../service/crud.service';
import {BookingListModalComponent} from './booking-list-modal/booking-list-modal.component';
import {MatDialog} from '@angular/material/dialog';
import {MatSnackBar} from '@angular/material/snack-bar';
import {CourseStatisticsModalComponent} from './course-statistics-modal/course-statistics-modal.component';

// ==================== INTERFACES ====================

interface SeasonDashboardData {
  season_info: {
    season_name: string;
    date_range: {
      start: string;
      end: string;
      total_days: number;
    };
    total_bookings: number;
    booking_classification: {
      total_bookings: number;
      production_count: number;
      test_count: number;
      cancelled_count: number;
      production_revenue: number;
      test_revenue: number;
      cancelled_revenue: number;
    };
  };
  executive_kpis: {
    total_production_bookings: number;
    total_clients: number;
    total_participants: number;
    revenue_expected: number;
    revenue_received: number;
    revenue_pending: number;
    collection_efficiency: number;
    consistency_rate: number;
    average_booking_value: number;
  };
  booking_sources: {
    total_bookings: number;
    source_breakdown: Array<{
      source: string;
      bookings: number;
      percentage: number;
      unique_clients: number;
      revenue: number;
      avg_booking_value: number;
      consistency_rate: number;
    }>;
  };
  payment_methods: {
    total_payments: number;
    total_revenue: number;
    methods: Array<{
      method: string;
      display_name: string;
      count: number;
      percentage: number;
      revenue: number;
      revenue_percentage: number;
      avg_payment_amount: number;
    }>;
    online_vs_offline: {
      online: {
        revenue: number;
        count: number;
        revenue_percentage: number;
        count_percentage: number;
      };
      offline: {
        revenue: number;
        count: number;
        revenue_percentage: number;
        count_percentage: number;
      };
    };
  };
  booking_status_analysis: {
    [key: string]: {
      count: number;
      percentage: number;
      expected_revenue: number;
      received_revenue: number;
      pending_revenue: number;
      collection_efficiency: number;
      issues: number;
    };
  };
  financial_summary: {
    revenue_breakdown: {
      total_expected: number;
      total_received: number;
      total_pending: number;
      total_refunded: number;
    };
    consistency_metrics: {
      consistent_bookings: number;
      inconsistent_bookings: number;
      consistency_rate: number;
      major_discrepancies: number;
    };
    voucher_usage: {
      total_vouchers_used: number;
      total_voucher_amount: number;
      unique_vouchers: number;
    };
  };
  courses: Array<{
    id: number;
    name: string;
    type: number;
    sport: string;
    revenue: number;
    participants: number;
    bookings: number;
    average_price: number;
    revenue_received: number;
    sales_conversion_rate: number;
    courses_sold: number;
    payment_methods: any;
    status_breakdown: any;
    source_breakdown: any;
  }>;
  critical_issues: {
    [key: string]: {
      count: number;
      items: Array<any>;
    };
  };
  executive_alerts: Array<{
    level: string;
    type: string;
    title: string;
    description: string;
    impact: string;
    action_required: boolean;
  }>;
  priority_recommendations: Array<{
    priority: string;
    category: string;
    title: string;
    description: string;
    impact: string;
    effort: string;
    timeline: string;
    actions: string[];
    expected_benefit: string;
  }>;
  trend_analysis: {
    monthly_breakdown: Array<{
      month: string;
      bookings: number;
      revenue: number;
      unique_clients: number;
      consistency_rate: number;
      avg_booking_value: number;
    }>;
    booking_velocity: {
      recent_production_bookings: number;
      bookings_per_week: number;
      trend_direction: string;
      quality_trend: string;
    };
  };
  performance_metrics: {
    execution_time_ms: number;
    total_bookings_analyzed: number;
    production_bookings_count: number;
    test_bookings_excluded: number;
    cancelled_bookings_count: number;
  };
}

interface AnalyticsFilters {
  startDate: string | null;
  endDate: string | null;
  presetRange: string | null;
  courseType: number[] | null;
  source: string[] | null;
  paymentMethod: string[] | null;
  sportId: number[] | null;
  onlyWeekends: boolean;
  onlyPaidBookings: boolean;
  includeRefunds: boolean;
  includeTestDetection: boolean;
  includePayrexxAnalysis: boolean;
  optimizationLevel: string;
}

// ==================== COMPONENT ====================

@Component({
  selector: 'vex-analytics',
  templateUrl: './analytics.component.html',
  styleUrls: ['./analytics.component.scss'],
  animations: [
    trigger('slideDown', [
      state('in', style({height: '*'})),
      transition(':enter', [
        style({height: 0, opacity: 0}),
        animate('300ms ease-in-out', style({height: '*', opacity: 1}))
      ]),
      transition(':leave', [
        animate('300ms ease-in-out', style({height: 0, opacity: 0}))
      ])
    ])
  ]
})
export class AnalyticsComponent implements OnInit, AfterViewInit, OnDestroy {

  // ==================== VIEW CHILDREN ====================
  @ViewChild('revenueChart', { static: false }) revenueChartRef!: ElementRef;
  @ViewChild('courseTypeRevenueChart', { static: false }) courseTypeRevenueChartRef!: ElementRef;
  @ViewChild('paymentMethodsChart', { static: false }) paymentMethodsChartRef!: ElementRef;
  @ViewChild('paymentTrendsChart', { static: false }) paymentTrendsChartRef!: ElementRef;
  @ViewChild('topCoursesChart', { static: false }) topCoursesChartRef!: ElementRef;
  @ViewChild('completionRatesChart', { static: false }) completionRatesChartRef!: ElementRef;
  @ViewChild('sourcesChart', { static: false }) sourcesChartRef!: ElementRef;
  @ViewChild('sourcePerformanceChart', { static: false }) sourcePerformanceChartRef!: ElementRef;
  @ViewChild('comparisonChart', { static: false }) comparisonChartRef!: ElementRef;

  // ==================== FORM CONTROLS ====================
  filterForm: FormGroup;

  // ==================== DATA PROPERTIES ====================
  dashboardData: SeasonDashboardData | null = null;
  revenueTableData = new MatTableDataSource<any>([]);
  coursesTableData = new MatTableDataSource<any>([]);
  public courseTypeBookingsSummary: any[] = [];
  // ==================== UI STATE ====================
  loading = false;
  activeTab = 'revenue'; // ← AÑADIR ESTA LÍNEA
  activeTabIndex = 0;
  showAdvancedFilters = false;

  // ==================== USER DATA ====================
  user: any;
  currency = 'CHF';
  allSports: any[] = [];

  // ==================== TABLE COLUMNS ====================
  revenueDisplayedColumns: string[] = ['month', 'revenue', 'bookings', 'averageValue', 'clients', 'consistencyRate'];
  coursesDisplayedColumns: string[] = ['courseName', 'courseType', 'totalRevenue', 'totalBookings', 'averagePrice', 'participants', 'actions'];

  // ==================== CHART COLORS ====================
  chartColors = {
    primary: '#3A57A7',
    secondary: '#FCB859',
    success: '#4CAF50',
    warning: '#FF9800',
    danger: '#F44336',
    info: '#2196F3',
    gradient: ['#3A57A7', '#FCB859', '#4CAF50', '#FF9800', '#F44336', '#2196F3']
  };

  // ==================== COURSE TYPE COLORS CONFIGURATION ====================

  private readonly courseTypeColors = {
    1: '#FAC710', // Colectivo - Amarillo/Dorado
    2: '#8FD14F', // Privado - Verde
    3: '#00beff', // Actividad - Azul
    collective: '#FAC710',
    private: '#8FD14F',
    activity: '#00beff'
  };

  // ==================== DESTROY SUBJECT ====================
  private destroy$ = new Subject<void>();

  // ==================== TABS CONFIGURATION (ACTUALIZADO) ====================
  tabs = [
    { id: 'revenue', label: 'Análisis de Ingresos', icon: 'monetization_on' },
    { id: 'payments', label: 'Métodos de Pago', icon: 'payment' },
    { id: 'courses', label: 'Análisis de Cursos', icon: 'school' },
    { id: 'sources', label: 'Fuentes de Reserva', icon: 'source' },
    { id: 'monitors', label: 'Monitores', icon: 'person' }, // ← NUEVA
    { id: 'comparative', label: 'Análisis Comparativo', icon: 'compare_arrows' },
    { id: 'alerts', label: 'Alertas y Recomendaciones', icon: 'warning' }
  ];

  // ==================== MODAL PROPERTIES ====================
  showPendingModal = false;
  showCancelledModal = false;
  pendingBookings: any[] = [];
  cancelledBookings: any[] = [];
  loadingPendingBookings = false;
  loadingCancelledBookings = false;

  // ==================== CONSTRUCTOR ====================
  constructor(
    private fb: FormBuilder,
    private cdr: ChangeDetectorRef,
    private router: Router,
    private translateService: TranslateService,
    private apiService: ApiCrudService,
    private dialog: MatDialog,
    private snackBar: MatSnackBar
  ) {
    this.initializeForm();
    this.loadUserData();
    this.loadMasterData();

    this.activeTab = this.tabs[0].id; // Inicializar con primera pestaña
  }

  // ==================== LIFECYCLE METHODS ====================

  ngOnInit(): void {
    this.setupFilterSubscriptions();
    this.loadAnalyticsData();
  }

  ngAfterViewInit(): void {
    // Los gráficos se crearán cuando los datos estén disponibles
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ==================== INITIALIZATION ====================

  private initializeForm(): void {
    const today = moment();
    const currentMonth = today.month(); // 0 = enero, 10 = noviembre

    let startDate: string;
    let endDate: string;

    if (currentMonth >= 5 && currentMonth <= 9) {
      // Junio a Octubre → temporada pasada fue Nov (año anterior) - Mayo (este año)
      startDate = moment().subtract(1, 'year').month(10).startOf('month').format('YYYY-MM-DD');
      endDate = moment().month(4).endOf('month').format('YYYY-MM-DD');
    } else {
      // Noviembre a Mayo → temporada pasada fue Nov (2 años atrás) - Mayo (año pasado)
      startDate = moment().subtract(2, 'year').month(10).startOf('month').format('YYYY-MM-DD');
      endDate = moment().subtract(1, 'year').month(4).endOf('month').format('YYYY-MM-DD');
    }

    this.filterForm = this.fb.group({
      startDate: new FormControl(startDate),
      endDate: new FormControl(endDate),
      presetRange: new FormControl(''),
      courseType: new FormControl([]),
      source: new FormControl([]),
      paymentMethod: new FormControl([]),
      sportId: new FormControl([]),
      onlyWeekends: new FormControl(false),
      onlyPaidBookings: new FormControl(false),
      includeRefunds: new FormControl(true),
      includeTestDetection: new FormControl(true),
      includePayrexxAnalysis: new FormControl(false),
      optimizationLevel: new FormControl('balanced')
    });
  }

  private loadUserData(): void {
    const userStr = localStorage.getItem('boukiiUser');
    if (userStr) {
      this.user = JSON.parse(userStr);
      this.currency = this.user?.school?.currency || 'CHF';
    }
  }

  private loadMasterData(): void {
    // Cargar deportes
    this.apiService.get('/sports').subscribe({
      next: (response) => {
        this.allSports = response.data || [];
      },
      error: (error) => console.error('Error loading sports:', error)
    });
  }

  private setupFilterSubscriptions(): void {
    // Escuchar cambios en los filtros con debounce
    this.filterForm.valueChanges.pipe(
      debounceTime(1000),
      distinctUntilChanged(),
      takeUntil(this.destroy$)
    ).subscribe(() => {
      if (!this.loading) {
        this.loadAnalyticsData();
      }
    });
  }

  // ==================== COLOR HELPER METHODS ====================

  /**
   * 🎨 Obtener color por tipo de curso (número)
   */
  public getCourseTypeColor(courseType: number): string {
    return this.courseTypeColors[courseType] || this.chartColors.primary;
  }

  /**
   * 🎨 Obtener color por nombre de tipo de curso
   */
  private getCourseTypeColorByName(typeName: string): string {
    const typeMap: { [key: string]: number } = {
      'collective': 1,
      'course_colective': 1,
      'colectivo': 1,
      'private': 2,
      'course_private': 2,
      'privado': 2,
      'activity': 3,
      'actividad': 3
    };

    const courseType = typeMap[typeName.toLowerCase()];
    return courseType ? this.courseTypeColors[courseType] : this.chartColors.primary;
  }

  /**
   * 🎨 Obtener array de colores para gráficos de tipos de curso
   */
  private getCourseTypeColorsArray(): string[] {
    return [
      this.courseTypeColors[1], // Colectivo
      this.courseTypeColors[2], // Privado
      this.courseTypeColors[3]  // Actividad
    ];
  }

  // ==================== DATA LOADING ====================

  public loadAnalyticsData(): void {
    this.loading = true;
    const filters = this.buildFiltersObject();

    console.log('🔍 Loading analytics data with filters:', filters);

    // Usar principalmente el endpoint season-dashboard
    this.apiService.get('/admin/finance/season-dashboard', [], filters).subscribe({
      next: (response) => {
        console.log('✅ Season dashboard data received:', response);
        this.processSeasonDashboardData(response.data);
        this.loading = false;
        this.cdr.detectChanges();

        // Crear gráficos después de que los datos estén listos
        setTimeout(() => this.createAllCharts(), 100);
      },
      error: (error) => {
        console.error('❌ Error loading analytics data:', error);
        this.loading = false;
        this.cdr.detectChanges();
      }
    });
  }

  private buildFiltersObject(): any {
    const formValue = this.filterForm.value;

    const filters: any = {
      start_date: formValue.startDate,
      end_date: formValue.endDate,
      include_test_detection: formValue.includeTestDetection,
      include_payrexx_analysis: formValue.includePayrexxAnalysis,
      optimization_level: formValue.optimizationLevel
    };

    // Filtros opcionales
    if (formValue.courseType?.length) {
      filters.course_type = formValue.courseType.join(',');
    }
    if (formValue.source?.length) {
      filters.source = formValue.source.join(',');
    }
    if (formValue.paymentMethod?.length) {
      filters.payment_method = formValue.paymentMethod.join(',');
    }
    if (formValue.sportId?.length) {
      filters.sport_id = formValue.sportId.join(',');
    }
    if (formValue.onlyWeekends) {
      filters.only_weekends = true;
    }
    if (formValue.onlyPaidBookings) {
      filters.only_paid = true;
    }
    if (!formValue.includeRefunds) {
      filters.exclude_refunds = true;
    }

    return filters;
  }

  private processSeasonDashboardData(data: SeasonDashboardData): void {
    console.log('📊 Processing season dashboard data:', data);

    this.dashboardData = data;

    // Actualizar datos de las tablas
    this.updateTableData();

    console.log('✅ Dashboard data processed successfully');
  }

  private updateTableData(): void {
    if (!this.dashboardData) return;

    // ✅ Procesar datos de revenue con fechas formateadas
    this.revenueTableData.data = (this.dashboardData.trend_analysis?.monthly_breakdown || []).map(item => ({
      ...item,
      month: this.formatDateWithMonthName(item.month), // ✅ FORMATEAR MES AQUÍ
      month_original: item.month // Mantener original para ordenamiento si es necesario
    }));

    // Tabla de cursos (sin cambios)
    this.coursesTableData.data = this.dashboardData.courses || [];

    console.log('📋 Tables updated:', {
      revenueRows: this.revenueTableData.data.length,
      coursesRows: this.coursesTableData.data.length
    });
  }

  // ==================== CHART CREATION ====================

  private createAllCharts(): void {
    if (!this.dashboardData) return;

    try {
      this.createRevenueChart();
      this.createCourseTypeRevenueChart();
      this.createPaymentMethodsChart();
      this.createPaymentTrendsChart();
      this.createTopCoursesChart();
      this.createCompletionRatesChart();
      this.createSourcesChart();
      this.createSourcePerformanceChart();
      this.createComparisonChart();
    } catch (error) {
      console.error('❌ Error creating charts:', error);
    }
  }

  private createRevenueChart(): void {
    if (!this.revenueChartRef?.nativeElement || !this.dashboardData) return;

    const data = this.dashboardData.trend_analysis?.monthly_breakdown || [];

    // ✅ ARREGLO: Procesar las fechas correctamente
    const processedData = data.map(item => ({
      ...item,
      month_formatted: this.formatDateWithMonthName(item.month), // Agregar versión formateada
      month_original: item.month // Mantener original para ordenamiento
    }));

    const trace = {
      x: processedData.map(d => d.month_formatted), // ✅ Usar fechas formateadas
      y: processedData.map(d => d.revenue),
      type: 'scatter',
      mode: 'lines+markers',
      name: 'Revenue',
      line: { color: this.chartColors.primary, width: 3 },
      marker: { color: this.chartColors.primary, size: 6 }
    };

    const layout = {
      title: false,
      xaxis: {
        title: this.translateService.instant('dates'),
        tickangle: -45 // ✅ Rotar etiquetas para mejor legibilidad
      },
      yaxis: { title: `Revenue (${this.currency})` },
      margin: { l: 60, r: 20, t: 20, b: 80 }, // ✅ Más margen abajo para fechas rotadas
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)'
    };

    Plotly.newPlot(this.revenueChartRef.nativeElement, [trace], layout, { responsive: true });
  }

  private createCourseTypeRevenueChart(): void {
    console.log('🔍 Iniciando createCourseTypeRevenueChart...');

    // Verificación del elemento DOM
    if (!this.courseTypeRevenueChartRef?.nativeElement) {
      console.error('❌ Elemento DOM del gráfico no está disponible');
      return;
    }

    // Verificación de datos principales
    if (!this.dashboardData) {
      console.error('❌ dashboardData no está disponible');
      return;
    }

    const courses = this.dashboardData.courses || [];
    console.log('📊 Datos de cursos:', courses);

    if (courses.length === 0) {
      console.warn('⚠️ No hay cursos disponibles');
      this.showEmptyChart();
      return;
    }

    const typeStats: { [typeName: string]: any } = {};

    // Procesar datos con logging detallado
    for (const course of courses) {
      console.log('🎯 Procesando curso:', course);

      const typeName = this.getCourseTypeName(course.type);
      const revenue = course.revenue || 0;

      console.log(`   - Tipo: ${typeName}, Revenue: ${revenue}`);

      if (!typeStats[typeName]) {
        typeStats[typeName] = {
          typeName,
          revenue: 0,
          bookings: 0,
          participants: 0,
          revenue_received: 0,
          conversion_rate_sum: 0,
          course_count: 0
        };
      }

      typeStats[typeName].revenue += revenue;
      typeStats[typeName].bookings += course.bookings || 0;
      typeStats[typeName].participants += course.participants || 0;
      typeStats[typeName].revenue_received += course.revenue_received || 0;
      typeStats[typeName].conversion_rate_sum += course.sales_conversion_rate || 0;
      typeStats[typeName].course_count += course.courses_sold || 0;
    }

    console.log('📈 Estadísticas por tipo:', typeStats);

    // Verificar que tenemos datos válidos
    const hasValidData = Object.values(typeStats).some(stat => stat.revenue > 0);

    if (!hasValidData) {
      console.warn('⚠️ No hay datos de revenue válidos para mostrar');
      this.showEmptyChart();
      return;
    }

    // Preparar datos para el gráfico
    try {
      const labels = Object.keys(typeStats).map(type => {
        const translated = this.translateService.instant(type);
        console.log(`🌐 Traducción: ${type} -> ${translated}`);
        return translated;
      });

      const values = Object.values(typeStats).map(stat => stat.revenue);
      const colors = Object.keys(typeStats).map(type => {
        const color = this.getCourseTypeColorByName(type);
        console.log(`🎨 Color para ${type}: ${color}`);
        return color;
      });

      console.log('📊 Datos finales del gráfico:');
      console.log('   - Labels:', labels);
      console.log('   - Values:', values);
      console.log('   - Colors:', colors);

      // Preparar datos adicionales para mostrar bookings
      const bookingsData = Object.values(typeStats).map(stat => stat.bookings);
      const totalBookings = bookingsData.reduce((sum, bookings) => sum + bookings, 0);
      const coursesSoldData = Object.values(typeStats).map(stat => stat.course_count); // ← NUEVA LÍNEA
      const totalRevenue = values.reduce((sum, revenue) => sum + revenue, 0);
      const totalCoursesSold = coursesSoldData.reduce((sum, courses) => sum + courses, 0); // ← NUEVA LÍNEA

      console.log('📊 Totales:');
      console.log(`   - Total Bookings: ${totalBookings}`);
      console.log(`   - Total Cursos Vendidos: ${totalCoursesSold}`); // ← NUEVA LÍNEA
      console.log(`   - Total Revenue: ${totalRevenue.toFixed(2)} €`);

      // Configurar el gráfico
      const trace = {
        values,
        labels,
        type: 'pie',
        marker: { colors },
        textinfo: 'label+percent',
        texttemplate: '%{label}<br>%{percent}',

        // ✅ Crear hover text manual (100% funcional)
        hovertemplate: '%{hovertext}<extra></extra>',
        hovertext: Object.values(typeStats).map((stats: any) => {
          const typeName = this.translateService.instant(stats.typeName);
          return `<b>${typeName}</b><br>` +
            `💰 Revenue: ${stats.revenue.toFixed(2)} €<br>` +
            `📋 Reservas: ${stats.bookings} bookings<br>` +
            `📚 Cursos vendidos: ${stats.course_count} cursos<br>` +
            `👥 Participantes: ${stats.participants}`;
        })
      };

// 🔧 CAMBIO ADICIONAL: Asegurar que typeStats tenga el campo typeName
// Modifica esta parte en tu loop de procesamiento:

      for (const course of courses) {
        console.log('🎯 Procesando curso:', course);

        const typeName = this.getCourseTypeName(course.type);
        const revenue = course.revenue || 0;

        console.log(`   - Tipo: ${typeName}, Revenue: ${revenue}`);

        if (!typeStats[typeName]) {
          typeStats[typeName] = {
            typeName,        // ← ASEGURAR QUE ESTE CAMPO ESTÉ AQUÍ
            revenue: 0,
            bookings: 0,
            participants: 0,
            revenue_received: 0,
            conversion_rate_sum: 0,
            course_count: 0
          };
        }
      }


      const layout = {
        title: {
          text: this.translateService.instant('revenue_by_course_type'),
          font: { size: 16 }
        },
        margin: { l: 20, r: 20, t: 40, b: 20 },
        plot_bgcolor: 'rgba(0,0,0,0)',
        paper_bgcolor: 'rgba(0,0,0,0)',
        showlegend: true,
        legend: {
          orientation: 'v',
          x: 1.02,
          y: 0.5
        }
      };

      const config = {
        responsive: true,
        displayModeBar: false,
        displaylogo: false
      };

      console.log('🚀 Creando gráfico con Plotly...');

      Plotly.newPlot(
        this.courseTypeRevenueChartRef.nativeElement,
        [trace],
        layout,
        config
      ).then(() => {
        console.log('✅ Gráfico creado exitosamente');
        this.displayBookingsSummary(typeStats, totalBookings, totalRevenue, totalCoursesSold);
      }).catch(error => {
        console.error('❌ Error al crear el gráfico:', error);
      });

      // Opcional: Almacenar las estadísticas para uso posterior
     // this.courseTypeStats = typeStats;

    } catch (error) {
      console.error('❌ Error durante la creación del gráfico:', error);
      this.showEmptyChart();
    }
  }

  // Método para mostrar resumen detallado de bookings y revenue
  private displayBookingsSummary(typeStats: any, totalBookings: number, totalRevenue: number, totalCoursesSold: number): void {
    console.log('\n📊 ===== RESUMEN DETALLADO POR TIPO DE CURSO =====');
    console.log(`📈 TOTAL GENERAL: ${totalBookings} reservas | ${totalCoursesSold} cursos vendidos | ${totalRevenue.toFixed(2)} € revenue\n`);

    Object.entries(typeStats).forEach(([typeName, stats]: [string, any]) => {
      const bookingPercentage = totalBookings > 0 ? ((stats.bookings / totalBookings) * 100).toFixed(1) : '0';
      const coursePercentage = totalCoursesSold > 0 ? ((stats.course_count / totalCoursesSold) * 100).toFixed(1) : '0';
      const revenuePercentage = totalRevenue > 0 ? ((stats.revenue / totalRevenue) * 100).toFixed(1) : '0';
      const avgRevenuePerBooking = stats.bookings > 0 ? (stats.revenue / stats.bookings).toFixed(2) : '0';
      const avgRevenuePerCourse = stats.course_count > 0 ? (stats.revenue / stats.course_count).toFixed(2) : '0';

      console.log(`🎯 ${typeName.toUpperCase()}:`);
      console.log(`   📋 Reservas: ${stats.bookings} (${bookingPercentage}% del total)`);
      console.log(`   📚 Cursos vendidos: ${stats.course_count} (${coursePercentage}% del total)`); // ← NUEVA LÍNEA
      console.log(`   💰 Revenue: ${stats.revenue.toFixed(2)} € (${revenuePercentage}% del total)`);
      console.log(`   👥 Participantes: ${stats.participants}`);
      console.log(`   💵 Revenue recibido: ${stats.revenue_received.toFixed(2)} €`);
      console.log(`   📊 Revenue promedio por reserva: ${avgRevenuePerBooking} €`);
      console.log(`   🎯 Revenue promedio por curso: ${avgRevenuePerCourse} €`); // ← NUEVA LÍNEA
      console.log('   ─────────────────────────────────────────────────');
    });

    // También crear el array para uso en el template si lo necesitas
    this.courseTypeBookingsSummary = Object.entries(typeStats).map(([typeName, stats]: [string, any]) => ({
      type: typeName,
      typeName: this.translateService.instant(typeName),
      bookings: stats.bookings,
      courses_sold: stats.course_count, // ← NUEVA LÍNEA
      revenue: stats.revenue,
      participants: stats.participants,
      revenueReceived: stats.revenue_received,
      avgRevenuePerBooking: stats.bookings > 0 ? stats.revenue / stats.bookings : 0,
      avgRevenuePerCourse: stats.course_count > 0 ? stats.revenue / stats.course_count : 0, // ← NUEVA LÍNEA
      bookingPercentage: totalBookings > 0 ? (stats.bookings / totalBookings) * 100 : 0,
      coursePercentage: totalCoursesSold > 0 ? (stats.course_count / totalCoursesSold) * 100 : 0, // ← NUEVA LÍNEA
      revenuePercentage: totalRevenue > 0 ? (stats.revenue / totalRevenue) * 100 : 0
    }));

    console.log('\n💾 Datos guardados en this.courseTypeBookingsSummary para uso en template');
  }

// Método auxiliar para mostrar un gráfico vacío con mensaje
  private showEmptyChart(): void {
    if (!this.courseTypeRevenueChartRef?.nativeElement) return;

    const trace = {
      values: [1],
      labels: [this.translateService.instant('no_data_available')],
      type: 'pie',
      marker: { colors: ['#E0E0E0'] },
      textinfo: 'label',
      hoverinfo: 'none'
    };

    const layout = {
      title: {
        text: this.translateService.instant('revenue_by_course_type'),
        font: { size: 16 }
      },
      margin: { l: 20, r: 20, t: 40, b: 20 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)',
      showlegend: false,
      annotations: [{
        text: this.translateService.instant('no_data_to_display'),
        x: 0.5,
        y: 0.5,
        showarrow: false,
        font: { size: 14, color: '#666' }
      }]
    };

    Plotly.newPlot(
      this.courseTypeRevenueChartRef.nativeElement,
      [trace],
      layout,
      { responsive: true, displayModeBar: false }
    );
  }
  private createPaymentMethodsChart(): void {
    if (!this.paymentMethodsChartRef?.nativeElement || !this.dashboardData) return;

    const methods = this.dashboardData.payment_methods?.methods || [];

    const trace = {
      values: methods.map(m => m.revenue),
      labels: methods.map(m => m.display_name),
      type: 'pie',
      marker: { colors: this.chartColors.gradient }
    };

    const layout = {
      title: false,
      margin: { l: 20, r: 20, t: 20, b: 20 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)'
    };

    Plotly.newPlot(this.paymentMethodsChartRef.nativeElement, [trace], layout, { responsive: true });
  }

  private createPaymentTrendsChart(): void {
    if (!this.paymentTrendsChartRef?.nativeElement || !this.dashboardData) return;

    const onlineVsOffline = this.dashboardData.payment_methods?.online_vs_offline;

    if (!onlineVsOffline) return;

    const trace = {
      x: ['Online', 'Offline'],
      y: [onlineVsOffline.online.revenue, onlineVsOffline.offline.revenue],
      type: 'bar',
      marker: { color: [this.chartColors.info, this.chartColors.secondary] }
    };

    const layout = {
      title: false,
      xaxis: { title: 'Payment Type' },
      yaxis: { title: `Revenue (${this.currency})` },
      margin: { l: 60, r: 20, t: 20, b: 40 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)'
    };

    Plotly.newPlot(this.paymentTrendsChartRef.nativeElement, [trace], layout, { responsive: true });
  }

  private createTopCoursesChart(): void {
    if (!this.topCoursesChartRef?.nativeElement || !this.dashboardData) return;

    const courses = this.dashboardData.courses || [];
    const topCourses = courses
      .sort((a, b) => b.revenue - a.revenue)
      .slice(0, 10);

    // ✅ APLICAR COLORES POR TIPO DE CURSO
    const colors = topCourses.map(course => this.getCourseTypeColor(course.type));

    const trace = {
      x: topCourses.map(c => c.revenue),
      y: topCourses.map(c => c.name),
      type: 'bar',
      orientation: 'h',
      marker: { color: colors } // ✅ COLORES POR TIPO
    };

    const layout = {
      title: false,
      xaxis: { title: `Revenue (${this.currency})` },
      margin: { l: 150, r: 20, t: 20, b: 40 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)'
    };

    Plotly.newPlot(this.topCoursesChartRef.nativeElement, [trace], layout, { responsive: true });
  }

  private createCompletionRatesChart(): void {
    if (!this.completionRatesChartRef?.nativeElement || !this.dashboardData) return;

    const courses = this.dashboardData.courses?.slice(0, 10) || [];

    // ✅ APLICAR COLORES POR TIPO DE CURSO
    const colors = courses.map(course => this.getCourseTypeColor(course.type));

    const trace = {
      x: courses.map(c => c.name),
      y: courses.map(c => {
        const statusBreakdown = c.status_breakdown || {};
        const total = Number(Object.values(statusBreakdown).reduce(
          (sum: number, count: unknown) => sum + Number(count ?? 0),
          0
        ));
        const completed = Number(statusBreakdown[1] ?? 0);
        return total > 0 ? (completed / total) * 100 : 0;
      }),
      type: 'bar',
      marker: { color: colors } // ✅ COLORES POR TIPO
    };

    const layout = {
      title: false,
      xaxis: { title: 'Courses' },
      yaxis: { title: 'Completion Rate (%)' },
      margin: { l: 60, r: 20, t: 20, b: 100 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)'
    };

    Plotly.newPlot(this.completionRatesChartRef.nativeElement, [trace], layout, { responsive: true });
  }

  private createSourcesChart(): void {
    if (!this.sourcesChartRef?.nativeElement || !this.dashboardData) return;

    const sources = this.dashboardData.booking_sources?.source_breakdown || [];

    const trace = {
      values: sources.map(s => s.revenue),
      labels: sources.map(s => s.source),
      type: 'pie',
      marker: { colors: this.chartColors.gradient }
    };

    const layout = {
      title: false,
      margin: { l: 20, r: 20, t: 20, b: 20 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)'
    };

    Plotly.newPlot(this.sourcesChartRef.nativeElement, [trace], layout, { responsive: true });
  }

  private createSourcePerformanceChart(): void {
    if (!this.sourcePerformanceChartRef?.nativeElement || !this.dashboardData) return;

    const sources = this.dashboardData.booking_sources?.source_breakdown || [];

    const trace = {
      x: sources.map(s => s.source),
      y: sources.map(s => s.consistency_rate),
      type: 'bar',
      marker: { color: this.chartColors.warning }
    };

    const layout = {
      title: false,
      xaxis: { title: 'Source' },
      yaxis: { title: 'Consistency Rate (%)' },
      margin: { l: 60, r: 20, t: 20, b: 40 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)'
    };

    Plotly.newPlot(this.sourcePerformanceChartRef.nativeElement, [trace], layout, { responsive: true });
  }

  private createComparisonChart(): void {
    if (!this.comparisonChartRef?.nativeElement || !this.dashboardData) return;

    const kpis = this.dashboardData.executive_kpis;
    const classification = this.dashboardData.season_info.booking_classification;

    const trace1 = {
      x: ['Production', 'Test', 'Cancelled'],
      y: [classification.production_count, classification.test_count, classification.cancelled_count],
      name: 'Bookings Count',
      type: 'bar',
      marker: { color: this.chartColors.primary }
    };

    const trace2 = {
      x: ['Production', 'Test', 'Cancelled'],
      y: [classification.production_revenue, classification.test_revenue, classification.cancelled_revenue],
      name: 'Revenue',
      type: 'bar',
      yaxis: 'y2',
      marker: { color: this.chartColors.secondary }
    };

    const layout = {
      title: false,
      xaxis: { title: 'Booking Type' },
      yaxis: { title: 'Count', side: 'left' },
      yaxis2: { title: 'Revenue', side: 'right', overlaying: 'y' },
      margin: { l: 60, r: 60, t: 20, b: 40 },
      plot_bgcolor: 'rgba(0,0,0,0)',
      paper_bgcolor: 'rgba(0,0,0,0)'
    };

    Plotly.newPlot(this.comparisonChartRef.nativeElement, [trace1, trace2], layout, { responsive: true });
  }

  public onPendingRevenueClick(): void {
    if (!this.dashboardData?.executive_kpis?.revenue_pending ||
      this.dashboardData.executive_kpis.revenue_pending <= 0) {
      this.showMessage('No hay reservas con pagos pendientes', 'info');
      return;
    }

    this.showMessage('Cargando reservas pendientes...', 'info');
    this.loadPendingBookingsDetailed();
  }

  // 📋 CARGAR RESERVAS PENDIENTES CON DETALLE
  private loadPendingBookingsDetailed(): void {
    const filters = this.buildFiltersObject();
    filters.only_pending = true;

    this.apiService.get('/admin/finance/booking-details', [], filters).subscribe({
      next: (response) => {
        if (!response.data?.bookings?.length) {
          this.showMessage('No se encontraron reservas pendientes', 'warning');
          return;
        }

        const pendingBookingsData = {
          title: `Reservas con Pagos Pendientes (${response.data.bookings.length})`,
          type: 'pending',
          bookings: response.data.bookings,
          currency: this.currency
        };

        this.openBookingListModal(pendingBookingsData);
      },
      error: (error) => {
        console.error('Error cargando reservas pendientes:', error);
        this.showMessage('Error cargando reservas pendientes', 'error');
      }
    });
  }

  public onCancelledBookingsClick(): void {
    if (!this.dashboardData?.season_info?.booking_classification?.cancelled_count ||
      this.dashboardData.season_info.booking_classification.cancelled_count <= 0) {
      this.showMessage('No hay reservas canceladas', 'info');
      return;
    }

    this.showMessage('Cargando reservas canceladas...', 'info');
    this.loadCancelledBookingsDetailed();
  }

  private loadCancelledBookingsDetailed(): void {
    const filters = this.buildFiltersObject();
    filters.only_cancelled = true;

    this.apiService.get('/admin/finance/booking-details', [], filters).subscribe({
      next: (response) => {
        if (!response.data?.bookings?.length) {
          this.showMessage('No se encontraron reservas canceladas', 'warning');
          return;
        }

        const cancelledBookingsData = {
          title: `Reservas Canceladas (${response.data.bookings.length})`,
          type: 'cancelled',
          bookings: response.data.bookings,
          currency: this.currency
        };

        this.openBookingListModal(cancelledBookingsData);
      },
      error: (error) => {
        console.error('Error cargando reservas canceladas:', error);
        this.showMessage('Error cargando reservas canceladas', 'error');
      }
    });
  }

  private openBookingListModal(data: any): void {
    const dialogRef = this.dialog.open(BookingListModalComponent, {
      width: '95vw',
      maxWidth: '1400px',
      height: '85vh',
      maxHeight: '800px',
      data: data,
      panelClass: 'booking-list-modal',
      disableClose: false,
      autoFocus: false
    });

    // Manejar acciones del modal
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.handleModalAction(result);
      }
    });
  }

// 🎬 MANEJAR ACCIONES DEL MODAL
  private handleModalAction(result: any): void {
    switch (result.action) {
      case 'export':
        this.handleExportAllBookings(result);
        break;

      case 'export_single':
        this.handleExportSingleBooking(result);
        break;

      case 'view_details':
        this.handleViewBookingDetails(result);
        break;

      default:
        console.log('Acción no reconocida:', result.action);
    }
  }

// 📤 MANEJAR EXPORTACIÓN DE TODAS LAS RESERVAS
  private handleExportAllBookings(result: any): void {
    const exportType = result.type === 'pending' ? 'pending' : 'cancelled';
    const fileName = `reservas_${exportType}_${this.getCurrentDateString()}`;

    this.showMessage(`Exportando ${result.data.length} reservas...`, 'info');

    const filters = {
      ...this.buildFiltersObject(),
      [`only_${exportType}`]: true,
      format: 'csv'
    };

    const endpoint = exportType === 'pending'
      ? '/admin/finance/export-pending-bookings'
      : '/admin/finance/export-cancelled-bookings';

    this.apiService.get(endpoint, [], filters).subscribe({
      next: (response) => {
        if (response.data?.download_url) {
          window.open(response.data.download_url, '_blank');
          this.showMessage(`Exportación de ${result.data.length} reservas completada`, 'success');
        } else {
          this.showMessage('Error en la exportación', 'error');
        }
      },
      error: (error) => {
        console.error('Error exportando reservas:', error);
        this.showMessage('Error exportando reservas', 'error');
      }
    });
  }

// 📤 MANEJAR EXPORTACIÓN DE RESERVA INDIVIDUAL
  private handleExportSingleBooking(result: any): void {
    const booking = result.booking;

    this.showMessage(`Exportando reserva #${booking.id}...`, 'info');

    // Crear un mini CSV para la reserva individual
    const csvContent = this.createSingleBookingCsv(booking, result.type);
    const fileName = `reserva_${booking.id}_${this.getCurrentDateString()}.csv`;

    this.downloadCsvContent(csvContent, fileName);
    this.showMessage(`Reserva #${booking.id} exportada`, 'success');
  }

// 👁️ MANEJAR VER DETALLES DE RESERVA
  private handleViewBookingDetails(result: any): void {
    const booking = result.booking;

    if (booking.id) {
      // Navegar al detalle de la reserva
      this.router.navigate(['/admin/bookings', booking.id]);
    } else {
      this.showMessage('ID de reserva no disponible', 'warning');
    }
  }

// 📄 CREAR CSV PARA RESERVA INDIVIDUAL
  private createSingleBookingCsv(booking: any, type: string): string {
    let csvContent = '\xEF\xBB\xBF'; // BOM for UTF-8

    csvContent += `DETALLE DE RESERVA #${booking.id}\n`;
    csvContent += `Generado: ${new Date().toLocaleString('es-ES')}\n\n`;

    // Headers
    if (type === 'pending') {
      csvContent += '"Campo","Valor"\n';
      csvContent += `"ID","${booking.id}"\n`;
      csvContent += `"Cliente","${booking.client_name}"\n`;
      csvContent += `"Email","${booking.client_email}"\n`;
      csvContent += `"Fecha","${booking.booking_date}"\n`;
      csvContent += `"Importe Total","${this.formatCurrencyForCsv(booking.amount)}"\n`;
      csvContent += `"Importe Recibido","${this.formatCurrencyForCsv(booking.received_amount)}"\n`;
      csvContent += `"Importe Pendiente","${this.formatCurrencyForCsv(booking.pending_amount)}"\n`;
      csvContent += `"Estado","${booking.status}"\n`;
    } else {
      csvContent += '"Campo","Valor"\n';
      csvContent += `"ID","${booking.id}"\n`;
      csvContent += `"Cliente","${booking.client_name}"\n`;
      csvContent += `"Email","${booking.client_email}"\n`;
      csvContent += `"Fecha","${booking.booking_date}"\n`;
      csvContent += `"Importe","${this.formatCurrencyForCsv(booking.amount)}"\n`;
      csvContent += `"Estado","${booking.status}"\n`;
    }

    return csvContent;
  }

// 💰 FORMATEAR CURRENCY PARA CSV
  private formatCurrencyForCsv(amount: number | null | undefined): string {
    if (amount === null || amount === undefined || isNaN(amount)) {
      return '0,00 EUR';
    }
    return `${amount.toFixed(2).replace('.', ',')} EUR`;
  }

// 📅 OBTENER FECHA ACTUAL COMO STRING
  private getCurrentDateString(): string {
    return new Date().toISOString().split('T')[0].replace(/-/g, '');
  }

  /**
   * Procesar fechas para mostrar nombres de meses
   */
  private processDateLabels(dates: string[]): string[] {
    return dates.map(date => this.formatDateWithMonthName(date));
  }

  /**
   * Crear configuración de eje X con fechas traducidas
   */
  private createTranslatedXAxisConfig(dates: string[]) {
    const translatedLabels = this.processDateLabels(dates);

    return {
      title: this.translateService.instant('dates'),
      tickmode: 'array',
      tickvals: dates,
      ticktext: translatedLabels,
      tickangle: -45, // Rotar las etiquetas para mejor legibilidad
    };
  }

  /**
   * Formatear fechas con nombres de meses traducidos
   */
  private formatDateWithMonthName(dateString: string): string {
    if (!dateString) return '';

    try {
      const date = moment(dateString);

      // Si el formato es "YYYY-MM", procesarlo correctamente
      if (dateString.match(/^\d{4}-\d{2}$/)) {
        const year = date.format('YYYY');
        const monthNumber = date.format('MM');

        // Mapear número de mes a nombre
        const monthNames = [
          'january', 'february', 'march', 'april', 'may', 'june',
          'july', 'august', 'september', 'october', 'november', 'december'
        ];

        const monthIndex = parseInt(monthNumber, 10) - 1;
        const monthKey = monthNames[monthIndex];

        const translatedMonth = this.translateService.instant(`months.${monthKey}`);
        return `${translatedMonth} ${year}`;
      }

      // Para otros formatos, usar moment
      const monthKey = date.format('MMMM').toLowerCase();
      const year = date.format('YYYY');
      const translatedMonth = this.translateService.instant(`months.${monthKey}`);

      return `${translatedMonth} ${year}`;

    } catch (error) {
      console.warn('Error formatting date:', dateString, error);
      return dateString; // Fallback al original
    }
  }

// 💾 DESCARGAR CONTENIDO CSV
  private downloadCsvContent(content: string, fileName: string): void {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');

    if (link.download !== undefined) {
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', fileName);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    }
  }

// 💬 MOSTRAR MENSAJE AL USUARIO
  private showMessage(message: string, type: 'success' | 'error' | 'warning' | 'info' = 'info'): void {
    const config = {
      duration: 4000,
      horizontalPosition: 'end' as const,
      verticalPosition: 'top' as const,
      panelClass: [`snackbar-${type}`]
    };

    this.snackBar.open(message, 'Cerrar', config);
  }

// 6. EXPORTAR RESERVAS PENDIENTES
  private exportPendingBookings(): void {
    const filters = { ...this.buildFiltersObject(), only_pending: true, format: 'csv' };

    this.apiService.get('/admin/finance/export-pending-bookings', [], filters).subscribe({
      next: (response) => {
        if (response.data?.download_url) {
          window.open(response.data.download_url, '_blank');
        }
      },
      error: (error) => console.error('Error exportando reservas pendientes:', error)
    });
  }

// 7. EXPORTAR RESERVAS CANCELADAS
  private exportCancelledBookings(): void {
    const filters = { ...this.buildFiltersObject(), only_cancelled: true, format: 'csv' };

    this.apiService.get('/admin/finance/export-cancelled-bookings', [], filters).subscribe({
      next: (response) => {
        if (response.data?.download_url) {
          window.open(response.data.download_url, '_blank');
        }
      },
      error: (error) => console.error('Error exportando reservas canceladas:', error)
    });
  }


  private loadPendingBookings(): void {
    this.loadingPendingBookings = true;

    // Simular carga de datos - esto se conectará al backend más tarde
    setTimeout(() => {
      this.pendingBookings = [
        {
          id: 12345,
          client_name: 'Juan Pérez',
          client_email: 'juan@example.com',
          booking_date: '2024-12-15',
          amount: 150.00,
          pending_amount: 75.00,
          status: 'active'
        },
        {
          id: 12346,
          client_name: 'María García',
          client_email: 'maria@example.com',
          booking_date: '2024-12-16',
          amount: 200.00,
          pending_amount: 200.00,
          status: 'active'
        }
      ];
      this.loadingPendingBookings = false;
    }, 1000);
  }

  private loadCancelledBookings(): void {
    this.loadingCancelledBookings = true;

    // Simular carga de datos - esto se conectará al backend más tarde
    setTimeout(() => {
      this.cancelledBookings = [
        {
          id: 12340,
          client_name: 'Carlos López',
          client_email: 'carlos@example.com',
          booking_date: '2024-12-10',
          amount: 180.00,
          status: 'cancelled'
        },
        {
          id: 12341,
          client_name: 'Ana Martínez',
          client_email: 'ana@example.com',
          booking_date: '2024-12-11',
          amount: 95.00,
          status: 'cancelled'
        }
      ];
      this.loadingCancelledBookings = false;
    }, 1000);
  }

  // ==================== MODAL EVENT HANDLERS ====================

  public onViewBookingDetails(booking: any): void {
    console.log('Viewing booking details:', booking);
    // TODO: Navegar al detalle de la reserva
  }

  public onEditBooking(booking: any): void {
    console.log('Editing booking:', booking);
    // TODO: Abrir formulario de edición
  }

  public onExportBooking(booking: any): void {
    console.log('Exporting booking:', booking);
    // TODO: Exportar reserva individual
  }

  public onExportAllBookings(bookings: any[]): void {
    console.log('Exporting all bookings:', bookings.length);
    // TODO: Exportar todas las reservas del modal
  }

  // ==================== TAB MANAGEMENT (ACTUALIZADO) ====================

  onTabChange(event: MatTabChangeEvent): void {
    this.activeTabIndex = event.index;
    this.activeTab = this.tabs[event.index].id; // ← ESTO YA ESTABA

    // Cargar datos específicos de la pestaña
    switch (this.activeTab) {
      case 'revenue':
        // Ya se carga con loadAnalyticsData()
        console.log('Revenue tab selected');
        break;
      case 'payments':
        // Ya se carga con loadAnalyticsData()
        console.log('Payments tab selected');
        break;
      case 'courses':
        // Ya se carga con loadAnalyticsData()
        console.log('Courses tab selected');
        break;
      case 'sources':
        // Ya se carga con loadAnalyticsData()
        console.log('Sources tab selected');
        break;
      case 'comparative':
        // Ya se carga con loadAnalyticsData()
        console.log('Comparative tab selected');
        break;
      case 'alerts':
        // Ya se carga con loadAnalyticsData()
        console.log('Alerts tab selected');
        break;
      case 'monitors': // ← NUEVO CASE PARA MONITORES
        console.log('Monitors tab selected');
        // El componente MonitorsLegacy maneja su propia carga
        break;
      default:
        console.log('Default tab selected:', this.activeTab);
        break;
    }

    // Los gráficos se crean automáticamente con createAllCharts()
    // No necesitamos createTabSpecificCharts() porque no está implementado
  }

  public onPresetRangeChange(event: any): void {
    const value = event.value;
    if (!value) return;

    let startDate: moment.Moment;
    let endDate: moment.Moment = moment();

    switch (value) {
      case 'today':
        startDate = moment().startOf('day');
        endDate = moment().endOf('day');
        break;
      case 'yesterday':
        startDate = moment().subtract(1, 'day').startOf('day');
        endDate = moment().subtract(1, 'day').endOf('day');
        break;
      case 'this_week':
        startDate = moment().startOf('week');
        break;
      case 'last_week':
        startDate = moment().subtract(1, 'week').startOf('week');
        endDate = moment().subtract(1, 'week').endOf('week');
        break;
      case 'this_month':
        startDate = moment().startOf('month');
        break;
      case 'last_month':
        startDate = moment().subtract(1, 'month').startOf('month');
        endDate = moment().subtract(1, 'month').endOf('month');
        break;
      case 'this_quarter':
        startDate = moment().startOf('quarter');
        break;
      case 'last_quarter':
        startDate = moment().subtract(1, 'quarter').startOf('quarter');
        endDate = moment().subtract(1, 'quarter').endOf('quarter');
        break;
      case 'this_year':
        startDate = moment().startOf('year');
        break;
      case 'last_year':
        startDate = moment().subtract(1, 'year').startOf('year');
        endDate = moment().subtract(1, 'year').endOf('year');
        break;
      default:
        return;
    }

    this.filterForm.patchValue({
      startDate: startDate.format('YYYY-MM-DD'),
      endDate: endDate.format('YYYY-MM-DD')
    });
  }

  public applyFilters(): void {
    this.loadAnalyticsData();
  }

  public refreshData(): void {
    this.loadAnalyticsData();
  }

  public toggleAdvancedFilters(): void {
    this.showAdvancedFilters = !this.showAdvancedFilters;
  }

  public onExportData(): void {
    if (!this.dashboardData) return;

    const filters = this.buildFiltersObject();
    const exportFilters = {
      ...filters,
      format: 'csv',
      sections: ['executive_summary', 'financial_kpis', 'booking_analysis', 'critical_issues']
    };

    this.apiService.get('/admin/finance/season-dashboard/export', [], exportFilters).subscribe({
      next: (response) => {
        debugger;
        console.log('✅ Export successful:', response);
        if (response.data?.download_url) {
          window.open(response.data.download_url, '_blank');
        }
      },
      error: (error) => console.error('❌ Export error:', error)
    });
  }

  public filterCourses(event: any): void {
    const filterValue = (event.target as HTMLInputElement).value;
    this.coursesTableData.filter = filterValue.trim().toLowerCase();
  }

  public viewCourseDetails(course: any): void {
    if (!course?.id) {
      this.showMessage('ID de curso no disponible', 'warning');
      return;
    }

    // Preparar datos para el modal
    const modalData = {
      courseId: course.id,
      courseName: course.name || `Curso #${course.id}`,
      courseType: course.type,
      sport: course.sport,
      dateRange: this.filterForm.value.startDate && this.filterForm.value.endDate ? {
        start: this.filterForm.value.startDate,
        end: this.filterForm.value.endDate
      } : undefined
    };

    // Abrir modal
    const dialogRef = this.dialog.open(CourseStatisticsModalComponent, {
      width: '85vw',
      maxWidth: '1200px',
      height: '80vh',
      maxHeight: '800px',
      data: modalData,
      panelClass: 'course-statistics-modal-overlay',
      disableClose: false,
      autoFocus: false,
      restoreFocus: true,
      hasBackdrop: true,
      backdropClass: 'modal-backdrop'
    });

    // Manejar el cierre del modal
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.handleCourseModalAction(result, course);
      }
    });
  }

  private handleCourseModalAction(result: any, course: any): void {
    switch (result.action) {
      case 'export':
        this.exportSingleCourseStatistics(result.courseId, course);
        break;

      case 'refresh':
        // Refrescar datos del dashboard
        this.loadAnalyticsData();
        break;

      default:
        console.log('Acción no reconocida desde modal de curso:', result.action);
    }
  }

  private exportSingleCourseStatistics(courseId: number, course: any): void {
    this.showMessage(`Exportando estadísticas de ${course.name}...`, 'info');

    const filters = {
      ...this.buildFiltersObject(),
      course_id: courseId,
      format: 'csv'
    };

    this.apiService.get(`/admin/finance/courses/${courseId}/statistics/export`, [], filters).subscribe({
      next: (response) => {
        if (response.data?.download_url) {
          window.open(response.data.download_url, '_blank');
          this.showMessage(`Estadísticas de ${course.name} exportadas correctamente`, 'success');
        } else {
          this.showMessage('Error en la exportación', 'error');
        }
      },
      error: (error) => {
        console.error('Error exportando estadísticas del curso:', error);
        this.showMessage('Error exportando estadísticas', 'error');
      }
    });
  }

  public exportCourseData(course: any): void {
    this.exportSingleCourseStatistics(course.id, course);
  }

// Agregar estos métodos al analytics.component.ts

  public navigateToCourseBookings(course: any): void {
    if (course?.id) {
      this.router.navigate(['/admin/bookings'], {
        queryParams: { courseId: course.id }
      });
    }
  }

  public navigateToCourseEdit(course: any): void {
    if (course?.id) {
      this.router.navigate(['/admin/courses', course.id, 'edit']);
    }
  }

// Método helper para formatear monedas
  public formatCurrency(amount: number): string {
    if (amount === null || amount === undefined || isNaN(amount)) {
      return `0.00 ${this.currency}`;
    }

    return new Intl.NumberFormat('es-ES', {
      style: 'currency',
      currency: this.currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  }


// ==================== GETTERS FOR TEMPLATE ====================

  get analyticsData() {
    if (!this.dashboardData) return null;

    const kpis = this.dashboardData.executive_kpis;
    const classification = this.dashboardData.season_info.booking_classification;

    return {
      totalRevenue: kpis.revenue_expected,
      totalBookings: kpis.total_production_bookings,
      totalParticipants: kpis.total_participants,
      averageBookingValue: kpis.average_booking_value,
      collectionEfficiency: kpis.collection_efficiency,
      pendingRevenue: kpis.revenue_pending,
      paymentMethods: this.dashboardData.payment_methods?.methods || [],
      bookingSources: this.dashboardData.booking_sources?.source_breakdown || [],
      courseAnalytics: this.dashboardData.courses || [],
      revenueOverTime: this.dashboardData.trend_analysis?.monthly_breakdown || []
    };
  }

// ==================== UTILITY METHODS ====================

  public getTrend(current: number, previous?: number): 'up' | 'down' | 'stable' {
    if (!previous || previous === 0) return 'stable';
    if (current > previous) return 'up';
    if (current < previous) return 'down';
    return 'stable';
  }

  public getTrendValue(current: number, previous?: number): number {
    if (!previous || previous === 0) return 0;
    return Math.abs(((current - previous) / previous) * 100);
  }

  public getCourseTypeName(type: number): string {
    switch (type) {
      case 1: return 'collective';
      case 2: return 'private';
      case 3: return 'activity';
      default: return 'unknown';
    }
  }

  public getComparisonClass(current: number, previous?: number): string {
    const trend = this.getTrend(current, previous);
    return trend === 'up' ? 'positive' : trend === 'down' ? 'negative' : 'neutral';
  }

  public getComparisonValue(current: number, previous?: number): string {
    if (!previous || previous === 0) return 'N/A';
    const diff = current - previous;
    const percentage = (diff / previous) * 100;
    const sign = diff >= 0 ? '+' : '';
    return `${sign}${percentage.toFixed(1)}%`;
  }

  getTotalRevenue(): number {
    if (!this.dashboardData?.payment_methods?.total_revenue) {
      // Fallback: calcular desde el array de methods
      const methods = this.dashboardData?.payment_methods?.methods || [];
      return methods.reduce((total: number, method: any) => total + (method.revenue || 0), 0);
    }

    return this.dashboardData.payment_methods.total_revenue;
  }

  getTotalTransactions(): number {
    if (!this.dashboardData?.payment_methods?.total_payments) {
      // Fallback: calcular desde el array de methods
      const methods = this.dashboardData?.payment_methods?.methods || [];
      return methods.reduce((total: number, method: any) => total + (method.count || 0), 0);
    }

    return this.dashboardData.payment_methods.total_payments;
  }

  getMostUsedPaymentMethod(): string {
    const methods = this.dashboardData?.payment_methods?.methods || [];
    if (methods.length === 0) return 'N/A';

    let maxRevenue = 0;
    let mostUsedMethod = 'N/A';

    methods.forEach((method: any) => {
      if (method.revenue > maxRevenue) {
        maxRevenue = method.revenue;
        mostUsedMethod = method.display_name || this.getPaymentMethodDisplayName(method.method);
      }
    });

    return mostUsedMethod;
  }

  getMostUsedPaymentPercentage(): number {
    const methods = this.dashboardData?.payment_methods?.methods || [];
    if (methods.length === 0) return 0;

    let maxRevenuePercentage = 0;
    methods.forEach((method: any) => {
      if (method.revenue_percentage > maxRevenuePercentage) {
        maxRevenuePercentage = method.revenue_percentage;
      }
    });

    return Math.round(maxRevenuePercentage);
  }

  getPaymentMethodsArray(): any[] {
    const methods = this.dashboardData?.payment_methods?.methods || [];

    return methods.map((method: any) => ({
      key: method.method,
      display_name: method.display_name || this.getPaymentMethodDisplayName(method.method),
      revenue: method.revenue || 0,
      count: method.count || 0,
      revenue_percentage: method.revenue_percentage || 0
    }));
  }

  /**
   * Get display name for payment method
   */
  getPaymentMethodDisplayName(method: string): string {
    const displayNames: { [key: string]: string } = {
      'cash': 'Efectivo',
      'card': 'Tarjeta',
      'online': 'Online',
      'vouchers': 'Vouchers',
      'pending': 'Pendiente',
      'bank_transfer': 'Transferencia',
      'paypal': 'PayPal'
    };

    return displayNames[method] || method.charAt(0).toUpperCase() + method.slice(1);
  }

  /**
   * Get icon for payment method
   */
  getPaymentMethodIcon(method: string): string {
    const icons: { [key: string]: string } = {
      'cash': 'payments',
      'card': 'credit_card',
      'online': 'language',
      'vouchers': 'card_giftcard',
      'pending': 'schedule',
      'bank_transfer': 'account_balance',
      'paypal': 'payment'
    };

    return icons[method] || 'payment';
  }

  /**
   * Get CSS class for payment method icon
   */
  getPaymentMethodIconClass(method: string): string {
    return `mat-icon ${method}-icon`;
  }

  /**
   * Get CSS class for revenue bar
   */
  getPaymentMethodBarClass(method: string): string {
    return `${method}-bar`;
  }

  /**
   * Format percentage
   */
  formatPercentage(value: number): string {
    if (!value && value !== 0) return '0%';

    return new Intl.NumberFormat('es-ES', {
      style: 'percent',
      minimumFractionDigits: 1,
      maximumFractionDigits: 1
    }).format(value / 100);
  }
}
