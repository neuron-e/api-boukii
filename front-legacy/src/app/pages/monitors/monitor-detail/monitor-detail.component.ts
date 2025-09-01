import { ChangeDetectorRef, Component, ViewChild } from '@angular/core';
import { FormArray, FormControl, UntypedFormBuilder, UntypedFormGroup, Validators } from '@angular/forms';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTable, _MatTableDataSource } from '@angular/material/table';
import { ActivatedRoute, Router } from '@angular/router';
import { Observable, map, startWith, retry, catchError, of, forkJoin, tap } from 'rxjs';
import { fadeInRight400ms } from 'src/@vex/animations/fade-in-right.animation';
import { scaleIn400ms } from 'src/@vex/animations/scale-in.animation';
import { stagger20ms } from 'src/@vex/animations/stagger.animation';
import { MOCK_COUNTRIES } from 'src/app/static-data/countries-data';
import { LEVELS } from 'src/app/static-data/level-data';
import { MOCK_PROVINCES } from 'src/app/static-data/province-data';
import { ApiCrudService } from 'src/service/crud.service';
import * as moment from 'moment';
import { MatDialog } from '@angular/material/dialog';
import { ConfirmModalComponent } from './confirm-dialog/confirm-dialog.component';
import { TableColumn } from 'src/@vex/interfaces/table-column.interface';
import { CalendarEditComponent } from './calendar/calendar-edit/calendar-edit.component';
import { CourseDetailModalComponent } from '../../courses/course-detail-modal/course-detail-modal.component';
import { addDays, getDay, startOfWeek, endOfWeek, addWeeks, subWeeks, format, isSameMonth, startOfMonth, endOfMonth, addMonths, subMonths, max, min } from 'date-fns';
import { fadeInUp400ms } from 'src/@vex/animations/fade-in-up.animation';
import { BookingDetailModalComponent } from '../../bookings/booking-detail-modal/booking-detail-modal.component';
import { CourseUserTransferTimelineComponent } from '../../timeline/course-user-transfer-timeline/course-user-transfer-timeline.component';
import { TranslateService } from '@ngx-translate/core';
import { DateAdapter } from '@angular/material/core';
import { switchMap } from 'rxjs/operators';
@Component({
  selector: 'vex-monitor-detail',
  templateUrl: './monitor-detail.component.html',
  styleUrls: ['./monitor-detail.component.scss'],
  animations: [fadeInRight400ms, scaleIn400ms, stagger20ms, fadeInUp400ms]
})
export class MonitorDetailComponent {
  @ViewChild('sportsCurrentTable') currentSportsTable: MatTable<any>;


  showDetail: boolean = false;
  detailData: any;

  entity = '/booking-users';
  columns: TableColumn<any>[] = [
    { label: 'Id', property: 'id', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'type', property: 'booking', type: 'booking_users_image_monitors', visible: true },
    { label: 'course', property: 'course', type: 'course_type_data', visible: true },
    { label: 'client', property: 'client', type: 'client', visible: true },
    { label: 'register', property: 'created_at', type: 'date', visible: true },
    //{ label: 'Options', property: 'options', type: 'text', visible: true },
    { label: 'bonus', property: 'bonus', type: 'light', visible: true },
    //{ label: 'OP. Rem', property: 'has_cancellation_insurance', type: 'light_data', visible: true },
    //{ label: 'B. Care', property: 'has_boukii_care', type: 'light_data', visible: true },
    { label: 'price', property: 'price', type: 'price', visible: true },
    //{ label: 'M. Paiment', property: 'payment_method', type: 'text', visible: true },
    //{ label: 'Status', property: 'paid', type: 'payment_status_data', visible: true },
    //{ label: 'Status 2', property: 'cancelation', type: 'cancelation_status', visible: true },
    { label: 'Actions', property: 'actions', type: 'button', visible: true }
  ];

  showInfo = true;
  showPersonalInfo = true;
  showWorkInfo = true;
  showCivilStatusInfo = true;
  showSportInfo = true;
  showWork = true;
  showAddressInfo = true;
  showFamilyInfo = true;

  editInfo = false;
  editFamilyInfo = false;
  editPersonalInfo = false;
  editWorkInfo = false;
  editCivilStatusInfo = false;
  editSportInfo = false;
  editAddressInfo = false;
  editWork = false;

  selectedTabPreviewIndex = 0;
  imagePreviewUrl: string | ArrayBuffer;
  formInfoAccount: UntypedFormGroup;
  formPersonalInfo: UntypedFormGroup;
  formWorkInfo: UntypedFormGroup;
  formCivilStatusInfo: UntypedFormGroup;
  formSportInfo: UntypedFormGroup;
  myControlStations = new FormControl();
  myControlCountries = new FormControl();
  myControlWorkCountries = new FormControl();
  myControlProvinces = new FormControl();
  myControlCivilStatus = new FormControl();
  levelForm = new FormControl();
  languagesControl = new FormControl([]);

  filteredStations: Observable<any[]>;
  filteredCountries: Observable<any[]>;
  filteredWorkCountries: Observable<any[]>;
  filteredProvinces: Observable<any[]>;
  filteredCivilStatus: Observable<string[]>;
  filteredLevel: Observable<any[]>;
  filteredLanguages: Observable<any[]>;
  filteredSalary: Observable<any[]>;

  salaryForm = new FormControl();
  currency = '';
  sportsControl = new FormControl([]);
  selectedSports: any[] = [];
  selectedNewSports: any[] = [];
  filteredSports: Observable<any[]>;
  sportsData = new _MatTableDataSource([]);
  sportsCurrentData = new _MatTableDataSource([]);
  deletedItems = [];

  selectedLanguages = [];
  monitorSportsDegree = [];
  salaryData = [];
  authorizedLevels = [];
  clients = [];

  today: Date;
  minDate: Date;
  minDateChild: Date;
  childrenData = new _MatTableDataSource([]);

  displayedCurrentColumns: string[] = ['delete', 'name', 'level', 'salary', 'auth'];
  displayedColumns: string[] = ['name', 'level', 'salary', 'auth'];
  displayedColumnsChildren: string[] = ['name', 'date'];
  mockCivilStatus: string[] = ['Single', 'Mariée', 'Veuf', 'Divorcé'];
  mockCountriesData = MOCK_COUNTRIES;
  mockWorkCountriesData = MOCK_COUNTRIES;
  mockProvincesData = MOCK_PROVINCES;
  mockLevelData = LEVELS;
  languages = [];
  authorisedLevels = [];

  defaults = {
    email: null,
    username: null,
    first_name: null,
    last_name: null,
    birth_date: null,
    phone: null,
    telephone: null,
    address: null,
    cp: null,
    city: null,
    province: null,
    country: null,
    image: null,
    avs: null,
    work_license: null,
    bank_details: null,
    children: null,
    civil_status: null,
    family_allowance: null,
    partner_work_license: null,
    partner_works: null,
    language1_id: null,
    language2_id: null,
    language3_id: null,
    language4_id: null,
    language5_id: null,
    language6_id: null,
    partner_percentaje: null,
    user_id: null,
    active_station: null,
    world_country: null
  }

  defaultsUser = {
    username: null,
    email: null,
    password: null,
    image: null,
    type: 'monitor',
    active: false,
  }

  id: any;
  maxSelection = 6;
  selectedTabIndex = 0;
  loading: boolean = true;
  editing: boolean = false;
  user: any;
  schoolSports: any[] = [];
  stations: any = [];

  groupedByColor = {};
  colorKeys: string[] = []; // Aquí almacenaremos las claves de colores

  inputType = 'password';
  visible = false;

  //Planificador
  hoursRange: string[];
  hoursRangeMinusLast: string[];
  hoursRangeMinutes: string[];
  activeSchool: any = null;
  sports: any[] = [];
  degrees: any[] = [];
  showDetailTimeline: boolean = false;
  idDetailTimeline: any;
  taskDetailTimeline: any;
  showBlockTimeline: boolean = false;
  idBlockTimeline: any;
  blockDetailTimeline: any;

  showEditBlock: boolean = false;
  allHoursDay: boolean = false;
  startTimeDay: string;
  endTimeDay: string;
  nameBlockDay: string;
  divideDay: boolean = false;
  startTimeDivision: string;
  endTimeDivision: string;

  monitorSchoolRel: any;
  plannerTasks: any[] = [];
  vacationDays: any[];
  tasksCalendarStyle: any[];
  filteredTasks: any[];
  currentDate = new Date();
  timelineView: string = 'day';
  currentWeek: string = '';
  weekDays: string[] = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
  currentMonth: string = '';
  weeksInMonth: any[] = [];

  searchDate: any;

  constructor(private fb: UntypedFormBuilder, private cdr: ChangeDetectorRef, private crudService: ApiCrudService, private snackbar: MatSnackBar, private router: Router,
    private activatedRoute: ActivatedRoute, private dialog: MatDialog, public translateService: TranslateService,
    private dateAdapter: DateAdapter<Date>) {
    this.dateAdapter.setLocale(this.translateService.getDefaultLang());
    this.dateAdapter.getFirstDayOfWeek = () => { return 1; }
    this.mockLevelData.forEach(level => {
      if (!this.groupedByColor[level.color]) {
        this.groupedByColor[level.color] = [];
      }
      this.groupedByColor[level.color].push(level);
    });

    this.colorKeys = Object.keys(this.groupedByColor);
  }

  ngOnInit() {
    this.activatedRoute.params.pipe(
      switchMap(params => {
        this.getInitialData().pipe(
          switchMap(() => this.getData())
        ).subscribe(() => {
          // Aquí puedes realizar cualquier lógica adicional después de obtener los datos iniciales y los datos principales.
        });
        return this.activatedRoute.queryParams; // Para obtener los parámetros de consulta (pestaña)
      })
    ).subscribe(queryParams => {
      if (queryParams['tab'] === 'timeline') {
        this.selectedTabPreviewIndex = 2; // Establecer el índice de la pestaña deseada
      }
      // Aquí puedes realizar cualquier lógica adicional después de obtener los parámetros
    });
  }

