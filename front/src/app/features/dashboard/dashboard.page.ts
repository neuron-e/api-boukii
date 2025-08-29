import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { NgChartsModule } from 'ng2-charts';
import { ChartData, ChartOptions } from 'chart.js';

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
export class DashboardPageComponent implements OnInit {
  metrics = signal<Metric[]>([]);
  activities = signal<Activity[]>([]);
  quickLinks = signal<QuickLink[]>([]);
  weather = signal<{ temp: string; condition: string } | null>(null);
  userName = signal<string>('Carlos');

  loadingMetrics = signal(true);
  loadingActivities = signal(true);

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
    setTimeout(() => {
      this.metrics.set([
        { label: 'Reservas del día', value: 24, icon: 'i-calendar' },
        { label: 'Clientes activos', value: 102, icon: 'i-users' },
        { label: 'Cursos programados', value: 7, icon: 'i-academic-cap' },
        { label: 'Ingresos del período', value: '€5,300', icon: 'i-banknotes' },
      ]);
      this.loadingMetrics.set(false);
    }, 800);

    setTimeout(() => {
      this.activities.set([
        {
          id: 1,
          title: 'Nueva reserva',
          description: 'Clase de esquí para Ana',
          time: 'hace 2 min',
          status: 'success',
        },
        {
          id: 2,
          title: 'Pago pendiente',
          description: 'Reserva #1234',
          time: 'hace 10 min',
          status: 'warning',
        },
        {
          id: 3,
          title: 'Curso actualizado',
          description: 'Snowboard avanzado',
          time: 'hace 1 h',
          status: 'info',
        },
      ]);
      this.loadingActivities.set(false);
    }, 1000);

    this.quickLinks.set([
      { label: 'Nueva reserva', icon: 'i-plus-circle', route: '/reservations/new' },
      { label: 'Añadir cliente', icon: 'i-user-plus', route: '/clients/new' },
      { label: 'Gestionar cursos', icon: 'i-academic-cap', route: '/courses' },
      { label: 'Ver reportes', icon: 'i-chart-bar', route: '/statistics' },
    ]);

    this.weather.set({ temp: '2°C', condition: 'Nublado' });
    
    // Set user name - in real app this would come from auth service
    this.userName.set('Carlos');
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
}

