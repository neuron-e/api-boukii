import {
  ChangeDetectorRef,
  Component,
  ElementRef,
  HostListener,
  NgZone,
  OnInit,
  QueryList,
  ViewChild,
  ViewEncapsulation
} from '@angular/core';
import { TableColumn } from 'src/@vex/interfaces/table-column.interface';
import { SalaryCreateUpdateModalComponent } from './salary-create-update-modal/salary-create-update-modal.component';
import { stagger20ms } from 'src/@vex/animations/stagger.animation';
import { LEVELS } from 'src/app/static-data/level-data';
import { FormControl, FormGroup, UntypedFormBuilder, UntypedFormGroup, Validators } from '@angular/forms';
import { Observable, forkJoin, map, startWith } from 'rxjs';
import { MOCK_COUNTRIES } from 'src/app/static-data/countries-data';
import { MOCK_PROVINCES } from 'src/app/static-data/province-data';
import { MatTable, MatTableDataSource } from '@angular/material/table';
import { MatDatepicker, MatDatepickerInputEvent } from '@angular/material/datepicker';
import * as moment from 'moment';
import { ApiCrudService } from 'src/service/crud.service';
import { MatSnackBar } from '@angular/material/snack-bar';
//import { ColorSchemeName } from 'src/@vex/config/colorSchemeName';
import { MatDialog } from '@angular/material/dialog';
import { ExtraCreateUpdateModalComponent } from './extra-create-update-modal/extra-create-update-modal.component';
import { LevelGoalsModalComponent } from './level-goals-modal/level-goals-modal.component';
import { SchoolService } from 'src/service/school.service';
import { TranslateService } from '@ngx-translate/core';
import { DateAdapter } from '@angular/material/core';
import { dropdownAnimation } from '../../../@vex/animations/dropdown.animation';
import { PreviewModalComponent } from '../../components/preview-modal/preview-modal.component';
import { LayoutService } from 'src/@vex/services/layout.service';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';

@Component({
  selector: 'vex-settings',
  templateUrl: './settings.component.html',
  styleUrls: ['./settings.component.scss'],
  animations: [
    dropdownAnimation,
    stagger20ms
  ],
  encapsulation: ViewEncapsulation.None
})
export class SettingsComponent implements OnInit {
  @ViewChild(MatDatepicker) pickers: QueryList<MatDatepicker<any>>;
  @ViewChild('table-level') dateTable: MatTable<any>;
  @ViewChild('table-extras-sports') dateTableSport: MatTable<any>;
  @ViewChild('forfaitTable') dateTableForfait: MatTable<any>;
  @ViewChild('transportTable') dateTableTransport: MatTable<any>;
  @ViewChild('foodTable') dateTableFood: MatTable<any>;
  @ViewChild('scrollContainer') scrollContainer: ElementRef;

  loading: boolean = true;
  mailType: any = 'booking_confirm';
  mailTypeTrad: any = 'mails.type1';
  subjectFr: any = '';
  subjectEn: any = '';
  subjectEs: any = '';
  subjectIt: any = '';
  subjectDe: any = '';
  bodyFr: any = '';
  bodyEn: any = '';
  bodyEs: any = '';
  bodyIt: any = '';
  bodyDe: any = '';
  titleFr: any = '';
  titleEn: any = '';
  titleEs: any = '';
  titleIt: any = '';
  titleDe: any = '';
  currentMails: any = [];
  selectedIndex = 0;

  emailTypes = [
    { value: 'booking_confirm', label: 'mails.type1' },
    { value: 'booking_cancel', label: 'mails.type2' },
    { value: 'booking_update', label: 'mails.type3' },
    { value: 'payment_link', label: 'mails.type4' },
    { value: 'payment_confirm', label: 'mails.type5' },
    { value: 'payment_reminder', label: 'mails.type9' },
    { value: 'voucher_confirm', label: 'mails.type6' },
    { value: 'voucher_create', label: 'mails.type7' },
    { value: 'course_reminder', label: 'mails.type8' }
  ];
  filteredHours: string[];

  seasonForm: UntypedFormGroup;
  myControlCountries = new FormControl();
  myControlProvinces = new FormControl();

  filteredCountries: Observable<any[]>;
  filteredProvinces: Observable<any[]>;

  school: any = [];
  blockages = [];
  mockLevelData = LEVELS;
  mockCountriesData = MOCK_COUNTRIES;
  mockProvincesData = MOCK_PROVINCES;

  holidays = [];
  holidaysSelected = [];
  people = 6; // Aquí puedes cambiar el número de personas
  intervalos = Array.from({ length: 28 }, (_, i) => 15 + i * 15);

  dataSource: any;
  displayedColumns = ['intervalo', ...Array.from({ length: this.people }, (_, i) => `${i + 1}`)];
  dataSourceLevels = new MatTableDataSource([]);
  displayedLevelsColumns: string[] = ['ageMin', 'ageMax', 'annotation', 'name', 'status', 'color', 'edit'];

