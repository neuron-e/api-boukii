// =============== MONITORS LEGACY COMPONENT ===============
// Archivo: src/app/components/analytics/monitors-legacy/monitors-legacy.component.ts

import { Component, Input, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { FormGroup } from '@angular/forms';
import { ApiCrudService } from '../../../../service/crud.service';
import { TranslateService } from '@ngx-translate/core';
import { Subject, takeUntil } from 'rxjs';
import Plotly from 'plotly.js-dist-min';

interface MonitorLegacyData {
  monitor: string;
  sport: string;
  hours_collective: number;
  hours_private: number;
  hours_activities: number;
  hours_nwd_payed: number;
  hour_price: number;
  total_hours: number;
  total_cost: number;
  monitor_id?: number; // Añadido para el detalle
}

interface MonitorDetailData {
  id: number;
  date: string;
  first_name: string;
  hours_collective: string;
  hours_private: string;
  hours_activities: string;
  hours_nwd: string;
  hours_nwd_payed: string;
  cost_collective: number;
  cost_private: number;
  cost_activities: number;
  cost_nwd: number;
  total_cost: number;
  total_hours: string;
  hour_price: number;
  sport: {
    id: number;
    name: string;
    icon_prive: string;
  };
  image?: string;
  currency: string;
}

interface MonitorKPIs {
  totalWorkedHours: number;
  totalNwdHours: number;
  totalBookingHours: number;
  totalMonitorHours: number;
  busy: number;
  total: number;
  totalPriceSell: number;
}

@Component({
  selector: 'app-monitors-legacy',
  template: `
    <!-- ==================== LOADING STATE ==================== -->
    <div *ngIf="loading" class="loading-container">
      <mat-card class="loading-card">
        <mat-card-content class="loading-content">
          <mat-spinner diameter="40"></mat-spinner>
          <h3>Cargando análisis de monitores...</h3>
          <p>Procesando datos de horas trabajadas</p>
        </mat-card-content>
      </mat-card>
    </div>

    <!-- ==================== MAIN CONTENT ==================== -->
    <div *ngIf="!loading" class="monitors-legacy-container">

      <!-- ==================== KPIs SUMMARY ==================== -->
      <div class="kpis-grid">

        <!-- Monitores Activos -->
        <mat-card class="kpi-card">
          <mat-card-content>
            <div class="kpi-content">
              <div class="kpi-info">
                <div class="kpi-value">{{ kpis.busy }}/{{ kpis.total }}</div>
                <div class="kpi-label">Monitores Activos</div>
                <div class="kpi-change">
                  <span>{{ getOccupancyPercentage() }}% ocupación</span>
                </div>
              </div>
              <mat-icon class="kpi-icon">people</mat-icon>
            </div>
          </mat-card-content>
        </mat-card>

        <!-- Total Horas Trabajadas -->
        <mat-card class="kpi-card">
          <mat-card-content>
            <div class="kpi-content">
              <div class="kpi-info">
                <div class="kpi-value">{{ kpis.totalWorkedHours | number:'1.0-2' }}h</div>
                <div class="kpi-label">Horas Trabajadas</div>
                <div class="kpi-change">
                  <span>{{ getEfficiencyPercentage() }}% eficiencia</span>
                </div>
              </div>
              <mat-icon class="kpi-icon">schedule</mat-icon>
            </div>
          </mat-card-content>
        </mat-card>

        <!-- Ingresos por Monitores -->
        <mat-card class="kpi-card">
          <mat-card-content>
            <div class="kpi-content">
              <div class="kpi-info">
                <div class="kpi-value">{{ formatCurrency(getTotalFilteredCost()) }}</div>
                <div class="kpi-label">Ingresos Monitores</div>
                <div class="kpi-change">
                  <span>{{ getAverageHourlyRate() + this.currency}}/hora promedio</span>
                </div>
              </div>
              <mat-icon class="kpi-icon">monetization_on</mat-icon>
            </div>
          </mat-card-content>
        </mat-card>

        <!-- Distribución de Horas -->
        <mat-card class="kpi-card">
          <mat-card-content>
            <div class="kpi-content">
              <div class="kpi-info">
                <div class="kpi-value">{{ getTotalBookingHours() }}h</div>
                <div class="kpi-label">Horas Cursos</div>
                <div class="kpi-change">
                  <span>+{{ kpis.totalNwdHours | number:'1.0-2'}}h bloqueos</span>
                </div>
              </div>
              <mat-icon class="kpi-icon">school</mat-icon>
            </div>
          </mat-card-content>
        </mat-card>

      </div>

      <!-- ==================== CHARTS SECTION ==================== -->
      <div class="charts-grid">

        <!-- Distribución de Horas por Tipo -->
        <mat-card class="chart-card">
          <mat-card-header>
            <mat-card-title>Distribución de Horas por Tipo</mat-card-title>
            <mat-card-subtitle>Análisis temporal de actividades</mat-card-subtitle>
          </mat-card-header>
          <mat-card-content>
            <div id="hoursTypeChart" class="chart-container"></div>
          </mat-card-content>
        </mat-card>

        <!-- Horas por Deporte -->
        <mat-card class="chart-card">
          <mat-card-header>
            <mat-card-title>Horas por Deporte</mat-card-title>
            <mat-card-subtitle>Tendencias por especialidad</mat-card-subtitle>
          </mat-card-header>
          <mat-card-content>
            <div id="hoursSportChart" class="chart-container"></div>
          </mat-card-content>
        </mat-card>

      </div>

      <!-- ==================== DETAILED TABLE ==================== -->
      <mat-card class="table-card">
        <mat-card-header>
          <mat-card-title>Análisis Detallado por Monitor</mat-card-title>
          <mat-card-subtitle>{{ monitorsData.length }} monitores analizados</mat-card-subtitle>
        </mat-card-header>
        <mat-card-content>

          <!-- Table Filters -->
          <div class="table-filters">
            <mat-form-field appearance="outline" class="filter-field">
              <mat-label>Buscar monitor</mat-label>
              <input matInput [(ngModel)]="searchFilter" (input)="applyTableFilter()">
              <mat-icon matSuffix>search</mat-icon>
            </mat-form-field>

            <mat-form-field appearance="outline" class="filter-field">
              <mat-label>Filtrar por deporte</mat-label>
              <mat-select [(ngModel)]="sportFilter" (selectionChange)="applyTableFilter()">
                <mat-option value="">Todos los deportes</mat-option>
                <mat-option *ngFor="let sport of availableSports" [value]="sport">
                  {{ sport }}
                </mat-option>
              </mat-select>
            </mat-form-field>
          </div>

          <!-- Data Table -->
          <table mat-table [dataSource]="filteredMonitorsData" class="monitors-table" matSort *ngIf="!selectedMonitor">

            <!-- Monitor Column -->
            <ng-container matColumnDef="monitor">
              <th mat-header-cell *matHeaderCellDef mat-sort-header>Monitor</th>
              <td mat-cell *matCellDef="let row" class="monitor-cell">
                <div class="monitor-info">
                  <span class="monitor-name">{{ row.monitor }}</span>
                </div>
              </td>
            </ng-container>

            <!-- Sport Column -->
            <ng-container matColumnDef="sport">
              <th mat-header-cell *matHeaderCellDef mat-sort-header>Deporte</th>
              <td mat-cell *matCellDef="let row">
                <mat-chip class="sport-chip">{{ row.sport }}</mat-chip>
              </td>
            </ng-container>

            <!-- Hours Collective -->
            <ng-container matColumnDef="hours_collective">
              <th mat-header-cell *matHeaderCellDef mat-sort-header>H. Colectivas</th>
              <td mat-cell *matCellDef="let row">{{ row.hours_collective }}h</td>
            </ng-container>

            <!-- Hours Private -->
            <ng-container matColumnDef="hours_private">
              <th mat-header-cell *matHeaderCellDef mat-sort-header>H. Privadas</th>
              <td mat-cell *matCellDef="let row">{{ row.hours_private }}h</td>
            </ng-container>

            <!-- Hours Activities -->
            <ng-container matColumnDef="hours_activities">
              <th mat-header-cell *matHeaderCellDef mat-sort-header>H. Actividades</th>
              <td mat-cell *matCellDef="let row">{{ row.hours_activities }}h</td>
            </ng-container>

            <!-- Hours NWD -->
            <ng-container matColumnDef="hours_nwd_payed">
              <th mat-header-cell *matHeaderCellDef mat-sort-header>H. Bloqueos</th>
              <td mat-cell *matCellDef="let row">{{ row.hours_nwd_payed }}h</td>
            </ng-container>

            <!-- Hour Price -->
            <ng-container matColumnDef="hour_price">
              <th mat-header-cell *matHeaderCellDef mat-sort-header>Precio/Hora</th>
              <td mat-cell *matCellDef="let row">{{ formatCurrency(row.hour_price) }}</td>
            </ng-container>

            <!-- Total Hours -->
            <ng-container matColumnDef="total_hours">
              <th mat-header-cell *matHeaderCellDef mat-sort-header>Total Horas</th>
              <td mat-cell *matCellDef="let row" class="total-hours-cell">
                <strong>{{ row.total_hours }}h</strong>
              </td>
            </ng-container>

            <!-- Total Cost -->
            <ng-container matColumnDef="total_cost">
              <th mat-header-cell *matHeaderCellDef mat-sort-header>Costo Total</th>
              <td mat-cell *matCellDef="let row" class="total-cost-cell">
                <strong>{{ formatCurrency(row.total_cost) }}</strong>
              </td>
            </ng-container>

            <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
            <tr (click)="selectMonitor(row)" mat-row *matRowDef="let row; columns: displayedColumns;" class="monitor-row"></tr>

          </table>

          <!-- Table Footer -->
          <div class="table-footer" *ngIf="filteredMonitorsData.length > 0">
            <div class="footer-stats">
              <span>{{ filteredMonitorsData.length }} monitores</span>
              <span>•</span>
              <span>{{ getTotalFilteredHours() }}h totales</span>
              <span>•</span>
              <span>{{ formatCurrency(getTotalFilteredCost()) }} costo total</span>
            </div>
          </div>

        </mat-card-content>
      </mat-card>

      <!-- ==================== MONITOR DETAIL SECTION ==================== -->
      <mat-card *ngIf="selectedMonitor && monitorDetailData.length > 0" class="detail-card">
        <mat-card-header>
          <div class="detail-header">
            <mat-card-title>
              <mat-icon>person</mat-icon>
              Detalle de {{ selectedMonitor.monitor }}
            </mat-card-title>
            <mat-card-subtitle>
              {{ monitorDetailData.length }} días trabajados
            </mat-card-subtitle>
            <button mat-icon-button (click)="closeDetail()" class="close-button">
              <mat-icon>close</mat-icon>
            </button>
          </div>
        </mat-card-header>
        <mat-card-content>

          <!-- Loading Detail -->
          <div *ngIf="loadingDetail" class="detail-loading">
            <mat-spinner diameter="30"></mat-spinner>
            <span>Cargando detalle del monitor...</span>
          </div>

          <!-- Detail Content -->
          <div *ngIf="!loadingDetail" class="detail-content">

            <!-- Detail KPIs -->
            <div class="detail-kpis">
              <div class="detail-kpi">
                <div class="detail-kpi-value">{{ getDetailTotalHours() }}</div>
                <div class="detail-kpi-label">Total Horas</div>
              </div>
              <div class="detail-kpi">
                <div class="detail-kpi-value">{{ formatCurrency(getDetailTotalCost()) }}</div>
                <div class="detail-kpi-label">Total Ingresos</div>
              </div>
              <div class="detail-kpi">
                <div class="detail-kpi-value">{{ getDetailAverageHourlyRate() }}€/h</div>
                <div class="detail-kpi-label">Tarifa Promedio</div>
              </div>
              <div class="detail-kpi">
                <div class="detail-kpi-value">{{ monitorDetailData.length }}</div>
                <div class="detail-kpi-label">Días Activos</div>
              </div>
            </div>

            <!-- Detail Table -->
            <table mat-table [dataSource]="monitorDetailData" class="detail-table" matSort>

              <!-- Date Column -->
              <ng-container matColumnDef="date">
                <th mat-header-cell *matHeaderCellDef mat-sort-header>Fecha</th>
                <td mat-cell *matCellDef="let row">
                  {{ formatDate(row.date) }}
                </td>
              </ng-container>

              <!-- Sport Column -->
              <ng-container matColumnDef="sport">
                <th mat-header-cell *matHeaderCellDef>Deporte</th>
                <td mat-cell *matCellDef="let row">
                  <div class="sport-info">
                    <img [src]="row.sport?.icon_prive" [alt]="row.sport?.name" class="sport-icon">
                    <span>{{ row.sport?.name }}</span>
                  </div>
                </td>
              </ng-container>

              <!-- Collective Hours -->
              <ng-container matColumnDef="hours_collective">
                <th mat-header-cell *matHeaderCellDef mat-sort-header>H. Colectivas</th>
                <td mat-cell *matCellDef="let row">{{ row.hours_collective }}</td>
              </ng-container>

              <!-- Private Hours -->
              <ng-container matColumnDef="hours_private">
                <th mat-header-cell *matHeaderCellDef mat-sort-header>H. Privadas</th>
                <td mat-cell *matCellDef="let row">{{ row.hours_private }}</td>
              </ng-container>

              <!-- Activities Hours -->
              <ng-container matColumnDef="hours_activities">
                <th mat-header-cell *matHeaderCellDef mat-sort-header>H. Actividades</th>
                <td mat-cell *matCellDef="let row">{{ row.hours_activities }}</td>
              </ng-container>

              <!-- NWD Hours -->
              <ng-container matColumnDef="hours_nwd_payed">
                <th mat-header-cell *matHeaderCellDef mat-sort-header>H. Bloqueos</th>
                <td mat-cell *matCellDef="let row">{{ row.hours_nwd_payed }}</td>
              </ng-container>

              <!-- Total Hours -->
              <ng-container matColumnDef="total_hours">
                <th mat-header-cell *matHeaderCellDef mat-sort-header>Total Horas</th>
                <td mat-cell *matCellDef="let row" class="total-hours-cell">
                  <strong>{{ row.total_hours }}</strong>
                </td>
              </ng-container>

              <!-- Hour Price -->
              <ng-container matColumnDef="hour_price">
                <th mat-header-cell *matHeaderCellDef mat-sort-header>Precio/Hora</th>
                <td mat-cell *matCellDef="let row">
                  {{ formatCurrency(row.hour_price) }}
                </td>
              </ng-container>

              <!-- Total Cost -->
              <ng-container matColumnDef="total_cost">
                <th mat-header-cell *matHeaderCellDef mat-sort-header>Total</th>
                <td mat-cell *matCellDef="let row" class="total-cost-cell">
                  <strong>{{ formatCurrency(row.total_cost) }}</strong>
                </td>
              </ng-container>

              <tr mat-header-row *matHeaderRowDef="detailColumns"></tr>
              <tr mat-row *matRowDef="let row; columns: detailColumns;" class="detail-row"></tr>

            </table>

          </div>

        </mat-card-content>
      </mat-card>

    </div>
  `,
  styleUrls: ['./monitors-legacy.component.scss']
})
export class MonitorsLegacyComponent implements OnInit, OnDestroy {

  // ==================== INPUTS ====================
  @Input() filterForm!: FormGroup;
  @Input() user: any;
  @Input() currency: string = 'CHF';

  // ==================== COMPONENT STATE ====================
  loading = false;
  loadingDetail = false;
  private destroy$ = new Subject<void>();

  // ==================== DATA PROPERTIES ====================
  monitorsData: MonitorLegacyData[] = [];
  selectedMonitor: MonitorLegacyData | null = null;
  monitorDetailData: MonitorDetailData[] = [];

  // Nuevas propiedades para almacenar las respuestas de los endpoints
  private monitorBookingsResponse: any[] = [];
  private monitorHoursResponse: any = {};
  private monitorActiveResponse: any = {};
  private monitorTotalPriceResponse: any = {};
  filteredMonitorsData: MonitorLegacyData[] = [];
  kpis: MonitorKPIs = {
    totalWorkedHours: 0,
    totalNwdHours: 0,
    totalBookingHours: 0,
    totalMonitorHours: 0,
    busy: 0,
    total: 0,
    totalPriceSell: 0
  };

  // ==================== COURSE TYPE COLORS CONFIGURATION ====================

  private readonly courseTypeColors = {
    1: '#FAC710', // Colectivo - Amarillo/Dorado
    2: '#8FD14F', // Privado - Verde
    3: '#00beff'  // Actividad - Azul
  };

  // ==================== TABLE CONFIGURATION ====================
  displayedColumns: string[] = [
    'monitor', 'sport', 'hours_collective', 'hours_private',
    'hours_activities', 'hours_nwd_payed', 'hour_price',
    'total_hours', 'total_cost'
  ];

  detailColumns: string[] = [
    'date', 'sport', 'hours_collective', 'hours_private',
    'hours_activities', 'hours_nwd_payed', 'total_hours',
    'hour_price', 'total_cost'
  ];

  // ==================== FILTERS ====================
  searchFilter = '';
  sportFilter = '';
  availableSports: string[] = [];

  // ==================== CHART DATA ====================
  hoursTypeData: any = {};
  hoursSportData: any = {};

  constructor(
    private crudService: ApiCrudService,
    private translateService: TranslateService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.initializeComponent();
    this.subscribeToFilterChanges();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ==================== INITIALIZATION ====================

  private initializeComponent(): void {
    this.loadAllMonitorData();
  }

  // ==================== MONITOR SELECTION & DETAIL ====================

  async selectMonitor(monitor: MonitorLegacyData): Promise<void> {
    this.selectedMonitor = monitor;
    this.loadingDetail = true;
    this.monitorDetailData = [];

    try {
      await this.loadMonitorDetail(monitor.monitor_id!);
    } catch (error) {
      console.error('Error loading monitor detail:', error);
    } finally {
      this.loadingDetail = false;
      this.cdr.detectChanges();
    }
  }

  closeDetail(): void {
    this.selectedMonitor = null;
    this.monitorDetailData = [];
  }

  private async loadMonitorDetail(monitorId: number): Promise<void> {
    const params = this.buildDetailQueryParams(monitorId);

    try {
      const response = await this.crudService.list(
        `/admin/statistics/bookings/monitors/${monitorId}`,
        1, 99999, 'desc', 'id',
        params
      ).toPromise();

      if (response.success && response.data) {
        this.monitorDetailData = response.data;
      }
    } catch (error) {
      console.error('Error loading monitor detail:', error);
      this.monitorDetailData = [];
    }
  }

  private buildDetailQueryParams(monitorId: number): string {
    const formValue = this.filterForm?.value || {};
    let params = `&finished=1&school_active=1&school_id=${this.user?.schools?.[0]?.id || 1}&monitor_id=${monitorId}`;

    if (formValue.startDate) {
      params += `&start_date=${formValue.startDate}`;
    }
    if (formValue.endDate) {
      params += `&end_date=${formValue.endDate}`;
    }

    // Añadir los with parameters
    params += '&with[]=sports&with[]=monitorsSchools&with[]=monitorsSchools&with[]=monitorSportsDegrees.monitorSportAuthorizedDegrees.degree';
    params += '&exclude=';

    return params;
  }

  private subscribeToFilterChanges(): void {
    this.filterForm?.valueChanges
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.loadAllMonitorData();
        // Si hay un monitor seleccionado, recargar su detalle
        if (this.selectedMonitor && this.selectedMonitor.monitor_id) {
          this.selectMonitor(this.selectedMonitor);
        }
      });
  }

  /**
   * Formatear fechas con nombres de meses traducidos
   */
  private formatDateWithMonthName(dateString: string): string {
    const date = new Date(dateString);
    const month = date.toLocaleString('default', { month: 'long' }).toLowerCase();
    const year = date.getFullYear();

    // Traducir el nombre del mes
    const translatedMonth = this.translateService.instant(`months.${month}`);

    return `${translatedMonth} ${year}`;
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
      tickangle: -45,
    };
  }

  // ==================== DATA LOADING (USING LEGACY ENDPOINTS) ====================

  private async loadAllMonitorData():  Promise<void> {
    this.loading = true;

    try {
      const [
        bookings,
        active,
        hours,
        total,
        byDate,
        _unused,
        bySport
      ] = await Promise.all([
        this.loadMonitorsBookings(),
        this.loadActiveMonitors(),
        this.loadTotalHours(),
        this.loadBookingsTotal(),
        this.loadBookingsByDate(),
        this.loadHoursByDate(),
        this.loadHoursBySport()
      ]);

      this.monitorBookingsResponse = bookings.data;
      this.monitorActiveResponse = active.data;
      this.monitorHoursResponse = hours.data;
      this.monitorTotalPriceResponse = total.data;
      this.hoursTypeData = byDate.data;
      this.hoursSportData = this.transformSportData(bySport.data);

      this.processAllData();
      this.createCharts();
      this.loading = false;
      this.cdr.detectChanges();
    } catch(error) {
      console.error('Error loading monitor data:', error);
      this.loading = false;
    } finally {
      this.loading = false;
    }
  }

  private processMonitorsData(): void {
    this.monitorsData = this.monitorBookingsResponse.map(m => ({
      monitor: `${m.first_name}`,
      sport: m.sport_name || 'Todos los deportes',
      hours_collective: m.hours_collective || 0,
      hours_private: m.hours_private || 0,
      hours_activities: m.hours_activities || 0,
      hours_nwd_payed: m.hours_nwd_payed || 0,
      hour_price: m.hour_price || 0,
      total_hours: m.total_hours || 0,
      total_cost: m.total_cost || 0,
      monitor_id: m.id // Importante: guardamos el ID para el detalle
    }));
  }

  private processKPIs(): void {
    this.kpis = {
      totalWorkedHours: this.monitorHoursResponse.totalWorkedHours || 0,
      totalNwdHours: this.monitorHoursResponse.totalNwdHours || 0,
      totalBookingHours: this.monitorHoursResponse.totalBookingHours || 0,
      totalMonitorHours: this.monitorHoursResponse.totalMonitorHours || 0,
      busy: this.monitorActiveResponse.busy || 0,
      total: this.monitorActiveResponse.total || 0,
      totalPriceSell: this.monitorTotalPriceResponse || 0
    };
  }

  private transformSportData(data: any): Record<string, Record<string, number>> {
    const result: Record<string, Record<string, number>> = {};
    Object.values(data).forEach((entry: any) => {
      const sport = entry.sport?.name || 'Otro';
      const date = entry.date || 'Sin fecha';
      const hours = entry.hours || 0;

      if (!result[date]) result[date] = {};
      result[date][sport] = hours;
    });
    return result;
  }

  private loadMonitorsBookings(): Promise<any> {
    return this.crudService.list(
      '/admin/statistics/bookings/monitors',
      1, 10000, 'desc', 'id',
      this.buildQueryParams()
    ).toPromise();
  }

  private loadActiveMonitors(): Promise<any> {
    return this.crudService.list(
      '/admin/statistics/bookings/monitors/active',
      1, 10000, 'desc', 'id',
      this.buildQueryParams()
    ).toPromise();
  }

  private loadTotalHours(): Promise<any> {
    return this.crudService.list(
      '/admin/statistics/bookings/monitors/hours',
      1, 10000, 'desc', 'id',
      this.buildQueryParams()
    ).toPromise();
  }

  private loadBookingsTotal(): Promise<any> {
    return this.crudService.list(
      '/admin/statistics/total',
      1, 10000, 'desc', 'id',
      this.buildQueryParams()
    ).toPromise();
  }

  private loadBookingsByDate(): Promise<any> {
    return this.crudService.list(
      '/admin/statistics/bookings/dates',
      1, 10000, 'desc', 'id',
      this.buildQueryParams()
    ).toPromise();
  }

  private loadHoursByDate(): Promise<any> {
    return this.crudService.list(
      '/admin/statistics/bookings/dates',
      1, 10000, 'desc', 'id',
      this.buildQueryParams() + '&group_by=course_type'
    ).toPromise();
  }

  private loadHoursBySport(): Promise<any> {
    return this.crudService.list(
      '/admin/statistics/bookings/monitors/sports',
      1, 10000, 'desc', 'id',
      this.buildQueryParams()
    ).toPromise();
  }

  // ==================== DATA PROCESSING ====================

  private processAllData(): void {
    // Procesar datos de la tabla de monitores
    this.processMonitorsData();

    // Procesar KPIs
    this.processKPIs();

    // Procesar datos de deportes disponibles
    this.processAvailableSports();

    // Aplicar filtros iniciales
    this.applyTableFilter();
  }

  private processAvailableSports(): void {
    this.availableSports = [...new Set(this.monitorsData.map(m => m.sport))];
  }

  /**
   * 🎨 Obtener color por tipo de curso
   */
  private getCourseTypeColor(courseType: number): string {
    return this.courseTypeColors[courseType] || '#3A57A7';
  }

  // ==================== UTILITY METHODS ====================

  private buildQueryParams(): string {
    const formValue = this.filterForm?.value || {};
    let params = `&school_id=${this.user?.schools?.[0]?.id || 1}`;

    if (formValue.startDate) {
      params += `&start_date=${formValue.startDate}`;
    }
    if (formValue.endDate) {
      params += `&end_date=${formValue.endDate}`;
    }
    if (formValue.sportId) {
      params += `&sport_id=${formValue.sportId}`;
    }

    return params;
  }

  // ==================== CALCULATIONS ====================

  getOccupancyPercentage(): number {
    return this.kpis.total > 0 ? Math.round((this.kpis.busy / this.kpis.total) * 100) : 0;
  }

  getEfficiencyPercentage(): number {
    return this.kpis.totalMonitorHours > 0 ?
      Math.round((this.kpis.totalWorkedHours / this.kpis.totalMonitorHours) * 100) : 0;
  }

