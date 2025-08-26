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

  loadingMetrics = signal(true);
  loadingActivities = signal(true);

  reservationsData: ChartData<'line'> = {
    labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
    datasets: [
      {
        data: [10, 12, 8, 14, 9, 6, 4],
        label: 'Reservas',
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37,99,235,0.3)',
        tension: 0.4,
        fill: true,
      },
    ],
  };

  reservationsOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
  };

  courseDistributionData: ChartData<'doughnut'> = {
    labels: ['Esquí', 'Snowboard', 'Infantil'],
    datasets: [
      {
        data: [45, 30, 25],
        backgroundColor: ['#3b82f6', '#2563eb', '#93c5fd'],
      },
    ],
  };

  courseDistributionOptions: ChartOptions<'doughnut'> = {
    responsive: true,
    maintainAspectRatio: false,
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

