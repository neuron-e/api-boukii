import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NgChartsModule } from 'ng2-charts';
import { Chart, ChartData, ChartOptions, registerables } from 'chart.js';

Chart.register(...registerables);

@Component({
  selector: 'app-statistics',
  standalone: true,
  imports: [CommonModule, NgChartsModule],
  template: `
    <div class="page">
      <div class="page-header">
        <h1>Estadísticas</h1>
        <div class="subtitle">Resumen de KPIs</div>
      </div>

      <div class="kpi-grid">
        <div class="card" *ngFor="let kpi of kpis">
          <div class="kpi-label">{{ kpi.label }}</div>
          <div class="kpi-value">{{ kpi.value }}</div>
        </div>
      </div>

      <div class="charts-grid">
        <div class="card">
          <h3>Ingresos en el tiempo</h3>
          <canvas
            baseChart
            [data]="revenueData"
            [options]="revenueOptions"
            [type]="'line'"
          ></canvas>
        </div>
        <div class="card">
          <h3>Rendimiento de cursos</h3>
          <canvas
            baseChart
            [data]="coursesData"
            [options]="coursesOptions"
            [type]="'bar'"
          ></canvas>
        </div>
        <div class="card">
          <h3>Carga de monitores</h3>
          <canvas
            baseChart
            [data]="monitorsData"
            [options]="monitorsOptions"
            [type]="'doughnut'"
          ></canvas>
        </div>
      </div>
    </div>
  `,
  styles: [
    `
      .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
      }

      .kpi-label {
        font-size: var(--fs-14);
        color: var(--text-2);
      }

      .kpi-value {
        font-size: var(--fs-24);
        font-weight: 600;
        color: var(--text-1);
      }

      .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
      }

      h3 {
        font-size: var(--fs-18);
        font-weight: 600;
        color: var(--text-1);
        margin: 0 0 16px 0;
      }
    `,
  ],
})
export class StatisticsComponent {
  kpis = [
    { label: 'Reservas Totales', value: 350 },
    { label: 'Ingresos', value: '€12,500' },
    { label: 'Tasa de Cancelación', value: '5%' },
  ];

  revenueData: ChartData<'line'> = {
    labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
    datasets: [
      {
        data: [500, 700, 400, 900, 1200, 800],
        label: 'Ingresos',
        fill: false,
        borderColor: '#3b82f6',
        tension: 0.1,
      },
    ],
  };
  revenueOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
  };

  coursesData: ChartData<'bar'> = {
    labels: ['Surf', 'Yoga', 'Paddle', 'Windsurf'],
    datasets: [
      {
        data: [65, 59, 80, 81],
        label: 'Rendimiento',
        backgroundColor: '#00d4aa',
      },
    ],
  };
  coursesOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: { beginAtZero: true },
    },
  };

  monitorsData: ChartData<'doughnut'> = {
    labels: ['Ana', 'Luis', 'Pedro', 'Marta'],
    datasets: [
      {
        data: [5, 3, 4, 2],
        backgroundColor: ['#00d4aa', '#3b82f6', '#f59e0b', '#ef4444'],
      },
    ],
  };
  monitorsOptions: ChartOptions<'doughnut'> = {
    responsive: true,
    maintainAspectRatio: false,
  };
}