/*  getAverageHourlyRate(): number {
    return this.kpis.totalWorkedHours > 0 ?
      Math.round(this.getTotalFilteredCost() / this.kpis.totalWorkedHours) : 0;
  }*/

  getAverageHourlyRate(): string {
    let totalMinutes = 0;
    let totalCost = 0;

    for (const monitor of this.filteredMonitorsData) {
      totalMinutes += this.parseHoursToMinutes(monitor.total_hours?.toString() ?? '0h 00m');
      totalCost += monitor.total_cost || 0;
    }

    const totalHours = totalMinutes / 60;
    const rate = totalHours > 0 ? totalCost / totalHours : 0;

    return `${rate.toFixed(2)}`;
  }

  getTotalBookingHours(): string {
    const totalMinutes = this.monitorsData.reduce((sum, m) => {
      const collective = this.parseHoursToMinutes(m.hours_collective?.toString() ?? '0h 00m');
      const privateH = this.parseHoursToMinutes(m.hours_private?.toString() ?? '0h 00m');
      const activities = this.parseHoursToMinutes(m.hours_activities?.toString() ?? '0h 00m');
      return sum + collective + privateH + activities;
    }, 0);

    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${hours}h ${minutes.toString().padStart(2, '0')}m`;
  }

  getTotalFilteredHours(): string {
    const totalMinutes = this.filteredMonitorsData.reduce((sum, m) => {
      return sum + this.parseHoursToMinutes(m.total_hours?.toString() ?? '0h 00m');
    }, 0);

    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${hours}h ${minutes.toString().padStart(2, '0')}m`;
  }

  getTotalFilteredCost(): number {
    return this.filteredMonitorsData.reduce((sum, m) => sum + m.total_cost, 0);
  }

  // ==================== DETAIL CALCULATIONS ====================

  getDetailTotalHours(): string {
    const totalMinutes = this.monitorDetailData.reduce((sum, day) => {
      return sum + this.parseHoursToMinutes(day.total_hours);
    }, 0);

    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${hours}h ${minutes.toString().padStart(2, '0')}m`;
  }

  getDetailTotalCost(): number {
    return this.monitorDetailData.reduce((sum, day) => sum + day.total_cost, 0);
  }

  getDetailAverageHourlyRate(): number {
    const totalCost = this.getDetailTotalCost();
    const totalMinutes = this.monitorDetailData.reduce((sum, day) => {
      return sum + this.parseHoursToMinutes(day.total_hours);
    }, 0);

    if (totalMinutes === 0) return 0;

    const totalHours = totalMinutes / 60;
    return Math.round(totalCost / totalHours);
  }

  private parseHoursToMinutes(hoursString: string): number {
    if (!hoursString) return 0;

    const match = hoursString.match(/(\d+)h\s*(\d+)m/);
    if (match) {
      const hours = parseInt(match[1], 10);
      const minutes = parseInt(match[2], 10);
      return hours * 60 + minutes;
    }

    return 0;
  }

  formatCurrency(amount: number): string {
    return `${amount.toFixed(2)} ${this.currency}`;
  }

  formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  }

  // ==================== TABLE FILTERING ====================

  applyTableFilter(): void {
    this.filteredMonitorsData = this.monitorsData.filter(monitor => {
      const matchesSearch = !this.searchFilter ||
        monitor.monitor.toLowerCase().includes(this.searchFilter.toLowerCase());

      const matchesSport = !this.sportFilter ||
        monitor.sport === this.sportFilter;

      return matchesSearch && matchesSport;
    });
  }

  // ==================== CHART CREATION ====================

  private createCharts(): void {
    setTimeout(() => {
      this.createHoursTypeChart();
      this.createHoursSportChart();
    }, 100);
  }

  private createHoursTypeChart(): void {
    const dates = Object.keys(this.hoursTypeData);

    const traces = [
      {
        x: dates,
        y: dates.map(date => this.hoursTypeData[date]?.[1] || 0),
        mode: 'lines+markers',
        name: 'Cursos Colectivos',
        line: { color: this.courseTypeColors[1] } // ✅ COLOR CONSISTENTE
      },
      {
        x: dates,
        y: dates.map(date => this.hoursTypeData[date]?.[2] || 0),
        mode: 'lines+markers',
        name: 'Cursos Privados',
        line: { color: this.courseTypeColors[2] } // ✅ COLOR CONSISTENTE
      },
      {
        x: dates,
        y: dates.map(date => this.hoursTypeData[date]?.[3] || 0),
        mode: 'lines+markers',
        name: 'Actividades',
        line: { color: this.courseTypeColors[3] } // ✅ COLOR CONSISTENTE
      }
    ];

    const layout = {
      title: 'Distribución de Horas por Tipo de Curso',
      xaxis: this.createTranslatedXAxisConfig(dates),
      yaxis: { title: 'Horas' },
      paper_bgcolor: 'rgba(0,0,0,0)',
      plot_bgcolor: 'rgba(0,0,0,0)',
      showlegend: true,
      height: 300
    };

    Plotly.newPlot('hoursTypeChart', traces, layout, { displayModeBar: false });
  }

  private createHoursSportChart(): void {
    const dates = Object.keys(this.hoursSportData);
    const sports: Record<string, number[]> = {};

    // Organizar datos por deporte
    dates.forEach(date => {
      const dayData = this.hoursSportData[date];
      Object.keys(dayData).forEach(sport => {
        if (!sports[sport]) {
          sports[sport] = [];
        }
        sports[sport].push(dayData[sport]);
      });
    });

    const traces = Object.keys(sports).map(sport => ({
      x: dates,
      y: sports[sport],
      mode: 'lines+markers',
      name: sport
    }));

    const layout = {
      title: 'Horas Trabajadas por Deporte',
      xaxis: this.createTranslatedXAxisConfig(dates), // ✅ CAMBIO PRINCIPAL
      yaxis: { title: 'Horas' },
      paper_bgcolor: 'rgba(0,0,0,0)',
      plot_bgcolor: 'rgba(0,0,0,0)',
      showlegend: true,
      height: 300
    };

    Plotly.newPlot('hoursSportChart', traces, layout, { displayModeBar: false });
  }
}