  dataSourceSport = new MatTableDataSource([]);
  dataSourceForfait = new MatTableDataSource([]);
  dataSourceTransport = new MatTableDataSource([]);
  dataSourceFood = new MatTableDataSource([]);
  displayedSportColumns: string[] = ['id', 'sport', 'name', 'price', 'tva', 'status', 'edit', 'delete'];
  displayedExtrasColumns: string[] = ['id', 'product', 'name', 'price', 'tva', 'status', 'edit', 'delete'];
  currencies: string[] = ['CHF', 'EUR', 'GBP']
  tva = 0;
  currency = '';
  boukiiCarePrice = 0;
  cancellationInsurancePercent = 0;
  cancellationNoRem = 0;
  cancellationRem = 0;
  loadedTabs: boolean[] = [];
  today = new Date();

  selectedFrom = null;
  selectedTo = null;
  selectedFromHour: any;
  selectedToHour: any;
  hours: string[] = [];
  sports: any = [];
  sportsList: any = [];
  schoolSports: any = [];
  season: any = null;

  defaultsSchoolData = {
    contact_phone: null,
    contact_address: null,
    contact_address_number: null,
    contact_cp: null,
    contact_city: null,
    contact_country: null,
    contact_province: null,
    contact_email: null
  }

  defaultsCommonExtras = {
    forfait: [],
    transport: [],
    food: [],

  };

  theme = 'light';
  @HostListener('wheel', ['$event'])
  onScroll(event: WheelEvent) {
    this.ngZone.runOutsideAngular(() => {
      if (this.scrollContainer && this.scrollContainer.nativeElement) {
        const container = this.scrollContainer.nativeElement;
        const isHorizontalScroll = event.shiftKey;
        if (isHorizontalScroll) {
          container.scrollLeft += event.deltaY;
        } else {
          container.scrollLeft += event.deltaY;
        }
        event.preventDefault();
      }
    });
  }

  Translate: { Code: string, Name: string }[] = [
    { Code: "fr", Name: "French" },
    { Code: "de", Name: "German" },
    { Code: "en", Name: "English" },
    { Code: "it", Name: "Italian" },
    { Code: "es", Name: "Spanish" },
  ]

  selectedTabIndex = 0;

  createComponent = SalaryCreateUpdateModalComponent;

  entitySalary = '/school-salary-levels';
  dataSourceSalary = new MatTableDataSource();
  columns: TableColumn<any>[] = [
    { label: '#', property: 'id', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'name', property: 'name', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'method_paiment', property: 'pay', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'status', property: 'active', type: 'status', visible: true, cssClasses: ['font-medium'] },
    { label: 'Actions', property: 'actions', type: 'button', visible: true }

  ];
  authorized = true;
  authorizedBookingComm = true;
  selectedSport = -1;
  degrees: any = [];

  hasCancellationInsurance = false;
  hasBoukiiCare = false;
  hasTVA = false;

  user: any;
  safeUrl: SafeResourceUrl;

  constructor(private ngZone: NgZone, private fb: UntypedFormBuilder, private crudService: ApiCrudService,
              private snackbar: MatSnackBar, private cdr: ChangeDetectorRef,
              private dialog: MatDialog, private schoolService: SchoolService,
              public layoutService: LayoutService, private sanitizer: DomSanitizer,
              private translateService: TranslateService, private dateAdapter: DateAdapter<Date>) {
    this.filteredHours = this.hours;
    this.dateAdapter.setLocale(this.translateService.getDefaultLang());
    this.dateAdapter.getFirstDayOfWeek = () => { return 1; }
    this.safeUrl = this.sanitizer.bypassSecurityTrustResourceUrl('https://booking.boukii.com/' + JSON.parse(localStorage.getItem('boukiiUser')).schools[0].slug);
  }


  ngOnInit() {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.loadedTabs = this.Translate.map(() => false);
    // Marca la primera pestaña como cargada
    this.loadedTabs[0] = true;
    /*this.mockLevelData.forEach(element => {
      this.crudService.create('/degrees', element)

      .subscribe(() => {})
    });*/
    /*this.crudService.list('/degrees', 1, 10000)
      .subscribe((data) => {
        data.data.forEach(element => {
          this.crudService.delete('/degrees', element.id)
          .subscribe(() =>{})
        });
      })*/
    this.generateHours();
    this.getData();

  }


