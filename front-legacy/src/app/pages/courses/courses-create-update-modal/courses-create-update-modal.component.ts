import { Component, Inject, OnInit, ViewChild } from '@angular/core';
import { FormControl, UntypedFormBuilder, UntypedFormGroup, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialog, MatDialogRef } from '@angular/material/dialog';
import { Observable, map, of, startWith } from 'rxjs';
import { fadeInUp400ms } from 'src/@vex/animations/fade-in-up.animation';
import { stagger20ms } from 'src/@vex/animations/stagger.animation';
import { DateTimeDialogComponent } from 'src/@vex/components/date-time-dialog/date-time-dialog.component';
import { MatSort } from '@angular/material/sort';
import { MatTable, MatTableDataSource } from '@angular/material/table';
import * as moment from 'moment';
import { ReductionDialogComponent } from 'src/@vex/components/reduction-dialog/reduction-dialog.component';
import { PrivateDatesDialogComponent } from 'src/@vex/components/private-dates-dialog/private-dates-dialog.component';
import { ApiCrudService } from 'src/service/crud.service';
import { ActivatedRoute, Router } from '@angular/router';
import { SchoolService } from 'src/service/school.service';
import { MatStepper } from '@angular/material/stepper';
import { MatSnackBar } from '@angular/material/snack-bar';
import { TranslateService } from '@ngx-translate/core';
import { DateAdapter } from '@angular/material/core';

@Component({
  selector: 'vex-courses-create-modal-update',
  templateUrl: './courses-create-update-modal.component.html',
  styleUrls: ['./courses-create-update-modal.component.scss'],
  animations: [fadeInUp400ms, stagger20ms]
})
export class CoursesCreateUpdateModalComponent implements OnInit {

  @ViewChild(MatSort, { static: true }) sort: MatSort;
  @ViewChild('dateTable') dateTable: MatTable<any>;
  @ViewChild('reductionTable') reductionTable: MatTable<any>;
  @ViewChild('privateDatesTable') privateDatesTable: MatTable<any>;
  @ViewChild('privateReductionTable') privateReductionTable: MatTable<any>;
  @ViewChild('levelTable') table: MatTable<any>;

  userAvatar = '../../../../assets/img/avatar.png';

  people = 6; // Aquí puedes cambiar el número de personas
  intervalos = Array.from({ length: 28 }, (_, i) => 15 + i * 15);

  hours: string[] = [
    '00:00', '00:15', '00:30', '00:45',
    '01:00', '01:15', '01:30', '01:45',
    '02:00', '02:15', '02:30', '02:45',
    '03:00', '03:15', '03:30', '03:45',
    '04:00', '04:15', '04:30', '04:45',
    '05:00', '05:15', '05:30', '05:45',
    '06:00', '06:15', '06:30', '06:45',
    '07:00', '07:15', '07:30', '07:45',
    '08:00', '08:15', '08:30', '08:45',
    '09:00', '09:15', '09:30', '09:45',
    '10:00', '10:15', '10:30', '10:45',
    '11:00', '11:15', '11:30', '11:45',
    '12:00', '12:15', '12:30', '12:45',
    '13:00', '13:15', '13:30', '13:45',
    '14:00', '14:15', '14:30', '14:45',
    '15:00', '15:15', '15:30', '15:45',
    '16:00', '16:15', '16:30', '16:45',
    '17:00', '17:15', '17:30', '17:45',
    '18:00', '18:15', '18:30', '18:45',
    '19:00', '19:15', '19:30', '19:45',
    '20:00', '20:15', '20:30', '20:45',
    '21:00', '21:15', '21:30', '21:45',
    '22:00', '22:15', '22:30', '22:45',
    '23:00'
  ];
  filteredToHours = [];

  summary = ``;
  description = ``;

  daysDates = [];
  daysDatesLevels = [];
  days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
  startDayControl = new FormControl();
  endDayControl = new FormControl();
  availableEndDays: string[] = [];

  //separatedDates = false;
  displayedColumns: string[] = ['date', 'duration', 'hour', 'delete'];
  displayedReductionsColumns: string[] = ['date', 'percentage', 'delete'];
  displayedPrivateDateColumns: string[] = ['dateFrom', 'dateTo', 'delete'];
  dataSource: any = new MatTableDataSource([]);
  dataSourceReductions = new MatTableDataSource([]);
  dataSourceDatePrivate: any = new MatTableDataSource([]);
  dataSourceReductionsPrivate = new MatTableDataSource([]);

  dataSourceFlexiblePrices: any;
  displayedColumnsFlexiblePrices: string[] = ['intervalo', ...Array.from({ length: this.people }, (_, i) => `${i + 1}`)];

  myControl = new FormControl();
  myControlSport = new FormControl();
  myControlStations = new FormControl(Validators.required);
  monitorsForm = new FormControl();

  options: any[] = [{ id: 1, name: 'Cours collectif' }, { id: 2, name: 'Cours privés' }];
  stations = [];

  filteredOptions: Observable<any[]>;
  filteredSports: Observable<any[]>;
  filteredStations: Observable<any[]>;
  filteredMonitors: Observable<any[]>;

  courseTypeFormGroup: UntypedFormGroup;
  courseInfoFormGroup: UntypedFormGroup;
  courseInfoPriveFormGroup: UntypedFormGroup;
  courseInfoPriveSeparatedFormGroup: UntypedFormGroup;
  courseInfoCollecDateSplitFormGroup: UntypedFormGroup;
  courseLevelFormGroup: UntypedFormGroup;
  rangeForm: UntypedFormGroup;

  // Nuevos
  courseConfigForm: UntypedFormGroup;

  imagePreviewUrl: string | ArrayBuffer = null;

  minDate = new Date();
  maxDate = new Date();
  from: any = null;
  to: any = null;
  daySelectedIndex: any = 0;
  subGroupSelectedIndex: any = 0;
  selectedDate: string;
  selectedItem: any;
  selectedTabNameIndex: any = 0;
  selectedTabDescIndex: any = 0;
  loadingMonitors = true;
  defaults: any = {
    unique: false,
    course_type: null,
    is_flexible: false,
    name: null,
    short_description: null,
    description: null,
    price: null,
    currency: '',
    date_start: null,
    date_end: null,
    date_start_res: null,
    date_end_res: null,
    confirm_attendance: false,
    active: true,
    online: true,
    image: this.imagePreviewUrl,
    translations:
    {
      es: {
        name: '',
        short_description: '',
        description: ''
      },
      en: {
        name: '',
        short_description: '',
        description: ''
      },
      fr: {
        name: '',
        short_description: '',
        description: ''
      },
      it: {
        name: '',
        short_description: '',
        description: ''
      },
      de: {
        name: '',
        short_description: '',
        description: ''
      },
    },
    price_range: null,
    discounts: null,
    settings: {
      weekDays: {
        monday: false,
        tuesday: false,
        wednesday: false,
        thursday: false,
        friday: false,
        saturday: false,
        sunday: false
      }
    },
    sport_id: null,
    school_id: null,
    station_id: null,
    max_participants: null,
    duration: null,
    hour_min: null,
    hour_max: null,
    age_min: null,
    age_max: null,
    course_dates: []
  };

  defaults_course_date = {
    date: null,
    hour_start: null,
    hour_end: null,
  }

  defaults_groups = {
    course_id: null,
    course_date_id: null,
    degree_id: null,
    age_min: null,
    age_max: null,
    recommended_age: null,
    teachers_min: null,
    teachers_max: null,
    observations: null,
    auto: null
  }

  defaults_subgroups = {
    course_id: null,
    course_date_id: null,
    degree_id: null,
    course_group_id: null,
    monitor_id: null,
    max_participants: null,
  }

  sportTypeSelected: number = -1;
  sportData = [];
  sportDataList = [];
  sportTypeData = [];
  levels = [];
  monitors = [];

  groupedByColor = {};
  colorKeys: string[] = []; // Aquí almacenaremos las claves de colores
  selectedCourses = new MatTableDataSource([]);
  displayedCourseColumns: string[] = ['course', 'min', 'max', 'levels', 'checkbox', 'delete'];

  mode: 'create' | 'update' = 'create';
  loading: boolean = true;
  loadingTable: boolean = false;

  durations = [];
  filteredMaxDurations = [];
  courseType: any = null;
  courseComplete: boolean = false;
  user: any;
  id: any = null;

  schoolData: any = [];
  schoolPriceRanges: any = [];
  season: any = [];
  holidays: any = [];

  myHolidayDates = [];