  getInitialData() {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.id = this.activatedRoute.snapshot.params.id;

    const requestsInitial = {
      languages: this.getLanguages().pipe(retry(3), catchError(error => {
        console.error('Error fetching languages:', error);
        return of([]); // Devuelve un array vacío en caso de error
      })),
      stations: this.getStations().pipe(retry(3), catchError(error => {
        console.error('Error fetching stations:', error);
        return of([]); // Devuelve un array vacío en caso de error
      })),
      salary: this.getSalarySchoolData().pipe(retry(3), catchError(error => {
        console.error('Error fetching clients:', error);
        return of([]); // Devuelve un array vacío en caso de error
      })),
      schoolRel: this.getSchoolRel().pipe(retry(3), catchError(error => {
        console.error('Error fetching clients:', error);
        return of([]); // Devuelve un array vacío en caso de error
      })),
      degrees: this.getDegrees().pipe(retry(3), catchError(error => {
        console.error('Error fetching degrees:', error);
        return of([]); // Devuelve un array vacío en caso de error
      }))

    };

    return forkJoin(requestsInitial).pipe(tap((results) => {
      this.crudService.get('/schools/' + this.user.schools[0].id)
        .subscribe((data) => {
          let school = data.data;
          const settings = typeof school.settings === 'string' ? JSON.parse(school.settings) : school.settings;
          this.currency = settings?.taxes?.currency;
          this.formInfoAccount = this.fb.group({
            image: [''],
            name: ['', Validators.required],
            surname: ['', Validators.required],
            email: ['', [Validators.required, Validators.email]],
            username: [''],
            station: [''],
            password: [''],

          });

          this.formPersonalInfo = this.fb.group({
            fromDate: ['', Validators.required],
            phone: [''],
            mobile: ['', Validators.required],
            address: [''],
            postalCode: [''],
            country: this.myControlCountries,
            province: this.myControlProvinces

          });

          this.formWorkInfo = this.fb.group({
            avs: [],
            workId: [],
            iban: [],
            countryWork: this.myControlWorkCountries,
            children: [],
            childName: [],
            childAge: [],
            sports: [],
            sportName: [],
          });

          this.formCivilStatusInfo = this.fb.group({

            civilStatus: [this.defaults.civil_status],
            spouse: [this.defaults.partner_works ? 'y' : 'n'],
            workMobility: [this.defaults.family_allowance ? 'y' : 'n'],
            spouseWorkId: [],
            spousePercentage: []
          });

          this.filteredStations = this.myControlStations.valueChanges
            .pipe(
              startWith(''),
              map(value => this._filterStations(value))
            );

          this.myControlStations.valueChanges.subscribe(value => {
            this.formInfoAccount.get('station').setValue(value);
          });

          this.filteredCountries = this.myControlCountries.valueChanges.pipe(
            startWith(''),
            map(value => typeof value === 'string' ? value : value.name),
            map(name => name ? this._filterCountries(name) : this.mockCountriesData.slice())
          );

          this.myControlCountries.valueChanges.subscribe(country => {
            if (country) {
              this.myControlProvinces.setValue('');  // Limpia la selección anterior de la provincia
              this.filteredProvinces = this._filterProvinces(country.id);
            }
          });
        });



    }));

  }

  getData() {
    return this.crudService.get('/monitors/' + this.id, ['user'])
      .pipe(
        tap((data) => {

          this.defaults = data.data;
          this.defaultsUser = data.data.user;
          this.setInitLanguages();

          //this.getStations();
          //this.getLanguages();

          this.filteredWorkCountries = this.myControlWorkCountries.valueChanges.pipe(
            startWith(''),
            map(value => typeof value === 'string' ? value : value.name),
            map(name => name ? this._filterCountries(name) : this.mockWorkCountriesData.slice())
          );

          this.filteredCivilStatus = this.myControlCivilStatus.valueChanges.pipe(
            startWith(''),
            map(value => this._filterCivilStatus(value))
          );

          this.filteredLevel = this.levelForm.valueChanges.pipe(
            startWith(''),
            map((value: any) => typeof value === 'string' ? value : value?.annotation),
            map(annotation => annotation ? this._filterLevel(annotation) : this.mockLevelData.slice())
          );

          this.filteredLanguages = this.languagesControl.valueChanges.pipe(
            startWith(''),
            map(language => (language ? this._filterLanguages(language) : this.languages.slice()))
          );

          this.filteredSalary = this.salaryForm.valueChanges.pipe(
            startWith(''),
            map((value: any) => typeof value === 'string' ? value : value?.annotation),
            map(annotation => annotation ? this._filterSalary(annotation) : this.salaryData.slice())
          );

          this.getSchoolSportDegrees().subscribe((results) => {
            //this.getMonitorSportsDegree();
            this.getSports();

            this.myControlCivilStatus.setValue(this.mockCivilStatus.find((cv) => cv === this.defaults.civil_status));
            this.myControlStations.setValue(this.stations.find((s) => s.id === this.defaults.active_station)?.name);
            this.myControlCountries.setValue(this.mockCountriesData.find((c) => c.id === +this.defaults.country));
            this.myControlProvinces.setValue(this.mockProvincesData.find((c) => c.id === +this.defaults.province));
            this.myControlWorkCountries.setValue(this.mockWorkCountriesData.find((c) => c.id === +this.defaults.world_country));

            const langs = [];
            this.languages.forEach(element => {
              if (element.id === this.defaults?.language1_id || element.id === this.defaults?.language2_id || element.id === this.defaults?.language3_id ||
                element.id === this.defaults?.language4_id || element.id === this.defaults?.language5_id || element.id === this.defaults?.language6_id) {
                langs.push(element);
              }
            });

            this.languagesControl.setValue(langs);
            this.activeSchool = this.user.schools.find(school => school.active === true) || 0;

            this.getSportsTimeline();


            this.crudService.list('/seasons', 1, 10000, 'asc', 'id', '&school_id=' + this.user.schools[0].id + '&is_active=1')
              .subscribe((season) => {
                let hour_start = '08:00';
                let hour_end = '18:00';
                if (season.data.length > 0) {
                  this.vacationDays = JSON.parse(season.data[0].vacation_days);
                  hour_start = season.data[0].hour_start ? season.data[0].hour_start.substring(0, 5) : '08:00';
                  hour_end = season.data[0].hour_end ? season.data[0].hour_end.substring(0, 5) : '18:00';
                }
                this.hoursRange = this.generateHoursRange(hour_start, hour_end);
                this.hoursRangeMinusLast = this.hoursRange.slice(0, -1);
                this.hoursRangeMinutes = this.generateHoursRangeMinutes(hour_start, hour_end);
              })

            this.calculateWeeksInMonth();
            //await this.calculateTaskPositions();
            this.loadBookings(this.currentDate);
            this.loading = false;

          });

        }))

    //Planificador

  }

  onDateChange(event: any) {
    this.currentDate = event.value;
    this.timelineView = 'day';
    this.updateView();
  }

  showInfoEvent(event: boolean) {
    this.showInfo = event;
  }

  showPersonalInfoEvent(event: boolean) {
    this.showPersonalInfo = event;
  }

  showWorkInfoEvent(event: boolean) {
    this.showWorkInfo = event;
  }

  showCivilStatusInfoEvent(event: boolean) {
    this.showCivilStatusInfo = event;
  }


  showInfoEditEvent(event: boolean) {
    this.editInfo = event;
    this.selectedTabIndex = 0;
    this.editing = true;
  }

  showPersonalInfoEditEvent(event: boolean) {
    this.editPersonalInfo = event;
    this.selectedTabIndex = 2;
    this.editing = true;
  }

  showWorkInfoEditEvent(event: boolean) {
    this.editWorkInfo = event;
    this.editing = true;
  }

  showCivilStatusInfoEditEvent(event: boolean) {
    this.editCivilStatusInfo = event;
    this.editing = true;
  }

  showSportInfoEvent(event: boolean) {
    this.showSportInfo = event;
  }

  showSportInfoEditEvent(event: boolean) {
    this.editSportInfo = event;
    this.selectedTabIndex = 3;
    this.editing = true;
  }

  showFamilyInfoEvent(event: boolean) {
    this.showFamilyInfo = event;
  }

  showFamilyInfoEditEvent(event: boolean) {
    this.editFamilyInfo = event;
    this.selectedTabIndex = 4;
    this.editing = true;
  }

  showWorkEvent(event: boolean) {
    this.showWork = event;
    this.editing = true;
  }

  showWorkEditEvent(event: boolean) {
    this.editWork = event;
    this.editing = true;
  }

  showAddressInfoEvent(event: boolean) {
    this.showAddressInfo = event;
    this.editing = true;
  }

  showAddressInfoEditEvent(event: boolean) {
    this.editAddressInfo = event;
    this.selectedTabIndex = 1;
    this.editing = true;
  }


  private _filter(name: string, countryId: number): any[] {
    const filterValue = name.toLowerCase();
    return this.mockProvincesData.filter(province => province.country_id === countryId && province.name.toLowerCase().includes(filterValue));
  }

  private _filterCivilStatus(value: string): string[] {
    const filterValue = value.toLowerCase();
    return this.mockCivilStatus.filter(civilStatus => civilStatus.toLowerCase().includes(filterValue));
  }

  private _filterSports(value: any): any[] {
    const filterValue = typeof value === 'string' ? value.toLowerCase() : value?.name.toLowerCase();
    return this.schoolSports.filter(sport => sport?.name.toLowerCase().indexOf(filterValue) === 0);
  }

  private _filterLevel(name: string): any[] {
    const filterValue = name.toLowerCase();
    return this.mockLevelData.filter(level => level.annotation.toLowerCase().includes(filterValue));
  }