  getData() {
    this.loading = true;
    this.crudService.get('/schools/' + this.user.schools[0].id)
      .subscribe((data) => {

        this.school = data.data;
        this.getDegrees();

        forkJoin([this.getSchoolSeason(), this.getSports(), this.getBlockages(), this.getSchoolSports(), this.getEmails()])
          .subscribe((data: any) => {
            this.season = data[0].data.filter((s) => s.is_active)[0];
            this.sports = data[1].data;
            this.blockages = data[2].data;
            this.schoolSports = data[3].data;
            this.currentMails = data[4].data;
            data[3].data.forEach((element, idx) => {
              this.sportsList.push(element.sport_id);

              const sportData = this.sports.find((s) => s.id === element.sport_id);
              this.schoolSports[idx].name = sportData.name;
              this.schoolSports[idx].icon_selected = sportData.icon_selected;
              this.schoolSports[idx].icon_unselected = sportData.icon_unselected;
              this.schoolSports[idx].sport_type = sportData.sport_type;
            });

            if (this.season) {

              this.holidays = JSON.parse(this.season.vacation_days);
            }
            this.getSchoolSportDegrees();

            this.selectedFrom = moment(this.season?.start_date).toDate();
            this.selectedFromHour = this.season?.hour_start?.split(':').slice(0, 2).join(':'); // "18:00"
            this.selectedToHour = this.season?.hour_end?.split(':').slice(0, 2).join(':'); // "18:00"
            this.selectedTo = moment(this.season?.end_date).toDate();


            this.seasonForm = this.fb.group({
              fromDate: [moment(this.season?.start_date).toDate()],
              toDate: [moment(this.season?.end_date).toDate()],
              startHour: [this.season?.hour_start],
              endHour: [this.season?.hour_end],
              contact_phone: [this.school.contact_phone],
              contact_address: [this.school.contact_address],
              contact_address_number: [this.school.contact_address_number],
              contact_cp: [this.school.contact_cp],
              contact_country: [this.school.contact_country],
              contact_province: [this.school.contact_province],
              contact_city: [this.school.contact_city]

            });

            this.defaultsSchoolData.contact_phone = this.school.contact_phone;
            this.defaultsSchoolData.contact_address = this.school.contact_address;
            this.defaultsSchoolData.contact_address_number = this.school.contact_address_number;
            this.defaultsSchoolData.contact_cp = this.school.contact_cp;
            this.defaultsSchoolData.contact_city = this.school.contact_city;
            this.defaultsSchoolData.contact_province = this.school.contact_province;
            this.defaultsSchoolData.contact_country = this.school.contact_country;
            this.defaultsSchoolData.contact_email = this.school.contact_email;


            this.myControlCountries.setValue(this.mockCountriesData.find((c) => c.id === +this.defaultsSchoolData.contact_country));
            this.myControlProvinces.setValue(this.mockProvincesData.find((c) => c.id === +this.defaultsSchoolData.contact_province));
            this.seasonForm.get('startHour').valueChanges.subscribe(selectedHour => {
              this.filterHours(selectedHour);
            });

            this.filteredCountries = this.myControlCountries.valueChanges.pipe(
              startWith(''),
              map(value => typeof value === 'string' ? value : value.name),
              map(name => name ? this._filterCountries(name) : this.mockCountriesData.slice())
            );

            this.myControlCountries.valueChanges.subscribe(country => {
              this.myControlProvinces.setValue('');  // Limpia la selección anterior de la provincia
              this.filteredProvinces = this._filterProvinces(country.id);
            });

            const settings = typeof this.school.settings === 'string' ? JSON.parse(this.school.settings) : this.school.settings;
            this.people = settings && settings.prices_range.people ? settings.prices_range.people : this.people;
            this.displayedColumns = ['intervalo', ...Array.from({ length: this.people }, (_, i) => `${i + 1}`)];
            this.dataSource = settings && settings.prices_range.prices && settings.prices_range.prices !== null ? settings.prices_range.prices :
              this.intervalos.map(intervalo => {
                const fila: any = { intervalo: this.formatIntervalo(intervalo) };
                for (let i = 1; i <= this.people; i++) {
                  fila[`${i}`] = '';
                }
                return fila;
              });


            this.hasCancellationInsurance = settings?.taxes?.cancellation_insurance_percent !== null && parseFloat(settings?.taxes?.cancellation_insurance_percent) !== 0 && !isNaN(parseFloat(settings?.taxes?.cancellation_insurance_percent));
            this.hasBoukiiCare = settings?.taxes?.boukii_care_price !== null && parseInt(settings?.taxes?.boukii_care_price) !== 0 && !isNaN(parseInt(settings?.taxes?.boukii_care_price));
            this.hasTVA = settings?.taxes?.tva !== null && parseFloat(settings?.taxes?.tva) !== 0 && !isNaN(parseFloat(settings?.taxes?.tva));

            this.currency = settings?.taxes?.currency;

            this.cancellationInsurancePercent = parseFloat(settings?.taxes?.cancellation_insurance_percent);
            this.boukiiCarePrice = parseInt(settings?.taxes?.boukii_care_price);
            this.tva = parseFloat(settings?.taxes?.tva);
            this.cancellationNoRem = settings?.cancellations?.without_cancellation_insurance;
            this.cancellationRem = settings?.cancellations?.with_cancellation_insurance;

            this.dataSourceForfait.data = settings?.extras.forfait;
            this.dataSourceFood.data = settings?.extras.food;
            this.dataSourceTransport.data = settings?.extras.transport;

            this.PageForm.BannerPromocional = this.fb.group({
              link: [settings?.bookingPage?.banner.link],
              desktopImg: [settings?.bookingPage?.banner.desktopImg],
              mobileImg: [settings?.bookingPage?.banner.mobileImg],
            })
            this.PageForm.Conditions = this.fb.group({
              terms: [settings?.bookingPage?.conditions?.terms || { es: '', en: '', fr: '', de: '', it: '' }],
              privacy: [settings?.bookingPage?.conditions?.privacy || { es: '', en: '', fr: '', de: '', it: '' }],
              contact: [settings?.bookingPage?.conditions?.contact || { es: '', en: '', fr: '', de: '', it: '' }]
            });
            /*            this.PageForm.MessageInformation = this.fb.group({
                          index: [0, Validators.required],
                          title: ["", Validators.required],
                          desc: ["", Validators.required],
                          color: ["#D2EFFF", Validators.required],
                        })*/
            this.MessageStorage = settings?.bookingPage?.messages || []
            this.SponsorImg = settings?.bookingPage?.sponsors || []

            setTimeout(() => {
              this.dataSourceLevels.data = this.schoolSports[0].degrees;
            }, 500);

            this.loading = false;
          });
      });
  }