  constructor(private fb: UntypedFormBuilder, public dialog: MatDialog, private crudService: ApiCrudService, private router: Router, private activatedRoute: ActivatedRoute, private dialogRef: MatDialogRef<any>,
    private schoolService: SchoolService, private snackbar: MatSnackBar, private translateService: TranslateService,
    @Inject(MAT_DIALOG_DATA) public externalData: any, private dateAdapter: DateAdapter<Date>) {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.id = this.externalData && this.externalData.id ? this.externalData.id : this.activatedRoute.snapshot.params.id;
    this.dateAdapter.setLocale(this.translateService.getDefaultLang());
    this.dateAdapter.getFirstDayOfWeek = () => { return 1; }
    this.generateDurations();

    this.startDayControl.valueChanges.subscribe(startDay => {
      const index = this.days.indexOf(startDay);
      if (index !== -1) {
        this.availableEndDays = this.days.slice(index + 1);
        this.endDayControl.enable();
        this.endDayControl.setValue(null); // Reset end day if start day changes
      } else {
        this.endDayControl.disable();
      }
    });

    this.rangeForm = this.fb.group({
      minAge: ['', [Validators.required, Validators.min(3)]],
      maxAge: ['', [Validators.required, Validators.max(80)]]
    }, { validator: this.ageRangeValidator });

  }

  ageRangeValidator(group: UntypedFormGroup): { [key: string]: any } | null {
    const minAge = group.get('minAge').value;
    const maxAge = group.get('maxAge').value;
    return minAge && maxAge && minAge < maxAge ? null : { 'ageRange': true };
  }

  get minAge() {
    return this.rangeForm.get('minAge');
  }

  get maxAge() {
    return this.rangeForm.get('maxAge');
  }

  ngAfterViewInit() {
    this.dataSource.sort = this.sort;
  }

  ngOnInit() {

    if (!this.id) {
      this.mode = 'create';
    } else {
      this.mode = 'update';
    }

    this.getSportsType();
    this.getSports();
    this.getStations();
    this.getMonitors();

    this.schoolService.getSchoolData()
      .subscribe((data) => {
        this.schoolData = data.data;
        this.crudService.list('/seasons', 1, 10000, 'desc', 'id', '&school_id=' + data.data.id + '&is_active=1')
          .subscribe((season) => {
            this.season = season.data[0];
            // Extrae las horas de inicio y fin
            const hourStart = this.season.hour_start.substr(0, 5); // '08:00'
            const hourEnd = this.season.hour_end.substr(0, 5); // '17:00'

            // Filtra el array de horas
            this.hours = this.hours.filter(hour => hour >= hourStart && hour <= hourEnd);

            this.minDate = moment(this.season.start_date, 'YYYY-MM-DD').isSameOrAfter(this.minDate) ? moment(this.season.start_date, 'YYYY-MM-DD').toDate() : this.minDate;
            this.maxDate = moment(this.season.end_date).toDate();

            this.holidays = this.season.vacation_days !== null && this.season.vacation_days !== '' ? JSON.parse(this.season.vacation_days) : [];

            this.holidays.forEach(element => {
              this.myHolidayDates.push(moment(element).toDate());
            });
          });
        this.schoolPriceRanges = JSON.parse(data.data.settings)?.prices_range;
        this.people = this.schoolPriceRanges?.people ? this.schoolPriceRanges.people : 6;
        this.displayedColumnsFlexiblePrices = ['intervalo', ...Array.from({ length: this.people }, (_, i) => `${i + 1}`)];
        this.dataSourceFlexiblePrices = this.schoolPriceRanges && this.schoolPriceRanges.prices && this.schoolPriceRanges.prices !== null ? this.schoolPriceRanges.prices :
          this.intervalos.map(intervalo => {
            const fila: any = { intervalo: this.formatIntervalo(intervalo) };
            for (let i = 1; i <= this.people; i++) {
              fila[`${i}`] = '';
            }
            return fila;
          });
      })

    if (this.mode === 'update') {
      this.crudService.get('/admin/courses/' + this.id)
        .subscribe((course) => {
          this.defaults = course.data;
          if (this.defaults.translations === null) {
            this.defaults.translations = {
              es: {
                name: '',
                short_description: '',
                description: ''
              },
              en: {
                name: '',
                short_description: '',
                description: ''
              },
              fr: {
                name: '',
                short_description: '',
                description: ''
              },
              it: {
                name: '',
                short_description: '',
                description: ''
              },
              de: {
                name: '',
                short_description: '',
                description: ''
              },
            };

          } else {
            this.defaults.translations = JSON.parse(this.defaults.translations);
          }
          this.defaults.translations.fr.name = this.defaults.name;
          this.defaults.translations.fr.short_description = this.defaults.short_description;
          this.defaults.translations.fr.description = this.defaults.description;
          this.defaults.hour_min = this.defaults.course_dates[0].hour_start.replace(': 00', '');
          this.defaults.hour_max = this.defaults.course_dates[0].hour_end.replace(': 00', '');
          this.people = this.defaults.max_participants;
          this.defaults.course_dates = this.sortEventsByDate();
          this.defaults.settings = typeof course.data.settings === 'string' ? JSON.parse(course.data.settings) : course.data.settings;
          /*this.dataSourceFlexiblePrices = this.defaults.price_range;
          this.updateTable(null);*/
          this.getSeparatedDates(this.defaults.course_dates, true);
          this.getDegrees();

          this.courseTypeFormGroup = this.fb.group({

            sportType: [1, Validators.required], // Posiblemente establezcas un valor predeterminado aquí
            sport: [null, Validators.required],
            courseType: [null, Validators.required],
            separatedDates: [false]
          })

          this.courseInfoFormGroup = this.fb.group({

            course_name: [null, Validators.required],
            course_name_en: [null],
            course_name_de: [null],
            course_name_es: [null],
            course_name_it: [null],
            price: [null],
            station: [null, Validators.required],
            summary: [null, Validators.required],
            description: [null, Validators.required],
            summary_en: [null],
            description_en: [null],
            summary_de: [null],
            description_de: [null],
            summary_es: [null],
            description_es: [null],
            summary_it: [null],
            description_it: [null],
            image: [null],
            ageFrom: [null],
            ageTo: [null],
          })


          this.courseInfoPriveFormGroup = this.fb.group({

            duration: [null],
            minDuration: [null],
            maxDuration: [null],
            fromHour: [this.defaults.course_dates[0].hour_start.replace(': 00', ''), Validators.required],
            toHour: [this.defaults.course_dates[0].hour_end.replace(': 00', ''), Validators.required],
            participants: [this.defaults.max_participants, Validators.required],
            fromDate: [this.toDate(this.defaults.date_start)],
            toDate: [this.toDate(this.defaults.date_end)],
            fromDateUnique: [null],
            toDateUnique: [null],
            from: [this.toDate(this.defaults.date_start)],
            to: [this.toDate(this.defaults.date_end)],
            image: [null],
            periodeUnique: [this.defaults.unique],
            periodeMultiple: [!this.defaults.unique]
          });

          this.courseInfoPriveFormGroup.controls.periodeUnique.patchValue(this.defaults.unique);
          this.courseInfoPriveFormGroup.controls.periodeMultiple.patchValue(!this.defaults.unique);
          this.courseInfoPriveFormGroup.controls.periodeUnique.disable();
          this.courseInfoPriveFormGroup.controls.periodeMultiple.disable();

          this.courseLevelFormGroup = this.fb.group({});

          this.courseInfoCollecDateSplitFormGroup = this.fb.group({
            course_name: [null, Validators.required],

            course_name_en: [null],
            course_name_de: [null],
            course_name_es: [null],
            course_name_it: [null],
            price: [null],
            station: [null, Validators.required],
            summary: [null, Validators.required],
            description: [null, Validators.required],

            summary_en: [null],
            description_en: [null],
            summary_de: [null],
            description_de: [null],
            summary_es: [null],
            description_es: [null],
            summary_it: [null],
            description_it: [null],
            duration: [null, Validators.required],
            participants: [null, Validators.required],
            image: [null],
          });

          this.courseInfoPriveSeparatedFormGroup = this.fb.group({
            course_name: [null, Validators.required],

            course_name_en: [null],
            course_name_de: [null],
            course_name_es: [null],
            course_name_it: [null],
            price: ['Flexible'],
            station: [null, Validators.required],
            summary: [null, Validators.required],
            description: [null, Validators.required],
            summary_en: [null],
            description_en: [null],
            summary_de: [null],
            description_de: [null],
            summary_es: [null],
            description_es: [null],
            summary_it: [null],
            description_it: [null],
            duration: [null, Validators.required],
            participants: [null, Validators.required],
            image: [null],
          });

          this.courseConfigForm = this.fb.group({

            fromDate: [this.toDate(this.defaults.date_start), Validators.required],
            toDate: [this.toDate(this.defaults.date_end), Validators.required],
            from: [this.toDate(this.defaults.date_start)],
            to: [this.toDate(this.defaults.date_end)],
            duration: [null],
            participants: [null, Validators.required],
          });

          this.filteredOptions = this.myControl.valueChanges
            .pipe(
              startWith(''),
              map(value => this._filter(value))
            );

          this.filteredStations = this.myControlStations.valueChanges
            .pipe(
              startWith(''),
              map(value => typeof value === 'string' ? value : value.name),
              map(name => name ? this._filterStations(name) : this.stations.slice())
            );

          this.filteredSports = this.myControlSport.valueChanges.pipe(
            startWith(''),
            map((value: any) => typeof value === 'string' ? value : value?.name),
            map(name => name ? this._filterSport(name) : this.sportData.slice())
          );

          this.filteredMonitors = this.monitorsForm.valueChanges.pipe(
            startWith(''),
            map((value: any) => typeof value === 'string' ? value : value?.full_name),
            map(full_name => full_name ? this._filterMonitor(full_name) : this.monitors.slice())
          );

          this.myControl.valueChanges.subscribe(value => {
            this.courseTypeFormGroup.get('courseType').setValue(value);
          });

          this.myControlSport.valueChanges.subscribe(value => {
            this.courseTypeFormGroup.get('sport').setValue(value);
          });

          this.myControlStations.valueChanges.subscribe(value => {
            this.courseInfoFormGroup.get('station').setValue(value);
          });

          this.courseInfoPriveFormGroup.get('minDuration').valueChanges.subscribe(selectedDuration => {
            this.updateMaxDurationOptions(selectedDuration);
          });

          this.courseInfoPriveFormGroup.get('fromHour').valueChanges.subscribe(selectedStartHour => {
            this.updateToHourOptions(selectedStartHour);
          });


          if (this.defaults.course_type === 2 && this.defaults.is_flexible) {
            this.defaults.course_dates.forEach(element => {
              this.dataSourceDatePrivate.data.push({ dateFrom: moment(element.date).format('YYYY-MM-DD'), dateTo: moment(element.date).format('YYYY-MM-DD'), active: element.active, id: element.id });
            });

            this.dataSourceReductionsPrivate.data = JSON.parse(this.defaults.discounts);
          }
          if (this.defaults.course_type === 1) {
            this.defaults.course_dates.forEach(element => {
              this.dataSource.data.push({
                date: moment(element.date).format('YYYY-MM-DD'), hour: element.hour_start + ' - ' + element.hour_end,
                duration: this.calculateFormattedDuration(element.hour_start, element.hour_end), active: element.active, id: element.id
              });
            });

            if (this.defaults.is_flexible) {
              this.dataSourceReductions.data = JSON.parse(this.defaults.discounts);
            }
          }


          setTimeout(() => {
            this.filterSportsByType();
            this.defaults.station_id = this.stations.filter((s) => s.id === this.defaults.station_id)[0];
            this.loading = false;
          }, 500);
        })
    } else {
      this.courseTypeFormGroup = this.fb.group({

        sportType: [1, Validators.required], // Posiblemente establezcas un valor predeterminado aquí
        sport: [null, Validators.required],
        courseType: [null, Validators.required],
        separatedDates: [false]
      })

      this.courseInfoFormGroup = this.fb.group({

        course_name: [null, Validators.required],
        course_name_en: [null],
        course_name_de: [null],
        course_name_es: [null],
        course_name_it: [null],
        price: [null],
        station: [null, Validators.required],
        summary: [null, Validators.required],
        description: [null, Validators.required],

        summary_en: [null],
        description_en: [null],
        summary_de: [null],
        description_de: [null],
        summary_es: [null],
        description_es: [null],
        summary_it: [null],
        description_it: [null],
        duration: [null],
        participants: [null],
        ageFrom: [null],
        ageTo: [null],
        image: [null],
      })


      this.courseInfoPriveFormGroup = this.fb.group({

        duration: [null, Validators.required],
        minDuration: [null],
        maxDuration: [null],
        fromHour: [null, Validators.required],
        toHour: [null, Validators.required],
        participants: [null, Validators.required],
        fromDate: [null, Validators.required],
        toDate: [null, Validators.required],
        from: [null],
        to: [null],
        image: [null],
        fromDateUnique: [null],
        toDateUnique: [null],
        periodeUnique: new FormControl(true),
        periodeMultiple: new FormControl(false)
      })

      this.courseLevelFormGroup = this.fb.group({});

      this.courseInfoCollecDateSplitFormGroup = this.fb.group({
        course_name: [null, Validators.required],
        course_name_en: [null],
        course_name_de: [null],
        course_name_es: [null],
        course_name_it: [null],
        price: [null],
        station: [null, Validators.required],
        summary: [null, Validators.required],
        description: [null, Validators.required],

        summary_en: [null],
        description_en: [null],
        summary_de: [null],
        description_de: [null],
        summary_es: [null],
        description_es: [null],
        summary_it: [null],
        description_it: [null],
        duration: [null, Validators.required],
        participants: [null, Validators.required],
        image: [null],
      });

      this.courseInfoPriveSeparatedFormGroup = this.fb.group({
        course_name: [null, Validators.required],
        course_name_en: [null],
        course_name_de: [null],
        course_name_es: [null],
        course_name_it: [null],
        price: ['Flexible'],
        station: [null, Validators.required],
        summary: [null, Validators.required],
        description: [null, Validators.required],

        summary_en: [null],
        description_en: [null],
        summary_de: [null],
        description_de: [null],
        summary_es: [null],
        description_es: [null],
        summary_it: [null],
        description_it: [null],
        duration: [null, Validators.required],
        participants: [null, Validators.required],
        image: [null],
      });

      this.courseConfigForm = this.fb.group({

        fromDate: [null, Validators.required],
        toDate: [null, Validators.required],
        from: [null],
        to: [null],
        duration: [null],
        participants: [null, Validators.required],
      });

      this.filteredOptions = this.myControl.valueChanges
        .pipe(
          startWith(''),
          map(value => this._filter(value))
        );

      this.filteredStations = this.myControlStations.valueChanges
        .pipe(
          startWith(''),
          map(value => typeof value === 'string' ? value : value.name),
          map(name => name ? this._filterStations(name) : this.stations.slice())
        );

      this.filteredSports = this.myControlSport.valueChanges.pipe(
        startWith(''),
        map((value: any) => typeof value === 'string' ? value : value?.name),
        map(name => name ? this._filterSport(name) : this.sportData.slice())
      );

      this.filteredMonitors = this.monitorsForm.valueChanges.pipe(
        startWith(''),
        map((value: any) => typeof value === 'string' ? value : value?.full_name),
        map(full_name => full_name ? this._filterMonitor(full_name) : this.monitors.slice())
      );

      this.myControl.valueChanges.subscribe(value => {
        this.courseTypeFormGroup.get('courseType').setValue(value);
      });

      this.myControlSport.valueChanges.subscribe(value => {
        this.courseTypeFormGroup.get('sport').setValue(value);
      });

      this.myControlStations.valueChanges.subscribe(value => {
        this.courseInfoFormGroup.get('station').setValue(value);
      });

      this.courseInfoPriveFormGroup.get('minDuration').valueChanges.subscribe(selectedDuration => {
        this.updateMaxDurationOptions(selectedDuration);
      });

      this.courseInfoPriveFormGroup.get('fromHour').valueChanges.subscribe(selectedStartHour => {
        this.updateToHourOptions(selectedStartHour);
      });

      setTimeout(() => {
        this.filterSportsByType();
        this.loading = false;
      }, 500);
    }
  }

