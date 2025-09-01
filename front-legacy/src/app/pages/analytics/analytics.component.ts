import { AfterViewChecked, AfterViewInit, ChangeDetectorRef, Component, OnInit, OnDestroy } from '@angular/core';
import Plotly from 'plotly.js-dist-min';
import { MatTabChangeEvent } from '@angular/material/tabs';
import { MonitorsCreateUpdateComponent } from '../monitors/monitors-create-update/monitors-create-update.component';
import { TableColumn } from '../../../@vex/interfaces/table-column.interface';
import { Router } from '@angular/router';
import { ApiCrudService } from '../../../service/crud.service';
import { Observable, Subject, takeUntil } from 'rxjs';
import { TranslateService } from '@ngx-translate/core';
import moment from 'moment/moment';
import { MOCK_COUNTRIES } from '../../static-data/countries-data';
import { MOCK_PROVINCES } from '../../static-data/province-data';
import { FormBuilder, FormGroup, FormControl } from '@angular/forms';

export interface SportData {
  id: number;
  name: string;
  value: number;
  icon: string;
  color: string;
}

export interface CourseTypeData {
  name: string;
  value: number;
  max_value: number;
  color: string;
}

export interface PlotlyTextConfig {
  title: string;
  subtitle: string;
  price: string;
  subprice: string;
}

@Component({
  selector: 'vex-analytics',
  templateUrl: './analytics.component.html',
  styleUrls: ['./analytics.component.scss']
})
export class AnalyticsComponent implements AfterViewInit, AfterViewChecked, OnDestroy {
  // Controles de formulario expuestos
  fromDateControl = new FormControl(null);
  toDateControl = new FormControl(null);
  sportControl = new FormControl(null);
  onlyWeekendsControl = new FormControl(false);

  // Filtros
  filterForm: FormGroup;

  // Datos de gráficos
  courseTypeHoursData: CourseTypeData[] = [];
  sportHoursData: SportData[] = [];
  totalHours = 0;
  totalSportHours = 0;
  totalPriceSell = 0;
  totalMonitorPriceSell = 0;

  // Datos estáticos
  countries = MOCK_COUNTRIES;
  provinces = MOCK_PROVINCES;
  allSports: any[] = [];
  currency = '';

  // Estado UI
  tabActive = 'general';
  showDetail = false;
  areGraphsVisible = true;
  private resizePending = false;

  // Datos de usuario y monitores
  user: any;
  filter = '';
  monitor = null;
  imageAvatar = '../../../assets/img/avatar.png';
  icon = '../../../assets/img/icons/monitores.svg';
  selectedId = null;

  // Configuración de componentes
  createComponent = MonitorsCreateUpdateComponent;
  entity = '/admin/statistics/bookings/monitors';
  entitySales = '/admin/statistics/bookings/sells';
  deleteEntity = '/monitors';

  // Control de destrucción de componente
  private destroy$ = new Subject<void>();

  // Columnas de tabla
  columns: TableColumn<any>[] = [
    { label: 'monitor', property: 'monitor', type: 'monitor', visible: true, cssClasses: ['font-medium'] },
    { label: 'sport', property: 'sport', type: 'sport', visible: true, cssClasses: ['font-medium'] },
    { label: 'hours_collective', property: 'hours_collective', type: 'text', visible: true },
    { label: 'hours_private', property: 'hours_private', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'hours_activities', property: 'hours_activities', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'hours_nwd_payed', property: 'hours_nwd_payed', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'base_price', property: 'hour_price', type: 'price', visible: true },
    { label: 'total_hours', property: 'total_hours', type: 'text', visible: true },
    { label: 'total', property: 'total_cost', type: 'price', visible: true },
  ];

