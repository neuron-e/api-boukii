import { Component, EventEmitter, OnInit, Output, Input, Inject, Optional } from '@angular/core';
import {
  FormControl,
  UntypedFormBuilder,
  UntypedFormGroup,
} from "@angular/forms";
import {
  Observable,
  Subscription,
  forkJoin,
  map,
  of,
  startWith,
  mergeMap,
} from "rxjs";
import { stagger20ms } from "src/@vex/animations/stagger.animation";
import { fadeInUp400ms } from "src/@vex/animations/fade-in-up.animation";
import { MAT_DIALOG_DATA, MatDialog } from '@angular/material/dialog';
import { BookingsCreateUpdateModalComponent } from "../bookings-create-update-modal/bookings-create-update-modal.component";
import { ApiCrudService } from "src/service/crud.service";
import {
  MatCalendarCellCssClasses,
  MatDatepickerInputEvent,
} from "@angular/material/datepicker";
import * as moment from "moment";
import { CalendarService } from "src/service/calendar.service";
import { MatSnackBar } from "@angular/material/snack-bar";
import { AddDiscountBonusModalComponent } from "../bookings-create-update/add-discount-bonus/add-discount-bonus.component";
import { AddReductionModalComponent } from "../bookings-create-update/add-reduction/add-reduction.component";
import { ActivatedRoute, Router } from "@angular/router";
import { MOCK_COUNTRIES } from "src/app/static-data/countries-data";
import { SchoolService } from "src/service/school.service";
import { CancelBookingModalComponent } from "../cancel-booking/cancel-booking.component";
import { CancelPartialBookingModalComponent } from "../cancel-partial-booking/cancel-partial-booking.component";
import { TranslateService } from "@ngx-translate/core";
import { BookingService } from "src/service/bookings.service";
import { UpdateCourseModalModule } from "./update-course/update-course.module";
import { UpdateCourseModalComponent } from "./update-course/update-course.component";
import { ConfirmModalComponent } from "../../monitors/monitor-detail/confirm-dialog/confirm-dialog.component";
import { RefundBookingModalComponent } from "../refund-booking/refund-booking.component";
import { DateAdapter } from "@angular/material/core";
import { switchMap } from "rxjs/operators";

@Component({
  selector: "vex-booking-detail",
  templateUrl: "./booking-detail.component.html",
  styleUrls: ["./booking-detail.component.scss"],
  animations: [stagger20ms, fadeInUp400ms],
})
export class BookingDetailComponent implements OnInit {
  privateIcon = "https://api.boukii.com/storage/icons/prive_ski2x.png";
  collectifIcon = "https://api.boukii.com/storage/icons/collectif_ski2x.png";
  @Input()
  public monthAndYear = new Date();

  @Output()
  public monthAndYearChange = new EventEmitter<Date | null>();

  borderActive: number = -1;
  showDetail: any = [];

  createComponent = BookingsCreateUpdateModalComponent;
  selectedDatePrivate = new Date();

  title = "Título de la Reserva";
  titleMoniteur = "Nombre monitor";
  usersCount = 5;
  duration = "3 horas";
  dates = ["03/11/2023", "04/11/2023", "05/11/2023"]; // Ejemplo de fechas
  durations = ["1h 30", "2h 00", "2h 30"]; // Ejemplo de duraciones
  persons = []; // Ejemplo de número de personas

  reservedDates = [
    new Date(),
    new Date(),
    new Date(),
    new Date(),
    new Date(),
    // ... otras fechas
  ];
  userAvatar = "../../../../assets/img/avatar.png";
  userName = "Nombre de Usuario";
  userNameSub = "Nombre de Utilizador";
  userLevel = "Intermedio";
  selectedButton: string = "1";
  selectedSubButton: string = "";
  bookingComplete: boolean = false;

  static id = 100;
  minDate: Date;
  selectedDate = null;
  selectedPrivateCoursesDate = moment();
  selectedItem: any = null;
  selectedCourseDateItem: any = null;
  selectedSubGroupItem: any = null;
  selectedSubGroupItemIndex: any = null;
  courseDates: any = [];
  reservableCourseDate: any = [];

  periodUnique = true;
  periodMultiple = false;
  sameMonitor = false;

  times: string[] = this.generateTimes();
  filteredTimes: Observable<string[]>;

  dateControl = new FormControl();
  timeControl = new FormControl();
  durationControl = new FormControl();
  personsControl = new FormControl();
  clientsForm = new FormControl("");
  subClientForm = new FormControl();
  sportForm = new FormControl();
  levelForm = new FormControl();
  monitorsForm = new FormControl();

  filteredOptions: Observable<any[]>;
  filteredSubClients: Observable<any[]>;
  filteredSports: Observable<any[]>;
  filteredLevel: Observable<any[]>;
  filteredMonitors: Observable<any[]>;
  languages = [];
  courseType: any = "collectif";
  courseTypeId: any = 1;
  opRem = 0;
  boukiiCare = 0;
  form: UntypedFormGroup;
  defaults: any = {
    price_total: null,
    has_cancellation_insurance: false,
    price_cancellation_insurance: 0,
    has_boukii_care: false,
    price_boukii_care: 0,
    currency: null,
    paid_total: null,
    paid: null,
    payrexx_reference: null,
    payrexx_transaction: null,
    attendance: null,
    payrexx_refund: null,
    notes: null,
    notes_school: null,
    school_id: null,
    client_main_id: null,
    payment_method_id: null,
    paxes: null,
    color: null,
  };

  defaultsBookingUser: any = {
    school_id: null,
    booking_id: null,
    client_id: null,
    course_subgroup_id: null,
    course_id: null,
    course_date_id: null,
    degree_id: null,
    course_group_id: null,
    monitor_id: null,
    hour_start: null,
    hour_end: null,
    price: null,
    currency: null,
    date: null,
    attended: null,
    color: null,
  };

  options: string[] = ["One", "Two", "Three"];
  mode: "create" | "update" = "create";
  loading: boolean = true;
  loadingCalendar: boolean = true;
  sportTypeSelected: number = 1;

  bookings = [];
  bookingsToCreate = [];
  discounts = [];
  clients = [];
  users = [];
  sportData = [];
  sportDataList = [];
  sportTypeData = [];
  levels = [];
  utilizers = [];
  courses = [];
  bookingExtras = [];
  courseExtra = [];
  coursesMonth = [];
  monitors = [];
  season = [];
  school = [];
  settings: any = [];
  user: any;
  @Input() id: any;
  selectedForfait = [];
  mainIdSelected = true;
  detailClient: any;
  reduction: any = null;
  finalPrice: any = null;
  bonusPrices: any = null;
  finalPriceNoTaxes: any = null;
  bonus: any = [];
  currentBonus: any = [];
  bonusLog: any = [];
  bookingLog: any = [];
  totalPrice: any = 0;
  booking: any;
  bookingPendingPrice: any = 0;
  bookingUsers: any;
  bookingUsersUnique: any;
  countries = MOCK_COUNTRIES;
  schoolSettings: any = [];
  clientsIds = [];

  tva = 0;
  tvaPrice: any = 0;
  priceRefund: any = 0;
  priceNoRefund: any = 0;
  cancellationInsurance = 0;
  boukiiCarePrice = 0;
  payments = [];
  degreesClient: any[] = [];

  private subscription: Subscription;

  constructor(
    private fb: UntypedFormBuilder,
    private dialog: MatDialog,
    private crudService: ApiCrudService,
    private calendarService: CalendarService,
    private bookingService: BookingService,
    private snackbar: MatSnackBar,
    private activatedRoute: ActivatedRoute,
    private schoolService: SchoolService,
    private router: Router,
    public translateService: TranslateService,
    private dateAdapter: DateAdapter<Date>,
    @Optional() @Inject(MAT_DIALOG_DATA) public incData: any
  ) {
    this.dateAdapter.setLocale(this.translateService.getDefaultLang());
    this.dateAdapter.getFirstDayOfWeek = () => {
      return 1;
    };
    this.minDate = new Date(); // Establecer la fecha mínima como la fecha actual
    this.subscription = this.calendarService.monthChanged$.subscribe(
      (firstDayOfMonth) => {
        this.handleMonthChange(firstDayOfMonth);
      }
    );
  }

  async ngOnInit() {
    this.user = JSON.parse(localStorage.getItem("boukiiUser"));
    if (!this.incData) {
      this.id = this.activatedRoute.snapshot.params.id;
    } else {
      this.id = this.incData.id;
    }
    this.schoolService.getSchoolData(this.user).subscribe((school) => {
      this.schoolSettings = school.data;
    })

    await this.getDegreesClient();
    await this.getData(false);
  }

  async getDegreesClient() {
    try {
      const data: any = await this.crudService
        .get(
          "/degrees?perPage=" + 99999 + "&school_id=" + this.user.schools[0].id
        )
        .toPromise();
      this.degreesClient = data.data.sort(
        (a, b) => a.degree_order - b.degree_order
      );
      this.degreesClient.forEach((degree: any) => {
        degree.inactive_color = this.lightenColor(degree.color, 30);
      });
    } catch (error) {
      console.error("There was an error!", error);
    }
  }

  private lightenColor(hexColor: string, percent: number): string {
    let r: any = parseInt(hexColor.substring(1, 3), 16);
    let g: any = parseInt(hexColor.substring(3, 5), 16);
    let b: any = parseInt(hexColor.substring(5, 7), 16);

    // Increase the lightness
    r = Math.round(r + ((255 - r) * percent) / 100);
    g = Math.round(g + ((255 - g) * percent) / 100);
    b = Math.round(b + ((255 - b) * percent) / 100);

    // Convert RGB back to hex
    r = r.toString(16).padStart(2, "0");
    g = g.toString(16).padStart(2, "0");
    b = b.toString(16).padStart(2, "0");

    return "#" + r + g + b;
  }

