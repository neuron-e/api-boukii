import { Component, Input } from '@angular/core';
import { TableColumn } from 'src/@vex/interfaces/table-column.interface';
import { BookingsCreateUpdateV2Component } from './bookings-create-update/bookings-create-update.component';
import moment from 'moment';
import { ApiCrudService } from 'src/service/crud.service';
import { Router } from '@angular/router';
import { MOCK_COUNTRIES } from 'src/app/static-data/countries-data';
import { MOCK_PROVINCES } from 'src/app/static-data/province-data';
import { SchoolService } from 'src/service/school.service';
import { map } from 'rxjs/operators';
import { forkJoin } from 'rxjs';
import { LayoutService } from 'src/@vex/services/layout.service';

@Component({
  selector: 'vex-bookings-v2',
  templateUrl: './bookings.component.html',
  styleUrls: ['./bookings.component.scss']
})
export class BookingsV2Component {
  @Input() filterCourseId: number = 0
  showDetail: boolean = false;
  detailData: any;
  imageAvatar = '../../../assets/img/avatar.png';

  countries = MOCK_COUNTRIES;
  provinces = MOCK_PROVINCES;
  clients = [];
  monitors = [];
  languages = [];
  sports = [];
  bonus: any = [];
  user: any;
  school: any;
  bookingLog: any = [];
  bookingUsersUnique = [];
  allLevels: any;

  createComponent = BookingsCreateUpdateV2Component;
  icon = '../../../assets/img/icons/reservas.svg';
  entity = '/bookings';
  deleteEntity = '/bookings';
  columns: TableColumn<any>[] = [
    { label: 'Id', property: 'id', type: 'id', visible: true, cssClasses: ['font-medium'] },
    { label: 'type', property: 'sport', type: 'booking_users_image', visible: true },
    { label: 'course', property: 'booking_users', type: 'booking_users', visible: true },
    { label: 'client', property: 'client_main', type: 'client', visible: true },
    { label: 'obs', property: 'has_observations', type: 'warning', visible: true },
    { label: 'dates', property: 'dates', type: 'booking_dates', visible: true },
    { label: 'register', property: 'created_at', type: 'date', visible: true },
    //{ label: 'options', property: 'options', type: 'text', visible: true },
    { label: 'op_rem_abr', property: 'has_cancellation_insurance', type: 'light', visible: true },
    { label: 'B. Care', property: 'has_boukii_care', type: 'light', visible: true },
    { label: 'price', property: 'price_total', type: 'price', visible: true },
    { label: 'method_paiment', property: 'payment_method', type: 'payment_method', visible: true },
    { label: 'bonus', property: 'bonus', type: 'light', visible: true },
    { label: 'paid', property: 'paid', type: 'payment_status', visible: true },
    { label: 'status', property: 'status', type: 'cancelation_status', visible: true },
    { label: 'Actions', property: 'actions', type: 'button', visible: true }
  ];