  getEmails() {
    return this.crudService.list('/mails', 1, 10000, 'asc', 'id', '&school_id=' + this.user.schools[0].id);
  }

  setCurrentMailType() {
    const mail = this.currentMails.find((m) => m.type === this.mailType);
    this.mailTypeTrad = this.emailTypes.find(type => type.value === this.mailType)?.label;

    if (mail) {
      const frMail = this.currentMails.find(m => m.lang === 'fr' && m.type === this.mailType);

      this.bodyFr = frMail?.body;
      this.titleFr = frMail?.title;
      this.subjectFr = frMail?.subject;
    } else {
      this.bodyFr = '';
      this.titleFr = '';
      this.subjectFr = '';
    }
    if (mail) {
      const enMail = this.currentMails.find((m) => m.lang === 'en' && m.type === this.mailType);

      this.bodyEn = enMail?.body;
      this.titleEn = enMail?.title;
      this.subjectEn = enMail?.subject;
    } else {
      this.bodyEn = '';
      this.titleEn = '';
      this.subjectEn = '';
    }
    if (mail) {
      const esMail = this.currentMails.find((m) => m.lang === 'es' && m.type === this.mailType);

      this.bodyEs = esMail?.body;
      this.titleEs = esMail?.title;
      this.subjectEs = esMail?.subject;
    } else {
      this.bodyEs = '';
      this.titleEs = '';
      this.subjectEs = '';
    }
    if (mail) {
      const deMail = this.currentMails.find((m) => m.lang === 'de' && m.type === this.mailType);
      this.bodyDe = deMail?.body;
      this.titleDe = deMail?.title;
      this.subjectDe = deMail?.subject;
    } else {
      this.bodyDe = '';
      this.titleDe = '';
      this.subjectDe = '';
    }
    if (mail) {
      const itMail = this.currentMails.find((m) => m.lang === 'it' && m.type === this.mailType);

      this.bodyIt = itMail?.body;
      this.titleIt = itMail?.title;
      this.subjectIt = itMail?.subject;
    } else {
      this.bodyIt = '';
      this.titleIt = '';
      this.subjectIt = '';
    }
  }

  openPreview(): void {
    const data = this.getEmailContent();

    const dialogRef = this.dialog.open(PreviewModalComponent, {
      width: '80%',
      data: data
    });

    dialogRef.afterClosed().subscribe(result => { });
  }

  getEmailContent() {
    switch (this.selectedIndex) {
      case 0: // Pestaña francesa
        return { language: 'fr', subject: this.subjectFr, title: this.titleFr, body: this.bodyFr };
      case 1: // Pestaña inglesa
        return { language: 'en', subject: this.subjectEn, title: this.titleEn, body: this.bodyEn };
      case 2: // Pestaña española
        return { language: 'es', subject: this.subjectEs, title: this.titleEs, body: this.bodyEs };
      case 3: // Pestaña italiana
        return { language: 'it', subject: this.subjectIt, title: this.titleIt, body: this.bodyIt };
      case 4: // Pestaña alemana
        return { language: 'de', subject: this.subjectDe, title: this.titleDe, body: this.bodyDe };
      default: // Fallback
        return { language: 'en', subject: '', title: '', body: '' };
    }
  }

  saveDefaultMail() {
    const data = [
      [{
        type: this.mailType,
        subject: this.subjectFr,
        body: this.bodyFr,
        title: this.titleFr,
        school_id: this.school.id,
        lang: 'fr'
      }],
      [{
        type: this.mailType,
        subject: this.subjectEn,
        body: this.bodyEn,
        title: this.titleEn,
        school_id: this.school.id,
        lang: 'en'
      }],
      [{
        type: this.mailType,
        subject: this.subjectEs,
        body: this.bodyEs,
        title: this.titleEs,
        school_id: this.school.id,
        lang: 'es'
      }],
      [{
        type: this.mailType,
        subject: this.subjectDe,
        body: this.bodyDe,
        title: this.titleIt,
        school_id: this.school.id,
        lang: 'de'
      }],
      [{
        type: this.mailType,
        subject: this.subjectIt,
        body: this.bodyIt,
        title: this.titleDe,
        school_id: this.school.id,
        lang: 'it'
      }]
    ];

    for (let i = 0; i < 5; i++) {

      const existMail = this.currentMails.find((c) => c.lang === data[i][0].lang && c.type === data[i][0].type);

      if (existMail) {

        const updateData = {
          type: this.mailType,
          subject: data[i][0].subject,
          body: data[i][0].body,
          title: data[i][0].title,
          school_id: this.school.id,
          lang: data[i][0].lang
        }

        this.crudService.update('/mails', updateData, existMail.id)
          .subscribe((res) => {

            if (i === 4) {

              this.snackbar.open('Se ha configurado el email por defecto', 'OK', { duration: 3000 });

            }

          })
      } else {
        this.crudService.post('/mails', data[i][0])
          .subscribe((res) => {

            if (i === 4) {
              this.snackbar.open('Se ha configurado el email por defecto', 'OK', { duration: 3000 });

            }

          })
      }

    }

  }

  onTabChange(event: any) {
    this.selectedIndex = event.index;
    this.setCurrentMailType();
  }

  onTabLangsChange(index: number): void {
    // Marca la pestaña actual como cargada
    this.loadedTabs[index] = true;
  }