  private _filterLanguages(value: any): any[] {
    const filterValue = value.toLowerCase();
    return this.languages.filter(language => language?.name.toLowerCase().includes(filterValue));
  }
  private _filterCountries(name: string): any[] {
    const filterValue = name.toLowerCase();
    return this.mockCountriesData.filter(country => country.name.toLowerCase().includes(filterValue));
  }

  private _filterProvinces(countryId: number): Observable<any[]> {
    return this.myControlProvinces.valueChanges.pipe(
      startWith(''),
      map(value => typeof value === 'string' ? value : value.name),
      map(name => name ? this._filter(name, countryId) : this.mockProvincesData.filter(p => p.country_id === countryId).slice())
    );
  }

  private _filterStations(value: string): string[] {
    const filterValue = value.toLowerCase();
    return this.stations.filter(option => option.name.toLowerCase().includes(filterValue));
  }

  displayFnCountry(country: any): string {
    return country && country.name ? country.name : '';
  }

  displayFnProvince(province: any): string {
    return province && province.name ? province.name : '';
  }

  displayFnLevel(level: any): string {
    return level && level.name ? level.name : '';
  }

  displayFnSalary(salary: any): string {
    return salary && salary.name ? salary.name : '';
  }

  private _filterSalary(name: string): any[] {
    const filterValue = name.toLowerCase();
    return this.salaryData.filter(level => level.annotation.toLowerCase().includes(filterValue));
  }

  passwordValidator(formControl: FormControl) {
    const { value } = formControl;
    const hasUpperCase = /[A-Z]/.test(value);
    const hasLowerCase = /[a-z]/.test(value);
    const hasNumeric = /[0-9]/.test(value);
    const hasSpecialChar = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(value);

    if (hasUpperCase && hasLowerCase && hasNumeric && hasSpecialChar) {
      return null;
    } else {
      return { passwordStrength: true };
    }
  }

  getSelectedLanguageNames(): string {
    return this.selectedLanguages.map(language => language.name).join(', ');
  }

  toggleSelectionLanguages(event: any, language: any): void {
    if (event.isUserInput) {

      if (this.selectedLanguages.length < this.maxSelection) {

        const index = this.selectedLanguages.findIndex(l => l.id === language.id);
        if (index >= 0) {
          this.selectedLanguages.splice(index, 1);
        } else {
          this.selectedLanguages.push({ id: language.id, name: language.name, code: language.code });
        }
      } else {
        this.snackbar.open(this.translateService.instant('snackbar.admin.langs'), 'OK', { duration: 3000 });
      }
    }
  }

  getLanguages() {
    return this.crudService.list('/languages', 1, 1000).pipe(
      tap((data) => {
        this.languages = data.data.reverse();
      })
    );
  }

  getSports() {
    this.crudService.list('/sports', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id)
      .subscribe((data) => {
        data.data.forEach(element => {
          this.schoolSports.forEach(sport => {
            if (element.id === sport.sport_id) {
              sport.name = element.name;
              sport.icon_selected = element.icon_selected;
            }
          });
        });
      })
  }

  getStations() {
    return this.crudService.list('/stations-schools', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id)
      .pipe(
        switchMap((station) => {
          const stationRequests = station.data.map(element =>
            this.crudService.get('/stations/' + element.station_id).pipe(
              map(data => data.data)
            )
          );
          return forkJoin(stationRequests);
        }),
        tap((stations) => {
          this.stations = stations;
        })
      );
  }

  getSchoolSportDegrees() {
    return this.crudService.list('/school-sports', 1, 10000, 'desc', 'id', '&school_id=' +
      this.user.schools[0].id, null, null, null, ['sport', 'degrees'])
      .pipe(
        switchMap((sport) => {
          this.schoolSports = sport.data;
          return this.getMonitorSportsDegree()
        })
      );
  }
  getSchoolSportDegreesOld() {
    this.crudService.list('/school-sports', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id)
      .subscribe((sport) => {
        this.schoolSports = sport.data;
        sport.data.forEach((element, idx) => {
          this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order', '&school_id=' + this.user.schools[0].id + '&sport_id=' + element.sport_id)
            .subscribe((data) => {
              this.schoolSports[idx].degrees = data.data
            });
        });
      })

  }

  getSchoolRel() {
    return this.crudService.list('/monitors-schools', 1, 10000, 'desc', 'id', '&monitor_id=' + this.id + '&school_id=' + this.user.schools[0].id)
      .pipe(
        map((data) => {
          this.monitorSchoolRel = data.data[0];
        })
      )
  }

  getMonitorSportsDegree() {
    return this.crudService.list('/monitor-sports-degrees', 1, 10000, 'desc', 'id',
      '&monitor_id=' + this.id + '&school_id=' + this.user.schools[0].id, null, null, null,
      ['monitorSportAuthorizedDegrees'])
      .pipe(
        map((monitorDegree) => {
          let selectedSports = []; // Obtén los deportes actualmente seleccionados o inicializa un arreglo vacío
          const level = [];

          this.schoolSports.forEach(element => {

            element.degrees.forEach(degree => {
              monitorDegree.data.forEach(monitorDegree => {
                if ((monitorDegree.sport_id === element.sport_id) && (monitorDegree.degree_id === degree.id)) {
                  monitorDegree.degrees = element.degrees;
                  monitorDegree.level = degree;
                  level.push(monitorDegree);
                }
              });
            });
            const mDegree = monitorDegree.data.filter((m) => m.sport_id === element.sport_id);
            if (mDegree.length > 0) {
              element.monitor_sports_degree_id = mDegree[0].id;
              selectedSports.push(element);
            }
          });

          selectedSports.forEach(element => {
            const degreeLevel = level.find((l) => l.sport_id === element.sport_id);
            if (degreeLevel) {
              element.level = degreeLevel.level;
              element.salary_level = degreeLevel.salary_level;
            }
          });

          this.sportsCurrentData.data = selectedSports;
          this.selectedSports = selectedSports;
          this.sportsControl.setValue(selectedSports);
          this.monitorSportsDegree = monitorDegree.data;
          const availableSports = [];
          this.schoolSports.forEach(element => {
            if (!this.sportsCurrentData.data.find((s) => s.id === element.id)) {
              availableSports.push(element);
            }
          });
          this.filteredSports = this.sportsControl.valueChanges.pipe(
            startWith(''),
            map((sport: string | null) => sport ? this._filterSports(sport) : availableSports.slice())
          );

          monitorDegree.data.forEach(mDG => {
            selectedSports.forEach(element => {
              element.degrees.forEach(dg => {
                if (dg.id === mDG.monitor_sport_authorized_degrees[0]?.degree_id) {
                  element.authorisedLevels = mDG.monitor_sport_authorized_degrees;

                }
              });

            });

          });
        })
      )
  }


  getMonitorSportsDegreeOld() {
    this.crudService.list('/monitor-sports-degrees', 1, 10000, 'desc', 'id',
      '&monitor_id=' + this.id + '&school_id=' + this.user.schools[0].id, null, null, null, ['monitorSportAuthorizedDegrees'])
      .subscribe((monitorDegree) => {
        let selectedSports = []; // Obtén los deportes actualmente seleccionados o inicializa un arreglo vacío
        const level = [];

        this.schoolSports.forEach(element => {

          element.degrees.forEach(degree => {
            monitorDegree.data.forEach(monitorDegree => {
              if ((monitorDegree.sport_id === element.sport_id) && (monitorDegree.degree_id === degree.id)) {
                monitorDegree.degrees = element.degrees;
                monitorDegree.level = degree;
                level.push(monitorDegree);
              }
            });
          });
          const mDegree = monitorDegree.data.filter((m) => m.sport_id === element.sport_id);
          if (mDegree.length > 0) {
            element.monitor_sports_degree_id = mDegree[0].id;
            selectedSports.push(element);
          }
        });

        selectedSports.forEach(element => {
          const degreeLevel = level.find((l) => l.sport_id === element.sport_id);
          if (degreeLevel) {
            element.level = degreeLevel.level;
            element.salary_level = degreeLevel.salary_level;
          }
        });

        this.sportsCurrentData.data = selectedSports;
        this.selectedSports = selectedSports;
        this.sportsControl.setValue(selectedSports);
        this.monitorSportsDegree = monitorDegree.data;
        const availableSports = [];
        this.schoolSports.forEach(element => {
          if (!this.sportsCurrentData.data.find((s) => s.id === element.id)) {
            availableSports.push(element);
          }
        });
        this.filteredSports = this.sportsControl.valueChanges.pipe(
          startWith(''),
          map((sport: string | null) => sport ? this._filterSports(sport) : availableSports.slice())
        );

        monitorDegree.data.forEach(mDG => {

          this.crudService.list('/monitor-sport-authorized-degrees', 1, 10000, 'desc', 'id', '&monitor_sport_id=' + mDG.id)
            .subscribe((data) => {

              selectedSports.forEach(element => {
                element.degrees.forEach(dg => {
                  if (dg.id === data.data[0].degree_id) {
                    element.authorisedLevels = data.data;
                  }
                });
              });
            })
        });
      })
  }


  get rows(): FormArray {
    return this.formWorkInfo.get('rows') as FormArray;
  }

  setLanguages() {
    if (this.selectedLanguages.length >= 1) {

      this.defaults.language1_id = this.selectedLanguages[0].id;
    } else {
      this.defaults.language1_id = null;
    }
    if (this.selectedLanguages.length >= 2) {

      this.defaults.language2_id = this.selectedLanguages[1].id;
    } else {
      this.defaults.language2_id = null;
    }
    if (this.selectedLanguages.length >= 3) {

      this.defaults.language3_id = this.selectedLanguages[2].id;
    } else {
      this.defaults.language3_id = null;
    }
    if (this.selectedLanguages.length >= 4) {

      this.defaults.language4_id = this.selectedLanguages[3].id;
    } else {
      this.defaults.language4_id = null;
    }
    if (this.selectedLanguages.length >= 5) {

      this.defaults.language5_id = this.selectedLanguages[4].id;
    } else {
      this.defaults.language5_id = null;
    }
    if (this.selectedLanguages.length === 6) {

      this.defaults.language6_id = this.selectedLanguages[5].id;
    } else {
      this.defaults.language6_id = null;
    }
  }

