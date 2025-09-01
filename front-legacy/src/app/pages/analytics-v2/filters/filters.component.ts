import { Component, Input, Output, EventEmitter, OnInit, OnDestroy, ChangeDetectionStrategy } from '@angular/core';
import { FormBuilder, FormGroup, FormControl } from '@angular/forms';
import { Subject, takeUntil, debounceTime, distinctUntilChanged } from 'rxjs';
import moment from 'moment';
// ==================== INTERFACES ====================

export interface FilterOptions {
  sports?: FilterOption[];
  seasons?: FilterOption[];
  sources?: FilterOption[];
  courseTypes?: FilterOption[];
  optimizationLevels?: FilterOption[];
}

export interface FilterOption {
  value: any;
  label: string;
  icon?: string;
  disabled?: boolean;
}

export interface AnalyticsFilters {
  startDate: string | null;
  endDate: string | null;
  seasonId: number | null;
  courseType: number | null;
  source: string | null;
  sportId: number | null;
  onlyWeekends: boolean;
  optimizationLevel: 'fast' | 'balanced' | 'detailed';
}

export interface DatePreset {
  label: string;
  value: string;
  startDate: () => moment.Moment;
  endDate: () => moment.Moment;
  icon?: string;
}

@Component({
  selector: 'vex-filters',
  templateUrl: './filters.component.html',
  styleUrls: ['./filters.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class FiltersComponent {
  @Input() filterOptions: FilterOptions = {};
  @Input() showAdvanced: boolean = false;
  @Input() compact: boolean = false;
  @Input() enablePresets: boolean = true;
  @Input() enableQuickActions: boolean = true;
  @Input() initialFilters?: Partial<AnalyticsFilters>;

  @Output() filtersChange = new EventEmitter<AnalyticsFilters>();
  @Output() presetsToggle = new EventEmitter<void>();
  @Output() advancedToggle = new EventEmitter<void>();
  @Output() filtersReset = new EventEmitter<void>();
  @Output() filtersApply = new EventEmitter<AnalyticsFilters>();

  // ==================== FORM ====================
  filterForm: FormGroup;

  // ==================== DATA ====================
  datePresets: DatePreset[] = [
    {
      label: 'Hoy',
      value: 'today',
      startDate: () => moment().startOf('day'),
      endDate: () => moment().endOf('day'),
      icon: 'today'
    },
    {
      label: 'Ayer',
      value: 'yesterday',
      startDate: () => moment().subtract(1, 'day').startOf('day'),
      endDate: () => moment().subtract(1, 'day').endOf('day'),
      icon: 'yesterday'
    },
    {
      label: 'Esta semana',
      value: 'this_week',
      startDate: () => moment().startOf('week'),
      endDate: () => moment().endOf('week'),
      icon: 'date_range'
    },
    {
      label: 'Semana pasada',
      value: 'last_week',
      startDate: () => moment().subtract(1, 'week').startOf('week'),
      endDate: () => moment().subtract(1, 'week').endOf('week'),
      icon: 'date_range'
    },
    {
      label: 'Este mes',
      value: 'this_month',
      startDate: () => moment().startOf('month'),
      endDate: () => moment().endOf('month'),
      icon: 'calendar_month'
    },
    {
      label: 'Mes pasado',
      value: 'last_month',
      startDate: () => moment().subtract(1, 'month').startOf('month'),
      endDate: () => moment().subtract(1, 'month').endOf('month'),
      icon: 'calendar_month'
    },
    {
      label: 'Últimos 7 días',
      value: 'last_7_days',
      startDate: () => moment().subtract(7, 'days'),
      endDate: () => moment(),
      icon: 'schedule'
    },
    {
      label: 'Últimos 30 días',
      value: 'last_30_days',
      startDate: () => moment().subtract(30, 'days'),
      endDate: () => moment(),
      icon: 'schedule'
    },
    {
      label: 'Últimos 90 días',
      value: 'last_90_days',
      startDate: () => moment().subtract(90, 'days'),
      endDate: () => moment(),
      icon: 'schedule'
    },
    {
      label: 'Este año',
      value: 'this_year',
      startDate: () => moment().startOf('year'),
      endDate: () => moment().endOf('year'),
      icon: 'calendar_today'
    }
  ];

  defaultCourseTypes: FilterOption[] = [
    { value: 1, label: 'Cursos Colectivos', icon: 'group' },
    { value: 2, label: 'Cursos Privados', icon: 'person' },
    { value: 3, label: 'Actividades', icon: 'local_activity' }
  ];

  defaultSources: FilterOption[] = [
    { value: 'web', label: 'Página Web', icon: 'language' },
    { value: 'app', label: 'Aplicación Móvil', icon: 'phone_android' },
    { value: 'phone', label: 'Teléfono', icon: 'phone' },
    { value: 'walk-in', label: 'Presencial', icon: 'store' },
    { value: 'partner', label: 'Socio/Partner', icon: 'business' }
  ];

  defaultOptimizationLevels: FilterOption[] = [
    { value: 'fast', label: 'Rápido', icon: 'speed' },
    { value: 'balanced', label: 'Balanceado', icon: 'balance' },
    { value: 'detailed', label: 'Detallado', icon: 'analytics' }
  ];

  // ==================== STATE ====================
  selectedPreset: string | null = null;
  showPresets: boolean = false;

  private destroy$ = new Subject<void>();

  // ==================== LIFECYCLE ====================

  constructor(private fb: FormBuilder) {
    this.initializeForm();
  }

  ngOnInit(): void {
    this.setupFormSubscriptions();
    this.applyInitialFilters();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ==================== INITIALIZATION ====================

  private initializeForm(): void {
    this.filterForm = this.fb.group({
      startDate: new FormControl(moment().subtract(30, 'days').format('YYYY-MM-DD')),
      endDate: new FormControl(moment().format('YYYY-MM-DD')),
      seasonId: new FormControl(null),
      courseType: new FormControl(null),
      source: new FormControl(null),
      sportId: new FormControl(null),
      onlyWeekends: new FormControl(false),
      optimizationLevel: new FormControl('balanced')
    });
  }

  private setupFormSubscriptions(): void {
    this.filterForm.valueChanges.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      takeUntil(this.destroy$)
    ).subscribe(filters => {
      this.selectedPreset = null; // Clear preset when manual changes are made
      this.filtersChange.emit(this.getFiltersValue());
    });
  }

  private applyInitialFilters(): void {
    if (this.initialFilters) {
      this.filterForm.patchValue(this.initialFilters, { emitEvent: false });
    }
  }

  // ==================== PUBLIC METHODS ====================

  onPresetSelect(preset: DatePreset): void {
    this.selectedPreset = preset.value;

    this.filterForm.patchValue({
      startDate: preset.startDate().format('YYYY-MM-DD'),
      endDate: preset.endDate().format('YYYY-MM-DD')
    });

    this.showPresets = false;
  }

  onResetFilters(): void {
    this.selectedPreset = null;
    this.filterForm.reset({
      startDate: moment().subtract(30, 'days').format('YYYY-MM-DD'),
      endDate: moment().format('YYYY-MM-DD'),
      seasonId: null,
      courseType: null,
      source: null,
      sportId: null,
      onlyWeekends: false,
      optimizationLevel: 'balanced'
    });

    this.filtersReset.emit();
  }

  onApplyFilters(): void {
    this.filtersApply.emit(this.getFiltersValue());
  }

  onTogglePresets(): void {
    this.showPresets = !this.showPresets;
    this.presetsToggle.emit();
  }

  onToggleAdvanced(): void {
    this.advancedToggle.emit();
  }

  // ==================== QUICK ACTIONS ====================

  setToday(): void {
    const today = moment();
    this.filterForm.patchValue({
      startDate: today.format('YYYY-MM-DD'),
      endDate: today.format('YYYY-MM-DD')
    });
    this.selectedPreset = 'today';
  }

  setThisWeek(): void {
    this.filterForm.patchValue({
      startDate: moment().startOf('week').format('YYYY-MM-DD'),
      endDate: moment().endOf('week').format('YYYY-MM-DD')
    });
    this.selectedPreset = 'this_week';
  }

  setThisMonth(): void {
    this.filterForm.patchValue({
      startDate: moment().startOf('month').format('YYYY-MM-DD'),
      endDate: moment().endOf('month').format('YYYY-MM-DD')
    });
    this.selectedPreset = 'this_month';
  }

  setLast30Days(): void {
    this.filterForm.patchValue({
      startDate: moment().subtract(30, 'days').format('YYYY-MM-DD'),
      endDate: moment().format('YYYY-MM-DD')
    });
    this.selectedPreset = 'last_30_days';
  }

  // ==================== GETTERS ====================

  get sports(): FilterOption[] {
    return this.filterOptions.sports || [];
  }

  get seasons(): FilterOption[] {
    return this.filterOptions.seasons || [];
  }

  get sources(): FilterOption[] {
    return this.filterOptions.sources || this.defaultSources;
  }

  get courseTypes(): FilterOption[] {
    return this.filterOptions.courseTypes || this.defaultCourseTypes;
  }

  get optimizationLevels(): FilterOption[] {
    return this.filterOptions.optimizationLevels || this.defaultOptimizationLevels;
  }

  get isWeekendOnly(): boolean {
    return this.filterForm.get('onlyWeekends')?.value || false;
  }

  get selectedDateRange(): string {
    const startDate = this.filterForm.get('startDate')?.value;
    const endDate = this.filterForm.get('endDate')?.value;

    if (!startDate || !endDate) {
      return 'Selecciona fechas';
    }

    const start = moment(startDate);
    const end = moment(endDate);

    if (start.isSame(end, 'day')) {
      return start.format('DD/MM/YYYY');
    }

    return `${start.format('DD/MM/YYYY')} - ${end.format('DD/MM/YYYY')}`;
  }

  get hasActiveFilters(): boolean {
    const filters = this.getFiltersValue();
    return !!(
      filters.seasonId ||
      filters.courseType ||
      filters.source ||
      filters.sportId ||
      filters.onlyWeekends ||
      filters.optimizationLevel !== 'balanced'
    );
  }

  // ==================== PRIVATE METHODS ====================

  private getFiltersValue(): AnalyticsFilters {
    const formValue = this.filterForm.value;

    return {
      startDate: formValue.startDate,
      endDate: formValue.endDate,
      seasonId: formValue.seasonId,
      courseType: formValue.courseType,
      source: formValue.source,
      sportId: formValue.sportId,
      onlyWeekends: formValue.onlyWeekends || false,
      optimizationLevel: formValue.optimizationLevel || 'balanced'
    };
  }

  // ==================== VALIDATION ====================

  get isDateRangeValid(): boolean {
    const startDate = this.filterForm.get('startDate')?.value;
    const endDate = this.filterForm.get('endDate')?.value;

    if (!startDate || !endDate) {
      return false;
    }

    return moment(startDate).isSameOrBefore(moment(endDate));
  }

  get dateRangeInDays(): number {
    const startDate = this.filterForm.get('startDate')?.value;
    const endDate = this.filterForm.get('endDate')?.value;

    if (!startDate || !endDate) {
      return 0;
    }

    return moment(endDate).diff(moment(startDate), 'days') + 1;
  }
}