  onFullTabChange(event: any) {
    if (event.index == 7) {
      this.setCurrentMailType();
    }
  }

  addHoliday() {
    this.holidays.push(new Date()); // Agrega una nueva fecha al array
  }

  generateHours() {
    for (let i = 0; i <= 23; i++) {
      for (let j = 0; j < 60; j += 60) {
        const formattedHour = `${i.toString().padStart(2, '0')}:${j.toString().padStart(2, '0')}`;
        this.hours.push(formattedHour);
      }
    }
  }

  formatIntervalo(minutos: number): string {
    const horas = Math.floor(minutos / 60);
    const mins = minutos % 60;
    return `${horas > 0 ? horas + 'h ' : ''}${mins > 0 ? mins + 'm' : ''}`.trim();
  }

  updateTable() {
    // Lógica para actualizar la tabla basándote en el valor de this.people

    // Por ejemplo, podrías actualizar las columnas mostradas:
    this.displayedColumns = ['intervalo']; // Inicializa con la columna de intervalo
    for (let i = 1; i <= this.people; i++) {
      this.displayedColumns.push(`${i}`); // Añade columnas para cada persona
    }

    // También podrías necesitar actualizar los datos mostrados en la tabla
    // ...
  }

  displayFnCountry(country: any): string {
    return country && country.name ? country.name : '';
  }

  displayFnProvince(province: any): string {
    return province && province.name ? province.name : '';
  }

  private _filter(name: string, countryId: number): any[] {
    const filterValue = name.toLowerCase();
    return this.mockProvincesData.filter(province => province.country_id === countryId && province.name.toLowerCase().includes(filterValue));
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

  editGoal(data: any, id: number) {
    const dialogRef = this.dialog.open(LevelGoalsModalComponent, {
      width: '90vw',
      height: '90vh',
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales
      data: data
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
        if (data.deletedGoals.length > 1) {
          data.deletedGoals.forEach(element => {
            this.crudService.delete('/degrees-school-sport-goals', element.id)
              .subscribe((res) => {
                this.snackbar.open(this.translateService.instant('snackbar.settings.objectives_update'), 'OK', { duration: 3000 });
              })
          });
        }
        data.goals.forEach(element => {
          if (element.id) {

            this.crudService.update('/degrees-school-sport-goals', element, element.id)
              .subscribe((data) => {
                this.snackbar.open(this.translateService.instant('snackbar.settings.objectives_update'), 'OK', { duration: 3000 });
              })
          } else {
            this.crudService.create('/degrees-school-sport-goals', element)
              .subscribe((data) => {
                this.snackbar.open(this.translateService.instant('snackbar.settings.objectives_created'), 'OK', { duration: 3000 });
              })
          }


        });


        setTimeout(() => {
          this.getData();
        }, 500);
      }
    });
  }

  isEven(index: number): boolean {
    return index % 2 === 0;
  }

  filterHours(selectedHour: string) {
    const selectedIndex = this.hours.indexOf(selectedHour);
    this.filteredHours = selectedIndex >= 0 ? this.hours.slice(selectedIndex) : this.hours;
  }

  onDateSelect(event: MatDatepickerInputEvent<Date>, index: any) {
    const selectedDate = event.value;
    const selectedDateFormat = moment(selectedDate).format('YYYY-MM-DD');

    if (index <= this.holidays.length && this.season && this.season !== null) {
      this.holidays[index] = selectedDateFormat;
    } else {
      this.holidays.push(selectedDateFormat);
    }

    if (!this.season || this.season === null) {
      this.holidaysSelected.push(selectedDateFormat);
    }
  }

  getSchoolSeason() {
    return this.crudService.list('/seasons', 1, 10000, 'asc', 'id', '&school_id=' + this.user.schools[0].id);
  }

  deleteHoliday(index: any) {
    this.holidays.splice(index, 1);
  }

  saveSeason() {

    let holidays = [];
    if (this.season && this.season !== null) {
      holidays = this.holidays;
    } else {
      this.holidaysSelected.forEach(element => {
        holidays.push(moment(element).format('YYYY-MM-DD'));
      });
    }

    const data = {
      name: "Temporada 1",
      start_date: moment(this.selectedFrom,).format('YYYY-MM-DD'),
      end_date: moment(this.selectedTo,).format('YYYY-MM-DD'),
      is_active: true,
      school_id: this.user.schools[0].id,
      hour_start: this.selectedFromHour,
      hour_end: this.selectedToHour,
      vacation_days: JSON.stringify(holidays)
    }

    if (this.season && this.season !== null) {
      this.crudService.update('/seasons', data, this.season.id)
        .subscribe((res) => {
          this.snackbar.open(this.translateService.instant('snackbar.settings.season_created'), 'Close', { duration: 3000 });
          this.getData();
          this.schoolService.refreshSchoolData();
        });
    } else {
      this.crudService.create('/seasons', data)
        .subscribe((res) => {
          this.snackbar.open(this.translateService.instant('snackbar.settings.season_updated'), 'Close', { duration: 3000 });
          this.getData();
          this.schoolService.refreshSchoolData();
        });
    }

  }