  setInitLanguages() {

    this.languages.forEach(element => {
      if (element.id === this.defaults.language1_id || element.id === this.defaults.language2_id || element.id === this.defaults.language3_id
        || element.id === this.defaults.language4_id || element.id === this.defaults.language5_id || element.id === this.defaults.language6_id) {
        this.selectedLanguages.push(element);
      }
    });
  }

  goTo(route: string) {
    this.router.navigate([route]);
  }



  onFileChanged(event: Event) {
    const file = (event.target as HTMLInputElement).files[0];
    if (file) {
      const reader = new FileReader();

      reader.onload = () => {
        this.imagePreviewUrl = reader.result;
        this.defaults.image = reader.result;
      };

      reader.readAsDataURL(file);
    }
  }

  updateChildren(event: Event): void {
    const inputElement = event.target as HTMLInputElement;
    const num = parseInt(inputElement.value, 10);

    if (isNaN(num) || num < 0) {
      return;
    }

    const currentData = this.childrenData.data;
    const difference = num - currentData.length;

    if (difference > 0) {
      for (let i = 0; i < difference; i++) {
        currentData.push({ name: '', date: null });
      }
    } else if (difference < 0) {
      currentData.splice(num);
    }

    this.childrenData.data = [...currentData];
  }



  toggleSelection(event: any, sport: any): void {

    if (event.isUserInput) {

      const index = this.selectedNewSports.findIndex(s => s.sport_id === sport.sport_id);
      if (index >= 0) {
        this.selectedNewSports.splice(index, 1);
      } else {
        this.selectedNewSports.push(sport);
      }

      // Crear una nueva referencia para el array
      this.selectedNewSports = [...this.selectedNewSports];

      // Actualizar los datos de la tabla
      this.sportsData.data = this.selectedNewSports;

      // Detectar cambios manualmente para asegurarse de que Angular reconozca los cambios
      this.cdr.detectChanges();
    }
  }

  getSelectedSportsNames(): string {
    return this.sportsControl.value?.map(sport => sport.name).join(', ') || '';
  }

  authoriseLevel(level) {

    const index = this.authorisedLevels.findIndex(l => l.id === level.id && l.sport_id === level.sport_id);
    if (index !== -1) {
      this.authorisedLevels.splice(index, 1);
    } else {
      this.authorisedLevels.push(level);
    }
  }

  isAuthorized(level: any) {
    return this.authorisedLevels.find(l => l.id === level.id && l.sport_id === level.sport_id);
  }

  authoriseCurrentLevel(level, element) {

    const index = element.authorisedLevels ? element.authorisedLevels.findIndex(l => l.degree_id === level.id) : -1;
    const authLevel = element.authorisedLevels ? element.authorisedLevels.find(l => l.degree_id === level.id) : null;
    if (index !== -1) {
      this.crudService.delete('/monitor-sport-authorized-degrees', authLevel.id)
        .subscribe(() => {
          element.authorisedLevels.splice(index, 1);
          this.snackbar.open(this.translateService.instant('snackbar.monitor.delete_auth'), 'OK', { duration: 3000 });
        });
    } else {
      this.crudService.create('/monitor-sport-authorized-degrees', { monitor_sport_id: element.monitor_sports_degree_id, degree_id: level.id })
        .subscribe((data) => {
          if (!element.authorisedLevels) {
            element.authorisedLevels = [];
          }
          element.authorisedLevels.push(data.data);
          this.snackbar.open(this.translateService.instant('snackbar.monitor.add_auth'), 'OK', { duration: 3000 });

        });
    }
  }

  authoriseAudlts(element) {
    element.authorisedLevels.forEach(auLevel => {
      const authorize = this.monitorSportsDegree[0].allow_adults ? false : true;
      const data = {
        is_default: false,
        sport_id: element.sport_id,
        school_id: this.user.schools[0].id,
        degree_id: auLevel.degree_id,
        monitor_id: this.id,
        salary_level: element.salary_level,
        allow_adults: authorize
      }

      this.crudService.update('/monitor-sports-degrees', data, auLevel.monitor_sport_id)
        .subscribe(() => {
          this.snackbar.open(this.translateService.instant('snackbar.monitor.add_auth_adult'), 'OK', { duration: 3000 });
          this.getData();
        })
    });
  }

  setValueSpouse(value: any) {

    this.defaults.partner_works = value.value === 'y';
    this.cdr.detectChanges();
  }

  setValueLocation(value: any) {

    this.defaults.family_allowance = value.value === 'y';
    this.cdr.detectChanges();

  }

  getSalarySchoolData() {
    return this.crudService.list('/school-salary-levels', 1, 10000, 'desc', 'pay', '&school_id=' + this.user.schools[0].id)
      .pipe(
        map((data) => {
          this.salaryData = data.data;
        })
      )
  }

  checkMonitorAuth(item: any, id: any) {
    let ret = false;

    if (item.authorisedLevels) {
      item.authorisedLevels.forEach(element => {
        if ((element.degree_id || element.id) === id.id && !ret) {
          ret = true;
        }
      });
    }

    return ret;
  }

  checkMonitorAuthAdults(item: any) {
    let ret = false;

    if (item.authorisedLevels) {
      this.monitorSportsDegree.forEach(element => {
        if (element.sport_id === item.sport_id && element.allow_adults && !ret) {
          ret = true;
        }
      });
    }

    return ret;
  }

  getSalary(id: any) {
    let ret: any;
    this.salaryData.forEach(element => {
      if (element.id === id) {
        ret = element;
      }
    });

    return ret;
  }

  removeSport(idx: number, element: any) {

    const dialogRef = this.dialog.open(ConfirmModalComponent, {
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales,
      data: { message: this.translateService.instant('delete_text'), title: this.translateService.instant('delete_title') }
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {

        this.crudService.delete('/monitor-sports-degrees', element.monitor_sports_degree_id)
          .subscribe(() => {
            this.deletedItems.push(this.sportsCurrentData.data[idx]);
            this.sportsCurrentData.data.splice(idx, 1);
            this.currentSportsTable.renderRows();
          })
      }
    });

  }


  save() {
    this.loading = true;
    this.defaultsUser.email = this.defaults.email;
    this.defaultsUser.image = this.imagePreviewUrl;
    this.defaults.image = this.imagePreviewUrl;
    this.defaults.birth_date = moment(this.defaults.birth_date, 'YYYY-MM-DD').format('YYYY-MM-DD');
    this.setLanguages();

    this.crudService.update('/users', this.defaultsUser, this.defaults.user_id)
      .subscribe((user) => {
        this.defaults.user_id = user.data.id;

        this.crudService.update('/monitors', this.defaults, this.id)
          .subscribe((monitor) => {
            //this.snackbar.open(this.translateService.instant('snackbar.monitor.update'), 'OK', {duration: 3000});

            const schoolRel = {
              monitor_id: monitor.data.id,
              school_id: this.user.schools[0].id,
              station_id: this.defaults.active_station,
              active_school: this.defaultsUser.active,
              block_price: this.monitorSchoolRel.block_price
            }
            this.crudService.update('/monitors-schools', schoolRel, this.monitorSchoolRel.id)
              .subscribe((a) => { })
            // revisar a partir de aqui
            this.sportsData.data.forEach(element => {
              this.crudService.create('/monitor-sports-degrees', { is_default: true, monitor_id: monitor.data.id, sport_id: element.sport_id, school_id: this.user.schools[0].id, degree_id: element.level.id, salary_level: element.salary_id })
                .subscribe((e) => {
                  this.authorisedLevels.forEach(auLevel => {

                    if (e.data.sport_id === auLevel.sport_id) {

                      this.crudService.create('/monitor-sport-authorized-degrees', { monitor_sport_id: e.data.id, degree_id: auLevel.id })
                        .subscribe((d) => { })
                    }
                  });
                })
            });
            setTimeout(() => {
              this.snackbar.open(this.translateService.instant('snackbar.monitor.update'), 'OK', { duration: 3000 });
              window.location.reload();
            }, 3000);
          })
      })
  }

  updateLevel(monitorDegree, level) {
    this.crudService.update('/monitor-sports-degrees', {
      is_default: true, monitor_id: this.id, sport_id: monitorDegree.sport_id, school_id: this.user.schools[0].id,
      degree_id: level.id, salary_level: monitorDegree.salary_id
    }, monitorDegree.authorisedLevels[0].monitor_sport_id)
      .subscribe((data) => {
        this.snackbar.open(this.translateService.instant('snackbar.client.level_updated'), 'OK', { duration: 3000 });
      })
  }

  updateSalary(monitorDegree, salary) {
    this.crudService.update('/monitor-sports-degrees', {
      is_default: true, monitor_id: this.id, sport_id: monitorDegree.sport_id, school_id: this.user.schools[0].id,
      degree_id: monitorDegree.level.id, salary_level: salary.id
    }, monitorDegree.authorisedLevels[0].monitor_sport_id)
      .subscribe((data) => {
        this.snackbar.open(this.translateService.instant('snackbar.monitor.salary_updated'), 'OK', { duration: 3000 });
      })
  }

  toggleVisibility() {
    if (this.visible) {
      this.inputType = 'password';
      this.visible = false;
      this.cdr.markForCheck();
    } else {
      this.inputType = 'text';
      this.visible = true;
      this.cdr.markForCheck();
    }
  }

  getCountry(id: any) {
    const country = this.mockCountriesData.find((c) => c.id == +id);
    return country ? country.name : 'NDF';
  }