  columnsSales: TableColumn<any>[] = [
    // 🔷 Curso
    { label: 'type', property: 'icon', type: 'booking_users_image', visible: true },
    { label: 'name', property: 'name', type: 'text', visible: true, cssClasses: ['font-medium'] },

    // 🪑 Plazas
    { label: 'availability', property: 'available_places', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'sold', property: 'booked_places', type: 'text', visible: true },
    { label: 'no_paid', property: 'no_paid', type: 'text', visible: true, cssClasses: ['font-medium'] },

    // 💳 Métodos de pago
    { label: 'cash', property: 'cash', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'other', property: 'other', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'T.Boukii', property: 'boukii', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'T.Boukii Web', property: 'boukii_web', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'Link', property: 'online', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'vouchers', property: 'vouchers', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'refunds', property: 'refunds', type: 'price', visible: true, cssClasses: ['font-medium'] },

    // 🧭 Origen
    { label: 'admin', property: 'admin', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'web', property: 'web', type: 'text', visible: true, cssClasses: ['font-medium'] },

    // ➕ Extras y seguros
    { label: 'extras', property: 'extras', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'bookings_page.cancelations.refund', property: 'insurance', type: 'price', visible: true, cssClasses: ['font-medium'] },
/*

    // ⚠️ Discrepancias
    { label: 'pending_payment', property: 'underpaid_count', type: 'price', visible: true, cssClasses: ['font-bold', 'text-red-600'] },
*/

    // 💰 Total
    { label: 'total', property: 'total_cost', type: 'price', visible: true },
    { label: 'expected', property: 'total_cost_expected', type: 'price', visible: true },
    { label: 'difference', property: 'difference_vs_expected', type: 'price', visible: true },
  ];

  columnsDetail: TableColumn<any>[] = [
    { label: 'date', property: 'date', type: 'date', visible: true, cssClasses: ['font-medium'] },
    { label: 'sport', property: 'sport', type: 'sport', visible: true, cssClasses: ['font-medium'] },
    { label: 'hours_collective', property: 'hours_collective', type: 'text', visible: true },
    { label: 'hours_private', property: 'hours_private', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'hours_activities', property: 'hours_activities', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'hours_nwd_payed', property: 'hours_nwd_payed', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'base_price', property: 'hour_price', type: 'price', visible: true },
    { label: 'total_hours', property: 'total_hours', type: 'text', visible: true },
    { label: 'total', property: 'total_cost', type: 'price', visible: true }
  ];

  constructor(
    private crudService: ApiCrudService,
    private router: Router,
    public translateService: TranslateService,
    private cdr: ChangeDetectorRef,
    private fb: FormBuilder
  ) {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.initFilterForm();
    this.initCourseTypeData();
  }