  async getData(updateBooking = false) {
    this.loading = true;
    this.discounts = [];
    this.bonus = [];
    this.bookingsToCreate = [];
    this.courseExtra = [];
    this.bookingExtras = [];
    this.bookingUsers = [];
    this.bookingUsersUnique = [];
    this.currentBonus = [];
    this.user = JSON.parse(localStorage.getItem("boukiiUser"));

    this.getMonitors();
    this.getLanguages();
    this.getPayments();
    this.getBookingsLogs();

    this.crudService
      .get("/schools/" + this.user.schools[0].id)
      .subscribe((school) => {
        this.school = school.data;
        this.settings = typeof school.data.settings === 'string' ?
          JSON.parse(school.data.settings) : school.data.settings;
        this.cancellationInsurance = parseFloat(
          this.settings?.taxes?.cancellation_insurance_percent
        );
        this.boukiiCarePrice = parseInt(
          this.settings?.taxes?.boukii_care_price
        );
        this.tva = parseFloat(this.settings?.taxes?.tva);

        forkJoin([
          this.getSportsType(),
          this.getClients(),
          this.getUsers(),
        ]).subscribe((data: any) => {
          this.sportTypeData = data[0].data.reverse();
          this.clients = data[1].data;
          this.users = data[2].data;
          this.detailClient = this.clients[0];
          this.crudService
            .get("/bookings/" + this.id, [
              "user",
              "vouchersLogs.voucher",
              "bookingUsers.client.clientSports",
              "bookingUsers.course.courseDates",
              "bookingUsers.bookingUserExtras.courseExtra",
            ])
            .subscribe((data) => {
              this.booking = data.data;
              let vl = this.booking.vouchers_logs;
              if (vl.length > 0) {
                this.bonusLog = vl;
                vl.forEach((voucherLog) => {
                  let v = voucherLog.voucher;

                  v.currentPay = parseFloat(voucherLog.amount);
                  v.before = true;

                  this.bonus.push({ bonus: v, log: voucherLog });
                  this.currentBonus.push({ bonus: v, log: voucherLog });
                });
              }

              this.bookingUsers = [...this.booking.booking_users];
              this.getUniqueBookingUsers();
              // Agrupación de booking_users
              const groupedByCourseOrClientId = this.booking.booking_users.reduce(
                (accumulator, currentValue) => {
                  const course = currentValue.course;
                  const isCourseTypeOne = course.course_type === 1;

                  // Usa `client_id-course_id` como clave si course_type es 1, de lo contrario solo `course_id`
                  const key = isCourseTypeOne ? `${currentValue.client_id}-${currentValue.course_id}` : currentValue.course_id;

                  // Inicializa la clave en el acumulador si aún no existe
                  if (!accumulator[key]) {
                    accumulator[key] = [];
                  }

                  // Agrega el objeto actual al array correspondiente para este curso o cliente
                  accumulator[key].push(currentValue);

                  return accumulator;
                },
                {}
              );

              // Procesa los grupos de acuerdo al tipo de curso
              for (const key in groupedByCourseOrClientId) {
                if (groupedByCourseOrClientId.hasOwnProperty(key)) {
                  const elements = groupedByCourseOrClientId[key];
                  const course = elements[0].course;

                  if (course.course_type === 2 && this.booking.old_id === null) {
                    elements.forEach((element) => {
                      const dataBook = {
                        price_total: 0,
                        courseDates: [],
                        degrees_sport: [],
                        sport_id: null,
                        clients: [],
                        relatedMainClient: null,
                      };

                      if (parseFloat(element.price) !== 0) {
                        dataBook.sport_id = course.sport_id;
                        dataBook.degrees_sport = this.degreesClient.filter(
                          (degree) => degree.sport_id === course.sport_id
                        );
                        dataBook.relatedMainClient = element.client_id;
                        dataBook.courseDates.push(element);
                        this.courses.push(course);
                        this.clientsIds.push(element.client_id);
                        this.bookingsToCreate.push(dataBook);
                      } else {
                        dataBook.relatedMainClient = this.booking.client_main_id;
                        dataBook.clients.push(element.client_id);
                        this.clientsIds.push(element.client_id);
                      }
                    });
                  } else {
                    this.courses.push(course);

                    const data = {
                      price_total: 0,
                      courseDates: [],
                      degrees_sport: [],
                      sport_id: null,
                    };
                    data.sport_id = course.sport_id;
                    data.degrees_sport = this.degreesClient.filter(
                      (degree) => degree.sport_id === course.sport_id
                    );

                    elements.forEach((element, idx) => {
                      if (course.course_type === 1 && !course.is_flexible) {
                        if (idx === 0) {
                          data.price_total = parseFloat(element.price);
                        }
                      }
                      data.courseDates.push(element);
                    });

                    this.bookingsToCreate.push(data);
                  }
                }
              }

              this.bookingUsers.forEach((bu, idx) => {
                let bue = bu.booking_user_extras;
                if (bue.length > 0) {
                  this.bookingExtras.push(bue[0]);
                  bue.forEach((element) => {
                    let ce = element.course_extra;
                    if (ce) {
                      ce.course_date_id = bu.course_date_id;
                      ce.booking_user_id = bu.id;
                      this.courseExtra.push(ce);
                    }
                  });
                }
              });


              this.calculateDiscounts();
              this.calculateFinalPrice();

              this.booking.price_total = this.finalPrice;

              this.booking.price_cancellation_insurance = this.booking.has_cancellation_insurance ? this.getOpRemPrice() : 0;
              //  this.bookingPendingPrice = parseFloat(this.booking.price_total) - parseFloat(this.booking.paid_total);
              if (updateBooking) {
                this.booking.paid = this.bookingPendingPrice <= 0;
                this.crudService
                  .update(
                    "/bookings",
                    {
                      price_total: parseFloat(this.finalPrice),
                      price_cancellation_insurance:
                        this.finalPrice -
                        Math.round(
                          (this.finalPrice /
                            (+this.cancellationInsurance + 1)) *
                          100
                        ) /
                        100,
                      paid: this.bookingPendingPrice <= 0,
                      send_mail: true,
                    },
                    this.id
                  )
                  .subscribe(() => {
                    this.bookingPendingPrice =
                      parseFloat(this.booking.price_total) -
                      parseFloat(this.booking.paid_total);
                  });
              }
              if (this.bookingPendingPrice < 0 && this.booking.paid) {
                const dialogRef = this.dialog.open(ConfirmModalComponent, {
                  data: {
                    message: "",
                    title: this.translateService.instant("refund_title"),
                  },
                });

                dialogRef.afterClosed().subscribe((data: any) => {
                  if (data) {
                    this.refundBooking();
                  }
                });
              }

              this.loading = false;
            });
        });
      });
  }

  getAmountCourse(item: any, index: number) {
    if (
      this.courses[index].course_type === 2 &&
      this.courses[index].is_flexible
    ) {
      const priceRange = this.courses[index].price_range.find(
        (a) => a[1] !== null
      );
      return priceRange[
        this.bookingUsersUnique.filter(
          (b) => b.course_id === this.courses[index].id
        ).length
      ];
    } else {
      return this.courses[index].price;
    }
  }

  get isActive(): boolean {
    if (!this.booking.booking_users || this.booking.booking_users.length === 0) {
      return false;
    }

    // Encuentra la fecha más futura en booking_users
    const maxDate = this.booking.booking_users.reduce((latest, user) => {
      const userDate = new Date(user.date); // Asumiendo que cada `user` tiene una propiedad `date`
      return userDate > latest ? userDate : latest;
    }, new Date(0)); // Inicializamos con una fecha muy pasada

    // Compara la fecha más futura con la fecha actual
    return this.booking.status === 1 &&
      maxDate > new Date();
  }

  get isFinished(): boolean {
    if (!this.booking.booking_users || this.booking.booking_users.length === 0) {
      return false;
    }

    // Encuentra la fecha más futura en booking_users
    const maxDate = this.booking.booking_users.reduce((latest, user) => {
      const userDate = new Date(user.date); // Asumiendo que cada `user` tiene una propiedad `date`
      return userDate > latest ? userDate : latest;
    }, new Date(0)); // Inicializamos con una fecha muy pasada

    // Compara la fecha más futura con la fecha actual
    return this.booking.status === 1 &&
      maxDate < new Date();
  }

  isFinishedBookingUser(bu: any): boolean {
    // Compara la fecha más futura con la fecha actual
    return bu.status === 1 &&
      new Date(bu.date) < new Date();
  }

  isActiveBookingUser(bu: any): boolean {
    // Compara la fecha más futura con la fecha actual
    return bu.status === 1 &&
      new Date(bu.date) > new Date();
  }

  findPaxesByCourseExtra(group) {
    return this.courseExtra.filter(cE => cE.description == group.groupName && cE.name == group.optionName).length
  }

  getSettingsOptions(course) {
    let settings = typeof course.settings === 'string' ?
      JSON.parse(course.settings) : course.settings;

    if (settings?.groups) {
      settings.groups.forEach(group => {
        group.paxes = 0;
      });
      return settings?.groups;
    }

    return [];
  }

  trackByIndex(index: number, item: any): number {
    return index; // Devuelve el índice como identificador único
  }

  getTotalBook(bI: number, item: any) {
    if ((this.courses[bI]?.course_type === 2 || this.courses[bI]?.course_type === 3) && this.courses[bI]?.is_flexible) {
      return this.getPrivateFlexPrice(item.courseDates);
    } else if (this.courses[bI]?.course_type === 1) {
      return item?.price_total;
    } else if (
      this.courses[bI]?.course_type === 2 &&
      !this.courses[bI]?.is_flexible
    ) {
      return this.courses[bI]?.price * item.courseDates.length;
    }
  }

  getUniqueBookingUsers() {
    const clientIds = new Set();
    const uniqueDates = new Set();
    const uniqueMonitors = new Set();
    const uniqueGroupIds = new Set();
    this.bookingUsersUnique = [];

    this.bookingUsersUnique = this.bookingUsers.filter(item => {
      if (item.group_id) {
        // Filtrar por group_id si existe
        if (!uniqueGroupIds.has(item.group_id)) {
          uniqueGroupIds.add(item.group_id);
          return true;
        }
      } else {
        // Lógica original si no hay group_id
        if ((!clientIds.has(item.client_id) && !uniqueDates.has(item.date)) ||
          (item.course.course_type != 1 && !uniqueMonitors.has(item.monitor_id))) {
          clientIds.add(item.client_id);
          uniqueDates.add(item.date);
          uniqueMonitors.add(item.monitor_id);
          return true;
        }
      }
      return false;
    });
  }


  generateArray(paxes: number) {
    this.persons = [];
    for (let i = 1; i <= paxes; i++) {
      this.persons.push(i);
    }
  }