  saveContactData() {

    const data = {
      contact_phone: this.defaultsSchoolData.contact_phone,
      contact_address: this.defaultsSchoolData.contact_address,
      contact_address_number: this.defaultsSchoolData.contact_address_number,
      contact_cp: this.defaultsSchoolData.contact_cp,
      contact_city: this.defaultsSchoolData.contact_city,
      contact_country: this.defaultsSchoolData.contact_country,
      contact_province: this.defaultsSchoolData.contact_province,
      contact_email: this.defaultsSchoolData.contact_email,
    }

    this.crudService.update('/schools', data, this.school.id)
      .subscribe((res) => {
        this.snackbar.open(this.translateService.instant('snackbar.settings.save'), this.translateService.instant('cancel'), { duration: 3000 });
        this.schoolService.refreshSchoolData();
        //this.getData();
      });
  }

  saveSchoolSports() {

    this.crudService.update('/schools', { sport_ids: this.sportsList }, this.school.id + '/sports')
      .subscribe((res) => {
        res.data.sports.forEach(sport => {

          const hasDegrees = this.degrees.filter((s) => s.sport_id === sport.id).length > 0;

          if (!hasDegrees) {
            this.mockLevelData.forEach((degree, idx) => {
              degree.sport_id = sport.id;
              degree.school_id = this.school.id;
              degree.degree_order = idx;
              degree.level = 'test';
              degree.progress = 0;


              this.crudService.create('/degrees', degree)
                .subscribe((data) => {
                  this.schoolService.refreshSchoolData();
                });
            });


          }

        });
        setTimeout(() => {
          this.snackbar.open(this.translateService.instant('snackbar.settings.sports'), 'OK', { duration: 3000 });

          this.getData();
        }, 1000);
      });
  }

  savePrices() {

    const data = {
      taxes: {
        cancellation_insurance_percent: this.hasCancellationInsurance ? this.cancellationInsurancePercent : 0,
        boukii_care_price: this.hasBoukiiCare ? this.boukiiCarePrice : 0, currency: this.currency,
        tva: this.hasTVA ? this.tva : 0
      },
      cancellations: { with_cancellation_insurance: this.cancellationRem, without_cancellation_insurance: this.cancellationNoRem },
      prices_range: { people: this.people, prices: this.dataSource },
      monitor_app_client_messages_permission: this.authorized,
      monitor_app_client_bookings_permission: this.authorizedBookingComm,
      extras: { forfait: this.dataSourceForfait.data, food: this.dataSourceFood.data, transport: this.dataSourceTransport.data },
      degrees: this.dataSourceLevels.data
    }

    this.crudService.update('/schools', { name: this.school.name, description: this.school.description, settings: JSON.stringify(data) }, this.school.id)
      .subscribe(() => {
        this.snackbar.open(this.translateService.instant('snackbar.settings.prices'), 'OK', { duration: 3000 });
        this.schoolService.refreshSchoolData();
      })
  }

  getSports() {
    return this.crudService.list('/sports', 1, 1000);
  }

  getSchoolSports() {
    return this.crudService.list('/school-sports', 1, 10000, 'desc', 'id', '&school_id=' + this.school.id);
  }

  getSchoolSportDegrees() {
    this.sportsList.forEach((element, idx) => {
      this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order', '&school_id=' + this.school.id + '&sport_id=' + element)
        .subscribe((data) => {
          this.schoolSports[idx].degrees = data.data;
          this.selectedSport = this.schoolSports[0].id;
        });
    });
  }

  getDegrees() {
    this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order', '&school_id=' + this.school.id)
      .subscribe((data) => {
        this.degrees = data.data;
      })
  }

  setSport(id: number) {
    let index = this.sportsList.indexOf(id);

    if (this.sportsList.length === 0) {
      this.sportsList.push(id);
    } else if (this.sportsList.length > 0 && index === -1) {
      this.sportsList.push(id);
    } else if (this.sportsList.length > 0 && index !== -1) {
      this.sportsList.splice(index, 1);
    }
  }

  getBlockages() {
    return this.crudService.list('/school-colors', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id + '&default=1');
  }

  saveBlockages() {
    this.blockages.forEach(element => {
      element.school_id = this.school.id;
      this.crudService.update('/school-colors', element, element.id)
        .subscribe(() => {
        })
    });

    this.snackbar.open(this.translateService.instant('snackbar.settings.blocks'), 'OK', { duration: 3000 });
  }


  onAuthorizedChange(event: any) {
    this.authorized = event;
  }


  onAuthorizedBookingChange(event: any) {
    this.authorizedBookingComm = event;
  }

  saveMonitorsAuth() {
    const data = {
      taxes: {
        cancellation_insurance_percent: this.hasCancellationInsurance ? this.cancellationInsurancePercent : 0,
        boukii_care_price: this.hasBoukiiCare ? this.boukiiCarePrice : 0, currency: this.currency,
        tva: this.hasTVA ? this.tva : 0
      },
      cancellations: { with_cancellation_insurance: this.cancellationRem, without_cancellation_insurance: this.cancellationNoRem },
      prices_range: { people: this.people, prices: this.dataSource },
      monitor_app_client_messages_permission: this.authorized,
      monitor_app_client_bookings_permission: this.authorizedBookingComm,
      extras: { forfait: this.dataSourceForfait.data, food: this.dataSourceFood.data, transport: this.dataSourceTransport.data },
      degrees: this.dataSourceLevels.data
    }
    this.crudService.update('/schools', { name: this.school.name, description: this.school.description, settings: JSON.stringify(data) }, this.school.id)
      .subscribe(() => {
        this.snackbar.open(this.translateService.instant('snackbar.settings.auths'), 'OK', { duration: 3000 });
        this.getData();
        this.schoolService.refreshSchoolData();
      })
  }



