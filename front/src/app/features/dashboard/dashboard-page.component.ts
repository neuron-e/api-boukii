import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TranslatePipe } from '@shared/pipes/translate.pipe';
import { NgChartsModule } from 'ng2-charts';
import { Chart, ChartData, ChartOptions, registerables } from 'chart.js';
import { TranslationService } from '@core/services/translation.service';

Chart.register(...registerables);

@Component({
  selector: 'app-dashboard-page',
  standalone: true,
  imports: [CommonModule, TranslatePipe, NgChartsModule],
  template: `
    <div class="page" data-cy="dashboard">
      <div class="page-header">
        <h1>{{ 'dashboard.title' | translate }}</h1>
        <div class="subtitle">{{ 'dashboard.welcome' | translate }}</div>
      </div>

      <div class="grid grid--two">
        <div class="card">
          <h3>{{ 'dashboard.stats.title' | translate }}</h3>
          <div class="stack">
            <div>
              <div class="label">{{ 'dashboard.stats.bookings' | translate }}</div>
              <div class="kpi">247</div>
              <span class="chip chip--green">+12%</span>
            </div>
            <div>
              <div class="label">{{ 'dashboard.stats.clients' | translate }}</div>
              <div class="kpi">1,024</div>
              <span class="chip chip--blue">+8%</span>
            </div>
            <div>
              <div class="label">{{ 'dashboard.stats.courses' | translate }}</div>
              <div class="kpi">15</div>
              <span class="chip chip--yellow">{{ 'dashboard.stats.active' | translate }}</span>
            </div>
            <div>
              <div class="label">{{ 'dashboard.stats.revenue' | translate }}</div>
              <div class="kpi">€12,450</div>
              <span class="chip chip--green">{{ 'dashboard.stats.thisMonth' | translate }}</span>
            </div>
          </div>
        </div>

        <div class="card">
          <h3>{{ 'dashboard.activity.title' | translate }}</h3>
          <div class="stack">
            <div class="activity-item">
              <div class="activity-content">
                <div class="activity-title">{{ 'dashboard.activity.newClient' | translate }}</div>
                <div class="activity-subtitle">María García se registró</div>
                <div class="activity-time">2 minutos</div>
              </div>
              <span class="chip chip--green">{{ 'dashboard.activity.completed' | translate }}</span>
            </div>
            <div class="activity-item">
              <div class="activity-content">
                <div class="activity-title">{{ 'dashboard.activity.bookingConfirmed' | translate }}</div>
                <div class="activity-subtitle">Clase de surf - Playa Norte</div>
                <div class="activity-time">5 minutos</div>
              </div>
              <span class="chip chip--yellow">{{ 'dashboard.activity.confirmed' | translate }}</span>
            </div>
            <div class="activity-item">
              <div class="activity-content">
                <div class="activity-title">{{ 'dashboard.activity.courseUpdated' | translate }}</div>
                <div class="activity-subtitle">Actualizado horario de Windsurf</div>
                <div class="activity-time">15 minutos</div>
              </div>
              <span class="chip chip--blue">{{ 'dashboard.activity.updated' | translate }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid--two" style="margin-top:24px">
        <div class="card">
          <h3>{{ 'dashboard.charts.dailyAttendance' | translate }}</h3>
          <div class="chart-box">
            <canvas
              baseChart
              [data]="attendanceData"
              [options]="attendanceOptions"
              [type]="'line'">
            </canvas>
          </div>
        </div>
        <div class="card">
          <h3>{{ 'dashboard.charts.hourlyBookings' | translate }}</h3>
          <div class="chart-box">
            <canvas
              baseChart
              [data]="bookingsByHourData"
              [options]="bookingsByHourOptions"
              [type]="'bar'"
            ></canvas>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:24px">
        <h3>{{ 'dashboard.actions.title' | translate }}</h3>
        <p class="subtitle">{{ 'dashboard.actions.description' | translate }}</p>
        <div class="row gap">
          <button class="btn btn--primary">{{ 'dashboard.actions.newBooking' | translate }}</button>
          <button class="btn">{{ 'dashboard.actions.addClient' | translate }}</button>
          <button class="btn">{{ 'dashboard.actions.manageCourses' | translate }}</button>
          <button class="btn">{{ 'dashboard.actions.viewReports' | translate }}</button>
        </div>
      </div>

      <div class="grid grid--auto" style="margin-top:24px">
        <div class="card card--tight">
          <span class="chip chip--green">99.9%</span>
          <strong>{{ 'dashboard.status.operational' | translate }}</strong>
        </div>
        <div class="card card--tight">
          <span class="chip chip--yellow">3</span>
          <strong>{{ 'dashboard.status.pending' | translate }}</strong>
        </div>
        <div class="card card--tight">
          <span class="chip chip--blue">V5.1.0</span>
          <strong>{{ 'dashboard.status.available' | translate }}</strong>
        </div>
      </div>
    </div>
  `,
  styles: [
    `
      .label {
        font-size: var(--fs-12);
        color: var(--text-2);
        margin-bottom: 4px;
      }

      .activity-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
      }

      .activity-content {
        flex: 1;
      }

      .activity-title {
        font-size: var(--fs-14);
        font-weight: 600;
        color: var(--text-1);
        margin-bottom: 2px;
      }

      .activity-subtitle {
        font-size: var(--fs-12);
        color: var(--text-2);
        margin-bottom: 4px;
      }

      .activity-time {
        font-size: var(--fs-12);
        color: var(--muted);
      }

      .card--tight {
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .chart-box {
        position: relative;   /* importante para Chart.js */
        height: 260px;        /* fija una altura estable */
        width: 100%;
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
export class DashboardPageComponent {
  private readonly translationService = inject(TranslationService);
  attendanceData: ChartData<'line'> = {
    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    datasets: [
      {
        data: [20, 25, 22, 30, 28, 35, 40],
        label: this.translationService.instant('dashboard.charts.legend.attendance'),
        fill: false,
        borderColor: '#3e95cd',
        tension: 0.1,
      },
    ],
  };

  attendanceOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: { beginAtZero: true },
    },
  };

  bookingsByHourData: ChartData<'bar'> = {
    labels: ['8h', '9h', '10h', '11h', '12h', '13h', '14h'],
    datasets: [
      {
        data: [2, 4, 6, 8, 5, 3, 1],
        label: this.translationService.instant('dashboard.charts.legend.bookings'),
        backgroundColor: '#8e5ea2',
      },
    ],
  };

  bookingsByHourOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: { beginAtZero: true },
    },
  };
}