  getProvince(id: any) {
    const province = this.mockProvincesData.find((c) => c.id == +id);
    return province ? province.name : 'NDF';
  }

  calculateAge(birthDateString) {
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

  getLanguage(id: any) {
    const lang = this.languages.find((c) => c.id == +id);
    return lang ? lang.code.toUpperCase() : 'NDF';
  }


  showDetailEvent(event: any) {

    if (event.showDetail || (!event.showDetail && this.detailData !== null && this.detailData.id !== event.item.id)) {
      this.detailData = event.item;
      this.detailData.sport = this.detailData.course.sport;
      if (this.detailData.course.course_type == 2) {
        //this.detailData.date_full =
      }
      /*      this.crudService.get('/admin/courses/'+this.detailData.course_id)
              .subscribe((course) => {

                this.crudService.get('/sports/'+this.detailData.course.sport_id)
                  .subscribe((sport) => {
                    this.detailData.sport = sport.data;
                  });

                if (this.detailData.degree_id !== null) {
                  this.crudService.get('/degrees/'+this.detailData.degree_id)
                    .subscribe((degree) => {
                      this.detailData.degree = degree.data;
                    })
                }

              })*/
      this.showDetail = true;
      /*      this.crudService.list('/booking-users', 1, 10000, 'desc', 'id', '&booking_id='+this.detailData.booking.id)
              .subscribe((booking) => {
                this.detailData.users = [];

                booking.data.forEach((element, idx) => {
                  if (moment(element.date).format('YYYY-MM-DD') === moment(this.detailData.date).format('YYYY-MM-DD')) {
                    this.detailData.users.push(element);

                    this.crudService.list('/client-sports', 1, 10000, 'desc', 'id', '&client_id='+element.client_id+"&school_id="+this.user.schools[0].id)
                      .subscribe((cd) => {

                        if (cd.data.length > 0) {
                          element.sports= [];

                          cd.data.forEach(c => {
                            element.sports.push(c);
                          });
                        }


                      })

                  }
                });
                this.showDetail = true;

              });*/


    } else {

      this.showDetail = event.showDetail;
      this.detailData = null;
    }

  }

  close() {
    this.showDetail = false;
    this.detailData = null;
  }

  getAllLevelsBySport() {
    let ret = [];
    this.schoolSports.forEach(element => {
      if (element.sport_id === this.detailData.sport.id) {
        ret = element.degrees;
      }
    });

    return ret;
  }


  getClients() {
    return this.crudService.list('/admin/clients/mains', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id).pipe(
      tap((client) => {
        this.clients = client.data;
      })
    );
  }

  getDateIndex() {
    let ret = 0;
    if (this.detailData.course && this.detailData.course.course_dates) {
      this.detailData.course.course_dates.forEach((element, idx) => {
        if (moment(element.date).format('YYYY-MM-DD') === moment(this.detailData.date).format('YYYY-MM-DD')) {
          ret = idx + 1;
        }
      });
    }

    return ret;
  }

  getGroupsQuantity() {
    let ret = 0;
    if (this.detailData.course && this.detailData.course.course_dates) {
      this.detailData.course.course_dates.forEach((element, idx) => {
        if (moment(element.date).format('YYYY-MM-DD') === moment(this.detailData.date).format('YYYY-MM-DD')) {
          ret = element.course_groups.length;
        }
      });
    }

    return ret;
  }


  getSubGroupsIndex() {
    let ret = 0;
    if (this.detailData.course && this.detailData.course.course_dates) {

      this.detailData.course.course_dates.forEach((element, idx) => {
        const group = element.course_groups.find((g) => g.id === this.detailData.course_group_id);

        if (group) {
          group.course_subgroups.forEach((s, sindex) => {
            if (s.id === this.detailData.course_subgroup_id) {
              ret = sindex + 1;
            }
          });
        }
      });
    }
    return ret;
  }

  getDateFormatLong(date: string) {
    return moment.utc(date).format('dddd, D MMMM YYYY');
  }

  getHoursMinutes(hour_start: string, hour_end: string) {
    const parseTime = (time: string) => {
      const [hours, minutes] = time.split(':').map(Number);
      return { hours, minutes };
    };

    const startTime = parseTime(hour_start);
    const endTime = parseTime(hour_end);

    let durationHours = endTime.hours - startTime.hours;
    let durationMinutes = endTime.minutes - startTime.minutes;

    if (durationMinutes < 0) {
      durationHours--;
      durationMinutes += 60;
    }

    return `${durationHours}h${durationMinutes}m`;
  }

  getHourRangeFormat(hour_start: string, hour_end: string) {
    return hour_start.substring(0, 5) + ' - ' + hour_end.substring(0, 5);
  }

  getClientDegree(sport_id: any, sports: any) {
    const sportObject = sports.find(sport => sport.sport_id === sport_id);
    if (sportObject) {
      return sportObject.degree_id;
    }
    else {
      return 0;
    }
  }

  getBirthYears(date: string) {
    const birthDate = moment(date);
    return moment().diff(birthDate, 'years');
  }

  getLanguageById(languageId: number): string {
    const language = this.languages.find(c => c.id === languageId);
    return language ? language.code.toUpperCase() : '';
  }

  getCountryById(countryId: number): string {
    const country = MOCK_COUNTRIES.find(c => c.id === countryId);
    return country ? country.code : 'Aucun';
  }

  calculateHourEnd(hour: any, duration: any) {
    if (duration.includes('h') && (duration.includes('min') || duration.includes('m'))) {
      const hours = duration.split(' ')[0].replace('h', '');
      const minutes = duration.split(' ')[1].replace('min', '').replace('m', '');

      return moment(hour, 'HH:mm').add(hours, 'h').add(minutes, 'm').format('HH:mm');
    } else if (duration.includes('h')) {
      const hours = duration.split(' ')[0].replace('h', '');

      return moment(hour, 'HH:mm').add(hours, 'h').format('HH:mm');
    } else {
      const minutes = duration.split(' ')[0].replace('min', '').replace('m', '');

      return moment(hour, 'HH:mm').add(minutes, 'm').format('HH:mm');
    }
  }

  //Planificador


  getSportsTimeline() {
    return this.crudService.get('/sports?perPage=' + 99999).pipe(
      map((data) => {
        this.sports = data.data;
      }));

  }

  getDegrees() {
    return this.crudService.get('/degrees?perPage=' + 99999).pipe(
      map((data) => {
        this.degrees = data.data.sort((a, b) => a.degree_order - b.degree_order);
        this.degrees.forEach((degree: any) => {
          degree.inactive_color = this.lightenColor(degree.color, 30);
        });
      }));
  }

  private lightenColor(hexColor: string, percent: number): string {
    let r: any = parseInt(hexColor.substring(1, 3), 16);
    let g: any = parseInt(hexColor.substring(3, 5), 16);
    let b: any = parseInt(hexColor.substring(5, 7), 16);

    // Increase the lightness
    r = Math.round(r + (255 - r) * percent / 100);
    g = Math.round(g + (255 - g) * percent / 100);
    b = Math.round(b + (255 - b) * percent / 100);

    // Convert RGB back to hex
    r = r.toString(16).padStart(2, '0');
    g = g.toString(16).padStart(2, '0');
    b = b.toString(16).padStart(2, '0');

    return '#' + r + g + b;
  }

  async calculateWeeksInMonth() {
    const startMonth = startOfWeek(startOfMonth(this.currentDate), { weekStartsOn: 1 });
    const endMonth = endOfWeek(endOfMonth(this.currentDate), { weekStartsOn: 1 });

    this.weeksInMonth = [];
    let currentWeekStart = startMonth;

    while (currentWeekStart <= endMonth) {
      const currentWeekEnd = endOfWeek(currentWeekStart, { weekStartsOn: 1 });

      const week = {
        startWeek: format(currentWeekStart, 'yyyy-MM-dd'),
        startDay: this.formatDayWithFrenchInitial(max([startOfMonth(this.currentDate), currentWeekStart])),
        endDay: this.formatDayWithFrenchInitial(min([endOfMonth(this.currentDate), currentWeekEnd]))
      };

      this.weeksInMonth.push(week);
      currentWeekStart = addWeeks(currentWeekStart, 1);
    }
  }

  formatDayWithFrenchInitial(date: Date): string {
    const frenchDayInitials = ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa'];
    const dayOfWeek = getDay(date);
    const initial = frenchDayInitials[dayOfWeek];
    return `${initial} ${format(date, 'd')}`;
  }

  loadBookings(date: Date) {
    let firstDate, lastDate;
    if (this.timelineView === 'week') {
      const startOfWeekDate = startOfWeek(date, { weekStartsOn: 1 });
      const endOfWeekDate = endOfWeek(date, { weekStartsOn: 1 });
      firstDate = moment(startOfWeekDate).format('YYYY-MM-DD');
      lastDate = moment(endOfWeekDate).format('YYYY-MM-DD');
      this.searchBookings(firstDate, lastDate);

      /*this.filteredTasks = this.tasksCalendarStyle.filter(task => {
        const taskDate = new Date(task.date);
        return taskDate >= startOfWeekDate && taskDate <= endOfWeekDate;
      });*/
    } else if (this.timelineView === 'month') {
      const startMonth = startOfMonth(date);
      const endMonth = endOfMonth(date);
      firstDate = moment(startMonth).format('YYYY-MM-DD');
      lastDate = moment(endMonth).format('YYYY-MM-DD');
      this.searchBookings(firstDate, lastDate);

      /*this.filteredTasks = this.tasksCalendarStyle.filter(task => {
        const taskDate = new Date(task.date);
        return taskDate >= startMonth && taskDate <= endMonth;
      });*/
    } else {
      const dateStr = date.toLocaleString().split('T')[0];
      firstDate = moment(date).format('YYYY-MM-DD');
      lastDate = firstDate;
      this.searchBookings(firstDate, lastDate);
      /*this.filteredTasks = this.tasksCalendarStyle.filter(task => task.date === dateStr);*/
    }
  }

  searchBookings(firstDate: string, lastDate: string) {
    this.crudService.get('/admin/getPlanner?date_start=' + firstDate + '&date_end=' + lastDate + '&school_id=' + this.activeSchool + '&monitor_id=' + this.id + '&perPage=' + 99999).subscribe(
      (data: any) => {
        this.processData(data.data);
      },
      error => {
        console.error('There was an error!', error);
      }
    );
  }

  normalizeToArray(data: any) {
    //Nwds sometimes as object sometimes as array
    if (Array.isArray(data)) {
      return data;
    }
    if (typeof data === 'object') {
      return Object.values(data);
    }
    return [];
  }

  processData(data: any) {
    let allNwds = [];
    let allBookings = [];

    for (const key in data) {
      const item = data[key];

      // Process 'nwds' field
      if (item.nwds) {
        const nwdsArray = this.normalizeToArray(item.nwds);
        allNwds.push(...nwdsArray);
      }

      // Process 'bookings' field
      /*NO NEED TO GROUP WHEN CHANGE IN CALL*/
      /*allBookings.push(...item.bookings);*/
      if (item.bookings && typeof item.bookings === 'object') {
        for (const bookingKey in item.bookings) {
          let bookingArray = item.bookings[bookingKey];
          if (!Array.isArray(bookingArray)) {
            bookingArray = [bookingArray];
          }

          let bookingArrayComplete = [];

          if (Array.isArray(bookingArray) && bookingArray.length > 0) {

            //Check if private bookings have the the same hours - and group them
            if (bookingArray[0].course.course_type === 2 && bookingArray.length > 1) {
              const groupedByTime = bookingArray.reduce((acc, curr) => {
                const timeKey = `${curr.hour_start}-${curr.hour_end}`;
                if (!acc[timeKey]) {
                  acc[timeKey] = [];
                }
                acc[timeKey].push(curr);
                return acc;
              }, {});
              for (const group in groupedByTime) {
                bookingArrayComplete.push(groupedByTime[group]);
              }

            } else {
              bookingArrayComplete.push(bookingArray);
            }

            //Do the same but for each separate group
            for (const groupedBookingArray of bookingArrayComplete) {
              const firstBooking = { ...groupedBookingArray[0], bookings_number: groupedBookingArray.length, bookings_clients: groupedBookingArray };
              allBookings.push(firstBooking);
            }
          }
        }
      }
    }

    //Convert them into TASKS

    //Subgroups without bookings
    allBookings.forEach(booking => {
      if (!booking.booking) {
        // Construct the booking object
        const courseDate = booking.course.course_dates.find(date => date.id === booking.course_date_id);

        booking.booking = {
          id: booking.id
        };
        booking.date = courseDate ? courseDate.date : null;
        booking.hour_start = courseDate ? courseDate.hour_start : null;
        booking.hour_end = courseDate ? courseDate.hour_end : null;
        booking.bookings_number = booking.booking_users.length;
        booking.bookings_clients = booking.booking_users;
      }
    });


    let tasksCalendar: any = [
      //BOOKINGS
      ...allBookings.map(booking => {
        let type;
        switch (booking.course.course_type) {
          case 1:
            type = 'collective';
            break;
          case 2:
            type = 'private';
            break;
          default:
            type = 'unknown';
        }

        const dateTotalAndIndex = booking.course.course_type === 2 ? { date_total: 0, date_index: 0 } : {
          date_total: booking.course.course_dates.length,
          date_index: this.getPositionDate(booking.course.course_dates, booking.course_date_id)
        };

        //Get Sport and Degree objects
        const sport = this.sports.find(s => s.id === booking.course.sport_id);
        const degrees_sport = this.degrees.filter(degree => degree.sport_id === booking.course.sport_id);
        let degree = {};
        let booking_color = null;
        if (type == 'collective') {
          degree = this.degrees.find(degree => degree.id === booking.degree_id) || degrees_sport[0];
        }
        else if (type == 'private') {
          const sportObject = booking?.bookings_clients?.[0]?.client?.sports?.find(
            sport => sport.id === booking?.course?.sport_id
          );

          if (sportObject && sportObject.pivot && sportObject.pivot.degree_id) {
            degree = this.degrees.find(degree => degree.id === sportObject.pivot.degree_id);
          }
          if (!degree) {
            degree = degrees_sport[0];
          }
          degree = this.degrees.find(degree => degree.id === booking.degree_id) || degrees_sport[0];

          //Booking color
          booking_color = booking.color;
        }

        return {
          booking_id: booking?.booking?.id,
          booking_color: booking_color,
          date: moment(booking.date).format('YYYY-MM-DD'),
          date_full: booking.date,
          date_start: moment(booking.course.date_start).format('YYYY-MM-DD'),
          date_end: moment(booking.course.date_end).format('YYYY-MM-DD'),
          hour_start: booking.hour_start.substring(0, 5),
          hour_end: booking.hour_end ? booking.hour_end.substring(0, 5) : '20:00',
          type: type,
          name: booking.course.name,
          sport_id: booking.course.sport_id,
          sport: sport,
          degree_id: booking.degree_id,
          degree: degree,
          degrees_sport: degrees_sport,
          clients_number: booking.bookings_number,
          all_clients: booking.bookings_clients,
          max_participants: booking.course.max_participants,
          monitor_id: booking.monitor_id,
          monitor: this.defaults,
          course_id: booking.course_id,
          course_date_id: booking.course_date_id,
          course_subgroup_id: booking.course_subgroup_id,
          subgroup_number: booking.subgroup_number,
          total_subgroups: booking.total_subgroups,
          course: booking.course,
          paid: booking?.booking?.paid,
          ...dateTotalAndIndex
        };
      }),
      //NWDS -> for active_school
      ...allNwds.map(nwd => {
        let type;
        if (nwd.user_nwd_subtype_id === 1) {
          type = 'block_personal';
        } else if (nwd.user_nwd_subtype_id === 2) {
          type = 'block_payed';
        } else if (nwd.user_nwd_subtype_id === 3) {
          type = 'block_no_payed';
        } else {
          type = 'unknown';
        }
        const hourTimesNwd = nwd.full_day ? {
          hour_start: this.hoursRange[0],
          hour_end: this.hoursRange[this.hoursRange.length - 1]
        } : {
          hour_start: nwd.start_time.substring(0, 5),
          hour_end: nwd.end_time.substring(0, 5)
        };

        return {
          school_id: nwd.school_id,
          station_id: nwd.station_id,
          block_id: nwd.id,
          date: moment(nwd.start_date).format('YYYY-MM-DD'),
          date_format: moment(nwd.start_date).format('YYYY-MM-DD'),
          full_day: nwd.full_day,
          type: type,
          color: nwd.user_nwd_subtype_id === 1 ? '#bbbbbb' : nwd.color,
          name: nwd.description,
          monitor_id: nwd.monitor_id,
          monitor: this.defaults,
          user_nwd_subtype_id: nwd.user_nwd_subtype_id,
          color_block: nwd.color,
          start_date: nwd.start_date,
          end_date: nwd.end_date,
          ...hourTimesNwd
        };
      })
    ];
    this.calculateTaskPositions(tasksCalendar);
  }

  async calculateTaskPositions(tasks: any) {
    const pixelsPerMinute = 150 / 60;
    const pixelsPerMinuteWeek = 300 / ((this.hoursRange.length - 1) * 60);
    let plannerTasks = tasks.map((task: any) => {
      //Style for days
      const startTime = this.parseTime(task.hour_start);
      const startMinutes = startTime.getHours() * 60 + startTime.getMinutes();
      const rangeStart = this.parseTime(this.hoursRange[0]);
      const rangeStartMinutes = rangeStart.getHours() * 60 + rangeStart.getMinutes();
      const leftMinutes = startMinutes - rangeStartMinutes;
      const leftPixels = leftMinutes * pixelsPerMinute;

      const endTime = this.parseTime(task.hour_end);
      const endMinutes = endTime.getHours() * 60 + endTime.getMinutes();
      const durationMinutes = endMinutes - startMinutes;
      const widthPixels = durationMinutes * pixelsPerMinute;

      //Only 1 monitor
      const monitorIndex = 0;
      const topPixels = monitorIndex * 100;

      const style = {
        'left': `${leftPixels}px`,
        'width': `${widthPixels}px`,
        'top': `${topPixels}px`
      };

      //Style for weeks
      const taskDate = new Date(task.date);
      const dayOfWeek = taskDate.getDay();
      const initialLeftOffset = (dayOfWeek === 0 ? 6 : dayOfWeek - 1) * 300;

      const startTimeWeek = this.parseTime(task.hour_start);
      const rangeStartWeek = this.parseTime(this.hoursRange[0]);
      const startMinutesWeek = startTimeWeek.getHours() * 60 + startTimeWeek.getMinutes();
      const rangeStartMinutesWeek = rangeStartWeek.getHours() * 60 + rangeStartWeek.getMinutes();
      const leftMinutesWeek = startMinutesWeek - rangeStartMinutesWeek;
      const additionalLeftPixels = leftMinutesWeek * pixelsPerMinuteWeek;

      const endTimeWeek = this.parseTime(task.hour_end);
      const endMinutesWeek = endTimeWeek.getHours() * 60 + endTimeWeek.getMinutes();
      const durationMinutesWeek = endMinutesWeek - startMinutesWeek;
      const widthPixelsWeek = durationMinutesWeek * pixelsPerMinuteWeek;

      const styleWeek = {
        'left': `${initialLeftOffset + additionalLeftPixels}px`,
        'width': `${widthPixelsWeek}px`,
        'top': `${topPixels}px`
      };

      //Style for months
      const taskMonthInfo = this.getMonthWeekInfo(task.date);
      const topPixelsMonth = (taskMonthInfo.weekIndex * 100) + (monitorIndex * taskMonthInfo.totalWeeks * 100);

      const styleMonth = {
        'left': styleWeek.left,
        'width': styleWeek.width,
        'top': `${topPixelsMonth}px`
      };

      //Background color of block tasks
      if (task.type === 'block_personal' || task.type === 'block_payed' || task.type === 'block_no_payed') {
        style['background-color'] = this.hexToRgbA(task.color, 0.4);
        styleWeek['background-color'] = this.hexToRgbA(task.color, 0.4);
        styleMonth['background-color'] = this.hexToRgbA(task.color, 0.4);
      }

      return {
        ...task,
        style,
        styleWeek,
        styleMonth,
        class: `task-${task.type}`
      };
    });

    this.plannerTasks = plannerTasks;

  }

  hexToRgbA(hex: string, transparency = 1) {
    const rgb = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    if (!rgb) {
      return null;
    }
    const r = parseInt(rgb[1], 16);
    const g = parseInt(rgb[2], 16);
    const b = parseInt(rgb[3], 16);
    return `rgba(${r},${g},${b},${transparency})`;
  }

  getPositionDate(courseDates: any[], courseDateId: string): number {
    const index = courseDates.findIndex(date => date.id === courseDateId);
    return index >= 0 ? index + 1 : 0;
  }

  generateHoursRange(start: string, end: string): string[] {
    const startTime = this.parseTime(start);
    const endTime = this.parseTime(end);
    let currentTime = new Date(startTime);
    let times = [];

    while (currentTime <= endTime) {
      times.push(this.formatTime(currentTime));
      currentTime.setHours(currentTime.getHours() + 1);
    }

    return times;
  }

  generateHoursRangeMinutes(start: string, end: string): string[] {
    const startTime = this.parseTime(start);
    const endTime = this.parseTime(end);
    let currentTime = new Date(startTime);
    let times = [];

    while (currentTime <= endTime) {
      times.push(this.formatTime(currentTime));
      currentTime = new Date(currentTime.getTime() + 5 * 60000);
    }

    return times;
  }

  parseTime(timeStr: string): Date {
    const [hours, minutes] = timeStr.split(':').map(Number);
    const time = new Date();
    time.setHours(hours, minutes, 0, 0);
    return time;
  }

  formatTime(date: Date): string {
    return date.toTimeString().substring(0, 5);
  }

  getMonthWeekInfo(dateString: any) {
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = date.getMonth();
    const firstDayOfMonth = new Date(year, month, 1);
    const lastDayOfMonth = new Date(year, month + 1, 0);

    //Week index
    const startDay = firstDayOfMonth.getDay() || 7;
    //Subtract 1 so that it starts on 0
    let weekIndex = Math.ceil((date.getDate() + startDay - 1) / 7) - 1;

    //Total weeks
    const lastDayWeekDay = lastDayOfMonth.getDay() || 7;
    const daysInLastWeek = 7 - lastDayWeekDay;
    const totalWeeks = Math.ceil((lastDayOfMonth.getDate() + daysInLastWeek) / 7);

    return {
      weekIndex,
      totalWeeks
    };
  }

  goToPrevious() {
    if (this.timelineView === 'day') {
      this.currentDate = new Date(this.currentDate.setDate(this.currentDate.getDate() - 1));
    } else if (this.timelineView === 'week') {
      this.currentDate = subWeeks(this.currentDate, 1);
    } else if (this.timelineView === 'month') {
      this.currentDate = subMonths(this.currentDate, 1);
    }
    this.updateView();
  }

  goToNext() {
    if (this.timelineView === 'day') {
      this.currentDate = new Date(this.currentDate.setDate(this.currentDate.getDate() + 1));
    } else if (this.timelineView === 'week') {
      this.currentDate = addWeeks(this.currentDate, 1);
    } else if (this.timelineView === 'month') {
      this.currentDate = addMonths(this.currentDate, 1);
    }
    this.updateView();
  }

  changeView(newView: string) {
    this.timelineView = newView;
    this.updateView();
  }

  updateView() {
    if (this.timelineView === 'week') {
      const start = startOfWeek(this.currentDate, { weekStartsOn: 1 });
      const end = endOfWeek(this.currentDate, { weekStartsOn: 1 });
      this.currentWeek = `${format(start, 'dd')} - ${format(end, 'dd MMMM yyyy')}`;
    } else if (this.timelineView === 'month') {
      this.currentMonth = format(this.currentDate, 'MMMM yyyy');
      this.calculateWeeksInMonth();
    } else {
      this.currentWeek = '';
    }
    this.loadBookings(this.currentDate);
  }

  isDayInMonth(dayIndex: number, weekIndex: number): boolean {
    const week = this.weeksInMonth[weekIndex];
    const weekStartDate = new Date(week.startWeek);
    const specificDate = addDays(weekStartDate, dayIndex);
    if (isSameMonth(specificDate, this.currentDate)) {
      return !this.vacationDays.includes(moment(specificDate).format('YYYY-MM-DD'));
    }
    else {
      return false;
    }
  }

  isDayVisibleWeek(dayIndex: number) {
    const startOfWeek = moment(this.currentDate).startOf('isoWeek');
    const specificDate = startOfWeek.add(dayIndex, 'days');
    return !this.vacationDays.includes(moment(specificDate).format('YYYY-MM-DD'));
  }

  isDayVisibleDay() {
    if (this.vacationDays) {

      return !this.vacationDays.includes(moment(this.currentDate).format('YYYY-MM-DD'));
    }
  }


  // LOGIC

  toggleDetailTimeline(task: any) {
    if (task.booking_id) {
      //Load course
      this.idDetailTimeline = task.booking_id;
      this.taskDetailTimeline = task;
      this.hideBlockTimeline();
      this.hideEditBlock();
      this.showDetailTimeline = true;
    }
  }

  toggleBlockTimeline(block: any) {
    this.idBlockTimeline = block.block_id;
    this.blockDetailTimeline = block;
    this.hideDetailTimeline();
    this.hideEditBlock();
    this.showBlockTimeline = true;
  }

  hideDetailTimeline() {
    this.idDetailTimeline = null;
    this.taskDetailTimeline = null;
    this.showDetailTimeline = false;
  }

  hideBlockTimeline() {
    this.idBlockTimeline = null;
    this.blockDetailTimeline = null;
    this.showBlockTimeline = false;
  }

  goToEditCourse() {
    const dialogRef = this.dialog.open(CourseDetailModalComponent, {
      width: '100%',
      height: '1200px',
      maxWidth: '90vw',
      panelClass: 'full-screen-dialog',
      data: {
        id: this.taskDetailTimeline.course.id
      }
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) { }
    });
  }