  createExtra(product: string, isEdit: boolean, idx: number, extra: any) {
    const dialogRef = this.dialog.open(ExtraCreateUpdateModalComponent, {
      width: '50vw',
      height: '36vh',
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      data: isEdit ? extra : {
        product: '',
        name: '',
        price: '',
        tva: '',
        status: false
      }
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
        this.addForfait(data, isEdit, idx);
        this.saveExtra();
      }
    });
  }

  selectSport(item: any) {
    this.selectedSport = item.id;
    this.dataSourceLevels.data = item.degrees
  }

  addForfait(data: any, isEdit: any, idx: number) {


    if (isEdit) {
      this.dataSourceForfait.data[idx] = data;
    } else {

      this.dataSourceForfait.data.push({
        id: 'FOR-' + this.generateRandomNumber(),
        product: data.product,
        name: data.name,
        price: data.price,
        tva: data.tva,
        status: data.status
      });
    }
    this.dateTableForfait.renderRows();

  }

  addFood(data: any, isEdit: any, idx: number) {


    if (isEdit) {
      this.dataSourceFood.data[idx] = data;
    } else {

      this.dataSourceFood.data.push({
        id: 'FOR-' + this.generateRandomNumber(),
        product: 'Forfait',
        name: data.name,
        price: data.price,
        tva: data.tva,
        status: data.status
      });
    }
    this.dateTableFood.renderRows();

  }

  addTransport(data: any, isEdit: any, idx: number) {


    if (isEdit) {
      this.dataSourceTransport.data[idx] = data;
    } else {

      this.dataSourceTransport.data.push({
        id: 'FOR-' + this.generateRandomNumber(),
        product: 'Forfait',
        name: data.name,
        price: data.price,
        tva: data.tva,
        status: data.status
      });
    }
    this.dateTableTransport.renderRows();

  }

  deleteExtra(index: number, type: string) {
    if (type === 'Forfait') {

      this.dataSourceForfait.data.splice(index, 1);
      this.dateTableForfait.renderRows();
    } else if (type === 'Food') {
      this.dataSourceFood.data.splice(index, 1);
      this.dateTableFood.renderRows();
    } else if (type === 'Transport') {
      this.dataSourceTransport.data.splice(index, 1);
      this.dateTableTransport.renderRows();
    }

    this.saveExtra();
  }

  saveExtra() {
    const data = {
      taxes: {
        cancellation_insurance_percent: this.hasCancellationInsurance ? this.cancellationInsurancePercent : 0,
        boukii_care_price: this.hasBoukiiCare ? this.boukiiCarePrice : 0, currency: this.currency,
        tva: this.hasTVA ? this.tva : 0
      },
      cancellations: { with_cancellation_insurance: this.cancellationRem, without_cancellation_insurance: this.cancellationNoRem },
      prices_range: { people: this.people, prices: this.dataSource },
      monitor_app_client_messages_permission: this.authorized,
      monitor_app_client_bookings_permission: this.authorizedBookingComm,
      extras: { forfait: this.dataSourceForfait.data, food: this.dataSourceFood.data, transport: this.dataSourceTransport.data },
      degrees: this.dataSourceLevels.data
    }

    this.crudService.update('/schools', { name: this.school.name, description: this.school.description, settings: JSON.stringify(data) }, this.school.id)
      .subscribe(() => {
        this.snackbar.open(this.translateService.instant('snackbar.settings.extras'), 'OK', { duration: 3000 });
        this.schoolService.refreshSchoolData();

        this.getData();

      })
  }

  saveTaxes() {

    const data = {
      taxes: {
        cancellation_insurance_percent: this.hasCancellationInsurance ? this.cancellationInsurancePercent : 0,
        boukii_care_price: this.hasBoukiiCare ? this.boukiiCarePrice : 0, currency: this.currency,
        tva: this.hasTVA ? this.tva : 0
      },
      cancellations: { with_cancellation_insurance: this.cancellationRem, without_cancellation_insurance: this.cancellationNoRem },
      prices_range: { people: this.people, prices: this.dataSource },
      monitor_app_client_messages_permission: this.authorized,
      monitor_app_client_bookings_permission: this.authorizedBookingComm,
      extras: { forfait: this.dataSourceForfait.data, food: this.dataSourceFood.data, transport: this.dataSourceTransport.data },
      degrees: this.dataSourceLevels.data
    }

    this.crudService.update('/schools', { name: this.school.name, description: this.school.description, settings: JSON.stringify(data) }, this.school.id)
      .subscribe(() => {

        this.snackbar.open(this.translateService.instant('snackbar.settings.taxes'), 'OK', { duration: 3000 });
        this.schoolService.refreshSchoolData();
        this.getData();

      })
  }

  saveBookingPage() {

    /*const data = {
      taxes: {
        cancellation_insurance_percent: this.hasCancellationInsurance ? this.cancellationInsurancePercent : 0,
        boukii_care_price: this.hasBoukiiCare ? this.boukiiCarePrice : 0, currency: this.currency,
        tva: this.hasTVA ? this.tva : 0
      },
      cancellations: { with_cancellation_insurance: this.cancellationRem, without_cancellation_insurance: this.cancellationNoRem },
      prices_range: { people: this.people, prices: this.dataSource },
      monitor_app_client_messages_permission: this.authorized,
      monitor_app_client_bookings_permission: this.authorizedBookingComm,
      extras: { forfait: this.dataSourceForfait.data, food: this.dataSourceFood.data, transport: this.dataSourceTransport.data },
      degrees: this.dataSourceLevels.data
    }


    this.crudService.update('/schools', {
      name: this.school.name,
      description: this.school.description,
      settings: JSON.stringify(data)
    }, this.school.id)
      .subscribe(() => {

        this.snackbar.open(this.translateService.instant('snackbar.settings.taxes'), 'OK', { duration: 3000 });
        this.schoolService.refreshSchoolData();
        this.getData();

      })*/
    const data = {
      taxes: {
        cancellation_insurance_percent: this.hasCancellationInsurance ? this.cancellationInsurancePercent : 0,
        boukii_care_price: this.hasBoukiiCare ? this.boukiiCarePrice : 0, currency: this.currency,
        tva: this.hasTVA ? this.tva : 0
      },
      cancellations: { with_cancellation_insurance: this.cancellationRem, without_cancellation_insurance: this.cancellationNoRem },
      prices_range: { people: this.people, prices: this.dataSource },
      monitor_app_client_messages_permission: this.authorized,
      monitor_app_client_bookings_permission: this.authorizedBookingComm,
      extras: { forfait: this.dataSourceForfait.data, food: this.dataSourceFood.data, transport: this.dataSourceTransport.data },
      degrees: this.dataSourceLevels.data,
      bookingPage: {
        messages: this.MessageStorage,
        sponsors: this.SponsorImg,
        banner: this.PageForm.BannerPromocional.value,
        conditions: this.PageForm.Conditions.value
      }
    }

    this.crudService.update('/schools', {
      name: this.school.name,
      description: this.school.description,
      settings: JSON.stringify(data)
    }, this.school.id)
      .subscribe(() => {
        this.snackbar.open(this.translateService.instant('snackbar.settings.save'), 'OK', { duration: 3000 });
        this.schoolService.refreshSchoolData();
        this.getData();
      })
  }

  trackLang(index: number, lang: any) {
    return lang.Code;
  }

  updateConditions(field: string, lang: string, value: any) {
    const currentConditions = { ...this.PageForm.Conditions.value };
    if (!currentConditions[field]) {
      currentConditions[field] = {};
    }
    currentConditions[field][lang] = value.target.innerHTML;

    this.PageForm.Conditions.get(field)?.setValue(currentConditions[field], { emitEvent: false });
    this.cdr.markForCheck();
  }

  updateTVAValue(event: any) {

    this.tva = parseInt(event.target.value) / 100;
  }

  updateBoukiiCareValue(event: any) {

    this.boukiiCarePrice = parseInt(event.target.value);
  }

  updateCancelationNoRem(event: any) {

    this.cancellationNoRem = parseInt(event.target.value);
  }

  updateCancelationRem(event: any) {

    this.cancellationRem = parseInt(event.target.value);
  }

  updateInsuranceValue(event: any) {

    this.cancellationInsurancePercent = parseInt(event.target.value) / 100;
  }

  updateSportDegrees() {
    this.dataSourceLevels.data.forEach(element => {
      this.crudService.update('/degrees', element, element.id).subscribe((degree) => { })
    });
    this.snackbar.open(this.translateService.instant('snackbar.settings.levels'), 'OK', { duration: 3000 });
  }

  generateRandomNumber() {
    const min = 10000000; // límite inferior para un número de 5 cifras
    const max = 99999999; // límite superior para un número de 5 cifras
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }


  //PAGINA DE RESERVA, MODAL
  PageModal: { BannerPromocional: boolean, MessageInformation: boolean, SponsoLink: boolean, Previum: boolean } =
    { BannerPromocional: false, MessageInformation: false, SponsoLink: false, Previum: false }
  PageForm: { BannerPromocional: FormGroup, MessageInformation: FormGroup, SponsoLink: FormGroup, Conditions: FormGroup } =
    {
      BannerPromocional: this.fb.group({
        link: ["", Validators.required],
        desktopImg: ["", Validators.required],
        mobileImg: ["", Validators.required],
      }),
      MessageInformation: this.fb.group({
        index: [0, Validators.required],
        title: ["", Validators.required],
        desc: ["", Validators.required],
        type: [false, Validators.required],
        color: ["#D2EFFF", Validators.required]
      }),
      SponsoLink: this.fb.group({
        index: [0, Validators.required],
        link: ["", Validators.required],
        img: ["", Validators.required],
      }),
      Conditions: this.fb.group({
        terms: [
          {
            es: '',
            en:  '',
            fr:  '',
            it:  '',
            de:  '',
          }
        ],
        privacy: [
          {
            es: '',
            en:  '',
            fr:  '',
            it:  '',
            de:  '',
          }
        ],
        contact: [
          {
            es: '',
            en:  '',
            fr:  '',
            it:  '',
            de:  '',
          }
        ],
      })
    }


  SponsorImg: { index: number, img: string, link: string }[] = []
  MessageStorage: { index: number, title: string, desc: string, type: boolean }[] = []

  removeSponsor(index: number): void {
    this.SponsorImg.splice(index, 1);
  }
  removeMessage(index: number): void {
    this.MessageStorage.splice(index, 1);
  }
}