  get periodeUnique() {
    return this.courseInfoPriveFormGroup.get('periodeUnique').value;
  }

  get periodeMultiple() {
    return this.courseInfoPriveFormGroup.get('periodeMultiple').value;
  }

  areAllTrue(obj) {
    return Object.values(obj).every(value => value === true);
  }

  onCheckboxChange(type: string) {
    if (type === 'unique') {
      this.courseInfoPriveFormGroup.patchValue({ periodeMultiple: false });
      this.dataSourceDatePrivate.data = [];
      this.privateDatesTable?.renderRows();

    } else {
      this.courseInfoPriveFormGroup.patchValue({ periodeUnique: false });
    }
  }

  save() {
    if (this.mode === 'create') {
      this.create();
    } else if (this.mode === 'update') {
      this.update();
    }
  }


  sortEventsByDate() {
    return this.defaults.course_dates.sort((a, b) => {
      // Convertir las fechas a objetos Date para compararlas
      const dateA = new Date(a.date);
      const dateB = new Date(b.date);

      return dateA.getTime() - dateB.getTime();
    });
  }

  goTo(route: string) {
    this.router.navigate([route]);
  }


  filterSportsByType() {
    this.sportTypeSelected = this.courseTypeFormGroup.get('sportType').value;
    let selectedSportType = this.courseTypeFormGroup.get('sportType').value;
    this.filteredSports = of(this.sportData.filter(sport => sport.sport_type === selectedSportType));
    this.sportDataList = this.sportData.filter(sport => sport.sport_type === selectedSportType);
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

  isCreateMode() {
    return this.mode === 'create';
  }

  isUpdateMode() {
    return this.mode === 'update';
  }

  private _filter(value: string): string[] {
    const filterValue = value.toLowerCase();
    return this.options.filter(option => option.name.toLowerCase().includes(filterValue));
  }


  private _filterStations(value: string): string[] {
    const filterValue = value.toLowerCase();
    return this.stations.filter(option => option.name.toLowerCase().includes(filterValue));
  }

  displayFn(sportType: any): string {
    return sportType && sportType.name ? sportType.name : '';
  }

  displayFnStation(station: any): string {
    return station && station.name ? station.name : '';
  }

  displayFnSport(sport: any): string {
    return sport && sport.name ? sport.name : '';
  }

  displayFnCourse(course: any): string {
    return course && course.name ? course.name : '';
  }

  displayFnLevel(sportType: any): string {
    return sportType && sportType.annotation && sportType.name ? sportType.annotation + ' - ' + sportType.name : '';
  }

  displayFnMoniteurs(monitor: any): string {
    return monitor && monitor.first_name && monitor.last_name ? monitor.first_name + ' ' + monitor.last_name : '';
  }

  private _filterSport(name: string): any[] {
    const filterValue = name.toLowerCase();
    return this.sportData.filter(sport => sport.name.toLowerCase().includes(filterValue));
  }

  private _filterMonitor(name: string): any[] {
    const filterValue = name.toLowerCase();
    return this.monitors.filter(monitor => monitor.first_name.toLowerCase().includes(filterValue) || monitor.last_name.toLowerCase().includes(filterValue));
  }

  generateDurations() {
    let minutes = 15;
    const maxMinutes = 7 * 60; // 7 horas en minutos
    this.durations = [];

    while (minutes <= maxMinutes) {
      const hours = Math.floor(minutes / 60);
      const remainingMinutes = minutes % 60;

      const durationString = `${hours ? hours + 'h ' : ''}${remainingMinutes}min`;
      this.durations.push({ text: durationString, value: minutes });

      minutes += 15;
    }
  }

  myHolidayFilter = (d: Date): boolean => {
    if (d !== null) {

      const time = d.getTime();
      return !this.myHolidayDates.find(x => x.getTime() == time);
    }
  }

  openDialog(): void {
    let blockedDays = this.myHolidayDates;
    if (this.mode === 'update') {

      this.dataSource.data.forEach(element => {
        if (element.active || element.active === 1) {
          blockedDays.push(moment(element.date).toDate())
        }
      });
    }

    const dialogRef = this.dialog.open(DateTimeDialogComponent, {
      width: '300px',
      data: { minDate: this.minDate, maxDate: this.maxDate, holidays: blockedDays },
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.dataSource.data.push({ date: moment(result.date).format('YYYY-MM-DD'), duration: result.duration, hour: result.hour, active: true });
        this.dateTable?.renderRows();
      }
    });
  }