  private initFilterForm(): void {
    // Inicializar el formulario con los controles ya creados
    this.filterForm = this.fb.group({
      fromDate: this.fromDateControl,
      toDate: this.toDateControl,
      sport: this.sportControl,
      onlyWeekends: this.onlyWeekendsControl
    });

    // Reaccionar a cambios en el formulario
    this.filterForm.valueChanges
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => this.filterData());
  }

  private initCourseTypeData(): void {
    this.courseTypeHoursData = [
      { name: this.translateService.instant('course_colective'), value: 0, max_value: 0, color: '#ff5733' },
      { name: this.translateService.instant('course_private'), value: 0, max_value: 0, color: '#33c7ff' },
      { name: this.translateService.instant('activity'), value: 0, max_value: 0, color: '#ff33b8' }
    ];
  }

  ngAfterViewInit(): void {
    this.initVoucherChart();
    this.loadInitialData();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  ngAfterViewChecked(): void {
    if (this.resizePending && this.areGraphsVisible) {
      this.resizePending = false;
      this.resizeAllCharts();
    }
  }

  private initVoucherChart(): void {
    const voucherText: PlotlyTextConfig = {
      'title': this.translateService.instant('gift_vouchers'),
      'subtitle': this.translateService.instant('sales'),
      'price': '0 CHF',
      'subprice': this.translateService.instant('occupation'),
    };
    this.setPlotly('#E91E63', voucherText, 'voucher', 0, 0);
  }

  private loadInitialData(): void {
    this.getBookingsByDate().subscribe(res => {
      this.setUserSessionAnalytics(false, res.data);
    });

    this.getBookingsByDateSport().subscribe(res => {
      this.updateChartBySport(res.data);
    });

    this.getSchoolSports().subscribe(res => {
      this.allSports = res.data;
      const settings = typeof this.user.schools[0].settings === 'string' ?
        JSON.parse(this.user.schools[0].settings) : this.user.schools[0].settings;
      this.currency = settings?.taxes?.currency;
      this.reloadData(false);
    });
  }

  // Métodos para manejar eventos UI
  onTabChange(event: MatTabChangeEvent): void {
    switch (event.index) {
      case 0: // Vista General
        this.tabActive = 'general';
        this.filter = '';
        this.showDetail = false;
        this.reloadData(false);
        this.getBookingsByDate().subscribe(res => {
          this.setUserSessionAnalytics(false, res.data);
        });
        break;

      case 1: // Ventas
        this.filter = '';
        this.tabActive = 'sells';
        this.showDetail = false;
        this.reloadData(false);
        break;

      case 2: // Monitores
        this.tabActive = 'monitors';
        this.reloadData(true);
        this.getBookingsByDate().subscribe(res => {
          this.setUserSessionAnalytics(true, res.data);
        });
        break;
    }
  }

  toggleGraphsVisibility(): void {
    this.areGraphsVisible = !this.areGraphsVisible;
    this.resizePending = true;
    this.cdr.detectChanges();
  }

  showDetailEvent(event: any): void {
    this.showDetail = true;
    this.selectedId = event.item.id;
    this.getMonitor().subscribe(res => {
      this.monitor = res.data;
      this.filter += '&monitor_id=' + this.selectedId;
      this.initCourseTypeData();
      this.reloadData(true);
    });
  }

  closeDetailEvent(event: any): void {
    this.showDetail = false;
    this.selectedId = null;
    this.reloadData(true);
  }

  filterData(): void {
    this.reloadData(this.tabActive === 'monitors');
  }

  // Métodos para obtener datos
  getSchoolSports(): Observable<any> {
    return this.crudService.list('/school-sports', 1, 10000, 'desc', 'id',
      '&school_id=' + this.user.schools[0].id, '', null, null, ['sport']);
  }

  getBookingsTotalByType(type: number): Observable<any> {
    return this.crudService.list('/admin/statistics/bookings', 1, 10000,
      'desc', 'id', '&school_id=' + this.user.schools[0].id + '&type=' + type + this.filter);
  }

  getBookingsTotal(): Observable<any> {
    return this.crudService.list('/admin/statistics/total', 1, 10000,
      'desc', 'id', '&school_id=' + this.user.schools[0].id + this.filter);
  }

  getMonitorsTotal(): Observable<any> {
    return this.crudService.list('/admin/statistics/monitors/total', 1, 10000,
      'desc', 'id', '&school_id=' + this.user.schools[0].id + this.filter);
  }

  getBookingsByDate(): Observable<any> {
    return this.crudService.list('/admin/statistics/bookings/dates', 1, 10000,
      'desc', 'id', '&school_id=' + this.user.schools[0].id + this.filter);
  }

  getBookingsByDateSport(): Observable<any> {
    return this.crudService.list('/admin/statistics/bookings/sports', 1, 10000,
      'desc', 'id', '&school_id=' + this.user.schools[0].id + this.filter);
  }

  getActiveMonitors(): Observable<any> {
    return this.crudService.list('/admin/statistics/bookings/monitors/active', 1, 10000,
      'desc', 'id', '&school_id=' + this.user.schools[0].id + this.filter);
  }

  getTotalHours(): Observable<any> {
    return this.crudService.list('/admin/statistics/bookings/monitors/hours', 1, 10000,
      'desc', 'id', '&school_id=' + this.user.schools[0].id + this.filter);
  }

  getTotalHoursBySport(): Observable<any> {
    return this.crudService.list('/admin/statistics/bookings/monitors/sports', 1, 10000,
      'desc', 'id', '&school_id=' + this.user.schools[0].id + this.filter);
  }

  getMonitor(): Observable<any> {
    return this.crudService.get('/monitors/' + this.selectedId, ['language1']);
  }

  // Método principal para recargar datos
  reloadData(isMonitorsTab: boolean): void {
    this.buildFilterString();
    this.resetDataValues();

    // Obtener datos comunes
    this.getBookingsTotal().subscribe(res => {
      this.totalPriceSell = res.data;
    });

    // Obtener datos por tipo de curso
    this.loadCourseTypeData(isMonitorsTab);

    // Obtener datos de deportes
    this.getTotalHoursBySport().subscribe(res => {
      this.processSportData(res.data);
    });

    // Cargar datos específicos según la pestaña
    if (this.tabActive === 'sells') {
      this.loadSellData();
    } else if (this.tabActive === 'monitors') {
      this.getMonitorsTotal().subscribe(res => {
        this.totalMonitorPriceSell = res.data;
      });
      this.loadMonitorsData(isMonitorsTab);
    } else {
      this.loadGeneralTabData(isMonitorsTab);
    }

    this.cdr.detectChanges();
  }

  private buildFilterString(): void {
    let filter = '';

    if (this.fromDateControl.value) {
      filter += '&start_date=' + moment(this.fromDateControl.value).format('YYYY-MM-DD');
    }

    if (this.toDateControl.value) {
      filter += '&end_date=' + moment(this.toDateControl.value).format('YYYY-MM-DD');
    }

    if (this.sportControl.value) {
      filter += '&sport_id=' + this.sportControl.value;
    }

    if (this.onlyWeekendsControl.value) {
      filter += '&onlyWeekends=true';
    }

    if (this.selectedId) {
      filter += '&monitor_id=' + this.selectedId;
    }

    this.filter = filter;
  }

  private resetDataValues(): void {
    this.totalPriceSell = 0;
    this.initCourseTypeData();
  }

  private loadCourseTypeData(isMonitorsTab: boolean): void {
    // Cargar datos para cursos colectivos (tipo 1)
    this.getBookingsTotalByType(1).subscribe(res => {
      const collectiveText: PlotlyTextConfig = {
        'title': this.translateService.instant('course_colective'),
        'subtitle': this.translateService.instant('sales'),
        'price': `${res.data.total_price}${this.currency}`,
        'subprice': this.translateService.instant('occupation'),
      };

      this.updateCourseValue(0, res.data.total_reservations_hours, res.data.total_hours);

      if (!isMonitorsTab) {
        this.setPlotly('#FA9917', collectiveText, 'collective',
          res.data.total_reservations_places, res.data.total_available_places);
      }
    });

    // Cargar datos para cursos privados (tipo 2)
    this.getBookingsTotalByType(2).subscribe(res => {
      const privateText: PlotlyTextConfig = {
        'title': this.translateService.instant('course_private'),
        'subtitle': this.translateService.instant('sales'),
        'price': `${res.data.total_price}${this.currency}`,
        'subprice': this.translateService.instant('occupation'),
      };

      this.updateCourseValue(1, res.data.total_reservations_hours, res.data.total_hours);

      if (!isMonitorsTab) {
        this.setPlotly('#2AC940', privateText, 'prive',
          res.data.total_reservations_places, res.data.total_places);
      }
    });

    // Cargar datos para actividades (tipo 3)
    this.getBookingsTotalByType(3).subscribe(res => {
      const activityText: PlotlyTextConfig = {
        'title': this.translateService.instant('activity'),
        'subtitle': this.translateService.instant('sales'),
        'price': `${res.data.total_price}${this.currency}`,
        'subprice': this.translateService.instant('occupation'),
      };

      this.updateCourseValue(2, res.data.total_reservations_hours, res.data.total_hours);

      if (!isMonitorsTab) {
        this.setPlotly('#9747FF', activityText, 'activity',
          res.data.total_reservations_places, res.data.total_places);
      }
    });
  }

  private loadGeneralTabData(isMonitorsTab: boolean): void {
    if (!this.showDetail) {
      this.getActiveMonitors().subscribe(res => {
        const monitorsText: PlotlyTextConfig = {
          'title': this.translateService.instant('monitors'),
          'subtitle': this.translateService.instant('sales'),
          'price': res.data.busy + '/' + res.data.total,
          'subprice': this.translateService.instant('occupation'),
        };

        const chartId = isMonitorsTab ? 'activeMonitorsFiltered' : 'activeMonitors';
        const chartElement = document.getElementById(chartId);

        if (chartElement) {
          this.setPlotly('#0547ED', monitorsText, chartId, res.data.busy, res.data.total);
        }
      });
    }

    this.getTotalHours().subscribe(res => {
      const hoursText: PlotlyTextConfig = {
        'title': this.translateService.instant('hours_worked'),
        'subtitle': this.translateService.instant('hours_worked_total'),
        'price': res.data.totalWorkedHours + 'h',
        'subprice': this.translateService.instant('hours_worked') + '/' + this.translateService.instant('blocks'),
      };

      const chartId = isMonitorsTab ? 'totalHoursFiltered' : 'totalHours';
      const chartElement = document.getElementById(chartId);

      if (chartElement) {
        this.setPlotly('#3A57A7', hoursText, chartId,
          res.data.totalNwdHours + res.data.totalBookingHours, res.data.totalMonitorHours);
      }
    });
  }

  private loadMonitorsData(isMonitorsTab: boolean): void {
    // Implementación específica para la pestaña de monitores
    this.loadGeneralTabData(isMonitorsTab);
  }

  loadSellData(): void {
    // Esta función está vacía en el código original, por lo que se mantiene así
    // En futuras actualizaciones se podría implementar
  }

  // Métodos auxiliares
  updateCourseValue(index: number, newValue: number, newMaxValue: number = 0): void {
    this.courseTypeHoursData[index].value = newValue;
    this.courseTypeHoursData[index].max_value = newMaxValue;
    this.updateTotalHours();

    // Crear una nueva referencia al array para la detección de cambios
    this.courseTypeHoursData = [...this.courseTypeHoursData];
    this.cdr.detectChanges();
  }

  updateTotalHours(): void {
    this.totalHours = this.courseTypeHoursData.reduce((acc, course) => acc + course.max_value, 0);
  }

  processSportData(data: any): void {
    this.totalSportHours = 0;
    this.sportHoursData = Object.keys(data).map(sportId => {
      const sportData = data[sportId];
      this.totalSportHours += sportData.hours;
      return {
        id: sportData.sport.id,
        name: sportData.sport.name,
        value: sportData.hours,
        icon: sportData.sport.icon_selected,
        color: '#FCB859'
      };
    });
  }

  getPercentage(value: number, maxValue: number): number {
    if (maxValue === 0) {
      return 0;
    }
    return (value / maxValue) * 100;
  }

  calculateAge(birthDateString: string): number {
    if (birthDateString && birthDateString !== null) {
      const today = new Date();
      const birthDate = new Date(birthDateString);
      let age = today.getFullYear() - birthDate.getFullYear();
      const m = today.getMonth() - birthDate.getMonth();

      if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
      }

      return age;
    } else {
      return 0;
    }
  }

  getCountry(id: any): string {
    const country = this.countries.find((c) => c.id == +id);
    return country ? country.name : 'NDF';
  }

  resizeAllCharts(): void {
    const chartIds = [
      'collectiveSales', 'priveSales', 'activitySales', 'voucher', 'voucherSales', 'user-session-analytics',
      'activeMonitorsFiltered', 'totalHoursFiltered', 'user-session-analytics2', 'collectiveSales', 'privateSales'
    ];

    chartIds.forEach(id => {
      const chartElement = document.getElementById(id);
      if (chartElement) {
        Plotly.Plots.resize(chartElement);
      }
    });
  }

  // Métodos de visualización
  setPlotly(color: string, text: PlotlyTextConfig, id: string, value: number, maxValue: number): void {
    // Calcular el porcentaje
    let percent: number = (value / maxValue) * 100;

    if (isNaN(percent)) {
      percent = 0;
    }

    const data = [{
      domain: { x: [0, 1], y: [0, 1] },
      value: percent,
      title: { text: "Medidor Semi Circular" },
      type: "indicator",
      mode: "gauge",
      gauge: {
        shape: "angular",
        bar: {
          color: color,
          thickness: 1
        },
        axis: {
          range: [null, 100],
          showticklabels: false,
          ticks: "",
          showline: true,
          linecolor: "black",
          linewidth: 1
        },
        steps: [
          { range: [0, percent], color: color },
          { range: [percent, 100], color: "lightgrey" }
        ],
        bgcolor: "rgba(0,0,0,0)",
        borderwidth: 0,
        bordercolor: "black"
      }
    }];

    const layout = {
      height: window.innerWidth > 1440 ? 250 : 350,
      margin: { t: 20, b: 10, l: window.innerWidth > 1440 ? 65 : 50, r: window.innerWidth > 1339 ? 50 : 50 },
      paper_bgcolor: "rgba(0,0,0,0)",
      plot_bgcolor: "rgba(0,0,0,0)",
      annotations: [
        {
          text: percent.toFixed(2) + "%",
          font: {
            family: "Dinamit, sans-serif",
            weight: 500,
            size: 25,
            color: color
          },
          showarrow: false,
          x: .5,
          y: .4,
          xref: 'paper',
          yref: 'paper',
          xanchor: 'center',
          yanchor: 'middle',
          align: 'center',
          pad: { t: 10, r: 10, b: 10, l: 10 }
        },
        {
          text: text.title,
          font: {
            family: "Dinamit, sans-serif",
            weight: 700,
            size: 17,
            color: color
          },
          showarrow: false,
          x: -.13,
          y: .96,
          xref: 'paper',
          yref: 'paper',
          xanchor: 'left',
          yanchor: 'bottom',
          align: 'left',
          pad: { t: 10, r: 10, b: 10, l: 10 }
        },
        {
          text: text.subtitle,
          font: {
            family: "Dinamit, sans-serif",
            weight: 400,
            size: 10,
            color: '#87888C'
          },
          showarrow: false,
          x: -.13,
          y: .94,
          xref: 'paper',
          yref: 'paper',
          xanchor: 'left',
          yanchor: 'top',
          align: 'left',
          pad: { t: 10, r: 10, b: 10, l: 10 }
        },
        {
          text: text.price,
          font: {
            family: "Dinamit, sans-serif",
            weight: 700,
            size: 20,
            color: '#424242'
          },
          showarrow: false,
          x: -.13,
          y: .865,
          xref: 'paper',
          yref: 'paper',
          xanchor: 'left',
          yanchor: 'top',
          align: 'left',
          pad: { t: 10, r: 10, b: 10, l: 10 }
        },
        {
          text: text.subprice,
          font: {
            family: "Dinamit, sans-serif",
            size: 11,
            weight: 400,
            color: '#87888C'
          },
          showarrow: false,
          x: -.13,
          y: .76,
          xref: 'paper',
          yref: 'paper',
          xanchor: 'left',
          yanchor: 'top',
          align: 'left',
          pad: { t: 10, r: 10, b: 10, l: 10 }
        }
      ]
    };

    const config = {
      displayModeBar: false,
      displaylogo: false,
      responsive: true,
      modeBarButtonsToRemove: ['pan2d', 'lasso2d']
    };

    Plotly.newPlot(id, data, layout, config);

    window.onresize = function() {
      Plotly.Plots.resize(document.getElementById(id));
    };
  }

  setUserSessionAnalytics(monitors: boolean, data: any): void {
    const dates = Object.keys(data);
    const courseType1 = dates.map(date => data[date][1] || 0);
    const courseType2 = dates.map(date => data[date][2] || 0);
    const courseType3 = dates.map(date => data[date][3] || 0);

    const traces = [
      {
        x: dates,
        y: courseType1,
        mode: 'lines+markers',
        name: this.translateService.instant('course_colective'),
        line: { color: '#FAC710' }
      },
      {
        x: dates,
        y: courseType2,
        mode: 'lines+markers',
        name: this.translateService.instant('course_private'),
        line: { color: '#8FD14F' }
      },
      {
        x: dates,
        y: courseType3,
        mode: 'lines+markers',
        name: this.translateService.instant('activity'),
        line: { color: '#00beff' }
      }
    ];

    const layout = {
      title: this.translateService.instant('hours_by_type'),
      xaxis: {
        title: this.translateService.instant('dates'),
        type: 'date',
        tickformat: '%Y-%m',
      },
      yaxis: {
        title: this.translateService.instant('total'),
      },
      paper_bgcolor: 'rgba(0,0,0,0)',
      plot_bgcolor: 'rgba(0,0,0,0)',
      showlegend: true,
      height: 250
    };

    let chartId = 'user-session-analytics';
    if (this.tabActive === 'monitors') {
      chartId = 'user-session-analytics4';
    } else if (this.tabActive === 'sells') {
      chartId = 'user-session-analytics-sales';
    }

    Plotly.newPlot(chartId, traces, layout);
  }

  updateChartBySport(data: any, id: string = 'hours_by_sport'): void {
    const dates = Object.keys(data);
    const sports: Record<string, number[]> = {};

    // Organizar los datos por deporte
    dates.forEach(date => {
      const dayData = data[date];
      Object.keys(dayData).forEach(sport => {
        if (!sports[sport]) {
          sports[sport] = Array(dates.indexOf(date)).fill(0);
        }

        // Asegurarse de que el array tiene suficientes elementos
        while (sports[sport].length < dates.indexOf(date)) {
          sports[sport].push(0);
        }

        sports[sport].push(dayData[sport]);
      });

      // Si algún deporte no tiene entrada para esta fecha, agregar 0
      Object.keys(sports).forEach(sport => {
        if (!dayData[sport]) {
          sports[sport].push(0);
        }
      });
    });

    const traces = Object.keys(sports).map(sport => ({
      x: dates,
      y: sports[sport],
      mode: 'lines+markers',
      name: sport,
    }));

    const layout = {
      title: this.translateService.instant(id),
      xaxis: {
        title: this.translateService.instant('date'),
        type: 'date',
        tickformat: '%Y-%m',
      },
      yaxis: {
        title: this.translateService.instant('total'),
      },
      paper_bgcolor: 'rgba(0,0,0,0)',
      plot_bgcolor: 'rgba(0,0,0,0)',
      showlegend: true,
    };

    Plotly.newPlot('user-session-analytics2', traces, layout);
  }
}