  handleDbClickEvent(action: string, event: any, type: string, position: any, hourDay?: any, positionWeek?: any): void {

    if (type == 'day' && !this.isDayVisibleDay()) {
      return;
    }
    if (type == 'week' && !this.isDayVisibleWeek(position)) {
      return;
    }
    if (type == 'month' && !this.isDayInMonth(position, positionWeek)) {
      return;
    }
    /* GET DATE,HOUR,MONITOR -> DOUBLE CLICK */

    let dateInfo;
    let currentDate = moment(this.currentDate);
    switch (type) {
      case 'day':
        dateInfo = {
          date: this.currentDate,
          date_format: moment(this.currentDate).format('YYYY-MM-DD'),
          hour: position,
          monitor_id: this.id
        };
        break;
      case 'week':
        let mondayOfWeek = currentDate.clone().startOf('isoWeek');
        let weekDayDate = mondayOfWeek.add(position, 'days');
        dateInfo = {
          date: moment(weekDayDate).format('ddd MMM DD YYYY HH:mm:ss [GMT]ZZ (zz)'),
          date_format: moment(weekDayDate).format('YYYY-MM-DD'),
          hour: hourDay,
          monitor_id: this.id
        };
        break;
      case 'month':
        let firstDayOfMonth = currentDate.clone().startOf('month');
        let startOfWeek = firstDayOfMonth.add(positionWeek, 'weeks');
        startOfWeek.startOf('isoWeek');
        let monthDayDate = startOfWeek.add(position, 'days');
        dateInfo = {
          date: moment(monthDayDate).format('ddd MMM DD YYYY HH:mm:ss [GMT]ZZ (zz)'),
          date_format: moment(monthDayDate).format('YYYY-MM-DD'),
          hour: hourDay,
          monitor_id: this.id
        };
        break;
      default:
        throw new Error('Invalid type');
    }
    /* END DATA DOUBLE CLICK */
    const dialogRef = this.dialog.open(CalendarEditComponent, {
      data: {
        event,
        monitor_id: dateInfo.monitor_id,
        date_param: dateInfo.date_format,
        hour_start: dateInfo.hour,
        monitor: this.defaults
      }
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {

        if (result.end_date && moment(result.end_date).isAfter(result.start_date)) {
          //RANGE OF DATES
          const dates = [];
          let currentDate = moment(result.start_date);
          const endDate = moment(result.end_date);

          while (currentDate <= endDate) {
            dates.push(currentDate.format('YYYY-MM-DD'));
            currentDate = currentDate.add(1, 'days');
          }

          //Promise for each date
          const promises = dates.map(date => {
            const data = {
              default: false, user_nwd_subtype_id: result.user_nwd_subtype_id, color: result.color, monitor_id: dateInfo.monitor_id, start_date: date, end_date: date, start_time: result.full_day ? null : `${result.start_time}:00`, end_time: result.full_day ? null : `${result.end_time}:00`, full_day: result.full_day, station_id: result.station_id, school_id: result.school_id, description: result.description
            };
            return this.crudService.create('/monitor-nwds', data).toPromise();
          });

          Promise.allSettled(promises).then(results => {
            const failedDates = [];
            results.forEach((result, index) => {
              if (result.status === 'rejected') {
                failedDates.push(dates[index]);
              }
            });

            this.loadBookings(this.currentDate);
            if (failedDates.length === 0) {
              this.snackbar.open(this.translateService.instant('all_events_created'), 'OK', { duration: 3000 });
            } else {
              this.snackbar.open(`${this.translateService.instant('some_dates_overlap')} : ${failedDates.join(', ')}`, 'OK', { duration: 4000 });
            }
          }).catch(error => {
            console.error('Error in range dates:', error);
            this.snackbar.open(this.translateService.instant('error'), 'OK', { duration: 3000 });
          });

        }
        else {
          //ONLY 1 DAY
          const data = {
            default: false, user_nwd_subtype_id: result.user_nwd_subtype_id, color: result.color, monitor_id: dateInfo.monitor_id, start_date: result.start_date, end_date: result.end_date, start_time: result.full_day ? null : `${result.start_time}:00`, end_time: result.full_day ? null : `${result.end_time}:00`, full_day: result.full_day, station_id: result.station_id, school_id: result.school_id, description: result.description
          }
          this.crudService.create('/monitor-nwds', data)
            .subscribe((data) => {

              //this.getData();
              this.loadBookings(this.currentDate);
              this.snackbar.open(this.translateService.instant('event_created'), 'OK', { duration: 3000 });
            },
              (error) => {
                // Error handling code
                console.error('Error occurred:', error);
                if (error.error && error.error.message && error.error.message == "El monitor está ocupado durante ese tiempo y no se puede crear el MonitorNwd") {
                  this.snackbar.open(this.translateService.instant('monitor_busy'), 'OK', { duration: 3000 });
                }
                else {
                  this.snackbar.open(this.translateService.instant('error'), 'OK', { duration: 3000 });
                }
              })
        }

        //CHANGE
        /*let id = 1
        result.monitor_id = id;

        const isOverlap = this.eventService.isOverlap(this.events, result);
        if (isOverlap.length === 0) {
          if (result.user_nwd_subtype_id !== 0) {

            this.crudService.create('/monitor-nwds', result)
            .subscribe((data) => {

              this.getData();
              this.snackbar.open('Event created');
            })
          }
        } else {

          const updateEdit = this.events[isOverlap[0].overlapedId].id;
          this.crudService.update('/monitor-nwds', isOverlap[0].dates[0], updateEdit)
            .subscribe((data) => {
              isOverlap[0].dates[1].start_time = data.data.end_time;
              this.crudService.create('/monitor-nwds', isOverlap[0].dates[1])
              .subscribe((data) => {

                this.getData();
                this.snackbar.open('Event created');
              })
            })
          // hacer el update y el create
          this.snackbar.open('Existe un solapamiento', 'OK', {duration: 3000});
        }*/

        this.getData();

      } else {
        this.getData();
      }
    });
  }

