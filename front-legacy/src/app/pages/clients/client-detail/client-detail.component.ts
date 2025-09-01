import {ChangeDetectorRef, Component, ElementRef, OnInit, ViewChild} from '@angular/core';
import { FormControl, UntypedFormBuilder, UntypedFormGroup, Validators } from '@angular/forms';
import { MatTable, _MatTableDataSource } from '@angular/material/table';
import { ActivatedRoute, Router } from '@angular/router';
import { Observable, map, startWith, forkJoin, tap, from, mergeMap, toArray, retry, catchError, of } from 'rxjs';
import { fadeInRight400ms } from 'src/@vex/animations/fade-in-right.animation';
import { fadeInUp400ms } from 'src/@vex/animations/fade-in-up.animation';
import { scaleIn400ms } from 'src/@vex/animations/scale-in.animation';
import { stagger20ms } from 'src/@vex/animations/stagger.animation';
import { MOCK_COUNTRIES } from 'src/app/static-data/countries-data';
import { MOCK_LANGS } from 'src/app/static-data/language-data';
import { LEVELS } from 'src/app/static-data/level-data';
import { MOCK_PROVINCES } from 'src/app/static-data/province-data';
import { MOCK_SPORT_DATA } from 'src/app/static-data/sports-data';
import { ApiCrudService } from 'src/service/crud.service';
import { ConfirmModalComponent } from '../../monitors/monitor-detail/confirm-dialog/confirm-dialog.component';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatDialog } from '@angular/material/dialog';
import { AddClientUserModalComponent } from '../add-client-user/add-client-user.component';
import moment from 'moment';
import { PasswordService } from 'src/service/password.service';
import { MatStepper } from '@angular/material/stepper';
import { TableColumn } from 'src/@vex/interfaces/table-column.interface';
import { TranslateService } from '@ngx-translate/core';
import { DateAdapter } from '@angular/material/core';
import { switchMap } from 'rxjs/operators';


@Component({
  selector: 'vex-client-detail',
  templateUrl: './client-detail.component.html',
  styleUrls: ['./client-detail.component.scss'],
  animations: [fadeInUp400ms, stagger20ms, scaleIn400ms, fadeInRight400ms]

})
export class ClientDetailComponent {

  @ViewChild('sportsCurrentTable') currentSportsTable: MatTable<any>;

  showInfo = true;
  showPersonalInfo = true;
  showAddressInfo: boolean = true;
  showSportInfo: boolean = true;
  editInfo = false;
  editPersonalInfo = false;
  editAddressInfo: boolean = false;
  editSportInfo: boolean = false;
  countries = MOCK_COUNTRIES;
  provinces = MOCK_PROVINCES;

  displayedCurrentColumns: string[] = ['name', 'level', 'delete'];
  displayedColumns: string[] = ['name', 'date'];

  imagePreviewUrl: string | ArrayBuffer;
  formInfoAccount: UntypedFormGroup;
  formPersonalInfo: UntypedFormGroup;
  formSportInfo: UntypedFormGroup;
  formOtherInfo: UntypedFormGroup;
  myControlStations = new FormControl();
  myControlCountries = new FormControl();
  myControlProvinces = new FormControl();
  levelForm = new FormControl();

  filteredStations: Observable<any[]>;
  filteredCountries: Observable<any[]>;
  filteredProvinces: Observable<any[]>;
  filteredLevel: Observable<any[]>;
  filteredSports: Observable<any[]>;

  sportsControl = new FormControl();
  selectedNewSports: any[] = [];
  selectedSports: any[] = [];
  sportsData = new _MatTableDataSource([]);
  sportsCurrentData = new _MatTableDataSource([]);
  stations: any = [];

  languagesControl = new FormControl([]);
  languages = [];
  schoolSports = [];
  filteredLanguages: Observable<any[]>;
  selectedLanguages = [];
  deletedItems = [];
  clientUsers = [];
  selectedGoal = [];
  evaluations = [];
  evaluationFullfiled = [];
  maxSelection = 6;
  today: Date;
  minDate: Date;
  loading = true;
  editing = false;
  coloring = true;
  selectedTabIndex = 0;
  selectedTabPreviewIndex = 0;

  mockCivilStatus: string[] = ['Single', 'Mariée', 'Veuf', 'Divorcé'];
  mockLevelData: any = LEVELS;

  mainClient: any;
  currentImage: any;
  defaults = {
    id: null,
    email: null,
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
    language1_id: null,
    language2_id: null,
    language3_id: null,
    language4_id: null,
    language5_id: null,
    language6_id: null,
    user_id: null,
    station_id: null,
    active_station: null
  }

  defaultsObservations = {
    id: null,
    general: '',
    notes: '',
    historical: '',
    client_id: null,
    school_id: null
  }

  defaultsUser = {
    id: null,
    username: null,
    email: null,
    password: null,
    image: null,
    type: 'client',
    active: false,
  }

  groupedByColor = {};
  colorKeys: string[] = []; // Aquí almacenaremos las claves de colores
  user: any;
  id: any;
  active: false;

