import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject, combineLatest, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';

export interface DashboardStats {
  todayBookings: number;
  weeklyCoursesScheduled: number;
  performanceIncrease: number;
  activeMonitors: number;
  availableMonitors: number;
  availableHours: number;
  totalRevenue: number;
  monthlyBookings: number;
  coursesCompleted: number;
}

export interface WeatherData {
  temperature: number;
  condition: string;
  windSpeed: number;
  visibility: number;
  snowDepth: number;
  icon: string;
  station_name?: string;
}

export interface WeatherStation {
  id: number;
  name: string;
  city: string;
  country: string;
  province: string;
  latitude: string;
  longitude: string;
  active: boolean;
  has_weather_data: boolean;
}

// UserInfo interface removed - user comes from AuthV5Service

@Injectable({
  providedIn: 'root'
})
export class DashboardService {
  private http = inject(HttpClient);
  private apiUrl = '/dashboard';
  
  private statsSubject = new BehaviorSubject<DashboardStats | null>(null);
  private weatherSubject = new BehaviorSubject<WeatherData | null>(null);
  
  public stats$ = this.statsSubject.asObservable();
  public weather$ = this.weatherSubject.asObservable();

  getDashboardStats(): Observable<DashboardStats> {
    return this.http.get<DashboardStats>(`${this.apiUrl}/stats`).pipe(
      catchError(error => {
        console.error('Error loading dashboard stats from API:', error);
        console.warn('Dashboard stats API not available - this is expected if backend is not running');
        // Return empty stats - no fake data
        return this.getEmptyStats();
      })
    );
  }

  getWeatherData(stationId?: number): Observable<WeatherData> {
    const url = stationId 
      ? `${this.apiUrl}/weather?station_id=${stationId}`
      : `${this.apiUrl}/weather`;
    
    return this.http.get<WeatherData>(url).pipe(
      catchError(error => {
        console.error('Error loading weather data from API:', error);
        console.warn('Weather API not available - this is expected if backend is not running');
        // Return null/empty weather data - no fake data
        return this.getEmptyWeather();
      })
    );
  }

  getWeatherStations(): Observable<{stations: WeatherStation[], default_station_id?: number}> {
    return this.http.get<{stations: WeatherStation[], default_station_id?: number}>(`${this.apiUrl}/weather-stations`).pipe(
      catchError(error => {
        console.error('Error loading weather stations from API:', error);
        console.warn('Weather stations API not available - this is expected if backend is not running');
        return of({ stations: [], default_station_id: undefined });
      })
    );
  }

  // Removed getCurrentUser - user comes from AuthV5Service now

  getRevenueChart(period: 'daily' | 'weekly' | 'monthly' = 'monthly'): Observable<{
    labels: string[];
    datasets: {
      label: string;
      data: number[];
      borderColor: string;
      backgroundColor: string;
      tension: number;
    }[];
  }> {
    return this.http.get<any>(`${this.apiUrl}/revenue-chart`, { params: { period } }).pipe(
      catchError(error => {
        console.error('Error loading revenue chart from API:', error);
        console.warn('Revenue chart API not available - this is expected if backend is not running');
        return this.getEmptyRevenueChart();
      })
    );
  }

  getBookingsByType(): Observable<{
    labels: string[];
    data: number[];
    backgroundColor: string[];
  }> {
    return this.http.get<any>(`${this.apiUrl}/bookings-by-type`).pipe(
      catchError(error => {
        console.error('Error loading bookings by type from API:', error);
        console.warn('Bookings by type API not available - this is expected if backend is not running');
        return this.getEmptyBookingsByType();
      })
    );
  }

  // Method to refresh all dashboard data
  refreshDashboard(): void {
    // Load all data in parallel (user comes from AuthV5Service)
    combineLatest([
      this.getDashboardStats(),
      this.getWeatherData()
    ]).subscribe(([stats, weather]) => {
      this.statsSubject.next(stats);
      this.weatherSubject.next(weather);
    });
  }

  // Empty data methods for fallback when API is not available
  private getEmptyStats(): Observable<DashboardStats> {
    const emptyStats: DashboardStats = {
      todayBookings: 0,
      weeklyCoursesScheduled: 0,
      performanceIncrease: 0,
      activeMonitors: 0,
      availableMonitors: 0,
      availableHours: 0,
      totalRevenue: 0,
      monthlyBookings: 0,
      coursesCompleted: 0
    };
    return new Observable(observer => {
      observer.next(emptyStats);
      observer.complete();
    });
  }

  private getEmptyWeather(): Observable<WeatherData> {
    const emptyWeather: WeatherData = {
      temperature: 0,
      condition: 'No disponible',
      windSpeed: 0,
      visibility: 0,
      snowDepth: 0,
      icon: 'unknown'
    };
    return new Observable(observer => {
      observer.next(emptyWeather);
      observer.complete();
    });
  }

  // User data removed - comes from AuthV5Service

  private getEmptyRevenueChart(): Observable<any> {
    const emptyChart = {
      labels: ['Sin datos'],
      datasets: [
        {
          label: 'Sin datos disponibles',
          data: [0],
          borderColor: '#e5e7eb',
          backgroundColor: 'transparent',
          tension: 0.4
        }
      ]
    };
    return new Observable(observer => {
      observer.next(emptyChart);
      observer.complete();
    });
  }

  private getEmptyBookingsByType(): Observable<any> {
    const emptyData = {
      labels: ['Sin datos'],
      data: [0],
      backgroundColor: ['#e5e7eb']
    };
    return new Observable(observer => {
      observer.next(emptyData);
      observer.complete();
    });
  }
}