  /* Edit blocks */

  openEditBlock() {
    this.allHoursDay = this.blockDetailTimeline.full_day;
    this.startTimeDay = this.blockDetailTimeline.hour_start;
    this.endTimeDay = this.blockDetailTimeline.hour_end;
    this.nameBlockDay = this.blockDetailTimeline.name;
    this.divideDay = false;
    this.startTimeDivision = '';
    this.endTimeDivision = '';
    this.showEditBlock = true;
  }

  hideEditBlock() {
    this.showEditBlock = false;
  }

  onStartTimeDayChange() {
    const filteredEndHours = this.filteredEndHoursDay;

    if (!filteredEndHours.includes(this.endTimeDay)) {
      this.endTimeDay = filteredEndHours[0] || '';
    }
  }

  get filteredEndHoursDay() {
    const startIndex = this.hoursRangeMinutes.indexOf(this.startTimeDay);
    return this.hoursRangeMinutes.slice(startIndex + 1);
  }

  onStartTimeDivisionChange() {
    const filteredEndHours = this.filteredEndHoursDivision;
    if (!filteredEndHours.includes(this.endTimeDivision)) {
      this.endTimeDivision = filteredEndHours[0] || '';
    }
  }

  get filteredStartHoursDivision() {
    const startIndex = this.allHoursDay ? this.hoursRangeMinutes.indexOf(this.hoursRangeMinutes[0]) : this.hoursRangeMinutes.indexOf(this.startTimeDay);
    const endIndex = this.allHoursDay ? this.hoursRangeMinutes.indexOf(this.hoursRangeMinutes[this.hoursRangeMinutes.length - 1]) : this.hoursRangeMinutes.indexOf(this.endTimeDay);
    return this.hoursRangeMinutes.slice(startIndex + 1, endIndex - 1);
  }

