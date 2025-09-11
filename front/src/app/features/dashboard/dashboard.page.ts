import { Component, OnInit, OnDestroy, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { NgChartsModule } from 'ng2-charts';
import { ChartData, ChartOptions } from 'chart.js';
import { Subject, takeUntil, forkJoin } from 'rxjs';
import { DashboardService, DashboardStats, WeatherData, WeatherStation } from './services/dashboard.service';
import { AuthV5Service } from '../../core/services/auth-v5.service';

interface Metric {
  label: string;
  value: number | string;
  icon: string;
}

interface Activity {
  id: number;
  title: string;
  description: string;
  time: string;
  status: 'success' | 'warning' | 'info';
}

interface QuickLink {
  label: string;
  icon: string;
  route: string;
}

@Component({
  selector: 'app-dashboard-page',
  standalone: true,
  imports: [CommonModule, RouterLink, NgChartsModule],
  templateUrl: './dashboard.page.html',
  styleUrls: ['./dashboard.page.scss'],
})
export class DashboardPageComponent implements OnInit, OnDestroy {
  private dashboardService = inject(DashboardService);
  private authService = inject(AuthV5Service);
  private destroy$ = new Subject<void>();

  metrics = signal<Metric[]>([]);
  activities = signal<Activity[]>([]);
  quickLinks = signal<QuickLink[]>([]);
  weather = signal<WeatherData | null>(null);
  weatherStations = signal<WeatherStation[]>([]);
  selectedStationId = signal<number | null>(null);
  
  // Get user name from auth service
  userName = this.authService.user;
  currentUser = this.authService.user;

  loadingMetrics = signal(true);
  loadingActivities = signal(true);
  loadingWeather = signal(true);
  loadingStations = signal(true);

  // Sales by Channel Chart
  salesChannelData: ChartData<'line'> = {
    labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
    datasets: [
      {
        data: [40, 42, 35, 45, 38, 48, 50],
        label: 'Objetivo',
        borderColor: '#ef4444',
        backgroundColor: 'transparent',
        tension: 0.4,
        borderDash: [5, 5],
        pointRadius: 4,
        pointBackgroundColor: '#ef4444',
      },
      {
        data: [20, 25, 22, 28, 26, 32, 30],
        label: 'Ventas Admin',
        borderColor: '#8b5cf6',
        backgroundColor: 'rgba(139,92,246,0.1)',
        tension: 0.4,
        fill: true,
        pointRadius: 4,
        pointBackgroundColor: '#8b5cf6',
      },
      {
        data: [15, 18, 20, 22, 25, 28, 32],
        label: 'Ventas Online',
        borderColor: '#06b6d4',
        backgroundColor: 'rgba(6,182,212,0.1)',
        tension: 0.4,
        fill: true,
        pointRadius: 4,
        pointBackgroundColor: '#06b6d4',
      },
    ],
  };

  salesChannelOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false,
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        max: 8,
        ticks: {
          callback: function(value) {
            return '€' + value + 'k';
          }
        }
      }
    }
  };

  // Daily Sessions Chart
  dailySessionsData: ChartData<'bar'> = {
    labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
    datasets: [
      {
        data: [20, 45, 55, 65, 85, 100, 75],
        backgroundColor: ['#06b6d4', '#06b6d4', '#06b6d4', '#06b6d4', '#06b6d4', '#06b6d4', '#06b6d4'],
        borderRadius: 6,
        maxBarThickness: 24,
      },
    ],
  };

  dailySessionsOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false,
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        max: 100,
      }
    }
  };

  ngOnInit(): void {
    this.loadDashboardData();
    this.loadChartData();
    this.setupQuickLinks();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private loadDashboardData(): void {
    // Load dashboard data from API (no user data since it comes from AuthService)
    forkJoin({
      stats: this.dashboardService.getDashboardStats(),
      weather: this.dashboardService.getWeatherData()
    }).pipe(
      takeUntil(this.destroy$)
    ).subscribe({
      next: ({ stats, weather }) => {
        this.updateMetrics(stats);
        this.weather.set(weather);
        
        this.loadingMetrics.set(false);
        this.loadingWeather.set(false);
      },
      error: (error) => {
        console.error('Error loading dashboard data:', error);
        this.loadingMetrics.set(false);
        this.loadingWeather.set(false);
        
        // Si no hay datos reales, no mostrar nada falso
        this.metrics.set([]);
        this.weather.set(null);
      }
    });

    // Load activities separately (could be from different endpoint)
    this.loadActivities();
  }

  private updateMetrics(stats: DashboardStats): void {
    this.metrics.set([
      { 
        label: 'Reservas del día', 
        value: stats.todayBookings, 
        icon: 'i-calendar' 
      },
      { 
        label: 'Cursos programados', 
        value: stats.weeklyCoursesScheduled, 
        icon: 'i-academic-cap' 
      },
      { 
        label: 'Rendimiento', 
        value: `+${stats.performanceIncrease}%`, 
        icon: 'i-chart-line' 
      },
      { 
        label: 'Monitores activos', 
        value: `${stats.activeMonitors}/${stats.availableMonitors}`, 
        icon: 'i-users' 
      },
      { 
        label: 'Horas disponibles', 
        value: stats.availableHours, 
        icon: 'i-clock' 
      },
      { 
        label: 'Ingresos del período', 
        value: `€${stats.totalRevenue.toLocaleString()}`, 
        icon: 'i-banknotes' 
      }
    ]);
  }

  private loadActivities(): void {
    // TODO: Implement real activities API endpoint
    // For now, show empty state since we don't have real data
    this.activities.set([]);
    this.loadingActivities.set(false);
  }

  private loadChartData(): void {
    // Load revenue chart data
    this.dashboardService.getRevenueChart('monthly')
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (chartData) => {
          this.salesChannelData = {
            labels: chartData.labels,
            datasets: chartData.datasets
          };
        },
        error: (error) => {
          console.error('Error loading chart data:', error);
        }
      });

    // Load bookings by type for daily sessions chart
    this.dashboardService.getBookingsByType()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.dailySessionsData = {
            labels: data.labels,
            datasets: [{
              data: data.data,
              backgroundColor: data.backgroundColor,
              borderRadius: 6,
              maxBarThickness: 24,
            }]
          };
        },
        error: (error) => {
          console.error('Error loading bookings data:', error);
        }
      });
  }

  private setupQuickLinks(): void {
    this.quickLinks.set([
      { label: 'Nueva reserva', icon: 'i-plus-circle', route: '/reservations/new' },
      { label: 'Añadir cliente', icon: 'i-user-plus', route: '/clients/new' },
      { label: 'Gestionar cursos', icon: 'i-academic-cap', route: '/courses' },
      { label: 'Ver reportes', icon: 'i-chart-bar', route: '/statistics' },
    ]);
  }

  trackMetric(index: number, item: Metric) {
    return item.label;
  }

  trackActivity(index: number, item: Activity) {
    return item.id;
  }

  trackQuickLink(index: number, item: QuickLink) {
    return item.route;
  }

  getWindDescription(windSpeed?: number): string {
    if (!windSpeed) return 'Desconocido';
    if (windSpeed < 10) return 'Suave';
    if (windSpeed < 20) return 'Moderado';
    if (windSpeed < 30) return 'Fuerte';
    return 'Muy fuerte';
  }

  getVisibilityDescription(visibility?: number): string {
    if (!visibility) return 'Desconocida';
    if (visibility >= 8) return 'Excelente';
    if (visibility >= 5) return 'Buena';
    if (visibility >= 2) return 'Regular';
    return 'Mala';
  }

  getSnowDescription(snowDepth?: number): string {
    if (!snowDepth) return 'Sin datos';
    if (snowDepth >= 40) return 'Polvo fresco';
    if (snowDepth >= 20) return 'Buena base';
    if (snowDepth >= 10) return 'Base mínima';
    return 'Insuficiente';
  }
}