  allLevels: any = [];
  allClientLevels: any = [];
  sportIdx: any = -1;
  selectedSport: any;
  clientSport = [];
  clients = [];
  clientSchool = [];
  goals = [];
  bookingUsersUnique = [];
  bonus = [];
  mainId: any;
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


  constructor(private fb: UntypedFormBuilder, private cdr: ChangeDetectorRef, private crudService: ApiCrudService, private router: Router,
              private activatedRoute: ActivatedRoute, private snackbar: MatSnackBar, private dialog: MatDialog, private passwordGen: PasswordService,
              private translateService: TranslateService, private dateAdapter: DateAdapter<Date>) {
    this.today = new Date();
    this.minDate = new Date(this.today);
    this.minDate.setFullYear(this.today.getFullYear() - 18);
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

  ngOnInit(): void {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.id = this.activatedRoute.snapshot.params.id;
    this.mainId = this.activatedRoute.snapshot.params.id;
    this.getDegrees();
    this.getInitialData().pipe(
      switchMap(() => this.getData())
    ).subscribe(() => {
      this.crudService.get('/evaluation-fulfilled-goals').subscribe((data) => this.evaluationFullfiled = data.data)
      // Aquí puedes realizar cualquier lógica adicional después de obtener los datos iniciales y los datos principales.
    });
  }

  getInitialData() {

    const requestsInitial = {
      languages: this.getLanguages().pipe(retry(3), catchError(error => {
        console.error('Error fetching languages:', error);
        return of([]); // Devuelve un array vacío en caso de error
      })),
      stations: this.getStations().pipe(retry(3), catchError(error => {
        console.error('Error fetching stations:', error);
        return of([]); // Devuelve un array vacío en caso de error
      })),
      /*      clients: this.getClients().pipe(retry(3), catchError(error => {
              console.error('Error fetching clients:', error);
              return of([]); // Devuelve un array vacío en caso de error
            })),*/
    };

    return forkJoin(requestsInitial).pipe(tap((results) => {
      this.formInfoAccount = this.fb.group({
        image: [''],
        first_name: ['', Validators.required],
        last_name: ['', Validators.required],
        email: ['', [Validators.required, Validators.email]],
        username: [''],
        password: [''],
      });

      this.formPersonalInfo = this.fb.group({
        fromDate: [''],
        phone: [''],
        mobile: ['', Validators.required],
        address: [''],
        postalCode: [''],
        lang: [''],
        country: this.myControlCountries,
        province: this.myControlProvinces

      });

      this.formSportInfo = this.fb.group({
        sportName: [''],
      });

      this.formOtherInfo = this.fb.group({
        summary: [''],
        notes: [''],
        hitorical: ['']
      });

    }));

  }

  changeClientData(id: any) {
    this.loading = true;
    this.id = id;
    if (id === this.mainId) {
      this.minDate.setFullYear(this.today.getFullYear() - 18);
    } else {
      this.minDate.setFullYear(this.today.getFullYear());
    }
    this.getData(id, true).subscribe();
  }



  orderBookingUsers(users: any[]) {
    return users.sort((a, b) => {
      // Ordenar por fecha
      const dateComparison = new Date(a.date).getTime() - new Date(b.date).getTime();
      if (dateComparison !== 0) {
        return dateComparison;
      }

      // Si la fecha es la misma, ordenar por hora de inicio
      return a.hour_start.localeCompare(b.hour_start);
    });
  }


  getData(id = null, onChangeUser = false) {
    const getId = id === null ? this.mainId : id;


    return this.crudService.get('/clients/' + getId, ['user', 'clientSports.degree', 'clientSports.sport',
      'evaluations.evaluationFulfilledGoals.degreeSchoolSportGoal', 'evaluations.degree', 'observations'])
      .pipe(
        tap((data) => {

          this.defaults = data.data;

          this.evaluations = data.data.evaluations;
          this.evaluationFullfiled = [];
          this.evaluations.forEach(ev => {
            ev.evaluation_fulfilled_goals.forEach(element => {
              ;
              this.evaluationFullfiled.push(element);
            });
          });
          if (data.data.observations.length > 0) {
            this.defaultsObservations = data.data.observations[0];
          } else {
            this.defaultsObservations = {
              id: null,
              general: '',
              notes: '',
              historical: '',
              client_id: null,
              school_id: null
            };
          }
          this.currentImage = data.data.image;
          if (!onChangeUser) {
            this.mainClient = data.data;
          }

          const requestsClient = {
            clientSchool: this.getClientSchool().pipe(retry(3), catchError(error => {
              console.error('Error fetching client school:', error);
              return of([]); // Devuelve un array vacío en caso de error
            })),
            clientSport: this.getClientSport().pipe(retry(3), catchError(error => {
              console.error('Error fetching client sport:', error);
              return of([]); // Devuelve un array vacío en caso de error
            }))
          };

          forkJoin(requestsClient).subscribe((results) => {
            if (!onChangeUser) this.getClientUtilisateurs();

            if (data.data.user) this.defaultsUser = data.data.user;

            const langs = [];
            this.languages.forEach(element => {
              if (element.id === this.defaults?.language1_id || element.id === this.defaults?.language2_id || element.id === this.defaults?.language3_id ||
                element.id === this.defaults?.language4_id || element.id === this.defaults?.language5_id || element.id === this.defaults?.language6_id) {
                langs.push(element);
              }
            });

            this.languagesControl.setValue(langs);

            if (!onChangeUser) {

              this.filteredCountries = this.myControlCountries.valueChanges.pipe(
                startWith(''),
                map(value => typeof value === 'string' ? value : value.name),
                map(name => name ? this._filterCountries(name) : this.countries.slice())
              );

              this.myControlCountries.valueChanges.subscribe(country => {
                this.myControlProvinces.setValue('');  // Limpia la selección anterior de la provincia
                this.filteredProvinces = this._filterProvinces(country?.id);
              });

              this.filteredLevel = this.levelForm.valueChanges.pipe(
                startWith(''),
                map((value: any) => typeof value === 'string' ? value : value?.annotation),
                map(annotation => annotation ? this._filterLevel(annotation) : this.mockLevelData.slice())
              );
              this.filteredLanguages = this.languagesControl.valueChanges.pipe(
                startWith(''),
                map(language => (language ? this._filterLanguages(language) : this.languages.slice()))
              );

            }

            this.myControlStations.setValue(this.stations.find((s) => s.id === this.defaults.active_station)?.name);
            this.myControlCountries.setValue(this.countries.find((c) => c.id === +this.defaults.country));
            this.myControlProvinces.setValue(this.provinces.find((c) => c.id === +this.defaults.province));

            this.loading = false;
          });

        }))
  }

  getSchoolSportDegrees() {
    return this.crudService.list('/school-sports', 1, 10000, 'desc', 'id', '&school_id=' +
      this.user.schools[0].id, null, null, null, ['sport', 'degrees.degreesSchoolSportGoals'])
      .pipe(
        map((sport) => {
          this.goals = []
          this.schoolSports = sport.data;
          this.schoolSports.forEach(sport => {
            sport.name = sport.sport.name;
            sport.icon_selected = sport.sport.icon_selected;
            sport.icon_unselected = sport.sport.icon_unselected;
            sport.degrees.forEach(degree => {
              degree.degrees_school_sport_goals.forEach(goal => {
                this.goals.push(goal);
              });
            });

            this.clientSport.forEach(element => {
              if (element.sport_id === sport.sport_id) {
                element.name = sport.name;
                element.icon_selected = sport.icon_selected;
                element.icon_unselected = sport.icon_unselected;
                element.degrees = sport.degrees;
                element.degrees = element.degrees.filter(level => {
                  const age = this.calculateAge(this.defaults.birth_date);
                  return age >= level.age_min && age <= level.age_max;
                });
              }
            });
          });
          this.sportsCurrentData.data = this.clientSport;
          const availableSports = [];
          this.schoolSports.forEach(element => {
            element.degrees = element.degrees.filter(level => {
              const age = this.calculateAge(this.defaults.birth_date);
              return age >= level.age_min && age <= level.age_max;
            });
            if (!this.sportsCurrentData.data.find((s) => s.sport_id === element.sport_id)) {
              availableSports.push(element);
            }
          });

          this.filteredSports = this.sportsControl.valueChanges.pipe(
            startWith(''),
            map((sport: string | null) => sport ? this._filterSports(sport) : availableSports.slice())
          );


          //return this.getGoals();
        })
      );
  }

  checkClientStatus(data: any) {
    let ret = false;
    data.forEach(element => {
      if (element.school_id === this.user.schools[0].id) {
        ret = element.accepted_at !== null;
      }
    });

    return ret;
  }

  getClientSchool() {
    return this.crudService.list('/clients-schools', 1, 10000, 'desc', 'id', '&client_id=' + this.id)
      .pipe(
        map((data) => {
          this.clientSchool = data.data;
        })
      );
  }

  getClientSport() {
    return this.crudService.list('/client-sports', 1, 10000, 'desc', 'id', '&client_id='
      + this.id + "&school_id=" + this.user.schools[0].id, null, null, null, ['degree.degreesSchoolSportGoals'])
      .pipe(
        switchMap((data) => {
          this.clientSport = data.data;
          this.selectedSport = this.clientSport[0];
          this.goals = [];
          this.clientSport.forEach(element => {
            element.level = element.degree;
          });
          return this.getSchoolSportDegrees();
        })
      );
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



  getLanguages() {
    return this.crudService.list('/languages', 1, 1000).pipe(
      tap((data) => {
        this.languages = data.data.reverse();

      })
    );
  }

  getClients() {
    return this.crudService.list('/admin/clients/mains', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id).pipe(
      tap((client) => {
        this.clients = client.data;
      })
    );
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

  /**Countries */

  private _filterCountries(name: string): any[] {
    const filterValue = name.toLowerCase();
    return this.countries.filter(country => country.name.toLowerCase().includes(filterValue));
  }

  private _filterProvinces(countryId: number): Observable<any[]> {
    return this.myControlProvinces.valueChanges.pipe(
      startWith(''),
      map(value => typeof value === 'string' ? value : value.name),
      map(name => name ? this._filter(name, countryId) : this.provinces.filter(p => p.country_id === countryId).slice())
    );
  }

  private _filter(name: string, countryId: number): any[] {
    const filterValue = name.toLowerCase();
    return this.provinces.filter(province => province.country_id === countryId && province.name.toLowerCase().includes(filterValue));
  }
  private _filterLevel(name: string): any[] {
    const filterValue = name.toLowerCase();
    return this.mockLevelData.filter(level => level.annotation.toLowerCase().includes(filterValue));
  }

  private _filterLanguages(value: any): any[] {
    const filterValue = value.toLowerCase();
    return this.languages.filter(language => language?.name.toLowerCase().includes(filterValue));
  }

  private _filterSports(value: any): any[] {
    const filterValue = typeof value === 'string' ? value.toLowerCase() : value?.name?.toLowerCase();
    return this.schoolSports.filter(sport => sport?.name.toLowerCase().indexOf(filterValue) === 0);
  }

  displayFnCountry(country: any): string {
    return country && country.name ? country.name : '';
  }

  displayFnProvince(province: any): string {
    return province && province.name ? province.name : '';
  }

  displayFnLevel(level: any): string {
    return level && level?.name && level?.annotation ? level?.name + ' - ' + level?.annotation : level?.name;
  }

  updateSelectedSports(selected: any[]) {
    this.selectedSports = selected.map(sport => ({
      sportName: sport.name,
      sportId: sport.id,
      level: null
    }));
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

  getSelectedLanguageNames(): string {
    return this.selectedLanguages.map(language => language.name).join(', ');
  }

  getClientUtilisateurs() {
    this.crudService.list('/admin/clients/' + this.id + '/utilizers', 1, 10000, 'desc', 'id', '&client_id=' + this.id)
      .subscribe((data) => {
        this.clientUsers = data.data;
        this.crudService.list('/clients-utilizers', 1, 10000, 'desc', 'id', '&main_id=' + this.id)
          .subscribe((data) => {
            data.data.forEach(element => {
              this.clientUsers.forEach(cl => {
                if (element.client_id === cl.id) {
                  cl.utilizer_id = element.id;
                }
              });
            });
          })

      })
  }

  goTo(route: string) {
    this.router.navigate([route]);
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
    this.selectedLanguages = [];
    this.languages.forEach(element => {
      if (element.id === this.defaults.language1_id || element.id === this.defaults.language2_id || element.id === this.defaults.language3_id
        || element.id === this.defaults.language4_id || element.id === this.defaults.language5_id || element.id === this.defaults.language6_id) {
        this.selectedLanguages.push(element);
      }
    });
  }

  removeSport(idx: number, element: any) {

    const dialogRef = this.dialog.open(ConfirmModalComponent, {
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales,
      data: { message: this.translateService.instant('delete_text'), title: this.translateService.instant('delete_title') }
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {

        this.crudService.delete('/client-sports', element.id)
          .subscribe(() => {
            this.deletedItems.push(this.sportsCurrentData.data[idx]);
            this.sportsCurrentData.data.splice(idx, 1);
            this.currentSportsTable.renderRows();
          })
      }
    });
  }

  deleteUserClient(id: number) {

    const dialogRef = this.dialog.open(ConfirmModalComponent, {
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales,
      data: { message: this.translateService.instant('delete_text'), title: this.translateService.instant('delete_title') }
    });


    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {

        this.crudService.delete('/clients-utilizers', id)
          .subscribe(() => {
            this.snackbar.open(this.translateService.instant('snackbar.client.removed_user'), 'OK', { duration: 3000 });
            window.location.reload();
          })
      }
    });
  }

  updateLevel(clientSport, level) {
    this.crudService.update('/client-sports', { client_id: clientSport.client_id, sport_id: clientSport.sport_id, degree_id: level.id, school_id: this.user.schools[0].id }, clientSport.id)
      .subscribe((data) => {
        this.snackbar.open(this.translateService.instant('snackbar.client.level_updated'), 'OK', { duration: 3000 });
      })
  }

  setActive(event) {
    this.active = event.checked;
  }

  formatDate = (date: Date | string): string => {
    // Convertir a objeto Date si es un string
    const validDate = typeof date === 'string' ? new Date(date) : date;

    // Verificar si el objeto Date es válido
    if (isNaN(validDate.getTime())) {
      throw new Error('Invalid date format');
    }

    const day = validDate.getDate().toString().padStart(2, '0');
    const month = (validDate.getMonth() + 1).toString().padStart(2, '0');
    const year = validDate.getFullYear();

    return `${year}-${month}-${day}`;
  };

  save() {
    this.setLanguages();

    if (this.currentImage === this.defaults.image) {
      delete this.defaults.image;
      delete this.defaultsUser.image;
    } else {
      this.defaultsUser.image = this.imagePreviewUrl;
      this.defaults.image = this.imagePreviewUrl;
    }
    if (this.defaultsUser.password === '') delete this.defaultsUser.password;
    this.defaultsUser.email = this.defaults.email;
    this.crudService.update('/users', this.defaultsUser, this.defaultsUser.id)
      .subscribe((user) => {
        this.defaults.user_id = user.data.id;
        this.defaults.birth_date = this.formatDate(this.defaults.birth_date)
        this.crudService.update('/clients', this.defaults, this.id)
          .subscribe((client) => {
            this.snackbar.open(this.translateService.instant('snackbar.client.update'), 'OK', { duration: 3000 });
            this.defaultsObservations.client_id = client.data.id;
            this.defaultsObservations.school_id = this.user.schools[0].id;
            if (this.defaultsObservations.id) this.crudService.update('/client-observations', this.defaultsObservations, this.defaultsObservations.id).subscribe(() => { })
            else this.crudService.create('/client-observations', this.defaultsObservations).subscribe(() => { })
            this.sportsData.data.forEach(element => {
              this.crudService.create('/client-sports', {
                client_id: client.data.id,
                sport_id: element.sport_id,
                degree_id: element.level.id,
                school_id: this.user.schools[0].id
              }).subscribe(() => { })
            });

            // Actualizar deportes actuales del cliente
            this.sportsCurrentData.data.forEach(element => {
              this.crudService.update('/client-sports', {
                client_id: client.data.id,
                sport_id: element.sport_id,
                degree_id: element.level.id,
                school_id: this.user.schools[0].id
              }, element.id).subscribe(() => { })
            });

            // Verificar el valor de 'active' y manejar el 'accepted_at'
            if (this.active) {  // Si 'active' es true
              // Buscar si existe una relación cliente-escuela
              const existingSchool = this.clientSchool.find(element => element.school_id === this.user.schools[0].id);
              if (existingSchool) {
                // Si ya existe, actualizar el 'accepted_at'
                if (existingSchool.accepted_at === null) {
                  this.crudService.update('/clients-schools', { accepted_at: moment().toDate() }, existingSchool.id)
                    .subscribe(() => { });
                }
              } else {
                // Si no existe, crear la relación
                this.crudService.create('/clients-schools', {
                  client_id: client.data.id,
                  school_id: this.user.schools[0].id,
                  accepted_at: moment().toDate()
                })
                  .subscribe(() => { });
              }
            } else {  // Si 'active' es false
              // Buscar si existe la relación y actualizar 'accepted_at' a null
              const existingSchool = this.clientSchool.find(element => element.school_id === this.user.schools[0].id);
              if (existingSchool) {
                this.crudService.update('/clients-schools', { accepted_at: null }, existingSchool.id)
                  .subscribe(() => { });
              }
            }

            // Redirigir después de 2 segundos
            setTimeout(() => {
              this.router.navigate(['/clients']);
            }, 2000);
          });
      });
  }


  onTabChange(event: any) {
    if (event.index === 1) {
      this.selectedSport = this.clientSport[0];
      this.selectSportEvo(this.selectedSport);
      this.selectedTabIndex = 0;
      this.selectedTabPreviewIndex = 1;
      this.editing = false;
    }
  }

  selectSportEvo(sport: any) {
    this.coloring = true;
    this.allClientLevels = [];
    this.selectedGoal = [];
    this.selectedSport = sport;

    this.schoolSports.forEach(element => {
      if (this.selectedSport.sport_id === element.sport_id) {
        this.selectedSport.degrees = element.degrees;
      }
    });

    this.selectedSport.degrees.forEach(element => {
      element.inactive_color = this.lightenColor(element.color, 30);
      this.allClientLevels.push(element);
    });

    this.sportIdx = this.allClientLevels.findIndex((al) => al.id === sport.level.id);
    this.allClientLevels.sort((a, b) => a.degree_order - b.degree_order);

    this.goals.forEach(element => {
      if (element.degree_id === sport.level.id) {

        this.selectedGoal.push(element);
      }
    });
    if (sport && sport?.level) {
      for (const i in this.allClientLevels) {
        // Inicializa el array para cada grado (degree)
        this.sportCard[+i] = {
          degree: this.allClientLevels[i], // Almacenar el degree
          goals: [] // Inicializar los goals como un array vacío
        };

        // Buscar los goals correspondientes a cada degree y asignarlos
        this.goals.forEach((element: any) => {
          if (element.degree_id === this.allClientLevels[i].id) {
            this.sportCard[+i].goals.push(element);
          }
        });
      }
    }
    this.coloring = false;
  }

  changeLevel(nextLevel: any) {
    this.selectedGoal = [];
    this.sportIdx = this.sportIdx + nextLevel;

    if (this.sportIdx < 0) {
      this.sportIdx = this.allClientLevels.length - 1;
    } else if (this.sportIdx >= this.allClientLevels.length) {
      this.sportIdx = 0;
    }
    this.allClientLevels.sort((a: any, b: any) => a.degree_order - b.degree_order);
    this.selectedSport.level = this.allClientLevels[this.sportIdx];
    this.goals.forEach((element: any) => {
      if (element.degree_id === this.allClientLevels[this.sportIdx].id) {
        this.selectedGoal.push(element);
      }
    });
    this.coloring = false;
  }

  lightenColor(hexColor: any, percent: any) {

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

  canAddUtilisateur(date: string): boolean {
    const dateBirth = moment(date);
    const today = moment();
    const diff = today.diff(dateBirth, 'years');

    return diff >= 18;
  }

  addUtilisateur() {

    if (this.canAddUtilisateur(this.defaults.birth_date)) {
      const dialogRef = this.dialog.open(AddClientUserModalComponent, {
        width: '600px',  // Asegurarse de que no haya un ancho máximo
        panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales,
        data: { id: this.user.schools[0].id }
      });

      dialogRef.afterClosed().subscribe((data: any) => {
        if (data) {

          if (data.action === 'add') {
            this.crudService.create('/clients-utilizers', { client_id: data.ret, main_id: parseInt(this.id) })
              .subscribe((res) => {
                this.getClientUtilisateurs();
              })
          } else {
            const user = {
              username: data.data.name,
              email: this.defaults.email,
              password: this.passwordGen.generateRandomPassword(12),
              image: null,
              type: 'client',
              active: true,
            }

            const client = {
              email: this.defaults.email,
              first_name: data.data.first_name,
              last_name: data.data.last_name,
              birth_date: moment(data.data.fromDate).format('YYYY-MM-DD'),
              phone: this.defaults.phone,
              telephone: this.defaults.telephone,
              address: this.defaults.address,
              cp: this.defaults.cp,
              city: this.defaults.city,
              province: this.defaults.province,
              country: this.defaults.country,
              image: null,
              language1_id: null,
              language2_id: null,
              language3_id: null,
              language4_id: null,
              language5_id: null,
              language6_id: null,
              user_id: null,
              station_id: this.defaults.station_id
            }

            this.setLanguagesUtilizateur(data.data.languages, client);

            this.crudService.create('/users', user)
              .subscribe((user) => {
                client.user_id = user.data.id;

                this.crudService.create('/clients', client)
                  .subscribe((clientCreated) => {
                    this.snackbar.open(this.translateService.instant('snackbar.client.create'), 'OK', { duration: 3000 });

                    this.crudService.create('/clients-schools', { client_id: clientCreated.data.id, school_id: this.user.schools[0].id, accepted_at: moment().toDate() })
                      .subscribe((clientSchool) => {

                        setTimeout(() => {
                          this.crudService.create('/clients-utilizers', { client_id: clientCreated.data.id, main_id: this.id })
                            .subscribe((res) => {
                              this.getClientUtilisateurs();
                            })
                        }, 1000);
                      });
                  })
              })
          }
        }
      });
    } else {
      this.snackbar.open(this.translateService.instant('snackbar.client.no_age'), 'OK', { duration: 3000 });
    }

  }

  setLanguagesUtilizateur(langs: any, dataToModify: any) {
    if (langs.length >= 1) {

      dataToModify.language1_id = langs[0].id;
    } if (langs.length >= 2) {

      dataToModify.language2_id = langs[1].id;
    } if (langs.length >= 3) {

      dataToModify.language3_id = langs[2].id;
    } if (langs.length >= 4) {

      dataToModify.language4_id = langs[3].id;
    } if (langs.length >= 5) {

      dataToModify.language5_id = langs[4].id;
    } if (langs.length === 6) {

      dataToModify.language6_id = langs[5].id;
    }
  }

  goToStep3(stepper: MatStepper) {
    if (this.selectedLanguages.length === 0) {
      this.snackbar.open(this.translateService.instant('snackbar.client.mandatory_language'), 'OK', { duration: 3000 });
      return;
    }

    stepper.next();
  }

  getGoals() {
    return from(this.clientSport).pipe(
      mergeMap(cs => from(cs.degrees || []).pipe(
        mergeMap((dg: any) => this.crudService.list('/degrees-school-sport-goals', 1, 10000, 'desc', 'id', '&degree_id=' + dg.id)),
        map(response => response.data)
      )),
      toArray(),
      tap(goalsArrays => {
        this.goals = goalsArrays.flat();  // Aplanamos el array de arrays en un solo array
      })
    );
  }



  calculateGoalsScore() {
    let ret = 0;
    if (this.selectedSport?.level) {
      const goals = this.goals.filter((g) => g.degree_id == this.selectedSport.level.id);
      const maxPoints = goals.length * 10;
      for (const goal of goals) {
        this.evaluationFullfiled.forEach(element => {
          if (element.degrees_school_sport_goals_id === goal.id) {
            ret += element.score;
          }
        });
        ret = ret > maxPoints ? maxPoints : ret
        return (ret / maxPoints) * 100;
      }
    }
    return ret;
  }

  getDegrees() {
    this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order', '&school_id=' + this.user.schools[0].id + '&active=1')
      .subscribe((data) => {
        this.allLevels = data.data;
      })
  }

  getDegreeScore(goal: any) {
    const d = this.evaluationFullfiled.find(element => element.degrees_school_sport_goals_id === goal)
    if (d) return d.score
    return 0
  }

  showInfoEvent(event: boolean) {
    this.showInfo = event;
  }

  showInfoEditEvent(event: boolean) {
    this.editInfo = event;
    this.selectedTabIndex = 0;
    this.selectedTabPreviewIndex = 0;
    this.editing = true;
  }

  showPersonalInfoEvent(event: boolean) {
    this.showPersonalInfo = event;
  }


  showPersonalInfoEditEvent(event: boolean) {
    this.editPersonalInfo = event;
    this.selectedTabIndex = 0;
    this.selectedTabPreviewIndex = 0;
    this.editing = true;
  }

  showAddressInfoEvent(event: boolean) {
    this.showAddressInfo = event;
  }

  showAddressInfoEditEvent(event: boolean) {
    this.editAddressInfo = event;
    this.selectedTabIndex = 1;
    this.selectedTabPreviewIndex = 0;
    this.editing = true;
  }

  showSportInfoEvent(event: boolean) {
    this.showSportInfo = event;
  }

  showSportInfoEditEvent(event: boolean) {
    this.editSportInfo = event;
    this.selectedTabIndex = 2;
    this.selectedTabPreviewIndex = 0;
    this.editing = true;
  }

  getCountry(id: any) {
    const country = this.countries.find((c) => c.id == +id);
    return country ? country.name : 'NDF';
  }

  getProvince(id: any) {
    const province = this.provinces.find((c) => c.id == +id);
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

  getPaymentMethod(id: number) {
    switch (id) {
      case 1:
        return 'CASH';
      case 2:
        return 'BOUKII PAY';
      case 3:
        return 'ONLINE';
      case 4:
        return 'AUTRE';
      case 5:
        return 'payment_no_payment';
      case 6:
        return 'bonus';

      default:
        return 'payment_no_payment'
    }
  }

  getHighestAuthorizedDegree(monitor, sport_id: number): any | null {
    // Encuentra los deportes asociados al monitor
    const degrees = monitor.monitor_sports_degrees
      .filter(degree =>
        degree.sport_id === sport_id &&
        degree.school_id === this.user?.schools[0]?.id
      )
      .map(degree => degree.monitor_sport_authorized_degrees)
      .flat(); // Aplanamos el array para obtener todos los grados autorizados

    if (degrees.length === 0) {
      return null; // Si no hay grados autorizados, retornamos null
    }

    // Buscamos el degree autorizado con el degree_order más alto
    const highestDegree = degrees.reduce((prev, current) => {
      return current.degree.degree_order > prev.degree.degree_order ? current : prev;
    });

    return highestDegree;
  }


  isActiveBookingUser(bu: any): boolean {
    // Compara la fecha más futura con la fecha actual
    return bu.status === 1 &&
      new Date(bu.date) > new Date();
  }

  isFinishedBookingUser(bu: any): boolean {
    // Compara la fecha más futura con la fecha actual
    return bu.status === 1 &&
      new Date(bu.date) < new Date();
  }

  getExtrasPrice() {
    let ret = 0;
    this.detailData.bookingusers.forEach(element => {
      if (element.courseExtras && element.courseExtras.length > 0 && !ret) {
        element.courseExtras.forEach(ce => {
          ret = ret + parseFloat(ce.course_extra.price);
        });
      }
    });

    return ret;
  }



  existExtras() {
    let ret = false;

    this.detailData.bookingusers.forEach(element => {
      if (element.courseExtras && element.courseExtras.length > 0 && !ret) {
        ret = true;
      }
    });

    return ret;
  }

  getBonusPrice() {
    let ret = 0;
    this.bonus.forEach(element => {
      ret = ret + element.currentPay;
    });
    return ret;
  }

  async showDetailEvent(event: any) {

    if (event.showDetail || (!event.showDetail && this.detailData !== null && this.detailData.id !== event.item.id)) {
          this.detailData = event.item.booking;

          this.bonus = [];
         // this.detailData = booking.data;

          // Ordenar los usuarios de la reserva
          this.detailData.bookingusers = this.orderBookingUsers(this.detailData.booking_users);

          // Obtener usuarios únicos de la reserva
          this.getUniqueBookingUsers(this.detailData.bookingusers);

          this.getSchoolSportDegrees();

          // Obtener los logs de los vouchers directamente desde detailData
          if (this.detailData.vouchers_logs.length > 0) {
            this.detailData.vouchers_logs.forEach(voucherLog => {
              let voucher = voucherLog.voucher;
              voucher.currentPay = parseFloat(voucherLog.amount);
              this.bonus.push(voucher);
            });
          }

          // Procesar los extras de los usuarios de la reserva
          this.detailData.bookingusers.forEach(book => {
            book.courseExtras = [];
            book.booking_user_extras.forEach(extra => {
              // Se asume que los extras están directamente en el objeto book
              book.courseExtras.push(extra);
            });
          });
          this.showDetail = true;


    } else {

      this.showDetail = event.showDetail;
      this.detailData = null;
    }

  }

  getUniqueBookingUsers(data: any) {
    const uniqueEntriesMap = new Map();

    data.forEach((item: any) => {
      const key = `${item.client_id}-${item.course_id}`;

      if (!uniqueEntriesMap.has(key)) {
        uniqueEntriesMap.set(key, {
          ...item,
          bookingusers: [] // Crea un array de bookingusers para almacenar cada fecha
        });
      }

      // Agrega la fecha actual al array de bookingusers
      uniqueEntriesMap.get(key).bookingusers.push(item);
    });

    // Convertir el Map en un array de objetos únicos con fechas agrupadas
    this.bookingUsersUnique = Array.from(uniqueEntriesMap.values());
  }

  getLanguage(id: any) {
    const lang = this.languages.find((c) => c.id == +id);
    return lang ? lang.code.toUpperCase() : 'NDF';
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

  getClient(id: any) {
    if (id && id !== null) {
      return this.clients.find((c) => c.id === id);
    }
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
    return moment(date).format('dddd, D MMMM YYYY');
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

  getClientDegreeObject(client: any) {
    if (!client || !client.client_sports || !client.client_sports.length) {
      return 0;
    }
    const sportId = this.detailData.bookingusers && this.detailData.bookingusers[0] ? this.detailData.bookingusers[0].course.sport_id : null;
    if (!sportId) {
      return 0;
    }
    const clientSport = client.client_sports.find(cs => cs.sport_id === sportId && cs.school_id == this.user.schools[0].id);
    if (!clientSport || !clientSport.degree_id) {
      return 0;
    }
    return clientSport.degree;
  }



  getClientBookingDegree(client: any) {
    if (!client || !client.client_sports || !client.client_sports.length) {
      return 0;
    }
    const sportId = this.detailData.bookingusers && this.detailData.bookingusers[0] ? this.detailData.bookingusers[0].course.sport_id : null;
    if (!sportId) {
      return 0;
    }
    const clientSport = client.client_sports.find(cs => cs.sport_id === sportId && cs.school_id == this.user.schools[0].id);
    if (!clientSport || !clientSport.degree_id) {
      return 0;
    }
    return clientSport.degree_id;
  }

  get isActive(): boolean {
    if (!this.detailData.booking_users || this.detailData.booking_users.length === 0) {
      return false;
    }

    // Encuentra la fecha más futura en booking_users
    const maxDate = this.detailData.booking_users.reduce((latest, user) => {
      const userDate = new Date(user.date); // Asumiendo que cada `user` tiene una propiedad `date`
      return userDate > latest ? userDate : latest;
    }, new Date(0)); // Inicializamos con una fecha muy pasada

    // Compara la fecha más futura con la fecha actual
    return this.detailData.status === 1 &&
      maxDate > new Date();
  }

  get isFinished(): boolean {
    if (!this.detailData.booking_users || this.detailData.booking_users.length === 0) {
      return false;
    }

    // Encuentra la fecha más futura en booking_users
    const maxDate = this.detailData.booking_users.reduce((latest, user) => {
      const userDate = new Date(user.date); // Asumiendo que cada `user` tiene una propiedad `date`
      return userDate > latest ? userDate : latest;
    }, new Date(0)); // Inicializamos con una fecha muy pasada

    // Compara la fecha más futura con la fecha actual
    return this.detailData.status === 1 &&
      maxDate < new Date();
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

  close() {
    this.showDetail = false;
    this.detailData = null;
  }

  getGoalImage(goal: any): string {
    let ret = '';
    if (goal.length > 0) {
      this.allClientLevels.forEach((element: any) => {
        if (element.id === goal[0].degree_id) {
          ret = element.image;
        }
      });
    }
    return ret;
  }



  getEvaluationsData(level:any): any {
    let ret: any = [];

    this.evaluations.forEach((element: any) => {
      if (element.degree_id === level.id) {
        ret.push(element);
      }
    });

    return ret;
  }

  @ViewChild('sliderContainer', { static: false }) sliderContainer!: ElementRef;
  centeredCardIndex: number = 0;
  sportCard: any[] = [];

  scrollLeft(num: number) {
    this.sliderContainer.nativeElement.scrollBy({ left: num * 300, behavior: 'smooth' });
  }
  onScroll() {
    this.updateCenteredCardIndex();
  }

  private updateCenteredCardIndex() {
    const container = this.sliderContainer.nativeElement;
    const containerCenter = container.scrollLeft + container.clientWidth / 2;

    let closestIndex = 0;
    let closestDistance = Infinity;

    const cards = container.querySelectorAll('app-user-detail-sport-card');
    cards.forEach((card: HTMLElement, index: number) => {
      const cardCenter = card.offsetLeft + card.clientWidth / 2;
      const distance = Math.abs(containerCenter - cardCenter);
      if (distance < closestDistance) {
        closestDistance = distance;
        closestIndex = index;
      }
    });

    this.centeredCardIndex = closestIndex;
  }
}