  get filteredEndHoursDivision() {
    const defaultStartIndex = this.calculateDefaultStartTimeDivisionIndex();
    const startIndex = this.startTimeDivision ? this.hoursRangeMinutes.indexOf(this.startTimeDivision) : defaultStartIndex;
    const endIndex = this.allHoursDay ? this.hoursRangeMinutes.indexOf(this.hoursRangeMinutes[this.hoursRangeMinutes.length - 1]) : this.hoursRangeMinutes.indexOf(this.endTimeDay);
    return this.hoursRangeMinutes.slice(startIndex + 1, endIndex);
  }

  calculateDefaultStartTimeDivisionIndex() {
    const blockStartTimeIndex = this.allHoursDay ? this.hoursRangeMinutes.indexOf(this.hoursRangeMinutes[0]) : this.hoursRangeMinutes.indexOf(this.startTimeDay);
    return blockStartTimeIndex + 1;
  }

  isButtonDayEnabled() {
    if (this.divideDay) {
      return this.nameBlockDay && this.startTimeDivision && this.endTimeDivision && (this.allHoursDay || (this.startTimeDay && this.endTimeDay));
    } else {
      return this.nameBlockDay && (this.allHoursDay || (this.startTimeDay && this.endTimeDay));
    }
  }

  saveEditedBlock() {
    const commonData = {
      monitor_id: this.blockDetailTimeline.monitor_id,
      school_id: this.blockDetailTimeline.school_id,
      station_id: this.blockDetailTimeline.station_id,
      description: this.nameBlockDay,
      color: this.blockDetailTimeline.color_block,
      user_nwd_subtype_id: this.blockDetailTimeline.user_nwd_subtype_id,
    };
    let firstBlockData: any = { ...commonData, start_date: this.blockDetailTimeline.start_date, end_date: this.blockDetailTimeline.end_date };
    let secondBlockData: any;

    // Calculate time moments
    firstBlockData.start_time = this.allHoursDay ? `${this.hoursRangeMinutes[0]}:00` : `${this.startTimeDay}:00`;
    firstBlockData.end_time = this.divideDay ? `${this.startTimeDivision}:00` : (this.allHoursDay ? `${this.hoursRangeMinutes[this.hoursRangeMinutes.length - 1]}:00` : `${this.endTimeDay}:00`);
    firstBlockData.full_day = this.allHoursDay && !this.divideDay;
    // Function update first block -> CALL LATER
    const updateFirstBlock = () => {
      this.crudService.update('/monitor-nwds', firstBlockData, this.blockDetailTimeline.block_id).subscribe(
        response => {
          this.hideEditBlock();
          this.hideBlockTimeline();
          this.loadBookings(this.currentDate);
        },
        error => {
          console.error('Error updating first block:', error);
        }
      );
    };

    if (this.divideDay) {
      secondBlockData = { ...commonData, start_date: this.blockDetailTimeline.start_date, end_date: this.blockDetailTimeline.end_date, start_time: `${this.endTimeDivision}:00`, end_time: `${this.endTimeDay}:00`, full_day: false };

      this.crudService.post('/monitor-nwds', secondBlockData).subscribe(
        secondResponse => {
          updateFirstBlock();
        },
        error => {
          console.error('Error creating second block:', error);
        }
      );

    } else {
      // Update FIRST
      updateFirstBlock();
    }
  }

  deleteEditedBlock() {
    const isConfirmed = confirm('Êtes-vous sûr de vouloir supprimer le blocage?');
    if (isConfirmed) {
      this.crudService.delete('/monitor-nwds', this.blockDetailTimeline.block_id).subscribe(
        response => {
          this.hideEditBlock();
          this.hideBlockTimeline();
          this.loadBookings(this.currentDate);
        },
        error => {
          console.error('Error:', error);
        }
      );
    }
  }

  getDayOfWeek(dayIndex: number): number {
    const startOfWeek = moment(this.currentDate).startOf('isoWeek');
    const specificDate = startOfWeek.add(dayIndex, 'days');
    return specificDate.date();
  }

  /*
  getDayOfMonth(weekIndex: number, dayIndex: number): string {
    const startOfWeek = moment(startOfMonth(this.currentDate)).add(weekIndex, 'weeks');
    const specificDate = startOfWeek.startOf('isoWeek').add(dayIndex, 'days');
    if (specificDate.month() === this.currentDate.getMonth()) {
        return specificDate.format('D');
    }
    return '';
  }
  */

  detailBooking() {
    const dialogRef = this.dialog.open(BookingDetailModalComponent, {
      width: '100%',
      height: '1200px',
      maxWidth: '90vw',
      panelClass: 'full-screen-dialog',
      data: {
        id: this.taskDetailTimeline.booking_id,
      }
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
        this.snackbar.open(this.translateService.instant('snackbar.booking.create'), 'OK', { duration: 3000 });
      }
    });
  }

  openUserTransfer() {
    const dialogRef = this.dialog.open(CourseUserTransferTimelineComponent, {
      width: '800px',
      height: '800px',
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales
      data: {
        degree: this.taskDetailTimeline.degree, subgroup: this.taskDetailTimeline.course_subgroup_id, id: this.taskDetailTimeline.course_id,
        subgroupNumber: this.taskDetailTimeline.subgroup_number, currentDate: moment(this.taskDetailTimeline.date), degrees: this.taskDetailTimeline.degrees_sport
      }
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
        dialogRef.close();
      }
    });
  }

  setActive(event) {
    this.defaultsUser.active = event.checked;
  }
}