  openDialogReductions(): void {
    const dialogRef = this.dialog.open(ReductionDialogComponent, {
      width: '300px',
      data: { iterations: this.dataSource.data.length }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.dataSourceReductions.data.push({ date: result.dateIndex, percentage: result.percentage });
        this.reductionTable?.renderRows();
      }
    });
  }

  openDialogPrivateReductions(): void {
    const dialogRef = this.dialog.open(ReductionDialogComponent, {
      width: '300px',
      data: { iterations: this.dataSourceDatePrivate.data.length }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.dataSourceReductionsPrivate.data.push({ date: result.dateIndex, percentage: result.percentage });
        this.privateReductionTable?.renderRows();
      }
    });
  }

  openDialogPrivateDate(): void {

    let blockedDays = this.myHolidayDates;
    if (this.mode === 'update') {

      this.dataSourceDatePrivate.data.forEach(element => {
        if (element.active || element.active === 1) {
          blockedDays.push(moment(element.dateFrom).toDate())
        }
      });
    }
    const dialogRef = this.dialog.open(PrivateDatesDialogComponent, {
      width: '300px',
      data: {
        iterations: this.dataSource.data.length,
        minDate: this.minDate,
        maxDate: this.maxDate,
        holidays: blockedDays
      }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.dataSourceDatePrivate.data.push({ dateFrom: moment(result.dateFrom).format('YYYY-MM-DD'), dateTo: moment(result.dateTo).format('YYYY-MM-DD') });
        this.privateDatesTable?.renderRows();
        this.getDatesBetween(moment(result.dateFrom), moment(result.dateTo), true);
      }
    });
  }

  onChipClick(level: any) {
    const selectedCourse: any = {
      annotation: level.annotation,
      name: level.name
    };
    this.selectedCourses.data.push(selectedCourse);
    this.table.renderRows();

  }

  removeCourse(course: any) {

    let index = -1;

    this.selectedCourses.data.forEach((element, idx) => {
      if (course.annotation === element.annotation && course.name === element.name) {
        index = idx;
      }
    });
    if (index > -1) {
      this.selectedCourses.data.splice(index, 1);
      this.table.renderRows();

    }
    // Aquí también puedes deseleccionar el chip correspondiente
  }

  removeReduction(redcution: any, index: any) {
    this.dataSourceReductions.data.splice(index, 1);
    this.reductionTable.renderRows();

    // Aquí también puedes deseleccionar el chip correspondiente
  }

  removePrivateReduction(redcution: any, index: any) {
    this.dataSourceReductionsPrivate.data.splice(index, 1);
    this.privateReductionTable.renderRows();

    // Aquí también puedes deseleccionar el chip correspondiente
  }

  removeteDate(index: any) {


    if (this.mode === 'update') {
      this.dataSource.data[index].active = false;
      this.defaults.course_dates[index].active = false;
      this.dateTable.renderRows();
    } else {
      this.dataSource.data.splice(index, 1);
      this.dateTable.renderRows();
    }

    // Aquí también puedes deseleccionar el chip correspondiente
  }

  removePrivateDate(index: any) {

    if (this.mode === 'update') {
      this.dataSourceDatePrivate.data[index].active = false;
      this.defaults.course_dates[index].active = false;
      this.privateDatesTable.renderRows();
    } else {
      this.dataSourceDatePrivate.data.splice(index, 1);
      this.privateDatesTable.renderRows();
    }


    // Aquí también puedes deseleccionar el chip correspondiente
  }

  selectSport(sport: any) {
    this.defaults.sport_id = sport.sport_id;
    this.courseTypeFormGroup.get("sport").patchValue(sport.sport_id);
    this.getDegrees();
  }

  setCourseType(type: string, id: number) {

    this.defaults = {
      unique: false,
      course_type: null,
      is_flexible: this.defaults.is_flexible,
      name: null,
      short_description: null,
      description: null,
      price: null,
      currency: '',
      date_start: null,
      date_end: null,
      date_start_res: null,
      date_end_res: null,
      confirm_attendance: false,
      active: true,
      online: true,
      image: this.imagePreviewUrl,
      translations:
      {
        es: {
          name: '',
          short_description: '',
          description: ''
        },
        en: {
          name: '',
          short_description: '',
          description: ''
        },
        fr: {
          name: '',
          short_description: '',
          description: ''
        },
        it: {
          name: '',
          short_description: '',
          description: ''
        },
        de: {
          name: '',
          short_description: '',
          description: ''
        },
      },
      price_range: this.dataSourceFlexiblePrices,
      discounts: null,
      settings: {
        weekDays: {
          monday: false,
          tuesday: false,
          wednesday: false,
          thursday: false,
          friday: false,
          saturday: false,
          sunday: false
        }
      },
      sport_id: this.defaults.sport_id,
      school_id: null,
      station_id: null,
      max_participants: null,
      duration: null,
      hour_min: null,
      hour_max: null,
      course_dates: [],
      groups: []
    };

    this.courseType = type;
    this.courseTypeFormGroup.get("courseType").patchValue(id);
    this.defaults.course_type = id;

    this.courseInfoFormGroup.reset();
    this.courseInfoPriveFormGroup.reset();
    this.courseInfoPriveSeparatedFormGroup.reset();
    this.courseInfoCollecDateSplitFormGroup.reset();
    this.courseLevelFormGroup.reset();
    this.courseInfoPriveFormGroup.get("periodeUnique").patchValue(true);
  }

  setFlexibility(event: any) {
    this.defaults.is_flexible = event.target.checked;
    this.daysDates = [];
    this.daysDatesLevels = [];

  }

  updateTable(event: any, fromInput = false) {
    if (fromInput) {
      this.people = parseInt(event);
    }
    this.displayedColumnsFlexiblePrices = ['intervalo']; // Inicializa con la columna de intervalo
    for (let i = 1; i <= this.people; i++) {
      this.displayedColumnsFlexiblePrices.push(`${i}`); // Añade columnas para cada persona
    }
  }

  formatIntervalo(minutos: number): string {
    const horas = Math.floor(minutos / 60);
    const mins = minutos % 60;
    return `${horas > 0 ? horas + 'h ' : ''}${mins > 0 ? mins + 'm' : ''}`.trim();
  }

  getSportsType() {
    this.crudService.list('/sport-types', 1, 1000)
      .subscribe((data) => {
        this.sportTypeData = data.data;
      });
  }

  getSports() {
    this.crudService.list('/school-sports', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id)
      .subscribe((sport) => {
        this.sportData = sport.data;
        this.sportData.forEach(element => {
          this.crudService.get('/sports/' + element.sport_id)
            .subscribe((data) => {
              element.name = data.data.name;
              element.icon_selected = data.data.icon_selected;
              element.icon_unselected = data.data.icon_unselected;
              element.sport_type = data.data.sport_type;

            });
        });

      })

  }

  getStations() {
    this.crudService.list('/stations-schools', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id)
      .subscribe((station) => {
        station.data.forEach(element => {
          this.crudService.get('/stations/' + element.station_id)
            .subscribe((data) => {
              this.stations.push(data.data);

            })
        });
      })
  }

  getMonitors() {
    this.crudService.list('/monitors', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id)
      .subscribe((data) => {
        this.monitors = data.data;
      })
  }

  getDegrees() {
    this.groupedByColor = {};
    this.colorKeys = [];
    this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order', '&school_id=' + this.user.schools[0].id + '&sport_id=' + this.defaults.sport_id)
      .subscribe((data) => {
        data.data.forEach(element => {
          if (element.active) {
            this.levels.push(element);
          }
        });
        this.levels.forEach(level => {
          if (!this.groupedByColor[level.color]) {
            this.groupedByColor[level.color] = [];
          }
          level.active = false;

          if (this.mode === 'update' && this.defaults.course_type === 1) {

            this.defaults.course_dates.forEach(cs => {
              cs.course_groups.forEach(group => {
                if (group.degree_id === level.id) {
                  level.active = true;
                  level.old = true;
                }
              });
            });
            this.selectedItem = this.daysDatesLevels[0].dateString;
          }
          this.groupedByColor[level.color].push(level);
        });

        this.colorKeys = Object.keys(this.groupedByColor);
      })
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

  calculateAgeMin(level: any) {
    let ret = 0;
    this.defaults.course_dates.forEach(courseDate => {
      courseDate.groups.forEach(group => {
        if (level.id === group.degree_id) {
          ret = group.age_min;
        }
      });
    });

    return ret;
  }

  calculateAgeMax(level: any) {
    let ret = 0;
    this.defaults.course_dates.forEach(courseDate => {
      courseDate.groups.forEach(group => {
        if (level.id === group.degree_id) {
          ret = group.age_max;
        }
      });
    });

    return ret;
  }

  getDatesBetween(startDate, endDate, process, hourStart = null, hourEnd = null) {

    let index = 0;
    if (process) {
      this.daysDatesLevels = [];
      let daysOfWeekAdded = new Set();
      let currentDate = moment(startDate);

      while (currentDate <= moment(endDate)) {
        let dayOfWeek = currentDate.format('dddd');

        if (!daysOfWeekAdded.has(dayOfWeek)) {
          this.daysDates.push(dayOfWeek.toLowerCase());

          /*{
            date: currentDate.format('YYYY-MM-DD'),
            dayOfWeek: dayOfWeek.toLowerCase()
          }*/
          daysOfWeekAdded.add(dayOfWeek);
        }

        this.daysDatesLevels.push({ date: currentDate.format('YYYY-MM-DD'), dateString: currentDate.locale('en').format('LLL').replace(' 0:00', '') });

        if (this.mode === 'update') {
          const existDate = this.defaults.course_dates.find((c) => moment(c.date, 'YYYY-MM-DD').format('YYYY-MM-DD') === currentDate.format('YYYY-MM-DD'));
          if (!existDate) {
            this.defaults.course_dates.push({
              date: currentDate.format('YYYY-MM-DD'),
              hour_start: hourStart,
              hour_end: hourEnd,
            })
          } else {
            this.defaults.course_dates[index].active = moment(this.defaults.date_end_res).isSameOrAfter(currentDate);
          }
          currentDate = currentDate.add(1, 'days');

        } else {
          this.defaults.course_dates.push({
            date: currentDate.format('YYYY-MM-DD'),
            hour_start: hourStart,
            hour_end: hourEnd,
          })
          currentDate = currentDate.add(1, 'days');
        }
        index = index + 1;
      }
    }

  }

  getSeparatedDates(dates: any, onLoad: boolean = false) {

    this.daysDates = [];
    this.daysDatesLevels = [];

    if (this.mode === 'create') {

      this.defaults.course_dates = [];
    }

    if (this.mode === 'update') {
      this.dataSource.data.forEach(element => {
        const existDate = this.defaults.course_dates.find((c) => moment(c.date, 'YYYY-MM-DD').format('YYYY-MM-DD') === moment(element.date).format('YYYY-MM-DD'));
        if (!existDate) {

          const dataNew = {
            date: moment(element.date).format('YYYY-MM-DD'),
            hour_start: element.hour,
            hour_end: this.calculateHourEnd(element.hour, element.duration),
            course_groups: []
          }

          this.defaults.course_dates[0].course_groups.forEach(element => {
            this.generateGroupForNewDate(dataNew, element);
          });

          this.defaults.course_dates.push(dataNew)
        }
      });

    } else {

      dates.forEach(element => {

        if (!onLoad) {
          const hour = element.hour;
          const duration = element.duration;
          const [hours, minutes] = duration.split(' ').reduce((acc, part) => {
            if (part.includes('h')) {
              acc[0] = parseInt(part, 10);
            } else if (part.includes('min')) {
              acc[1] = parseInt(part, 10);
            }
            return acc;
          }, [0, 0]);

          this.daysDatesLevels.push({ date: moment(element.date).format('YYYY-MM-DD'), dateString: moment(element.date).locale(this.translateService.getDefaultLang()).format('LLL').replace(' 0:00', '') });
          if (this.courseType === 'privee') {

            this.defaults.course_dates.push({
              date: moment(element.date).format('YYYY-MM-DD'),
              hour_start: element.hour,
              hour_end: moment(hour, "HH:mm").add(hours, 'hours').add(minutes, 'minutes').format("HH:mm")
            })
          } else {

            this.defaults.course_dates.push({
              date: moment(element.date).format('YYYY-MM-DD'),
              hour_start: element.hour,
              hour_end: moment(hour, "HH:mm").add(hours, 'hours').add(minutes, 'minutes').format("HH:mm"),
              groups: []
            })

          }
        } else {
          this.daysDatesLevels.push({ date: moment(element.date, 'YYYY-MM-DD').format('YYYY-MM-DD'), dateString: moment(element.date, 'YYYY-MM-DD').locale(this.translateService.getDefaultLang()).format('LLL').replace(' 0:00', '') });
        }

      });
    }




  }

  generateGroupForNewDate(date: any, group: any) {

    date.course_groups.push({
      course_id: group.course_id,
      course_date_id: null,
      degree_id: group.degree_id,
      age_min: group.age_min,
      age_max: group.age_max,
      recommended_age: group.recommended_age,
      teacher_min_degree: group.teacher_min_degree,
      teachers_min: null,
      teachers_max: null,
      observations: null,
      auto: true,
      course_subgroups: []
    });


    group.course_subgroups.forEach(element => {
      date.course_groups.forEach(group => {
        if (group.degree_id === element.degree_id) {
          group.active = true;
          group.teachers_min = group.teacher_min_degree;
          group.ge_min = group.age_min;
          group.age_max = group.age_max;
          group.course_subgroups.push({
            degree_id: element.degree_id,
            max_participants: element.max_participants
          })
        }

      });
    });
  }

  updateMaxDurationOptions(selectedMinDuration) {
    const minDurationValue = this.durations.find(d => d.text === selectedMinDuration)?.value;
    this.filteredMaxDurations = this.durations.filter(d => d.value >= minDurationValue);
  }

  updateToHourOptions(selectedStartHour) {
    const startIndex = this.hours.indexOf(selectedStartHour);
    this.filteredToHours = this.hours.slice(startIndex + 1);
  }

  loadData(event: any) {
    if (event.selectedIndex === 3 && this.defaults.course_type === 1) {

      this.getSeparatedDates(this.dataSource.data);
    }
  }

  generateGroups(level: any) {
    let ret = {};
    this.levels.forEach(element => {
      if (element.id === level.id) {
        ret = {
          course_id: null,
          course_date_id: null,
          degree_id: element.id,
          age_min: null,
          age_max: null,
          recommended_age: null,
          teachers_min: null,
          teachers_max: null,
          observations: null,
          auto: null,
          subgroups: []
        }
      }

    });

    return ret;
  }

  activeGroup(event: any, level: any) {

    this.selectedItem = this.daysDatesLevels[0].dateString;
    this.selectedDate = this.defaults.course_dates[0]?.date;
    level.active = event.source.checked;

    if (event.source.checked) {
      this.defaults.course_dates.forEach(element => {
        element.groups.push(this.generateGroups(level));
      });

      this.defaults.course_dates.forEach(element => {
        element.groups.forEach(group => {
          if (group.degree_id === level.id) {
            group.active = event.source.checked;
            group.teachers_min = level.id;
            group.age_min = level.age_min;
            group.age_max = level.age_max;
            group.subgroups.push({
              degree_id: level.id,
              monitor_id: null,
              max_participants: this.defaults.max_participants
            })
          }

        });
      });

      this.checkAvailableMonitors(level);
    } else {
      // eliminar el curso o desactivarlo

      this.defaults.course_dates.forEach((element) => {
        element.groups.forEach((group, idx) => {
          if (group.degree_id === level.id) {
            element.groups.splice(idx, 1);

          }
        });
      });


    }

  }

  addSubGroup(level: any) {
    this.defaults.course_dates.forEach(element => {
      element.groups.forEach(group => {
        if (level.id === group.degree_id) {
          group.subgroups.push({
            degree_id: level.id,
            monitor_id: null,
            max_participants: group.subgroups && group.subgroups.length > 0 ? group.subgroups[0].max_participants : null
          })
        }

      });
    });
  }

  readSubGroups(levelId: number) {

    let ret = [];
    this.defaults.course_dates[0].groups.forEach((group) => {
      if (group.degree_id === levelId) {
        ret = group.subgroups;
      }
    });

    return ret;
  }

  setLevelTeacher(level: any) {
    this.defaults.course_dates.forEach(element => {
      element.groups.forEach(group => {
        if (level.id === group.degree_id) {
          group.teachers_min = level.id;
        }

      });
    });
  }

  setMinAge(event: any, level: any) {
    if (+event.target.value >= 3) {

      this.defaults.course_dates.forEach(element => {
        element.groups.forEach(group => {
          if (level.id === group.degree_id) {
            group.age_min = +event.target.value;
          }

        });
      });
    }
  }

  setMaxAge(event: any, level: any) {
    if (+event.target.value < 81) {
      this.defaults.course_dates.forEach(element => {
        element.groups.forEach(group => {
          if (level.id === group.degree_id) {
            group.age_max = +event.target.value;
          }

        });
      });
    }
  }


  calculateFormattedDuration(hourStart: string, hourEnd: string): string {
    // Parsea las horas de inicio y fin
    let start = moment(hourStart, "HH:mm");
    let end = moment(hourEnd, "HH:mm");

    // Calcula la duración
    let duration = moment.duration(end.diff(start));

    // Formatea la duración
    let formattedDuration = "";
    if (duration.hours() > 0) {
      formattedDuration += duration.hours() + "h ";
    }
    if (duration.minutes() > 0) {
      formattedDuration += duration.minutes() + "m";
    }

    return formattedDuration.trim();
  }

  getMonitorValue(level: any, subGroupIndex: number, daySelectedIndex: number) {

    let ret = null;
    if (!level.old) {
      this.defaults.course_dates.forEach(courseDate => {

        if (moment(courseDate.date, 'YYYY-MM-DD').format('YYYY-MM-DD') === moment(this.selectedDate, 'YYYY-MM-DD').format('YYYY-MM-DD')) {
          courseDate.groups.forEach(group => {
            if (group.degree_id === level.id) {
              ret = group.subgroups[subGroupIndex]?.monitor;
            }
          });
        }
      });

    } else {
      this.defaults.course_dates[daySelectedIndex].groups.forEach(group => {
        if (group.degree_id === level.id) {
          ret = group?.subgroups[subGroupIndex]?.monitor?.first_name + ' ' + group?.subgroups[subGroupIndex]?.monitor?.last_name;
        }

      });
    }


    return ret;
  }

  calculateMonitorLevel(level: any) {
    let ret = 0;
    this.defaults.course_dates.forEach(courseDate => {
      courseDate.groups.forEach(group => {
        if (level.id === group.degree_id) {
          ret = level;
        }
      });
    });

    return ret;
  }

  calculateSubGroupPaxes(level: any) {
    let ret = 0;

    this.defaults.course_dates.forEach(element => {
      element.groups.forEach(group => {
        if (level.id === group.degree_id) {
          group.subgroups.forEach(subgroup => {

            ret = ret + subgroup.max_participants;
          });
        }

      });
    });

    return ret;
  }

  checkIfExistInDate(daySelectedIndex, monitor, level) {

    let blocked = false;
    this.defaults.course_dates[daySelectedIndex].groups.forEach(gs => {
      if (!blocked) {
        gs.subgroups.forEach(sbs => {
          if (sbs.monitor_id === monitor.id) {
            blocked = true;
          }
        });
      }

    });

    return blocked;
  }

  setSubGroupMonitor(event: any, monitor: any, level: any, subGroupSelectedIndex: number, daySelectedIndex: number) {

    if (event.isUserInput) {
      if (daySelectedIndex === 0) {
        let monitorSet = false;

        if (!level.old) {
          this.defaults.course_dates.forEach(courseDate => {
            if (moment(courseDate.date).format('YYYY-MM-DD') === moment(this.selectedDate).format('YYYY-MM-DD')) {

              this.crudService.post('/admin/monitors/available/' + monitor.id, { date: moment(courseDate.date, 'YYYY-MM-DD'), hour_start: courseDate.hour_start, hour_end: courseDate.hour_end })
                .subscribe((result: any) => {

                  if (result.data.available) {
                    courseDate.course_groups.forEach(group => {
                      if (group.degree_id === level.id && !monitorSet) {

                        group.course_subgroups[subGroupSelectedIndex].monitor_id = monitor.id;
                        group.course_subgroups[subGroupSelectedIndex].monitor = monitor.first_name + ' ' + monitor.last_name;
                        monitorSet = true;
                      }
                    });
                  }
                })
            }
          });
        } else {
          this.defaults.course_dates.forEach((courseDate, idx) => {
            this.crudService.post('/admin/monitors/available/' + monitor.id, { date: moment(courseDate.date).format('YYYY-MM-DD'), hour_start: courseDate.hour_start, hour_end: courseDate.hour_end })
              .subscribe((result: any) => {
                if (result.data.available) {

                  this.defaults.course_dates[idx].course_groups.forEach(group => {
                    if (group.degree_id === level.id) {
                      group.course_subgroups[subGroupSelectedIndex].monitor = monitor;
                      group.course_subgroups[subGroupSelectedIndex].monitor_id = monitor.id;
                    }

                  });
                }
              })
          });
        }
      } else {
        let monitorSet = false;

        if (!level.old) {
          this.defaults.course_dates.forEach(courseDate => {
            if (moment(courseDate.date).format('YYYY-MM-DD') === moment(this.selectedDate).format('YYYY-MM-DD')) {
              courseDate.course_groups.forEach(group => {
                if (group.degree_id === level.id && !monitorSet) {

                  group.course_subgroups[subGroupSelectedIndex].monitor_id = monitor.id;
                  group.course_subgroups[subGroupSelectedIndex].monitor = monitor.first_name + ' ' + monitor.last_name;
                  monitorSet = true;
                }
              });
            }
          });
        } else {
          this.defaults.course_dates[daySelectedIndex].course_groups.forEach(group => {
            if (group.degree_id === level.id) {
              group.course_subgroups[subGroupSelectedIndex].monitor = monitor;
              group.course_subgroups[subGroupSelectedIndex].monitor_id = monitor.id;
            }

          });
        }
      }
    }
  }

  setSubGroupPax(event: any, level: any) {

    if (+event.target.value > this.defaults.max_participants) {
      this.snackbar.open(this.translateService.instant('snackbar.course.capacity'), 'OK', { duration: 3000 });
    }

    level.max_participants = +event.target.value <= this.defaults.max_participants ? +event.target.value : this.defaults.max_participants;

    this.defaults.course_dates.forEach(element => {
      element.groups.forEach(group => {
        if (level.id === group.degree_id) {
          group.subgroups.forEach(subGroup => {
            subGroup.max_participants = level.max_participants;
          });
        }
      });
    });
  }

  selectItem(item: any, index: any, subGroupIndex: any, level) {
    this.subGroupSelectedIndex = null;
    this.selectedItem = item.dateString;
    this.selectedDate = item.date;
    this.daySelectedIndex = index;
    this.subGroupSelectedIndex = subGroupIndex;

    this.checkAvailableMonitors(level);
  }

  setStation(station: any) {
    this.defaults.station_id = station.id;
  }

  addWeekDay(event: any, day: string) {
    if (day === 'all') {
      this.defaults.settings.weekDays.monday = event.source.checked;
      this.defaults.settings.weekDays.tuesday = event.source.checked;
      this.defaults.settings.weekDays.wednesday = event.source.checked;
      this.defaults.settings.weekDays.thursday = event.source.checked;
      this.defaults.settings.weekDays.friday = event.source.checked;
      this.defaults.settings.weekDays.saturday = event.source.checked;
      this.defaults.settings.weekDays.sunday = event.source.checked;
    } else {
      this.defaults.settings.weekDays[day] = event.source.checked;
    }
  }

  setDebut(hour: any) {
    this.defaults.course_dates.forEach(element => {
      element.hour_start = hour;
    });
  }

  setHourEnd(hour: any) {
    this.defaults.course_dates.forEach(element => {
      element.hour_end = hour;
    });
  }

  create() {

    if (this.defaults.course_type === 2) {
      this.checkStep3PrivateNoFlex();
      this.setDebut(this.defaults.hour_min);
      this.setHourEnd(this.defaults.hour_max);
    }

    let data: any = [];

    let courseDates = [];

    if (this.courseType === 'collectif') {
      this.defaults.course_dates.forEach(dates => {
        const group = [];
        dates.groups.forEach(dateGroup => {
          if (dateGroup.subgroups.length > 0) {
            group.push(dateGroup);
          }
        });
        dates.groups = group;
      });
    } else {
      courseDates = this.defaults.course_dates;
    }


    let settings = typeof this.user.schools[0].settings === 'string' ? JSON.parse(this.user.schools[0].settings) : this.user.schools[0].settings;
    if (this.defaults.course_type === 1 && this.defaults.is_flexible) {
      data = {
        course_type: this.defaults.course_type,
        is_flexible: this.defaults.is_flexible,
        name: this.defaults.translations.fr.name,
        short_description: this.defaults.translations.fr.short_description,
        description: this.defaults.translations.fr.description,
        price: this.defaults.price,
        currency: settings?.taxes?.currency || 'CHF',//poner currency de reglajes
        date_start: moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end: moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        date_start_res: moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        confirm_attendance: false,
        active: this.defaults.active,
        online: this.defaults.online,
        image: this.imagePreviewUrl,
        translations: JSON.stringify(this.defaults.translations),
        discounts: JSON.stringify(this.dataSourceReductions.data),
        sport_id: this.defaults.sport_id,
        school_id: this.user.schools[0].id, //sacar del global
        station_id: this.defaults.station_id.id,
        max_participants: this.defaults.max_participants,
        course_dates: this.defaults.course_dates
      }
    } else if (this.defaults.course_type === 1 && !this.defaults.is_flexible) {
      data = {
        course_type: this.defaults.course_type,
        is_flexible: this.defaults.is_flexible,
        name: this.defaults.translations.fr.name,
        short_description: this.defaults.translations.fr.short_description,
        description: this.defaults.translations.fr.description,
        price: this.defaults.price,
        currency: settings?.taxes?.currency || 'CHF',//poner currency de reglajes
        date_start: moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end: moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        date_start_res: moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        confirm_attendance: false,
        active: this.defaults.active,
        online: this.defaults.online,
        image: this.imagePreviewUrl,
        translations: JSON.stringify(this.defaults.translations),
        sport_id: this.defaults.sport_id,
        school_id: this.user.schools[0].id, //sacar del global
        station_id: this.defaults.station_id.id,
        max_participants: this.defaults.max_participants,
        course_dates: this.defaults.course_dates
      }
    } else if (this.defaults.course_type === 2 && this.defaults.is_flexible) {

      if (this.periodeUnique) {

        this.getDatesBetween(this.defaults.date_start, this.defaults.date_end, true, this.defaults.hour_min, this.defaults.hour_max);
      }
      data = {
        course_type: this.defaults.course_type,
        is_flexible: this.defaults.is_flexible,
        name: this.defaults.translations.fr.name,
        short_description: this.defaults.translations.fr.short_description,
        description: this.defaults.translations.fr.description,
        price: 0,
        currency: settings?.taxes?.currency || 'CHF',//poner currency de reglajes
        date_start: this.periodeUnique ? moment(this.defaults.date_start).format('YYYY-MM-DD') : moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end: this.periodeUnique ? moment(this.defaults.date_end).format('YYYY-MM-DD') : moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        date_start_res: moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        active: this.defaults.active,
        online: this.defaults.online,
        image: this.imagePreviewUrl,
        confirm_attendance: false,
        translations: JSON.stringify(this.defaults.translations),
        discounts: JSON.stringify(this.dataSourceReductionsPrivate.data),
        price_range: this.dataSourceFlexiblePrices,
        sport_id: this.defaults.sport_id,
        school_id: this.defaults.school_id,
        station_id: this.defaults.station_id.id,
        max_participants: this.defaults.max_participants,
        duration: this.defaults.duration,
        age_min: this.defaults.age_min,
        age_max: this.defaults.age_max,
        course_dates: this.defaults.course_dates,
        settings: JSON.stringify(this.defaults.settings),
        unique: this.periodeUnique
      };
    } else if (this.defaults.course_type === 2 && !this.defaults.is_flexible) {
      this.getDatesBetween(this.defaults.date_start_res, this.defaults.date_end_res, true, this.defaults.hour_min, this.defaults.hour_max);
      data = {
        course_type: this.defaults.course_type,
        is_flexible: this.defaults.is_flexible,
        name: this.defaults.translations.fr.name,
        short_description: this.defaults.translations.fr.short_description,
        description: this.defaults.translations.fr.description,
        price: this.defaults.price,
        currency: settings?.taxes?.currency || 'CHF',//poner currency de reglajes
        date_start_res: moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        date_start: moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end: moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        active: this.defaults.active,
        online: this.defaults.online,
        image: this.imagePreviewUrl,
        confirm_attendance: false,
        translations: JSON.stringify(this.defaults.translations),
        price_range: null,
        sport_id: this.defaults.sport_id,
        school_id: this.defaults.school_id,
        station_id: this.defaults.station_id.id,
        max_participants: this.defaults.max_participants,
        duration: this.defaults.duration,
        age_min: this.defaults.age_min,
        age_max: this.defaults.age_max,
        course_dates: this.defaults.course_dates,
        hour_min: this.defaults.hour_min,
        hour_max: this.defaults.hour_max,
        settings: JSON.stringify(this.defaults.settings)
      };
    }
    data.school_id = this.user.schools[0].id;

    this.crudService.create('/admin/courses', data)
      .subscribe((res) => {
        this.dialogRef.close();
      })

  }

  update() {
    if (this.defaults.course_type === 2) {
      this.checkStep3PrivateNoFlex();
      this.setDebut(this.defaults.hour_min);
      this.setHourEnd(this.defaults.hour_max);
    }

    let data: any = [];

    let dates: any = [];
    let sortedDates: any = [];

    if (this.defaults.course_type === 2 && this.defaults.is_flexible && this.periodeMultiple) {
      dates = this.dataSourceDatePrivate.data.filter((date) => date.active || date.active === 1);
      sortedDates = dates.map(d => new Date(d.dateFrom)).sort((a, b) => a - b);
    } else {

      dates = this.dataSource.data.filter((date) => date.active || date.active === 1);
      sortedDates = dates.map(d => new Date(d.date)).sort((a, b) => a - b);
    }

    let lowestDate = moment(sortedDates[0]).format('YYYY-MM-DD');
    let highestDate = moment(sortedDates[sortedDates.length - 1]).format('YYYY-MM-DD');

    if (this.defaults.course_type === 1 && this.defaults.is_flexible) {
      this.defaults.date_start_res = this.defaults.date_start;
      this.defaults.date_end_res = this.defaults.date_end;
      this.getSeparatedDates(this.defaults.course_dates);
      data = {
        course_type: this.defaults.course_type,
        is_flexible: this.defaults.is_flexible,
        name: this.defaults.name,
        short_description: this.defaults.short_description,
        description: this.defaults.description,
        price: this.defaults.price,
        currency: this.defaults.currency,//poner currency de reglajes
        date_start: lowestDate,
        date_end: highestDate,
        date_start_res: moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        confirm_attendance: false,
        active: this.defaults.active,
        online: this.defaults.online,
        image: this.imagePreviewUrl,
        translations: JSON.stringify(this.defaults.translations),
        discounts: JSON.stringify(this.dataSourceReductions.data),
        sport_id: this.defaults.sport_id,
        school_id: null, //sacar del global
        station_id: this.defaults.station_id.id,
        max_participants: this.defaults.max_participants,
        course_dates: this.defaults.course_dates
      }
    } else if (this.defaults.course_type === 1 && !this.defaults.is_flexible) {
      this.defaults.date_start_res = this.defaults.date_start;
      this.defaults.date_end_res = this.defaults.date_end;
      this.getSeparatedDates(this.defaults.course_dates);
      data = {
        course_type: this.defaults.course_type,
        is_flexible: this.defaults.is_flexible,
        name: this.defaults.name,
        short_description: this.defaults.short_description,
        description: this.defaults.description,
        price: this.defaults.price,
        currency: this.defaults.currency,//poner currency de reglajes
        date_start: lowestDate,
        date_end: highestDate,
        date_start_res: moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        confirm_attendance: false,
        active: this.defaults.active,
        online: this.defaults.online,
        image: this.imagePreviewUrl,
        translations: JSON.stringify(this.defaults.translations),
        sport_id: this.defaults.sport_id,
        school_id: this.defaults.school_id, //sacar del global
        station_id: this.defaults.station_id.id,
        max_participants: this.defaults.max_participants,
        course_dates: this.defaults.course_dates
      }
    } else if (this.defaults.course_type === 2 && this.defaults.is_flexible) {
      data = {
        course_type: this.defaults.course_type,
        is_flexible: this.defaults.is_flexible,
        name: this.defaults.name,
        short_description: this.defaults.short_description,
        description: this.defaults.description,
        price: 0,
        currency: this.defaults.currency,//poner currency de reglajes
        date_start: lowestDate,
        date_end: highestDate,
        date_start_res: moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        active: this.defaults.active,
        online: this.defaults.online,
        image: this.imagePreviewUrl,
        confirm_attendance: false,
        translations: JSON.stringify(this.defaults.translations),
        discounts: JSON.stringify(this.dataSourceReductionsPrivate.data),
        price_range: this.dataSourceFlexiblePrices,
        sport_id: this.defaults.sport_id,
        school_id: this.defaults.school_id,
        station_id: this.defaults.station_id.id,
        max_participants: this.defaults.max_participants,
        duration: this.defaults.duration,
        age_min: this.defaults.age_min,
        age_max: this.defaults.age_max,
        course_dates: this.defaults.course_dates,
        settings: JSON.stringify(this.defaults.settings),
        unique: this.periodeUnique
      };
    } else if (this.defaults.course_type === 2 && !this.defaults.is_flexible) {
      this.getDatesBetween(this.defaults.course_dates[0].date, this.defaults.date_end_res, true, this.defaults.hour_min, this.defaults.hour_max);
      let sortedDates = this.defaults.course_dates.map(d => new Date(d.date)).sort((a, b) => a - b);

      let lowestDateP = moment(sortedDates[0]).format('YYYY-MM-DD');
      let highestDateP = moment(sortedDates[sortedDates.length - 1]).format('YYYY-MM-DD');

      data = {
        course_type: this.defaults.course_type,
        is_flexible: this.defaults.is_flexible,
        name: this.defaults.name,
        short_description: this.defaults.short_description,
        description: this.defaults.description,
        price: this.defaults.price,
        currency: this.defaults.currency,//poner currency de reglajes
        date_start_res: moment(this.defaults.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(this.defaults.date_end_res).format('YYYY-MM-DD'),
        date_start: lowestDateP,
        date_end: highestDateP,
        active: this.defaults.active,
        online: this.defaults.online,
        image: this.imagePreviewUrl,
        confirm_attendance: false,
        translations: JSON.stringify(this.defaults.translations),
        price_range: null,
        sport_id: this.defaults.sport_id,
        school_id: this.defaults.school_id,
        station_id: this.defaults.station_id.id,
        max_participants: this.defaults.max_participants,
        duration: this.defaults.duration,
        age_min: this.defaults.age_min,
        age_max: this.defaults.age_max,
        course_dates: this.defaults.course_dates,
        hour_min: this.defaults.hour_min,
        hour_max: this.defaults.hour_max,
        settings: JSON.stringify(this.defaults.settings)
      };
    }
    data.school_id = this.user.schools[0].id;

    this.crudService.update('/admin/courses', data, this.id)
      .subscribe((res) => {
        this.dialogRef.close();
      })
  }

  checkStep2PrivateNoFlex(stepper: MatStepper) {
    if (this.defaults.translations.fr.name === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.coursename'), 'OK', { duration: 3000 })
      return;
    }

    if (this.defaults.price === null && !this.defaults.is_flexible) {
      this.snackbar.open(this.translateService.instant('snackbar.course.price'), 'OK', { duration: 3000 })
      return;
    }

    if (this.myControlStations.value === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.station'), 'OK', { duration: 3000 })
      return;
    }

    if (this.defaults.age_min === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.min_age'), 'OK', { duration: 3000 })
      return;
    }

    if (this.defaults.age_max === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.max_age'), 'OK', { duration: 3000 })
      return;
    }

    if (this.defaults.translations.fr.short_description === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.summary'), 'OK', { duration: 3000 })
      return;
    }

    if (this.defaults.translations.fr.description === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.desc'), 'OK', { duration: 3000 })
      return;
    }

    stepper.next();

  }

  checkStep3PrivateNoFlex() {
    if (this.defaults.date_start_res === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.date_from'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.date_end_res === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.date_to'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.duration === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.duration'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.max_participants === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.pax'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.hour_min === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.hour_from'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.hour_max === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.hour_to'), 'OK', { duration: 3000 });
      return;
    }

  }


  checkStep3PrivateFlex(stepper: MatStepper) {
    if (this.defaults.date_start_res === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.date_res_from'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.date_end_res === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.date_res_to'), 'OK', { duration: 3000 });
      return;
    }

    if (!this.periodeMultiple && this.defaults.date_start === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.date_from'), 'OK', { duration: 3000 });
      return;
    }

    if (!this.periodeMultiple && this.defaults.date_end === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.date_to'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.duration === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.duration'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.max_participants === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.pax'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.hour_min === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.hour_from'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.hour_max === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.hour_to'), 'OK', { duration: 3000 });
      return;
    }

    stepper.next();
    this.updateTable(this.defaults.max_participants, true);
    return true;
  }


  checkStep2ColectiveNoFlex(stepper: MatStepper) {
    if (this.mode === 'create') {
      if (this.defaults.translations.fr.name === null) {
        this.snackbar.open(this.translateService.instant('snackbar.course.coursename'), 'OK', { duration: 3000 })
        return;
      }

      if (this.defaults.price === null) {
        this.snackbar.open(this.translateService.instant('snackbar.course.price'), 'OK', { duration: 3000 })
        return;
      }

      if (this.myControlStations.value === null) {
        this.snackbar.open(this.translateService.instant('snackbar.course.station'), 'OK', { duration: 3000 })
        return;
      }

      if (this.defaults.translations.fr.short_description === null) {
        this.snackbar.open(this.translateService.instant('snackbar.course.summary'), 'OK', { duration: 3000 })
        return;
      }

      if (this.defaults.translations.fr.description === null) {
        this.snackbar.open(this.translateService.instant('snackbar.course.desc'), 'OK', { duration: 3000 })
        return;
      }

      stepper.next();
    } else {
      this.update();
    }

  }

  checkStep3ColectiveNoFlex(stepper: MatStepper) {

    if (this.defaults.date_start_res === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.date_from'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.date_end_res === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.date_to'), 'OK', { duration: 3000 });
      return;
    }

    if (this.defaults.max_participants === null) {
      this.snackbar.open(this.translateService.instant('snackbar.course.pax'), 'OK', { duration: 3000 });
      return;
    }

    stepper.next();
  }

  onTabNameChanged(event: any) {
    this.selectedTabNameIndex = event.index;
  }

  onTabDesChanged(event: any) {
    this.selectedTabDescIndex = event.index;
  }

  checkAvailableMonitors(level: any) {
    this.loadingMonitors = true;
    let minDegree = 0;
    this.defaults.course_dates[this.daySelectedIndex].groups.forEach(element => {
      if (element.degree_id === level.id) {
        minDegree = element.teachers_min;
      }
    });
    const data = {
      sportId: this.defaults.sport_id,
      minimumDegreeId: minDegree,
      startTime: this.dataSource.data[this.daySelectedIndex].hour,
      endTime: this.calculateHourEnd(this.dataSource.data[this.daySelectedIndex].hour, this.dataSource.data[this.daySelectedIndex].duration),
      date: this.daysDatesLevels[this.daySelectedIndex].date
    };

    this.crudService.post('/admin/monitors/available', data)
      .subscribe((response) => {
        this.monitors = response.data;
        this.filteredMonitors = this.monitorsForm.valueChanges.pipe(
          startWith(''),
          map((value: any) => typeof value === 'string' ? value : value?.full_name),
          map(full_name => full_name ? this._filterMonitor(full_name) : this.monitors.slice())
        );

        this.loadingMonitors = false;
      })
  }

  toDate(date) {
    return moment(date).toDate()
  }
}