  getCurrentDateFormatted(): string {
    const currentDate = new Date();
    return (
      currentDate.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      }) +
      " " +
      currentDate.toLocaleTimeString("en-GB", {
        hour: "2-digit",
        minute: "2-digit",
      })
    );
  }

  ngOnDestroy() {
    this.subscription.unsubscribe();
  }

  filterSportsByType() {
    this.sportTypeSelected = this.form.get("sportType").value;
    let selectedSportType = this.form.get("sportType").value;
    this.filteredSports = of(
      this.sportData.filter((sport) => sport.sport_type === selectedSportType)
    );
    this.sportDataList = this.sportData.filter(
      (sport) => sport.sport_type === selectedSportType
    );
  }

  setCourseType(type: string, id: number) {
    this.courseType = type;
    this.courseTypeId = id;
    this.form.get("courseType").patchValue(id);

    const client = this.clients.find(
      (c) => c.id === this.defaultsBookingUser.client_id
    );
    client.sports.forEach((sport) => {
      if (sport.id === this.defaults.sport_id) {
        const level = this.levels.find((l) => l.id === sport.pivot.degree_id);
        this.levelForm.patchValue(level);
        this.defaultsBookingUser.degree_id = level.id;
        this.getCourses(level, this.monthAndYear);
      }
    });
  }

  selectItem(item: any) {
    if (item.course_type === 1) {
      if (this.selectedItem !== null && this.selectedItem?.id !== item.id) {
        this.reservableCourseDate = [];
      }
      this.courseDates = [];
      this.selectedItem = item;
      this.selectedSubGroupItem = null;
      this.courseDates.push(this.defaultsBookingUser);
    } else {
      this.generateArray(item.max_participants);
      this.courseDates = [];

      this.selectedItem = item;
      this.courseDates.push({
        school_id: this.user.schools[0].id,
        booking_id: null,
        client_id: this.detailClient.id,
        course_subgroup_id: null,
        course_id: item.id,
        hour_start: null,
        hour_end: null,
        price: this.selectedItem.price,
        currency: this.selectedItem.currency,
        date: null,
        attended: false,
      });
    }
  }

  selectSubGroupItem(item: any, subGroupIndex: any) {
    this.selectedSubGroupItem = item;
    this.selectedSubGroupItemIndex = subGroupIndex;
  }

  addCourseDate() {
    this.courseDates.push({
      school_id: this.user.schools[0].id,
      booking_id: null,
      client_id: this.detailClient.id,
      course_subgroup_id: null,
      course_id: this.selectedItem.id,
      hour_start: null,
      hour_end: null,
      price: this.selectedItem.price,
      currency: this.selectedItem.currency,
      date: null,
      attended: false,
    });
  }

  addReservableCourseDate(item, event) {
    if (event.checked) {
      this.reservableCourseDate.push(item);
    } else {
      const index = this.reservableCourseDate.findIndex(
        (c) => c.id === item.id
      );
      this.reservableCourseDate.splice(index, 1);
    }
  }

  setCourseDateItemPrivateNoFlexible(item: any, date: any) {
    item.course_date_id = date.id;
    item.date = date.date;
  }

  calculateAvailableHours(selectedCourseDateItem: any, time: any) {
    const start = moment(selectedCourseDateItem.hour_start, "HH:mm:ss");
    const end = moment(selectedCourseDateItem.hour_end, "HH:mm:ss");

    const hour = moment(time, "HH:mm");

    return !hour.isBetween(start, end);
  }

  resetCourseDates() {
    if (this.courseDates.length > 1) {
      const firstDate = this.courseDates[0];
      this.courseDates = [];
      this.courseDates.push(firstDate);
    }
  }

  addAnotherCourse() {
    this.selectedItem = null;
    this.selectedCourseDateItem = null;
    this.selectedSubGroupItem = null;
    this.selectedSubGroupItemIndex = null;
    this.reservableCourseDate = [];
    this.periodMultiple = false;
    this.periodUnique = true;
    this.sameMonitor = false;
    this.getData(true);
  }

  create() {
    if (
      this.defaults.payment_method_id === 2 ||
      this.defaults.payment_method_id === 3
    ) {
      this.loading = true;
      const observables = [];

      const bonuses = this.bonus.map((element) => ({
        name: element.bonus.code,
        quantity: 1,
        price: -element.bonus.quantity,
      }));

      const extras = this.courseExtra.map((element) => ({
        name: element.name,
        quantity: 1,
        price: parseFloat(element.price),
      }));

      const basket = {
        payment_method_id: this.defaults.payment_method_id,
        price_base: {
          name:
            this.bookingsToCreate.length > 1 ? "MULTI" : this.courses[0].name,
          quantity: 1,
          price: this.getBasePrice() - parseFloat(this.booking.paid_total),
        },
        reduction: { name: "Reduction", quantity: 1, price: -this.reduction },
        boukii_care: {
          name: "Boukii Care",
          quantity: 1,
          price: parseFloat(this.booking.price_boukii_care),
        },
        cancellation_insurance: {
          name: "Cancellation Insurance",
          quantity: 1,
          price: parseFloat(this.booking.price_cancellation_insurance),
        },
        extras: { total: this.courseExtra.length, extras: extras },
        bonus: { total: this.bonus.length, bonuses: bonuses },
        tva: { name: "TVA", quantity: 1, price: this.tvaPrice },
        price_total: this.finalPrice,
        paid_total: this.finalPrice - parseFloat(this.bookingPendingPrice),
        pending_amount: this.defaults.paid
          ? 0
          : parseFloat(this.bookingPendingPrice).toFixed(2),
      };

      this.crudService
        .post("/admin/bookings/payments/" + this.id, basket)
        .subscribe((result: any) => {
          if (this.defaults.payment_method_id === 3) {
            this.snackbar.open(this.translateService.instant('snackbar.booking_detail.send_mail'), 'OK', { duration: 1000 });
            this.loading = false;
          } else {
            window.open(result.data, "_self");
          }

        },
          (error) => {
            this.loading = false;
            this.snackbar.open(
              this.translateService.instant(
                "snackbar.booking_detail.send_mail.error"
              ),
              "OK",
              { duration: 1000 }
            );
          });
    } else {
      const dialogRef = this.dialog.open(ConfirmModalComponent, {
        data: { title: this.translateService.instant("is_paid") },
      });

      dialogRef.afterClosed().subscribe((data: any) => {
        if (data) {
          this.loading = true;
          const observables = [];

          observables.push(
            this.crudService.update(
              "/bookings",
              {
                paid: true,
                paid_total: this.finalPrice,
                payment_method_id: this.defaults.payment_method_id,
              },
              this.id
            )
          );

          if (this.bonus.length > 0) {
            this.bonus.forEach((element) => {
              if (!element.bonus.before) {
                const data = {
                  code: element.bonus.code,
                  quantity: element.bonus.quantity,
                  remaining_balance:
                    element.bonus.quantity - element.bonus.reducePrice,
                  payed:
                    element.bonus.quantity - element.bonus.reducePrice === 0,
                  client_id: element.bonus.client_id,
                  school_id: this.user.schools[0].id,
                };

                // Crear una observación para cada llamada HTTP y agregarla al array de observables
                observables.push(
                  this.crudService.update("/vouchers", data, element.bonus.id)
                );
                observables.push(
                  this.crudService.create("/vouchers-logs", {
                    voucher_id: element.bonus.id,
                    booking_id: this.id,
                    amount: element.bonus.reducePrice,
                  })
                );
              }
            });
          }

          if (this.defaults.payment_method_id != 5) {
            observables.push(
              this.crudService.create("/payments", {
                booking_id: this.id,
                school_id: this.user.schools[0].id,
                amount: this.bookingPendingPrice,
                status: "paid",
                notes: this.defaults.payment_method_id === 1 ? "cash" : "other",
              })
            );
          }

          const bookingLog = {
            booking_id: this.id,
            action: "update",
            description: "update booking",
            user_id: this.user.id,
            before_change: "confirmed",
            school_id: this.user.schools[0].id,
          };
          observables.push(this.crudService.post("/booking-logs", bookingLog));

          // Ejecutar las operaciones de actualización y creación en paralelo
          forkJoin(observables).subscribe(() => {
            this.snackbar.open(
              this.translateService.instant("snackbar.booking_detail.update"),
              "OK",
              { duration: 1000 }
            );
            //this.snackbar.open(this.translateService.instant('snackbar.booking.create'), 'OK', { duration: 1000 });
            this.getData(true);
            // this.goTo('/bookings');
          });
        }
      });
    }

    /*this.crudService.update('/bookings', {paid: this.defaults.paid, payment_method_id: this.defaults.payment_method_id}, this.id)
      .subscribe((res) => {
        this.snackbar.open(this.translateService.instant('snackbar.booking_detail.update'), 'OK', {duration: 3000});
        this.getData(true);
      })*/
  }

  processBonus(bookingId: any) {
    if (this.bonus.length > 0) {
      this.bonus.forEach((element) => {
        if (!element.bonus.before) {
          const data = {
            code: element.bonus.code,
            quantity: element.bonus.quantity,
            remaining_balance:
              element.bonus.quantity - element.bonus.reducePrice,
            payed: element.bonus.quantity - element.bonus.reducePrice === 0,
            client_id: element.bonus.client_id,
            school_id: this.user.schools[0].id,
          };
          this.crudService
            .update("/vouchers", data, element.bonus.id)
            .subscribe((result) => {
              this.crudService
                .create("/vouchers-logs", {
                  voucher_id: result.data.id,
                  booking_id: bookingId,
                  amount: element.bonus.reducePrice,
                })
                .subscribe((vresult) => { });
            });
        }
      });
    }
  }

  update() {
    const booking = this.form.value;
    booking.id = this.defaults.id;
  }

  isCreateMode() {
    return this.mode === "create";
  }

  isUpdateMode() {
    return this.mode === "update";
  }

  private _filterLevel(name: string): any[] {
    const filterValue = name.toLowerCase();
    return this.levels.filter((level) =>
      level.annotation.toLowerCase().includes(filterValue)
    );
  }

  displayFn(client: any): string {
    return client && client?.first_name && client?.last_name
      ? client?.first_name + " " + client?.last_name
      : client?.first_name;
  }

  displayFnMoniteurs(monitor: any): string {
    return monitor && monitor.first_name && monitor.last_name
      ? monitor.first_name + " " + monitor.last_name
      : "";
  }

  displayFnSport(sport: any): string {
    return sport && sport.name ? sport.name : "";
  }

  displayFnLevel(level: any): string {
    return level && level?.name && level?.annotation
      ? level?.annotation + " " + level?.name
      : level?.name;
  }

  displayFnTime(time: any): string {
    return time && time.name ? time.name : "";
  }

  generateTimes(): string[] {
    let times = [];
    let dt = new Date(2023, 0, 1, 8, 0, 0, 0);
    const end = new Date(2023, 0, 1, 17, 55, 0, 0);

    while (dt <= end) {
      const time =
        ("0" + dt.getHours()).slice(-2) +
        ":" +
        ("0" + dt.getMinutes()).slice(-2);
      times.push(time);
      dt.setMinutes(dt.getMinutes() + 5); // Incrementa en 5 minutos
    }
    return times;
  }

  openBookingModal() {
    const dialogRef = this.dialog.open(this.createComponent, {
      width: "90vw",
      height: "90vh",
      maxWidth: "100vw", // Asegurarse de que no haya un ancho máximo
      panelClass: "full-screen-dialog", // Si necesitas estilos adicionales
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
      }
    });
  }

  toggleBorder(index: number, utilizer: any) {
    this.mainIdSelected = false;
    this.borderActive = index;
    this.defaultsBookingUser.client_id = utilizer.id;
    this.detailClient = this.clients.find((c) => c.id === utilizer.id);

    this.detailClient.sports.forEach((sport) => {
      if (sport.id === this.defaults.sport_id) {
        const level = this.levels.find((l) => l.id === sport.pivot.degree_id);
        this.levelForm.patchValue(level);
        this.defaultsBookingUser.degree_id = level.id;
        this.getCourses(level, this.monthAndYear);
      }
    });
  }

  showDetailFn(id: number) {
    this.showDetail = id;
  }

  getClients() {
    return this.crudService.list(
      "/clients",
      1,
      100000,
      "desc",
      "id",
      "&school_id=" + this.user.schools[0].id,
      "&with[]=clientSports"
    ); /*
      .subscribe((data: any) => {
        this.clients = data.data;
        this.loading = false;

      })*/
  }

  getUsers() {
    return this.crudService.list(
      "/users",
      1,
      10000,
      "desc",
      "id",
      "&school_id=" + this.user.schools[0].id
    ); /*
      .subscribe((data: any) => {
        this.clients = data.data;
        this.loading = false;

      })*/
  }

  getSportsType() {
    return this.crudService.list("/sport-types", 1, 1000); /*
      .subscribe((data) => {
        this.sportTypeData = data.data.reverse();
      });*/
  }

  getSports() {
    this.crudService
      .list(
        "/school-sports",
        1,
        10000,
        "asc",
        "sport_id",
        "&school_id=" + this.user.schools[0].id
      )
      .subscribe((sport) => {
        this.sportData = sport.data.reverse();
        this.sportData.forEach((element) => {
          this.crudService
            .get("/sports/" + element.sport_id)
            .subscribe((data) => {
              element.name = data.data.name;
              element.icon_selected = data.data.icon_selected;
              element.icon_unselected = data.data.icon_unselected;
              element.sport_type = data.data.sport_type;
            });
        });
      });
  }

  selectSport(sport: any) {
    this.defaults.sport_id = sport.sport_id;
    this.form.get("sport").patchValue(sport.sport_id);
    this.getDegrees(sport.sport_id);
  }

  getDegrees(sportId: number, onLoad: boolean = false) {
    this.crudService
      .list(
        "/degrees",
        1,
        10000,
        "asc",
        "degree_order",
        "&school_id=" +
        this.user.schools[0].id +
        "&sport_id=" +
        sportId +
        "&active=1"
      )
      .subscribe((data) => {
        this.levels = data.data.sort((a, b) => a.degree_order - b.degree_order);
        this.filteredLevel = this.levelForm.valueChanges.pipe(
          startWith(""),
          map((value: any) =>
            typeof value === "string" ? value : value?.annotation
          ),
          map((annotation) =>
            annotation ? this._filterLevel(annotation) : this.levels.slice()
          )
        );
        if (onLoad) {
          this.clients[0].sports.forEach((sport) => {
            if (sport.id === this.defaults.sport_id) {
              const level = this.levels.find(
                (l) => l.id === sport.pivot.degree_id
              );
              this.levelForm.patchValue(level);
              this.defaultsBookingUser.degree_id = level.id;
              this.getCourses(level, this.monthAndYear);
            }
          });
        }
      });
  }

  getUtilzers(client: any, onLoad = false) {
    this.defaultsBookingUser.client_id = client.id;
    this.crudService
      .get("/admin/clients/" + client.id + "/utilizers")
      .subscribe((data: any) => {
        this.utilizers = data.data;
      });

    if (!onLoad) {
      client.sports.forEach((sport) => {
        if (sport.id === this.defaults.sport_id) {
          const level = this.levels.find((l) => l.id === sport.pivot.degree_id);
          this.levelForm.patchValue(level);
          this.defaultsBookingUser.degree_id = level.id;
          this.getCourses(level, this.monthAndYear);
        }
      });
    }

    this.getDegrees(this.defaults.sport_id, false);
  }

  getCourses(level: any, date: any, fromPrivate = false) {
    this.loadingCalendar = true;
    this.dateClass();
    let today, minDate, maxDate;

    if (!fromPrivate) {
      if (date === null) {
        today = moment();
        minDate = moment().startOf("month").isBefore(today)
          ? today
          : moment().startOf("month");
        maxDate = moment().endOf("month");
      } else {
        today = moment();
        minDate = moment(date).startOf("month").isBefore(today)
          ? today
          : moment(date).startOf("month");
        maxDate = moment(date).endOf("month");
      }
    } else {
      minDate = moment(date);
      maxDate = moment(date);
    }

    const rq = {
      start_date: minDate.format("YYYY-MM-DD"),
      end_date: maxDate.format("YYYY-MM-DD"),
      course_type: this.courseTypeId,
      sport_id: this.form.value.sport,
      client_id: this.defaultsBookingUser.client_id,
      degree_id: level.id,
      get_lower_degrees: true,
      school_id: this.user.schools[0].id,
    };

    this.crudService.post("/availability", rq).subscribe((data) => {
      this.defaultsBookingUser.degree_id = level.id;
      this.courses = data.data;
      if (!fromPrivate) {
        this.coursesMonth = data.data;
      }
      this.loading = false;
      this.loadingCalendar = false;
    });
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

  emitDateChange(event: MatDatepickerInputEvent<Date | null, unknown>): void {
    this.getCourses(this.levelForm.value, event.value, true);
  }

  getMonitors() {
    this.crudService
      .list(
        "/monitors",
        1,
        10000,
        "asc",
        "first_name",
        "&school_id=" + this.user.schools[0].id
      )
      .subscribe((data) => {
        this.monitors = data.data;
      });
  }

  addTimeToDate(timeString: any) {
    const match = timeString.match(/(\d+)h (\d+)min/);

    if (match) {
      const hours = parseInt(match[1], 10);
      const minutes = parseInt(match[2], 10);

      // Suponiendo que 'fecha' es tu fecha actual en Moment.js
      let fecha = moment();
      fecha.add(hours, "hours").add(minutes, "minutes");

      return fecha;
    } else {
      throw new Error("Formato de tiempo inválido");
    }
  }

  getSeason() {
    this.crudService
      .list(
        "/seasons",
        1,
        10000,
        "asc",
        "id",
        "&school_id=" + this.user.schools[0].id + "&is_active=1"
      )
      .subscribe((season) => {
        this.season = season.data[0];
      });
  }

  getSchool() {
    this.crudService
      .get("/schools/" + this.user.schools[0].id)
      .subscribe((school) => {
        this.school = school.data;
        this.settings = typeof school.data.settings === 'string' ?
          JSON.parse(school.data.settings) : school.data.settings;
        this.selectedForfait =
          this.settings.extras.forfait.length > 0
            ? this.settings.extras.forfait.length[0]
            : null;
      });
  }

  dateClass() {
    return (date: Date): MatCalendarCellCssClasses => {
      const dates = this.compareCourseDates();
      const currentDate = moment(date, "YYYY-MM-DD").format("YYYY-MM-DD");
      if (
        dates.indexOf(currentDate) !== -1 &&
        moment(this.minDate).isSameOrBefore(moment(date))
      ) {
        return "with-course";
      } else {
        return;
      }
    };
  }

  privateDateClass() {
    return (date: Date): MatCalendarCellCssClasses => {
      const dates = this.comparePrivateCourseDates();
      const currentDate = moment(date, "YYYY-MM-DD").format("YYYY-MM-DD");
      if (
        dates.indexOf(currentDate) !== -1 &&
        moment(this.minDate).isSameOrBefore(moment(date))
      ) {
        return "with-course-private";
      } else {
        return;
      }
    };
  }

  canBook(date: any) {
    return moment(date, "YYYY-MM-DD").isSameOrAfter(moment(this.minDate));
  }

  getLevelColor(id: any) {
    if (id && id !== null) {
      return this.levels.find((l) => l.id === id).color;
    }
  }

  getLevelOrder(id: any) {
    if (id && id !== null) {
      return this.levels.find((l) => l.id === id).degree_order;
    }
  }

  getDegree(id: any) {
    if (id && id !== null) {
      return this.degreesClient.find((l) => l.id === id);
    }
  }

  getLevelName(id: any) {
    if (id && id !== null) {
      const level = this.levels.find((l) => l.id === id);
      return level?.annotation + " - " + level?.name;
    }
  }

  getMonitorAvatar(id: number) {
    if (id && id === null) {
      return this.userAvatar;
    } else {
      const monitor = this.monitors.find((m) => m.id === id);
      return monitor?.image ? monitor.image : this.userAvatar;
    }
  }

  getMonitor(id: number) {
    if (id && id !== null) {
      const monitor = this.monitors.find((m) => m.id === id);

      return monitor;
    }
  }

  getMonitorName(id: number) {
    if (id && id !== null) {
      const monitor = this.monitors.find((m) => m.id === id);

      return monitor?.first_name + " " + monitor?.last_name;
    }
  }

  getClientAvatar(id: number) {
    if (id === null) {
      return this.userAvatar;
    } else {
      const client = this.clients.find((m) => m.id === id);
      return client?.image;
    }
  }

  getClientName(id: number) {
    if (id && id !== null) {
      const client = this.clients.find((m) => m.id === id);

      return client?.first_name + " " + client?.last_name;
    }
  }

  getClientDegree(id: number, sport_id: number) {
    if (id && id !== null && sport_id && sport_id !== null) {
      const client = this.clients.find((m) => m.id === id);
      const sportObject = client?.client_sports.find(
        (obj) => obj.sport_id === sport_id && obj.school_id == this.user.schools[0].id
      );

      return sportObject?.degree_id;
    }
  }

  getClientDegreeByClient(client: any, sport_id: number) {
    if (client && client !== null && sport_id && sport_id !== null) {
      const sportObject = client?.client_sports.find(
        (obj) => obj.sport_id === sport_id && obj.school_id == this.user.schools[0].id
      );

      return sportObject?.degree_id;
    }
  }


  getClient(id: number) {
    if (id && id !== null) {
      const client = this.clients.find((m) => m.id === id);

      return client;
    }
  }

  getUser(id: number) {
    if (id && id !== null) {
      const user = this.users.find((m) => m.id === id);

      return user;
    }
  }

  getCourse(id: number) {
    if (id && id !== null) {
      const course = this.courses.find((m) => m.id === id);

      return course;
    }
  }

  compareCourseDates() {
    let ret = [];
    this.courses.forEach((course) => {
      course.course_dates.forEach((courseDate) => {
        ret.push(moment(courseDate.date, "YYYY-MM-DD").format("YYYY-MM-DD"));
      });
    });

    return ret;
  }

  comparePrivateCourseDates() {
    let ret = [];
    this.coursesMonth.forEach((course) => {
      course.course_dates.forEach((courseDate) => {
        ret.push(moment(courseDate.date, "YYYY-MM-DD").format("YYYY-MM-DD"));
      });
    });

    return ret;
  }

  handleMonthChange(firstDayOfMonth: Date) {
    this.monthAndYear = moment(this.minDate).isAfter(moment(firstDayOfMonth))
      ? this.minDate
      : firstDayOfMonth;
    this.getCourses(this.levelForm.value, this.monthAndYear);
  }

  setClientsNotes(event: any) {
    this.bookingUsersUnique.forEach((element) => {
      this.crudService
        .update("/booking-users", { notes: event.target.value }, element.id)
        .subscribe(() => { });
    });

    this.snackbar.open(
      this.translateService.instant("snackbar.booking_detail.notes_client"),
      "OK",
      { duration: 3000 }
    );
  }

  setSchoolNotes(event: any) {
    this.bookingUsersUnique.forEach((element) => {
      this.crudService
        .update(
          "/booking-users",
          { notes_school: event.target.value },
          element.id
        )
        .subscribe(() => { });
    });
    this.snackbar.open(
      this.translateService.instant("snackbar.booking_detail.notes_school"),
      "OK",
      { duration: 3000 }
    );
  }

  public monthChanged(value: any, widget: any): void {
    this.monthAndYear = moment(this.minDate).isAfter(moment(value))
      ? this.minDate
      : value;
    this.getCourses(this.levelForm.value.id, this.monthAndYear);

    widget.close();
  }

  checkAllFields() {
    let ret = false;

    for (let i = 0; i < this.courseDates.length; i++) {
      if (
        (!this.courseDates[i].date && this.courseDates[i].date === null) ||
        this.courseDates[i].hour_start === null
      ) {
        ret = true;
        break;
      }
    }

    return ret;
  }

  getBookableCourses(dates: any) {
    return dates.find((d) => this.canBook(d.date)).course_groups;
  }

  calculateHourEnd(hour: any, duration: any) {
    if (
      duration.includes("h") &&
      (duration.includes("min") || duration.includes("m"))
    ) {
      const hours = duration.split(" ")[0].replacthe("h", "");
      const minutes = duration
        .split(" ")[1]
        .replace("min", "")
        .replace("m", "");

      return moment(hour, "HH:mm")
        .add(hours, "h")
        .add(minutes, "m")
        .format("HH:mm");
    } else if (duration.includes("h")) {
      const hours = duration.split(" ")[0].replace("h", "");

      return moment(hour, "HH:mm").add(hours, "h").format("HH:mm");
    } else {
      const minutes = duration
        .split(" ")[0]
        .replace("min", "")
        .replace("m", "");

      return moment(hour, "HH:mm").add(minutes, "m").format("HH:mm");
    }
  }

  getAvailableWeekDays(settings: any) {
    const data = typeof settings === 'string' ?
      JSON.parse(settings) : settings;
    let ret = null;
    if (data !== null) {
      if (data.weekDays.monday) {
        ret = ret === null ? "Monday" : ret + " - " + "Monday";
      }
      if (data.weekDays.tuesday) {
        ret = ret === null ? "Tuesday" : ret + " - " + "Tuesday";
      }
      if (data.weekDays.wednesday) {
        ret = ret === null ? "Wednesday" : ret + " - " + "Wednesday";
      }
      if (data.weekDays.thursday) {
        ret = ret === null ? "Thursday" : ret + " - " + "Thursday";
      }
      if (data.weekDays.friday) {
        ret = ret === null ? "Friday" : ret + " - " + "Friday";
      }
      if (data.weekDays.saturday) {
        ret = ret === null ? "Saturday" : ret + " - " + "Saturday";
      }
      if (data.weekDays.sunday) {
        ret = ret === null ? "Sunday" : ret + " - " + "Sunday";
      }
    }
    return ret;
  }

  calculateDiscounts() {
    if (this.courses.length > 0) {
      this.bookingsToCreate.forEach((b, idx) => {
        if (b.courseDates[0].status === 1 || this.booking.status === 2) {
          if (
            this.courses[idx].is_flexible &&
            this.courses[idx].course_type === 1
          ) {
            const discounts =
              typeof this.courses[idx].discounts === "string"
                ? JSON.parse(this.courses[idx].discounts)
                : this.courses[idx].discounts;
            if (discounts && discounts.length) {
              let i = 0;
              let price = 0;
              b.courseDates.forEach((element) => {
                const selectedDiscount = discounts.find(
                  (item) => item.date == i + 1
                );
                if (selectedDiscount) {
                  price =
                    price +
                    1 *
                    b.courseDates[0].course.price *
                    (selectedDiscount.percentage / 100);
                }
                i++;
              });
              this.discounts.push(price);
            }
          }
        }
      });
    }
  }

  getBasePrice(noDiscount = false) {
    let ret = 0;

    if (this.courses.length > 0) {
      this.bookingsToCreate.forEach((b, idx) => {

        if (
          this.courses[idx].is_flexible &&
          (this.courses[idx].course_type === 2 || this.courses[idx].course_type === 3)
        ) {
          ret = ret + this.getPrivateFlexPrice(b.courseDates);
          b.price_total = this.getPrivateFlexPrice(b.courseDates);
        } else if (
          !this.courses[idx].is_flexible &&
          this.courses[idx].course_type === 2
        ) {
          ret =
            ret + parseFloat(this.courses[idx]?.price) * b.courseDates.length;
          b.price_total =
            parseFloat(this.courses[idx]?.price) * b.courseDates.length;
        } else if (
          this.courses[idx].is_flexible &&
          this.courses[idx].course_type === 1
        ) {
          const discounts =
            typeof this.courses[idx].discounts === "string"
              ? JSON.parse(this.courses[idx].discounts)
              : this.courses[idx].discounts;
          let price = b?.courseDates[0].price * b.courseDates.length;
          let discount = 0;
          ret = ret + b?.courseDates[0].price * b.courseDates.length;
          if (!noDiscount) {
            discounts.forEach((element) => {
              if (element.date === b.courseDates.length) {
                ret = ret - ret * (element.percentage / 100);
                discount = price - price * (element.percentage / 100);
              }
            });
          }
          ret = ret - discount;
          b.price_total = price;
        } else {
          ret = ret + b?.price_total;
        }

      });

      return ret;
    }
  }

  getBasePriceForAnulations(noDiscount = false) {
    let ret = 0;

    if (this.courses.length > 0) {
      this.bookingsToCreate.forEach((b, idx) => {
        if (
          this.courses[idx].is_flexible &&
          this.courses[idx].course_type === 2
        ) {
          ret = ret + this.getPrivateFlexPrice(b.courseDates);
          b.price_total = this.getPrivateFlexPrice(b.courseDates);
        } else if (
          !this.courses[idx].is_flexible &&
          this.courses[idx].course_type === 2
        ) {
          ret =
            ret + parseFloat(this.courses[idx]?.price) * b.courseDates.length;
          b.price_total =
            parseFloat(this.courses[idx]?.price) * b.courseDates.length;
        } else if (
          this.courses[idx].is_flexible &&
          this.courses[idx].course_type === 1
        ) {
          const discounts =
            typeof this.courses[idx].discounts === "string"
              ? JSON.parse(this.courses[idx].discounts)
              : this.courses[idx].discounts;
          ret = ret + b?.courseDates[0].price * b.courseDates.length;
          if (!noDiscount) {
            discounts.forEach((element) => {
              if (element.date === b.courseDates.length) {
                ret = ret - ret * (element.percentage / 100);
              }
            });
          }

          b.price_total = ret;
        } else {
          ret = ret + b?.price_total;
        }
      });

      return ret;
    }
  }

  setForfait(
    event: any,
    forfait: any,
    booking: any,
    bookingIndex: number,
    bookingUser = null
  ) {
    const courseExtra = [];
    const bookingExtra = [];
    const courseId = booking.courseDates[0].course_id;
    let finalPrice = 0;

    if (booking.courseDates[0].course.course_type == 1) {
      this.courseExtra.forEach((element) => {
        if (
          booking.courseDates.find(
            (c) => element.course_date_id === c.course_date_id
          )
        ) {
          courseExtra.push(element);
        }
      });

      this.bookingExtras.forEach((element) => {
        if (
          courseExtra.find((b) => b.booking_user_id === element.booking_user_id)
        ) {
          bookingExtra.push(element);
        }
      });
    } else {
      bookingUser.booking_user_extras.forEach((element) => {
        bookingExtra.push(element);
        courseExtra.push(element.course_extra);
      });
    }

    if (event.source.checked) {
      const dialogRef = this.dialog.open(ConfirmModalComponent, {
        data: {
          title: this.translateService.instant("add_forfait"),
          message: this.translateService.instant("add_forfait_message"),
        },
      });

      dialogRef.afterClosed().subscribe((data: any) => {
        if (data) {
          this.loading = true;
          if (booking.courseDates[0].course.course_type == 1) {
            booking.courseDates.forEach((element) => {
              const bookingUserExtra = {
                booking_user_id: element.id,
                course_extra_id: null,
              };

              const courseExtra = {
                course_id: courseId,
                name: forfait.id,
                description: forfait.name,
                price: forfait.price + +((forfait.price * forfait.tva) / 100),
              };

              finalPrice = finalPrice + courseExtra.price;

              this.crudService
                .create("/course-extras", courseExtra)
                .subscribe((responseCourseExtra) => {
                  bookingUserExtra.course_extra_id =
                    responseCourseExtra.data.id;
                  this.crudService
                    .create("/booking-user-extras", bookingUserExtra)
                    .subscribe((bookExtra) => {
                      //this.crudService.update('/bookings', {paid_total: this.booking.price_total}, this.booking.id)
                    });
                });
            });
          } else {
            const bookingUserExtra = {
              booking_user_id: bookingUser.id,
              course_extra_id: null,
            };

            const courseExtra = {
              course_id: courseId,
              name: forfait.id,
              description: forfait.name,
              price: forfait.price + +((forfait.price * forfait.tva) / 100),
            };

            this.crudService
              .create("/course-extras", courseExtra)
              .subscribe((responseCourseExtra) => {
                bookingUserExtra.course_extra_id = responseCourseExtra.data.id;
                this.crudService
                  .create("/booking-user-extras", bookingUserExtra)
                  .subscribe((bookExtra) => {
                    //this.crudService.update('/bookings', {paid_total: this.booking.price_total}, this.booking.id)
                  });
              });
          }

          courseExtra.forEach((element) => {
            this.crudService
              .delete("/course-extras", element.id)
              .subscribe(() => { });
          });

          bookingExtra.forEach((element) => {
            this.crudService
              .delete("/booking-user-extras", element.id)
              .subscribe(() => { });
          });

          setTimeout(() => {
            this.getData(true);
          }, 1000);
        } else {
          event.source.checked = !event.source.checked
        }
      });
    } else {
      const dialogRef = this.dialog.open(ConfirmModalComponent, {
        data: {
          title: this.translateService.instant("delete_forfait"),
          message: this.translateService.instant("delete_forfait_message"),
        },
      });

      dialogRef.afterClosed().subscribe((data: any) => {
        if (data) {
          this.loading = true;

          courseExtra.forEach((element) => {
            finalPrice = finalPrice + element.price;

            this.crudService
              .delete("/course-extras", element.id)
              .subscribe(() => { });
          });

          bookingExtra.forEach((element) => {
            this.crudService
              .delete("/booking-user-extras", element.id)
              .subscribe(() => { });
          });

          setTimeout(() => {
            this.getData(true);
          }, 1000);
        } else {
          event.source.checked = !event.source.checked
        }
      });
    }
  }

  refundBooking() {
    const dialogRef = this.dialog.open(RefundBookingModalComponent, {
      width: "1000px", // Asegurarse de que no haya un ancho máximo
      panelClass: "full-screen-dialog", // Si necesitas estilos adicionales,
      data: { itemPrice: this.bookingPendingPrice, booking: this.booking },
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {


        if (data.type === "no_refund") {
          this.crudService
            .update(
              "/bookings",
              { paid_total: this.booking.price_total },
              this.booking.id
            )
            .subscribe(() => {
              this.crudService
                .create("/payments", {
                  booking_id: this.id,
                  school_id: this.user.schools[0].id,
                  amount: this.bookingPendingPrice,
                  status: "no_refund",
                  notes: "no refund applied",
                })
                .subscribe(() => { });

              this.snackbar.open(
                this.translateService.instant("snackbar.booking_detail.update"),
                "OK",
                { duration: 1000 }
              );
              this.getData();
            });
        } else if (data.type === "boukii_pay") {
          this.crudService
            .create("/booking-logs", {
              booking_id: this.id,
              action: "refund_boukii_pay",
              before_change: "confirmed",
              user_id: this.user.id,
              reason: data.reason,
            })
            .subscribe(() => {
              this.crudService
                .update(
                  "/bookings",
                  { paid_total: this.booking.price_total },
                  this.booking.id
                )
                .subscribe(() => {
                  this.crudService
                    .post("/admin/bookings/refunds/" + this.id, {
                      amount: -this.bookingPendingPrice,
                    })
                    .subscribe(() => {
                      this.snackbar.open(
                        this.translateService.instant(
                          "snackbar.booking_detail.update"
                        ),
                        "OK",
                        { duration: 1000 }
                      );
                      this.getData();
                    });
                });
            });
        } else if (data.type === "refund") {
          this.crudService
            .create("/booking-logs", {
              booking_id: this.id,
              action: "refund",
              before_change: "confirmed",
              user_id: this.user.id,
              reason: data.reason,
            })
            .subscribe(() => {
              this.crudService
                .update(
                  "/bookings",
                  { paid_total: this.booking.price_total },
                  this.booking.id
                )
                .subscribe(() => {
                  this.crudService
                    .create("/payments", {
                      booking_id: this.id,
                      school_id: this.user.schools[0].id,
                      amount: this.bookingPendingPrice,
                      status: "refund",
                      notes: "other",
                    })
                    .subscribe(() => {
                      this.snackbar.open(
                        this.translateService.instant(
                          "snackbar.booking_detail.update"
                        ),
                        "OK",
                        { duration: 1000 }
                      );
                      this.getData();
                    });
                });
            });
        } else if (data.type === "refund_gift") {
          const vData = {
            code: "BOU-" + this.generateRandomNumber(),
            quantity: -this.bookingPendingPrice,
            remaining_balance: -this.bookingPendingPrice,
            payed: false,
            client_id: this.booking.client_main_id,
            school_id: this.user.schools[0].id,
          };

          this.crudService
            .create("/booking-logs", {
              booking_id: this.id,
              action: "voucher_refund",
              before_change: "confirmed",
              user_id: this.user.id,
            })
            .subscribe(() => {
              this.crudService
                .update(
                  "/bookings",
                  { paid_total: this.booking.price_total },
                  this.booking.id
                )
                .subscribe(() => {
                  this.crudService
                    .create("/payments", {
                      booking_id: this.id,
                      school_id: this.user.schools[0].id,
                      amount: this.bookingPendingPrice,
                      status: "refund",
                      notes: "voucher",
                    })
                    .subscribe(() => { });
                  this.crudService
                    .create("/vouchers", vData)
                    .subscribe((result) => {
                      this.crudService
                        .create("/vouchers-logs", {
                          voucher_id: result.data.id,
                          booking_id: this.id,
                          amount: -vData.quantity,
                        })
                        .subscribe((vresult) => { });
                      this.snackbar.open(
                        this.translateService.instant(
                          "snackbar.booking_detail.update"
                        ),
                        "OK",
                        { duration: 1000 }
                      );
                      this.getData();
                    });
                });
            });
        }
      }
    });
  }

  deleteBooking() {
    const priceToRefund = !this.booking.has_cancellation_insurance
      ? this.finalPrice
      : this.finalPrice - this.booking.price_cancellation_insurance;
    const dialogRef = this.dialog.open(CancelBookingModalComponent, {
      width: "1000px", // Asegurarse de que no haya un ancho máximo
      panelClass: "full-screen-dialog", // Si necesitas estilos adicionales,
      data: {
        currentBonus: this.currentBonus,
        currentBonusLog: this.bonusLog,
        itemPrice: priceToRefund,
        booking: this.booking,
      },
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
        const bookingLog = {
          booking_id: this.id,
          action: "full_cancel",
          description: "cancel full booking",
          user_id: this.user.id,
          before_change: "confirmed",
          school_id: this.user.schools[0].id,
        };
        this.crudService.post("/booking-logs", bookingLog).subscribe(() => { });

        if (data.type === "no_refund") {
          this.crudService
            .create("/booking-logs", {
              booking_id: this.id,
              action: "no_refund",
              before_change: "confirmed",
              user_id: this.user.id,
              reason: data.reason,
            })
            .subscribe(() => {
              this.crudService
                .update("/bookings", { status: 2, price_total: 0 }, this.booking.id)
                .subscribe(() => {
                  this.crudService
                    .create("/payments", {
                      booking_id: this.id,
                      school_id: this.user.schools[0].id,
                      amount: this.bookingPendingPrice,
                      status: "no_refund",
                      notes: "no refund applied",
                    })
                    .subscribe(() => { });
                  this.crudService
                    .post("/admin/bookings/cancel", {
                      bookingUsers: this.bookingUsers.map((b) => b.id),
                    })
                    .subscribe(() => {
                      this.snackbar.open(
                        this.translateService.instant(
                          "snackbar.booking_detail.delete"
                        ),
                        "OK",
                        { duration: 3000 }
                      );
                      this.goTo("/bookings");
                    });
                });
            });
        } else if (data.type === "boukii_pay") {
          this.crudService
            .create("/booking-logs", {
              booking_id: this.id,
              action: "refund_boukii_pay",
              before_change: "confirmed",
              user_id: this.user.id,
              reason: data.reason,
            })
            .subscribe(() => {

              if (this.booking.paid) {
                this.crudService
                  .post("/admin/bookings/refunds/" + this.id, {
                    amount:
                      priceToRefund,
                  })
                  .subscribe(() => {
                    this.crudService
                      .update("/bookings", { status: 2, paid_total: this.booking.price_total - priceToRefund, price_total: 0 }, this.booking.id)
                      .subscribe(() => {
                        this.crudService
                          .post("/admin/bookings/cancel", {
                            bookingUsers: this.bookingUsers.map(
                              (b) => b.id
                            ),
                          })
                          .subscribe(() => {
                            this.snackbar.open(
                              this.translateService.instant(
                                "snackbar.booking_detail.update"
                              ),
                              "OK",
                              { duration: 1000 }
                            );
                            this.getData();
                          });
                      });
                  });
              } else {
                this.snackbar.open(
                  this.translateService.instant(
                    "snackbar.booking_detail.update"
                  ),
                  "OK",
                  { duration: 1000 }
                );
                this.getData();
              }
            });
        } else if (data.type === "refund") {
          this.crudService
            .create("/booking-logs", {
              booking_id: this.id,
              action: "refund_cash",
              before_change: "confirmed",
              user_id: this.user.id,
              reason: data.reason,
            })
            .subscribe(() => {
              this.crudService
                .create("/payments", {
                  booking_id: this.id,
                  school_id: this.user.schools[0].id,
                  amount:
                    priceToRefund,
                  status: "refund",
                  notes: "other",
                })
                .subscribe(() => { });

              this.crudService
                .update("/bookings", { status: 2 }, this.booking.id)
                .subscribe(() => {
                  this.crudService
                    .post("/admin/bookings/cancel", {
                      bookingUsers: this.bookingUsers.map((b) => b.id),
                    })
                    .subscribe(() => {
                      this.snackbar.open(
                        this.translateService.instant(
                          "snackbar.booking_detail.delete"
                        ),
                        "OK",
                        { duration: 3000 }
                      );
                      this.goTo("/bookings");
                    });
                });
            });
        } else if (data.type === "refund_gift") {
          const vData = {
            code: "BOU-" + this.generateRandomNumber(),
            quantity:
              priceToRefund,
            remaining_balance:
              priceToRefund,
            payed: false,
            client_id: this.booking.client_main_id,
            school_id: this.user.schools[0].id,
          };

          this.crudService
            .create("/booking-logs", {
              booking_id: this.id,
              action: "voucher_refund",
              before_change: "confirmed",
              user_id: this.user.id,
            })
            .subscribe(() => {
              this.crudService
                .update("/bookings", { status: 2 }, this.booking.id)
                .subscribe(() => {
                  this.crudService
                    .post("/admin/bookings/cancel", {
                      bookingUsers: this.bookingUsers.map((b) => b.id),
                    })
                    .subscribe(() => { });

                  /*                  this.crudService.create('/payments', {booking_id: this.id, school_id: this.user.schools[0].id, amount: this.finalPrice, status: 'refund', notes: 'voucher'})
                    .subscribe(() => {

                    })*/
                  this.crudService
                    .create("/vouchers", vData)
                    .subscribe((result) => {
                      this.crudService
                        .create("/vouchers-logs", {
                          voucher_id: result.data.id,
                          booking_id: this.id,
                          amount: -vData.quantity,
                        })
                        .subscribe((vresult) => { });
                      this.snackbar.open(
                        this.translateService.instant(
                          "snackbar.booking_detail.delete"
                        ),
                        "OK",
                        { duration: 3000 }
                      );
                      this.goTo("/bookings");
                    });
                });
            });
        }

        this.bookingUsers.forEach((element) => {
          this.crudService
            .update("/booking-users", { status: 2 }, element.id)
            .subscribe(() => {
              /*this.bookingExtras.forEach(element => {
                this.crudService.delete('/booking-user-extras', element.id)
                  .subscribe(() => {

                  })
              });

              this.courseExtra.forEach(element => {
                this.crudService.delete('/course-extras', element.id)
                  .subscribe(() => {

                  })
              });*/
            });
        });
      }
    });
  }

  getDiscountPrice(book) {
    let discountPrice = 0;
    const discounts =
      typeof book.courseDates[0].course.discounts === "string"
        ? JSON.parse(book.courseDates[0].course.discounts)
        : book.courseDates[0].course.discounts;
    if (discounts && discounts.length) {
      let i = 0;
      let price = 0;
      book.courseDates.forEach((element) => {
        const selectedDiscount = discounts.find(
          (item) => item.date == i + 1
        );
        if (selectedDiscount) {
          price =
            price +
            1 *
            book.courseDates[0].course.price *
            (selectedDiscount.percentage / 100);
        }
        i++;
      });
      discountPrice += price;
    }
    return discountPrice;
  }

  hasOneCancelled() {
    return this.bookingsToCreate.filter(b => b.courseDates[0].status != 1).length >= 1;
  }

  deletePartialBooking(index: number, book: any) {
    let bookTotalPrice = 0;
    if (book.courseDates[0].course.course_type == 1) {
      bookTotalPrice = book.price_total + this.getCourseExtraForfaitPriceByCourse(
        book.courseDates[0]
      );
    } else {
      bookTotalPrice = book.price_total + this.getCourseExtraForfaitPriceByDateHour(
        book.courseDates[0]
      );
    }

    let bookingUsers = book.courseDates[0].course.course_type == 1 ?
      this.getBookingUsersByCourseAndClientId(book.courseDates[0].course_id, book.courseDates[0].client_id) :
      this.getBookingUsersByCourseDateAndHour(book.courseDates[0].course_date_id, book.courseDates[0].hour_start,
        book.courseDates[0].hour_end, book.courseDates[0].monitor_id)
    if (book.courseDates[0].course.course_type == 1 && book.courseDates[0].course.is_flexible) {
      bookTotalPrice -= this.getDiscountPrice(book);
    }

    const dialogRef = this.dialog.open(CancelPartialBookingModalComponent, {
      width: "1000px", // Asegurarse de que no haya un ancho máximo
      panelClass: "full-screen-dialog", // Si necesitas estilos adicionales,
      data: {
        currentBonus: this.currentBonus,
        currentBonusLog: this.bonusLog,
        itemPrice: bookTotalPrice,
        booking: this.booking,
      },
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
        let isPartial = this.bookingsToCreate.filter(b => b.courseDates[0].status == 1).length > 1;
        const bookingLog = {
          booking_id: this.id,
          action: "partial_cancel",
          description: "partial cancel booking",
          user_id: this.user.id,
          before_change: "confirmed",
          school_id: this.user.schools[0].id,
        };
        this.crudService.post("/booking-logs", bookingLog).subscribe(() => { });

        let priceToRemove = this.booking.paid ? 0 : bookTotalPrice;

        if (data.type === "no_refund") {
          priceToRemove = 0;
          this.crudService
            .create("/booking-logs", {
              booking_id: this.id,
              action: "no_refund",
              before_change: "confirmed",
              user_id: this.user.id,
            })
            .subscribe(() => {
              this.crudService
                .create("/payments", {
                  booking_id: this.id,
                  school_id: this.user.schools[0].id,
                  amount: bookTotalPrice,
                  status: "no_refund",
                  notes: "no refund applied",
                })
                .subscribe(() => { });
              bookingUsers.forEach((element) => {
                element.status = 2;
                this.crudService
                  .update("/booking-users", { status: 2 }, element.id)
                  .subscribe(() => { });
              });

              this.crudService
                .post("/admin/bookings/cancel", {
                  bookingUsers: bookingUsers.map((b) => b.id),
                })
                .subscribe(() => { });
            });
          this.snackbar.open(
            this.translateService.instant("snackbar.booking_detail.delete"),
            "OK",
            { duration: 3000 }
          );
        } else if (data.type === "boukii_pay") {
          this.crudService
            .create("/booking-logs", {
              booking_id: this.id,
              action: "refund_boukii_pay",
              before_change: "confirmed",
              user_id: this.user.id,
              reason: data.reason,
            })
            .subscribe(() => {
              if (this.booking.paid) {
                this.crudService
                  .post("/admin/bookings/refunds/" + this.id, {
                    amount: bookTotalPrice,
                  })
                  .subscribe(() => {
                    book.courseDates.forEach((element) => {
                      element.status = 2;
                      this.crudService
                        .update("/booking-users", { status: 2 }, element.id)
                        .subscribe(() => { });
                    });

                    this.crudService
                      .post("/admin/bookings/cancel", {
                        bookingUsers: this.bookingUsers.map((b) => b.id),
                      })
                      .subscribe(() => { });
                    this.snackbar.open(
                      this.translateService.instant(
                        "snackbar.booking_detail.update"
                      ),
                      "OK",
                      { duration: 1000 }
                    );
                    this.getData();
                  });
              } else {
                this.snackbar.open(
                  this.translateService.instant(
                    "snackbar.booking_detail.update"
                  ),
                  "OK",
                  { duration: 1000 }
                );
                this.getData();
              }

            });
        } else if (data.type === "refund") {
          this.crudService
            .create("/booking-logs", {
              booking_id: this.id,
              action: "refund_cash",
              before_change: "confirmed",
              user_id: this.user.id,
              description: data.reason,
            })
            .subscribe(() => {
              this.crudService
                .create("/payments", {
                  booking_id: this.id,
                  school_id: this.user.schools[0].id,
                  amount: bookTotalPrice,
                  status: "refund",
                  notes: "other",
                })
                .subscribe(() => { });
              bookingUsers.forEach((element) => {
                element.status = 2;
                this.crudService
                  .update("/booking-users", { status: 2 }, element.id)
                  .subscribe(() => { });
              });

              this.crudService
                .post("/admin/bookings/cancel", {
                  bookingUsers: bookingUsers.map((b) => b.id),
                })
                .subscribe(() => { });
            });
          this.snackbar.open(
            this.translateService.instant("snackbar.booking_detail.delete"),
            "OK",
            { duration: 3000 }
          );
        } else if (data.type === "refund_gift") {
          const vData = {
            code: "BOU-" + this.generateRandomNumber(),
            quantity: bookTotalPrice,
            remaining_balance: bookTotalPrice,
            payed: false,
            client_id: this.booking.client_main_id,
            school_id: this.user.schools[0].id,
          };
          this.crudService
            .create("/booking-logs", {
              booking_id: this.id,
              action: "voucher_refund",
              before_change: "confirmed",
              user_id: this.user.id,
            })
            .subscribe(() => {
              bookingUsers.forEach((element) => {
                element.status = 2;
                this.crudService
                  .update("/booking-users", { status: 2 }, element.id)
                  .subscribe(() => { });
              });
            });

          this.crudService
            .post("/admin/bookings/cancel", {
              bookingUsers: bookingUsers.map((b) => b.id),
            })
            .subscribe(() => { });
          this.crudService
            .create("/payments", {
              booking_id: this.id,
              school_id: this.user.schools[0].id,
              amount: bookTotalPrice,
              status: "refund",
              notes: "voucher",
            })
            .subscribe(() => { });

          this.crudService.create("/vouchers", vData).subscribe((result) => {
            this.crudService
              .create("/vouchers-logs", {
                voucher_id: result.data.id,
                booking_id: this.id,
                amount: -vData.quantity,
              })
              .subscribe((vresult) => { });
          });
          this.snackbar.open(
            this.translateService.instant("snackbar.booking_detail.delete"),
            "OK",
            { duration: 3000 }
          );
        }

        setTimeout(() => {
          if (this.bookingsToCreate.length === 0) {
            this.crudService
              .update("/bookings", { status: 2 }, this.id)
              .subscribe(() => {
                this.getData(true);
              });
          } else {
            let price = parseFloat(this.booking.price_total);
            this.calculateFinalPrice();
            if (this.tva && !isNaN(this.tva)) {
              price = price + price * this.tva;
            }

            if (this.booking.has_boukii_care) {
              // coger valores de reglajes
              price =
                price +
                this.boukiiCarePrice *
                1 *
                this.bookingsToCreate[index].courseDates.length;
            }
            this.crudService
              .update("/bookings", { status: isPartial ? 3 : 2 }, this.id)
              .subscribe(() => {
                this.bookingsToCreate.splice(index, 1);
                this.getData();
              });

            /*this.crudService.update('/bookings', {status: 3}, this.id)
            .subscribe(() => {
              this.bookingsToCreate.splice(index, 1);
              this.getData(true);

            })*/
          }
        }, 1000);
      }
    });
  }

  generateRandomNumber() {
    const min = 10000000; // límite inferior para un número de 5 cifras
    const max = 99999999; // límite superior para un número de 5 cifras
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  generateRandomCode() {
    return "BOU-" + this.generateRandomNumber();
  }

  sendMailInfo() {
    this.crudService
      .post("/admin/bookings/mail/" + this.booking.id, {
        paid: this.booking.paid,
        is_info: true,
      })
      .subscribe(
        (data) => {
          this.snackbar.open(
            this.translateService.instant("snackbar.booking_detail.send_mail"),
            "OK",
            { duration: 1000 }
          );
        },
        (error) => {
          this.snackbar.open(
            this.translateService.instant(
              "snackbar.booking_detail.send_mail.error"
            ),
            "OK",
            { duration: 1000 }
          );
        }
      );
  }

  addBonus() {
    const dialogRef = this.dialog.open(AddDiscountBonusModalComponent, {
      width: "600px",
      data: {
        client_id: this.booking.client_main_id,
        school_id: this.booking.school_id,
        currentPrice: this.bookingPendingPrice,
        appliedBonus: this.bonus,
        currency: this.booking.currency,
      },
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.calculateFinalPrice();
        this.bonus.push(result);
        this.calculateFinalPrice();
      }
    });
  }

  addReduction() {
    const dialogRef = this.dialog.open(AddReductionModalComponent, {
      width: "300px",
      data: {
        currentPrice: this.finalPriceNoTaxes,
      },
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        //this.calculateFinalPrice();
        this.reduction = result;
        //this.calculateFinalPrice();
        this.crudService
          .update(
            "/bookings",
            {
              has_reduction: this.reduction.discount > 0,
              price_reduction:
                this.reduction.type === 1
                  ? (this.getBasePrice(true) * this.reduction.discount) / 100
                  : this.reduction.discount,
            },
            this.id
          )
          .subscribe(() => {
            this.getData(true);
          });
      }
    });
  }

  calculateReduction() {
    if (this.reduction.type === 1) {
      return (this.getBasePrice(true) * this.reduction.discount) / 100;
    } else {
      return this.reduction.discount > this.getBasePrice()
        ? this.getBasePrice()
        : this.reduction.discount;
    }
  }

  calculateBonusDiscount() {
    if (this.bonus.bonus.remaining_balance > this.getBasePrice()) {
      return this.getBasePrice();
    } else {
      return this.bonus.bonus.remaining_balance;
    }
  }

  goTo(route: string) {
    this.router.navigate([route]);
  }

  calculateRem(event: any) {
    if (event.source.checked) {
      this.opRem = this.finalPrice * this.cancellationInsurance;
      this.defaults.has_cancellation_insurance = event.source.checked;
      this.defaults.price_cancellation_insurance =
        this.finalPrice * this.cancellationInsurance;
      this.booking.price_cancellation_insurance =
        this.finalPrice * this.cancellationInsurance;
      this.calculateFinalPrice();
      this.crudService
        .update(
          "/bookings",
          {
            price_cancellation_insurance:
              this.booking.price_cancellation_insurance,
            has_cancellation_insurance: true,
          },
          this.id
        )
        .subscribe(() => {
          this.snackbar.open(
            this.translateService.instant("op_rem_added"),
            "OK",
            { duration: 3000 }
          );
        });
      return this.finalPrice * this.cancellationInsurance;
    } else {
      this.opRem = 0;
      this.defaults.has_cancellation_insurance = event.source.checked;
      this.defaults.price_cancellation_insurance = 0;
      this.booking.price_cancellation_insurance = 0;

      this.crudService
        .update(
          "/bookings",
          {
            price_cancellation_insurance: 0,
            has_cancellation_insurance: false,
          },
          this.id
        )
        .subscribe(() => {
          this.snackbar.open(
            this.translateService.instant("op_rem_added"),
            "OK",
            { duration: 3000 }
          );
        });
      this.calculateFinalPrice();
      return 0;
    }
  }

  addBoukiiCare(event: any) {
    const dialogRef = this.dialog.open(ConfirmModalComponent, {
      data: {
        title: this.translateService.instant("add_boukii_care"),
        message: this.translateService.instant("add_boukii_care_message"),
      },
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
        const price =
          this.boukiiCarePrice *
          this.getBookingPaxes() *
          this.getBookingDates();
        if (event.checked) {
          this.crudService
            .update(
              "/bookings",
              {
                price_total: this.finalPrice + price,
                has_boukii_care: true,
                price_boukii_care: price,
              },
              this.id
            )
            .subscribe(() => {
              this.crudService
                .create("/booking-logs", {
                  booking_id: this.id,
                  action: "add_boukii_care",
                  before_change: "confirmed",
                  user_id: this.user.id,
                  reason: data.reason,
                })
                .subscribe(() => { });
              this.getData(true);
            });
        } else {
          this.crudService
            .update(
              "/bookings",
              {
                price_total: this.finalPrice - this.booking.price_boukii_care,
                has_boukii_care: false,
                price_boukii_care: 0,
              },
              this.id
            )
            .subscribe(() => {
              this.crudService
                .create("/booking-logs", {
                  booking_id: this.id,
                  action: "delete_boukii_care",
                  before_change: "confirmed",
                  user_id: this.user.id,
                  reason: data.reason,
                })
                .subscribe(() => { });
              this.getData(true);
            });
        }
      }
    });
  }

  addCancellationInsurance(event: any) {
    this.calculateFinalPrice();
    const dialogRef = this.dialog.open(ConfirmModalComponent, {
      data: {
        title: event.checked
          ? this.translateService.instant("add_insurance")
          : this.translateService.instant("remove_insurance"),
        message: event.checked
          ? this.translateService.instant("add_insurance_message")
          : this.translateService.instant("remove_insurance_message"),
      },
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
        const price =
          this.finalPrice -
          Math.round(
            (this.finalPrice / (+this.cancellationInsurance + 1)) *
            100
          ) /
          100;
        if (event.checked) {
          this.crudService
            .update(
              "/bookings",
              {
                price_total: this.finalPrice + price,
                has_cancellation_insurance: true,
                price_cancellation_insurance: price,
              },
              this.id
            )
            .subscribe(() => {
              this.crudService
                .create("/booking-logs", {
                  booking_id: this.id,
                  action: "add_cancellation_insurance",
                  before_change: "confirmed",
                  user_id: this.user.id,
                  reason: data.reason,
                })
                .subscribe(() => { });
              this.getData(true);
            });
        } else {
          this.crudService
            .update(
              "/bookings",
              {
                price_total:
                  this.finalPrice - this.booking.price_cancellation_insurance,
                has_cancellation_insurance: false,
                price_cancellation_insurance: 0,
              },
              this.id
            )
            .subscribe(() => {
              this.crudService
                .create("/booking-logs", {
                  booking_id: this.id,
                  action: "delete_cancellation_insurance",
                  before_change: "confirmed",
                  user_id: this.user.id,
                  reason: data.reason,
                })
                .subscribe(() => { });
              this.getData(true);
            });
        }
      } else {
        this.booking.has_cancellation_insurance =
          !this.booking.has_cancellation_insurance;
        this.calculateFinalPrice();
      }
    });
  }

  calculateBoukiiCare(event: any) {
    if (event.source.checked) {
      this.boukiiCare =
        this.boukiiCarePrice * this.getBookingPaxes() * this.getBookingDates();
      this.calculateFinalPrice();
      this.defaults.has_boukii_care = event.source.checked;
      this.defaults.price_boukii_care =
        this.boukiiCarePrice * this.getBookingPaxes() * this.getBookingDates();
      this.booking.price_boukii_care =
        this.boukiiCarePrice * this.getBookingPaxes() * this.getBookingDates();

      this.crudService
        .update(
          "/bookings",
          {
            price_boukii_care: this.booking.price_boukii_care,
            has_boukii_care: true,
          },
          this.id
        )
        .subscribe(() => {
          this.snackbar.open(
            this.translateService.instant("b_care_added"),
            "OK",
            { duration: 3000 }
          );
        });
      return this.getBasePrice() + this.boukiiCarePrice;
    } else {
      this.boukiiCare = 0;
      this.calculateFinalPrice();
      this.defaults.has_boukii_care = event.source.checked;
      this.defaults.price_boukii_care = 0;
      this.booking.price_boukii_care = 0;

      this.crudService
        .update(
          "/bookings",
          { price_boukii_care: 0, has_boukii_care: false },
          this.id
        )
        .subscribe(() => {
          this.snackbar.open(
            this.translateService.instant("b_care_added"),
            "OK",
            { duration: 3000 }
          );
        });
      return 0;
    }
  }

  getLanguage(id: any) {
    const lang = this.languages.find((c) => c.id == +id);
    return lang ? lang.code.toUpperCase() : "NDF";
  }

  getLanguages() {
    this.crudService.list("/languages", 1, 1000).subscribe((data) => {
      this.languages = data.data.reverse();
    });
  }

  getCountry(id: any) {
    const country = this.countries.find((c) => c.id == id);
    return country ? country.name : "NDF";
  }

  geBasePriceAndExtraPrice() {
    let price = this.getBasePrice(true);

    //forfait primero

    this.courseExtra.forEach((element) => {
      price = price + +element.price;
    });
    return price;
  }

  getOpRemPrice() {
    let price = 0;
    if (this.booking.paid && this.booking.status === 1) {
      price =
        +this.booking.paid_total - this.priceRefund - this.priceNoRefund
    } else if (this.booking.status !== 1 && !this.booking.paid) {
      price =
        this.finalPrice - parseFloat(this.booking.paid_total) - this.bonusPrices - this.priceRefund - this.priceNoRefund;
    } else {
      price = this.finalPrice;
    }
    return +(
      price -
      Math.round(
        (price /
          (+this.cancellationInsurance + 1)) *
        100
      ) /
      100
    ).toFixed(2);
  }

  calculateFinalPrice() {
    let price = this.getBasePrice(true);

    //forfait primero
    let bookingsCancelled = this.bookingUsers.filter(b => b.status == 2).map(i => i.id);

    let extraPrice = 0;
    this.courseExtra.forEach((element) => {

      extraPrice = extraPrice + +element.price;

    });

    price += extraPrice;

    if (this.booking.has_reduction) {
      price = price - this.booking.price_reduction;
    }

    if (this.discounts && this.discounts.length) {
      price -= this.discounts.reduce((total, discount) => total + discount, 0);
    }

    this.priceRefund = this.payments.filter(p => p.status == 'refund').reduce((acc, item) => acc + parseFloat(item.amount), 0);
    this.priceNoRefund = this.payments.filter(p => p.status == 'no_refund').reduce((acc, item) => acc + parseFloat(item.amount), 0);

    price -= this.priceRefund;

    if (
      this.booking.has_cancellation_insurance &&
      this.cancellationInsurance > 0
    ) {
      price = price + price * this.cancellationInsurance;
    } else if (this.booking.has_cancellation_insurance) {
      price = price + parseFloat(this.booking.price_cancellation_insurance);
      this.tvaPrice = parseFloat(this.booking.price_cancellation_insurance);
    }

    if (this.booking.has_boukii_care && this.boukiiCarePrice > 0) {
      // coger valores de reglajes
      price =
        price +
        this.boukiiCarePrice * this.getBookingPaxes() * this.getBookingDates();
    } else if (this.booking.has_boukii_care) {
      price = price + parseFloat(this.booking.price_boukii_care);
      this.tvaPrice = parseFloat(this.booking.price_boukii_care);
    }

    // añadir desde reglajes el tva
    if (this.booking.status === 2) {
      this.finalPrice = parseFloat(this.booking.price_total);
      if (this.booking.has_tva) {
        this.tvaPrice = parseFloat(this.booking.price_tva);
      }
    } else {
      if (this.booking.has_tva) {
        this.tvaPrice = parseFloat(this.booking.price_tva);
        this.finalPrice = price + this.tvaPrice;
      } else {
        this.finalPrice = price;
      }
    }

    this.bonusPrices = 0;
    let bonusPricesOld = 0;
    let bonusPricesNew = 0;
    if (this.bonus !== null && price > 0) {
      this.bonus.forEach((element) => {
        if (price > 0) {
          if (element.bonus.remaining_balance >= this.finalPrice) {
            //price = price - price;
            if (element.bonus.before) {
              //price = price - element.bonus.currentPay;
              bonusPricesOld = this.finalPrice;
            } else {
              //price = price - element.bonus.remaining_balance;
              bonusPricesNew = this.finalPrice;
            }
          } else {
            if (element.bonus.before) {
              //price = price - element.bonus.currentPay;
              bonusPricesOld = bonusPricesOld + element.bonus.currentPay;
            } else {
              //price = price - element.bonus.remaining_balance;
              bonusPricesNew = bonusPricesNew + element.bonus.reducePrice;
            }
          }
        }
      });
    }

    this.bonusPrices = bonusPricesNew + bonusPricesOld;

    this.finalPriceNoTaxes = price;

    if (this.booking.paid_total && this.booking.paid_total != this.finalPrice) {
      if (this.booking.paid) {
        this.bookingPendingPrice = 0
      } else if (this.booking.status !== 1) {
        this.bookingPendingPrice =
          this.finalPrice - parseFloat(this.booking.paid_total) - bonusPricesNew;
      } else {
        this.bookingPendingPrice = this.finalPrice - parseFloat(this.booking.paid_total) - bonusPricesNew;
      }
    } else {
      this.bookingPendingPrice = 0;
    }

  }

  deleteBonus(index: number) {
    if (this.bonus[index].bonus.before) {
      const dialogRef = this.dialog.open(ConfirmModalComponent, {
        data: {
          message: "",
          title: this.translateService.instant("remove_bonus_title"),
        },
      });

      dialogRef.afterClosed().subscribe((data: any) => {
        if (data) {
          const data = {
            remaining_balance:
              this.bonus[index].bonus.remaining_balance +
              this.bonus[index].bonus.currentPay,
            payed: false,
          };
          const logData = this.bonus[index].log;
          logData.amount = -logData.amount;

          // Crear una observación para cada llamada HTTP y agregarla al array de observables
          this.crudService
            .update("/vouchers", data, this.bonus[index].bonus.id)
            .subscribe((res) => ({}));
          this.crudService
            .create("/vouchers-logs", logData)
            .subscribe((res) => ({}));
          this.crudService
            .update(
              "/bookings",
              {
                paid_total:
                  this.booking.paid_total - this.bonus[index].bonus.currentPay,
                paid: false,
              },
              this.booking.id
            )
            .subscribe((res) => ({}));
          this.bonus.splice(index, 1);
          this.calculateFinalPrice();
        }
      });
    } else {
      this.bonus.splice(index, 1);
      this.calculateFinalPrice();
    }
  }

  calculateForfaitPriceBookingPrivate(booking) {
    let price = 0;
    if (booking.forfait) {
      price =
        price +
        booking.forfait.price +
        booking.forfait.price * (booking.forfait.tva / 100);
    }
    if (booking?.people?.length) {
      booking.people.forEach((person) => {
        if (person.forfait) {
          price =
            price +
            person.forfait.price +
            person.forfait.price * (person.forfait.tva / 100);
        }
      });
    }
    return price;
  }

  getMonitorLang(id: number) {
    if (id && id !== null) {
      const monitor = this.monitors.find((m) => m.id === id);

      return +monitor?.language1_id;
    }
  }

  getMonitorProvince(id: number) {
    if (id && id !== null) {
      const monitor = this.monitors.find((m) => m.id === id);

      return +monitor?.province;
    }
  }

  getMonitorBirth(id: number) {
    if (id && id !== null) {
      const monitor = this.monitors.find((m) => m.id === id);

      return monitor?.birth_date;
    }
  }

  getCourseExtraForfait(forfait: any, data: any) {
    const courseExtra = data.booking_user_extras.find(
      (extra: any) =>
        extra.course_extra.course_id === data.course_id &&
        extra.course_extra.course_date_id === data.course_date_id &&
        forfait.id === extra.course_extra.name
    );

    if (courseExtra) {
      data.forfait = courseExtra;
      return true;
    }

    return false;
  }

  convertToInt(value: any) {
    return parseFloat(value);
  }

  getCourseExtraForfaitPrice(data: any) {
    let ret = 0;
    data.booking_user_extras.forEach((extra: any) => {
      if (extra.course_extra.course_id === data.course_id) {
        ret += parseFloat(extra.course_extra.price);
        data.forfait = extra.course_extra; // Esto asigna el último extra, ¿es correcto?
      }
    });
    return ret;
  }


  getCourseExtraForfaitPriceByDateHour(data: any) {
    let bookingUsers = this.getBookingUsersByCourseDateAndHour(
      data.course_date_id,
      data.hour_start,
      data.hour_end,
      data.monitor_id
    );
    let ret = 0;
    this.courseExtra.forEach((c) => {
      if (
        bookingUsers.some((bookingUser) => bookingUser.id === c.booking_user_id)
      ) {
        ret += parseFloat(c.price);
        data.forfait = c;
      }
    });
    return ret;
  }

  getCourseExtraForfaitPriceByCourse(data) {
    let ret = 0;
    data.booking_user_extras.forEach((extra: any) => {
      if (extra.course_extra.course_id === data.course_id) {
        ret += parseFloat(extra.course_extra.price);
        data.forfait = extra.course_extra; // Esto asigna el último extra, ¿es correcto?
      }
    });
    return ret;
  }

  getCourseExtraForfaitPriceByBookingUser(data) {
    let ret = 0;
    data.booking_user_extras.forEach((extra: any) => {
      if (extra.course_extra.course_id === data.course_id) {
        ret += parseFloat(extra.course_extra.price);
        data.forfait = extra.course_extra; // Esto asigna el último extra, ¿es correcto?
      }
    });
    return ret;
  }

  getCourseExtraForfaitByDateHour(data: any) {
    let bookingUsers = this.getBookingUsersByCourseDateAndHour(
      data.course_date_id,
      data.hour_start,
      data.hour_end,
      data.monitor_id
    );
    let ret = [];
    this.courseExtra.forEach((c) => {
      if (
        bookingUsers.some((bookingUser) => bookingUser.id === c.booking_user_id)
      ) {
        ret.push(c);
      }
    });
    return ret;
  }

  getBookingPaxes() {
    return this.bookingUsersUnique.length;
  }

  getBookingDates() {
    let ret = 0;
    this.bookingsToCreate.forEach((element) => {
      ret = ret + element.courseDates.length;
    });

    return ret;
  }

  isNanValue(value) {
    return isNaN(value);
  }

  getPrivateFlexPrice(courseDates) {
    let ret = 0;
    courseDates.forEach((element) => {
      ret = ret + parseFloat(element.price);
    });

    return ret;
  }

  parseFloatValue(value) {
    return parseFloat(value);
  }

  goToEdit(index: any, item: any) {
    this.bookingService.editData.id = this.id;
    this.bookingService.editData.booking = this.booking;
    this.bookingService.editData.price = this.finalPrice;
    this.bookingService.editData.client_main_id = this.booking.client_main_id;
    this.bookingService.editData.booking_extras =
      this.getCourseExtraForfaitByDateHour(item?.courseDates[0]);
    this.bookingService.editData.course_id = this.courses[index].id;
    this.bookingService.editData.sport_id = this.courses[index].sport_id;
    this.bookingService.editData.course_type = this.courses[index].course_type;
    this.bookingService.editData.course_is_flexible =
      this.courses[index].is_flexible;
    this.bookingService.editData.client_id =
      this.bookingsToCreate[index].courseDates[0].client_id;
    this.bookingService.editData.degree_id =
      this.bookingsToCreate[index].courseDates[0].degree_id;
    this.bookingService.editData.booking_users =
      this.getBookingUsersByCourseDateAndHour(
        item?.courseDates[0].course_date_id,
        item?.courseDates[0].hour_start,
        item?.courseDates[0].hour_end,
        item?.courseDates[0].monitor_id
      );
    this.bookingService.editData.is_main =
      this.bookingService.editData.client_main_id ===
      this.bookingService.editData.client_id;

    this.bookingService.editData.selectedPrice =
      this.courses[index].course_type == 1 ? this.parseFloatValue(this.getTotalBook(index, item)) +
        this.getCourseExtraForfaitPrice(item.courseDates[0]) : this.parseFloatValue(this.getTotalBook(index, item)) +
      this.getCourseExtraForfaitPriceByDateHour(
        item.courseDates[0]
      )

    this.router.navigate(["bookings/edit/" + this.id]);
  }

  editDates(index: any, item: any) {
    if (this.courses[index].course_type === 2) {
      const dialogRef = this.dialog.open(UpdateCourseModalComponent, {
        width: "60vw",
        maxWidth: "100vw",
        panelClass: "full-screen-dialog",
        data: {
          course: this.courses[index],
          dates: this.bookingUsers.filter(
            (b) => b.course_date_id === item.courseDates[0].course_date_id
          ),
          mainBooking: this.bookingUsersUnique.find(
            (b) => parseFloat(b.price) > 0
          ),
          clientIds: [...new Set(this.clientsIds)],
          clients: this.clients,
          tva: this.tva,
          boukiiCarePrice: this.boukiiCarePrice,
          cancellationInsurance: this.cancellationInsurance,
          bookingExtras: this.bookingExtras,
          courseExtra: this.courseExtra,
        },
      });

      dialogRef.afterClosed().subscribe((data: any) => {
        if (data) {
          /*          const bookingLog = {
                      booking_id: this.id,
                      action: 'update booking',
                      description: 'update booking',
                      user_id: this.user.id,
                      before_change: 'confirmed',
                      school_id: this.user.schools[0].id
                    }

                    this.crudService.post('/booking-logs', bookingLog).subscribe(() => {});*/

          this.getData(true);
        }
      });
    } else {
      const dialogRef = this.dialog.open(UpdateCourseModalComponent, {
        width: "60vw",
        maxWidth: "100vw",
        panelClass: "full-screen-dialog",
        data: {
          course: this.courses[index],
          dates: this.bookingUsers.filter(
            (b) =>
              b.course_id === this.courses[index].id &&
              b.client_id === item.courseDates[0].client_id
          ),
          mainBooking: this.bookingUsersUnique.find(
            (b) => parseFloat(b.price) > 0
          ),
          mainPrice: this.getBasePrice(),
          clientIds: [item.courseDates[0].client_id],
          tva: this.tva,
          boukiiCarePrice: this.boukiiCarePrice,
          cancellationInsurance: this.cancellationInsurance,
          bookingExtras: this.bookingExtras,
          courseExtra: this.courseExtra,
        },
      });

      dialogRef.afterClosed().subscribe((data: any) => {
        if (data) {
          this.bookingExtras.forEach((element) => {
            this.crudService
              .delete("/booking-user-extras", element.id)
              .subscribe(() => { });
          });

          this.courseExtra.forEach((element) => {
            this.crudService
              .delete("/course-extras", element.id)
              .subscribe(() => { });
          });

          setTimeout(() => {
            const bookingLog = {
              booking_id: this.id,
              action: "update booking",
              description: "update booking",
              user_id: this.user.id,
              before_change: "confirmed",
              school_id: this.user.schools[0].id,
            };

            this.crudService
              .post("/booking-logs", bookingLog)
              .subscribe(() => { });

            this.getData(true);
          }, 500);
        }
      });
    }
  }

  removeDuplicates(array: any, key: any) {
    const unique = array
      .map((e: any) => e[key])
      // Almacena las claves y elimina los duplicados.
      .map((e: any, i: any, final: any) => final.indexOf(e) === i && i)
      // Elimina las claves duplicadas y mapea el array.
      .filter((e: any) => array[e])
      .map((e: any) => array[e]);
    // Aquí puedes usar 'unique', que es tu array sin duplicados.
  }

  getPayments() {
    this.crudService
      .list(
        "/payments",
        1,
        10000,
        "asc",
        "id",
        "&booking_id=" + this.id + "&school_id=" + this.user.schools[0].id
      )
      .subscribe((data) => {
        this.payments = data.data;
      });
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

  getBookingsLogs() {
    this.crudService
      .list("/booking-logs", 1, 10000, "desc", "id", "&booking_id=" + this.id)
      .subscribe((data) => {
        this.bookingLog = data.data;
      });
  }

  canUpdate(date: any) {
    const today = moment();
    const dateFormat = moment(date);

    if (!today.isSameOrBefore(dateFormat)) {
      return false;
    }

    return !this.booking.paid;
  }

  encontrarPrimeraCombinacionConValores(data: any) {
    if (data !== null) {
      for (const intervalo of data) {
        // Usamos Object.values para obtener los valores del objeto y Object.keys para excluir 'intervalo'
        if (
          Object.keys(intervalo).some(
            (key) => key !== "intervalo" && intervalo[key] !== null
          )
        ) {
          return intervalo;
        }
      }
      return null; // Devuelve null si no encuentra ninguna combinación válida
    }
  }

  encontrarPrimeraClaveConValor(obj: any): string | null {
    if (obj !== null) {
      for (const clave of Object.keys(obj)) {
        if (obj[clave] !== null && clave !== "intervalo") {
          return obj[clave];
        }
      }
      return null;
    }
  }

  findCourseDateId(dateId, items) {
    let ret = false;

    items.forEach((element) => {
      if (dateId === element.course_date_id && !ret) {
        ret = true;
      }
    });

    return ret;
  }

  findCourseDateIdAndHour(dateId, hour, items) {
    let ret = false;

    items.forEach((element) => {
      if (
        dateId === element.course_date_id &&
        hour == element.hour_start &&
        !ret
      ) {
        ret = true;
      }
    });

    return ret;
  }

  getBookingUsersByCourseDateAndHour(dateId, hour_start, hour_end, monitor_id, group_id = null) {
    return this.bookingUsers.filter((b) => {
      if (group_id) {
        // Filtrar por group_id si se proporciona
        return (
          b.course_date_id === dateId && b.group_id === group_id
        );
      } else {
        // Filtrar por la lógica actual si no se pasa un group_id
        return (
          b.course_date_id === dateId &&
          b.hour_start == hour_start &&
          b.hour_end === hour_end &&
          b.monitor_id == monitor_id
        );
      }
    });
  }


  getBookingUsersByCourse(courseId) {
    return this.bookingUsers.filter(
      (b) => b.course_id === courseId
    );
  }

  getBookingUsersByCourseAndClientId(courseId, clientId) {
    return this.bookingUsers.filter(
      (b) => b.course_id === courseId && b.client_id === clientId
    );
  }

  protected readonly Math = Math;
}