  constructor(private crudService: ApiCrudService, private router: Router, private schoolService: SchoolService, public LayoutService: LayoutService) {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));

    this.schoolService.getSchoolData(this.user).subscribe((school) => {
      this.school = school.data;
      if (!JSON.parse(this.school.settings).taxes?.cancellation_insurance_percent) {
        this.columns = this.columns.filter(column => column.property !== 'has_cancellation_insurance');
      }
      if (!JSON.parse(this.school.settings).taxes?.boukii_care_price) {
        this.columns = this.columns.filter(column => column.property !== 'has_boukii_care');

      }
    })
    this.getDegrees();
    this.getSports();
    this.getLanguages();
  }

  getDegrees() {
    const user = JSON.parse(localStorage.getItem("boukiiUser"))
    this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order',
      '&school_id=' + user.schools[0].id + '&active=1')
      .subscribe((data) => this.allLevels = data.data)
  }


  async showDetailEvent(event: any) {

    if (event.showDetail || (!event.showDetail && this.detailData !== null && this.detailData.id !== event.item.id)) {
      this.bonus = [];
      this.detailData = event.item;

      // Ordenar los usuarios de la reserva
      this.detailData.bookingusers = this.orderBookingUsers(this.detailData.booking_users);

      // Obtener usuarios únicos de la reserva
      this.getUniqueBookingUsers(this.detailData.bookingusers);

      await this.getSchoolSportDegrees();

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
    }

  }

  calculateFormattedDuration(hourStart: string, hourEnd: string): string {
    // Parsea las horas de inicio y fin
    let start = moment(hourStart.replace(': 00', ''), "HH:mm");
    let end = moment(hourEnd.replace(': 00', ''), "HH:mm");

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


  getCountry(id: any) {
    const country = this.countries.find((c) => c.id == +id);
    return country ? country.name : 'NDF';
  }

  getProvince(id: any) {
    const province = this.provinces.find((c) => c.id === +id);
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

  getLanguages() {
    this.crudService.list('/languages', 1, 1000)
      .subscribe((data) => {
        this.languages = data.data.reverse();

      })
  }

  getClient(id: any) {
    if (id && id !== null) {
      return this.clients.find((c) => c.id === id);
    }
  }

  getClientDegree(client: any) {
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


  encontrarPrimeraClaveConValor(obj: any): string | null {
    if (obj !== null) {
      for (const clave of Object.keys(obj)) {
        if (obj[clave] !== null && clave !== 'intervalo') {
          return obj[clave];
        }
      }
      return null;
    }

  }

  encontrarPrimeraCombinacionConValores(data: any, course: any) {
    if (data !== null) {
      for (const intervalo of data) {
        // Usamos Object.values para obtener los valores del objeto y Object.keys para excluir 'intervalo'
        if (Object.keys(intervalo).some(key => key !== 'intervalo' && intervalo[key] !== null)) {
          return intervalo;
        }
      }
      return null; // Devuelve null si no encuentra ninguna combinación válida
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


  getSportName(id) {
    return this.sports.find((s) => s.id === id).name
  }

  getClients() {
    this.crudService.list('/clients', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id, '&with[]=clientSports')
      .subscribe((client) => {
        this.clients = client.data;
      })
  }

  getMonitors() {
    this.crudService.list('/monitors', 1, 10000, 'desc',
      'id', '&school_id=' + this.user.schools[0].id, '', null, '',
      ['sports', 'monitorsSchools', 'monitorsSchools', 'monitorSportsDegrees.monitorSportAuthorizedDegrees.degree', 'user'])
      .subscribe((monitor) => {
        this.monitors = monitor.data;
      })
  }


  getSports() {
    this.crudService.list('/sports', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id)
      .subscribe((sport) => {
        this.sports = sport.data;
      })
  }

  async getSchoolSportDegrees(): Promise<void> {
    try {
      const sportResponse = await this.crudService.list('/school-sports', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id).toPromise();
      this.detailData.sports = sportResponse.data;
      const degreeRequests = sportResponse.data.map((sport) =>
        this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order', `&school_id=${this.user.schools[0].id}&sport_id=${sport.sport_id}&active=1`)
          .pipe(
            map((degreeResponse) => ({
              sport_id: sport.sport_id,
              degrees: degreeResponse.data.map((degree: any) => ({
                ...degree,
                inactive_color: this.lightenColor(degree.color, 30)
              })).reverse()
            }))
          )
      );

      const sportsWithDegrees: any = await forkJoin(degreeRequests).toPromise();
      sportsWithDegrees.forEach((sportWithDegrees, idx) => {
        const sport = this.detailData.sports.find(s => s.sport_id === sportWithDegrees.sport_id);
        if (sport) {
          sport.degrees = sportWithDegrees.degrees;
        }
      });

      // Asignar degrees_sport en función de los bookingusers si es necesario
      if (this.detailData.bookingusers && this.detailData.bookingusers.length) {
        const sportId = this.detailData.bookingusers[0].course.sport_id;
        const matchingSport = this.detailData.sports.find(sport => sport.sport_id === sportId);
        this.detailData.degrees_sport = matchingSport && matchingSport.degrees ? [...matchingSport.degrees].reverse() : [];
      } else {
        this.detailData.degrees_sport = [];
      }

    } catch (error) {
      console.error("Error fetching sports and degrees:", error);
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

  getDegree(degreeId: any) {
    let ret = null;

    if (this.detailData && this.detailData.sports) {
      this.detailData.sports.forEach(sport => {

        if (sport?.degrees) {
          const degree = sport.degrees.find((d) => d.id === degreeId);

          if (degree) {
            ret = degree;
          }
        }

      });
    }

    return ret;
  }

  getMonitor(id: number) {
    if (id && id !== null) {

      const monitor = this.monitors.find((m) => m.id === id);

      return monitor;
    }
  }

  goTo(route: string) {
    this.router.navigate([route]);
  }

  getTVA() {
    return parseFloat(this.school.bookings_comission_cash) > 0;
  }

  getTVAValue() {
    return parseFloat(this.school.bookings_comission_cash);
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

  getBonusPrice() {
    let ret = 0;
    this.bonus.forEach(element => {
      ret = ret + element.currentPay;
    });
    return ret;
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

  /*  getUniqueBookingUsers(data: any) {
      const uniqueGroups = new Map<string, any>();
  
      data.forEach(item => {
        // Crear una clave única por fecha y monitor
        const key = `${item.date}-${item.monitor_id}`;
  
        if (uniqueGroups.has(key)) {
          const existingItem = uniqueGroups.get(key);
          // Si el precio actual es mayor que el del existente, reemplázalo
          if (item.price > existingItem.price) {
            uniqueGroups.set(key, item);
          }
        } else {
          // Si no existe el grupo, lo añadimos al Map
          uniqueGroups.set(key, item);
        }
      });
  
      // Convertimos el Map en un array de los valores
      this.bookingUsersUnique = Array.from(uniqueGroups.values());
    }*/

  /*  getUniqueBookingUsers(data: any) {
      const uniqueGroups = new Map<string, any>();
  
      data.forEach(item => {
        // Crear una clave única para cada combinación de client_id, date y monitor_id
        const key = `${item.client_id}-${item.date}-${item.monitor_id}`;
  
        // Si el grupo ya existe, comparamos los precios y nos quedamos con el más alto
        if (uniqueGroups.has(key)) {
          const existingItem = uniqueGroups.get(key);
          if (item.price > existingItem.price) {
            uniqueGroups.set(key, item);
          }
        } else {
          // Si el grupo no existe, lo añadimos
          uniqueGroups.set(key, item);
        }
      });
  
      // Convertimos el map en un array de los valores
      this.bookingUsersUnique = Array.from(uniqueGroups.values());
    }*/

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

  getBookingsLogs(id: any) {
    this.crudService.list('/booking-logs', 1, 10000, 'desc', 'id', '&booking_id=' + id)
      .subscribe((data) => {
        this.bookingLog = data.data;
      })
  }

  protected readonly parseFloat = parseFloat;
}
