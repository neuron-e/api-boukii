import {Component, EventEmitter, Input, OnInit, Output, ViewEncapsulation} from '@angular/core';
import {
  MOCK_POSIBLE_HOURS,
  MOCK_POSIBLE_DURATION,
  MOCK_POSIBLE_EXTRAS,
} from "../../mocks/course";
import { MOCK_MONITORS } from "../../mocks/monitor";
import { changeMonitorOptions } from "src/app/static-data/changeMonitorOptions";
import {FormArray, FormBuilder, FormGroup, Validators} from '@angular/forms';
import * as moment from 'moment/moment';
import {UtilsService} from '../../../../../../service/utils.service';
import {ApiCrudService} from '../../../../../../service/crud.service';
import {MatSnackBar} from '@angular/material/snack-bar';
import {TranslateService} from '@ngx-translate/core';
import {MatCalendarCellCssClasses} from '@angular/material/datepicker';
import {SchoolService} from '../../../../../../service/school.service';

@Component({
  selector: "booking-form-details-private",
  templateUrl: "./form-details-private.component.html",
  styleUrls: ["./form-details-private.component.scss"]

})
export class FormDetailsPrivateComponent implements OnInit {
  @Input() course: any;
  @Input() date: any;
  @Input() utilizers: any;
  @Input() sportLevel: any;
  @Input() initialData: any;
  @Input() activitiesBooked: any;
  @Output() stepCompleted = new EventEmitter<FormGroup>();
  @Output() prevStep = new EventEmitter();
  @Input() stepForm: FormGroup; // Recibe el formulario desde el padre
  @Input() selectedForm: FormGroup; // Recibe el formulario desde el padre
  @Input() addDateEvent!: boolean; // Recibe el evento como Input
  @Input() removeDateEvent!: boolean; // Recibe el evento como Input

  possibleHours;
  possibleDurations;
  possibleMonitors;
  possibleChangeMonitorSelection = changeMonitorOptions;
  possibleExtras;
  user;
  minDate: Date = new Date();
  season: any = [];
  holidays: any = [];
  myHolidayDates = [];

  constructor(private fb: FormBuilder,  private snackbar: MatSnackBar, public translateService: TranslateService,
              private crudService: ApiCrudService, public utilService: UtilsService) {
    this.possibleMonitors = [];
  }

  ngOnChanges() {
    // Reacciona cuando el Input cambia
    if (this.addDateEvent) {
      this.addCourseDate(); // Llamamos a la función para añadir la fecha
    }
  }


  ngOnInit(): void {
    this.user = JSON.parse(localStorage.getItem("boukiiUser"));
    this.possibleExtras = this.course.course_extras;
    this.getSeason();
    this.initializeForm();
    this.setupExtrasValueChanges();
    let formatDate = moment(this.date).format('YYYY-MM-DD');
    const courseDate = this.course.course_dates.find((d: any) =>
      moment(d.date).format('YYYY-MM-DD') === formatDate
    );
    this.possibleHours = this.utilService.generateCourseHours(courseDate.hour_start, courseDate.hour_end, this.course.minDuration, '5min');
    this.possibleDurations = this.utilService.generateCourseDurations(courseDate.hour_start, courseDate.hour_end,
      this.course, this.activitiesBooked, this.date, this.utilizers);
    this.courseDates.controls.forEach((dateGroup, index) => {
      const monitorControl = dateGroup.get('monitor');
      const changeMonitorControl = dateGroup.get('changeMonitorOption');

      // Observar los cambios en el campo monitor de cada grupo
      monitorControl.valueChanges.subscribe((monitorValue) => {
        if (monitorValue) {
          changeMonitorControl.enable();
        } else {
          changeMonitorControl.disable();
        }
      });

      // Inicialmente deshabilitar si no hay un monitor seleccionado
      if (!monitorControl.value) {
        changeMonitorControl.disable();
      }
    });

  }

  getSeason() {
    this.utilService.getSeason(this.user.schools[0].id).subscribe((season) => {
      this.season = season;
      this.holidays = this.utilService.getHolidays();
      this.holidays.forEach((element) => {
        this.myHolidayDates.push(moment(element).toDate());
      });
    });
  }

  initializeForm() {
    if (!this.stepForm.get('course_dates')) {
      this.stepForm.addControl('course_dates', this.fb.array([]));
    }
    if (this.initialData?.course_dates && this.initialData?.course_dates.length > 0) {
      // Si hay datos iniciales, usamos esos datos para restaurar los valores seleccionados
      this.initialData?.course_dates.forEach((data: any, index: number) => {
        this.addCourseDate(data);
      });
    } else {
      // Si no hay datos iniciales, añade una fecha por defecto
      this.addCourseDate();
    }
  }

  inUseDatesFilter = (d: Date): boolean => {
    return this.utilService.inUseDatesFilter(d, this.myHolidayDates, this.course);
  }
  addCourseDate(initialData: any = null) {
    const utilizerArray = this.fb.array(
      this.utilizers.map(utilizer =>
        this.createUtilizer(
          utilizer,
          initialData?.utilizers?.find(u => u.first_name === utilizer.first_name && u.last_name === utilizer.last_name)?.extras || []
        )
      )
    );

    let formattedDate = this.date.format('YYYY-MM-DD') ?? moment().format('YYYY-MM-DD'); // Fecha por defecto: hoy

    if (this.courseDates.length > 0) {
      const lastDateGroup = this.courseDates.at(this.courseDates.length - 1);
      const lastDate = lastDateGroup.get('date').value;

      // Encuentra las fechas válidas posteriores a lastDate
      const validDates = (this.course.course_dates || [])
        .map(d => moment(d.date)) // Convierte las fechas a objetos moment
        .filter(d => d.isAfter(moment(lastDate, 'YYYY-MM-DD'))); // Filtra fechas posteriores a lastDate

      // Si hay fechas válidas, toma la primera fecha posterior
      if (validDates.length > 1) {
        formattedDate = validDates.sort((a, b) => a.isBefore(b) ? -1 : 1)[1].format('YYYY-MM-DD'); // Ordena y selecciona la primera
      } else {
        // Si no hay fechas válidas posteriores, usa la última fecha seleccionada
        formattedDate = validDates[0].format('YYYY-MM-DD');
      }
    }

    let price = parseFloat(this.course.price);

    // Check if this is the first date entry and we have externalData
    const isFirstDate = this.courseDates.length === 0;
    const initialStartHour = initialData ? initialData.startHour :
      (isFirstDate && this.initialData?.hour) ? this.initialData.hour : null;
    const initialMonitor = initialData ? initialData.monitor :
      (isFirstDate && this.initialData?.monitor) ? this.initialData.monitor : null;

    const courseDateGroup = this.fb.group({
      selected: [initialData ? initialData.selected : true],
      date: [initialData ? initialData.date : formattedDate, Validators.required],
      startHour: [initialStartHour, Validators.required],
      endHour: [initialData ? initialData.endHour : null, Validators.required],
      duration: [
        initialData ? initialData.duration : (!this.course.is_flexible ? this.course.duration : null),
        Validators.required
      ],
      price: [initialData ? initialData.price : price],
      currency: this.course.currency,
      monitor: [initialMonitor],
      changeMonitorOption: [initialData ? initialData.changeMonitorOption : null],
      utilizers: utilizerArray,
    });

    this.courseDates.push(courseDateGroup);
    this.subscribeToFormChanges(courseDateGroup);

    // If we just set values from externalData, trigger any dependent calculations
    if (isFirstDate && (this.initialData?.hour || this.initialData?.monitorId)) {
      if (this.initialData?.hour) {
        this.updateEndHour(courseDateGroup);

        // If we have both hour and duration, also check availability and load monitors
        if (courseDateGroup.get('duration').value) {
          this.checkAval(courseDateGroup).then((isAvailable) => {
            if (isAvailable) {
              this.getMonitorsAvailable(courseDateGroup);
            }
          });
        }
      }
    }
  }


  createUtilizer(utilizer: any, initialExtras: any[] = []): FormGroup {
    return this.fb.group({
      first_name: [utilizer.first_name],
      last_name: [utilizer.last_name],
      extras: [{value: initialExtras, disabled: !this.possibleExtras || !this.possibleExtras.length}], // Carga los extras iniciales
      totalExtraPrice: [0] // Para almacenar el precio total de los extras
    });
  }

  checkAval(courseDateGroup: FormGroup): Promise<boolean> {
    return new Promise((resolve) => {
      // Preparamos el objeto con los datos de la fecha seleccionada
      const bookingUsers = this.utilizers.map(utilizer => ({
        client_id: utilizer.id,
        hour_start: courseDateGroup.get('startHour').value, // Reemplaza ":00" si es necesario
        hour_end: courseDateGroup.get('endHour').value, // Reemplaza ":00" si es necesario
        date: moment(courseDateGroup.get('date').value).format('YYYY-MM-DD') // Formateamos la fecha
      }));

      const hasLocalOverlap = this.checkLocalOverlap(bookingUsers, courseDateGroup);

      if (hasLocalOverlap) {
        // Si hay solapamiento en la verificación local, mostramos mensaje y resolvemos como false
        this.snackbar.open(this.translateService.instant('snackbar.booking.localOverlap'), 'OK', { duration: 3000 });
        resolve(false);
        return;
      }

      const checkAval = {
        bookingUsers,
        bookingUserIds: [] // Puedes rellenar esto según sea necesario
      };

      // Llamamos al servicio para verificar la disponibilidad de la fecha
      this.crudService.post('/admin/bookings/checkbooking', checkAval)
        .subscribe((response: any) => {
          // Supongamos que la API devuelve un campo 'available' que indica la disponibilidad
          const isAvailable = response.success; // Ajusta según la respuesta real de tu API
          resolve(isAvailable); // Resolvemos la promesa con el valor de disponibilidad
        }, (error) => {
          this.snackbar.open(this.translateService.instant('snackbar.booking.overlap') +
            moment(error.error.data[0].date).format('YYYY-MM-DD') +
            ' | ' + error.error.data[0].hour_start + ' - ' +
            error.error.data[0].hour_end, 'OK', { duration: 3000 })
          resolve(false); // En caso de error, rechazamos la promesa
        });
    });
  }

  checkLocalOverlap(bookingUsers: any[], currentFormGroup: FormGroup = null): boolean {
    // Recorremos cada normalizedDate
    for (let normalized of this.activitiesBooked) {
      if (this.selectedForm && this.selectedForm === normalized) {
        continue; // Saltamos la comparación si es el mismo FormGroup
      }
      // Verificamos si alguno de los utilizers de bookingUsers está en los utilizers de normalizedDates
      for (let bookingUser of bookingUsers) {
        const matchingUtilizer = normalized.utilizers.find(
          (utilizer: any) => utilizer.id === bookingUser.client_id
        );

        // Si encontramos un utilizer coincidente, verificamos las fechas
        if (matchingUtilizer) {
          for (let normalizedDate of normalized.dates) {
            // Comprobar si hay solapamiento entre la fecha seleccionada y la fecha de normalizedDates
            const formattedNormalizedDate = moment(normalizedDate.date).format('YYYY-MM-DD');
            const formattedBookingUserDate = moment(bookingUser.date).format('YYYY-MM-DD');

            if (formattedBookingUserDate === formattedNormalizedDate) {
              // Verificamos solapamiento en las horas
              if (bookingUser.hour_start < normalizedDate.endHour &&
                normalizedDate.startHour < bookingUser.hour_end) {
                return true; // Si hay solapamiento, retornamos true
              }
            }
          }
        }
        for (let normalizedDate of this.courseDates.controls) {

          if ((currentFormGroup && currentFormGroup === normalizedDate)) {
            continue; // Saltamos la comparación si es el mismo FormGroup
          }
          // Comprobar si hay solapamiento entre la fecha seleccionada y la fecha de normalizedDates
          const formattedNormalizedDate = moment(normalizedDate.get('date').value).format('YYYY-MM-DD');
          const formattedBookingUserDate = moment(bookingUser.date).format('YYYY-MM-DD');

          if (formattedBookingUserDate === formattedNormalizedDate) {
            // Verificamos solapamiento en las horas
            if (bookingUser.hour_start < normalizedDate.get('endHour').value &&
              normalizedDate.get('startHour').value < bookingUser.hour_end) {
              return true; // Si hay solapamiento, retornamos true
            }
          }
        }
      }
    }
    // Verificamos si alguno de los utilizers de bookingUsers está en los utilizers de normalizedDates
    return false; // Si no encontramos solapamientos, retornamos false
  }


  subscribeToFormChanges(courseDateGroup: FormGroup) {
    // Suscribirse a cambios en 'startHour'
    courseDateGroup.get('startHour').valueChanges.subscribe(() => {
      this.updateEndHour(courseDateGroup);

      if (this.course.is_flexible) {
        // Generar duraciones posibles basadas en la nueva 'startHour'
        this.possibleDurations = this.utilService.generateCourseDurations(courseDateGroup.get('startHour').value,
          this.course.hour_max, this.course, this.activitiesBooked, courseDateGroup.get('date').value, this.utilizers);

        // Verificar si la duración actual está dentro de las posibles duraciones
        const currentDuration = courseDateGroup.get('duration').value;
        if (!this.possibleDurations.includes(currentDuration)) {
          // Si la duración no está en las posibles, setearla en null
          courseDateGroup.get('duration').setValue(null);
        }
        if(courseDateGroup.get('duration').value && courseDateGroup.get('startHour').value) {
          this.checkAval(courseDateGroup).then((isAvailable) => {
            if (isAvailable) {
              this.getMonitorsAvailable(courseDateGroup)
            } else {
              // Si no hay disponibilidad, deshabilitamos la fecha de nuevo
              courseDateGroup.get('duration').setValue(null);
            }
          });
        }
      }
    });

    courseDateGroup.get('date').valueChanges.subscribe(() => {
      if(courseDateGroup.get('duration').value && courseDateGroup.get('startHour').value) {
        this.checkAval(courseDateGroup).then((isAvailable) => {
          if (isAvailable) {
            this.getMonitorsAvailable(courseDateGroup)
          } else {
            // Si no hay disponibilidad, deshabilitamos la fecha de nuevo
            courseDateGroup.get('duration').setValue(null);

          }
        });
      }

    });

    // Suscribirse a cambios en 'duration'
    courseDateGroup.get('duration').valueChanges.subscribe(() => {
      this.updateEndHour(courseDateGroup);

      const selectedDate = courseDateGroup.get('date').value;
      const newPrice = this.calculatePrice(selectedDate);

      // Actualizar el campo del precio con el nuevo cálculo
      courseDateGroup.get('price').setValue(newPrice);
      if(courseDateGroup.get('duration').value && courseDateGroup.get('startHour').value) {
        this.checkAval(courseDateGroup).then((isAvailable) => {
          if (isAvailable) {
            this.getMonitorsAvailable(courseDateGroup)
          } else {
            // Si no hay disponibilidad, deshabilitamos la fecha de nuevo
            courseDateGroup.get('duration').setValue(null);

          }
        });
      }
    });

  }

  private calculatePrice(date) {
    let total = 0;

    if (this.course.is_flexible) {
      // Calcula el precio basado en el intervalo y el número de utilizadores para cada fecha

      const duration = date.duration; // Duración de cada fecha
      const selectedUtilizers = this.utilizers.length; // Número de utilizadores

      // Encuentra el intervalo de duración que se aplica
      const interval = this.course.price_range.find(range => {
        return range.intervalo === duration; // Comparar con la duración de la fecha
      });

      if (interval) {
        total += parseFloat(interval[selectedUtilizers]); // Precio por utilizador para cada fecha
      }

    } else {
      // Si el curso no es flexible
      const dateTotal = parseFloat(this.course.price) * this.utilizers.length; // Precio por número de utilizadores
      total += dateTotal;

    }

    return total;
  }

  get courseDates(): FormArray {
    return this.stepForm.get("course_dates") as FormArray;
  }

  getUtilizersArray(dateIndex: number): FormArray {
    return this.courseDates.at(dateIndex).get('utilizers') as FormArray;
  }

  removeDate(index: number) {
    if (this.courseDates.length > 0) {
      this.courseDates.removeAt(index); // Utiliza removeAt para eliminar una fecha del FormArray
    }
  }

  isRow1Complete(index: number): boolean {
    const dateGroup = this.courseDates.at(index) as FormGroup;
    const date = dateGroup.get('date')?.value;
    const startHour = dateGroup.get('startHour')?.value;
    const duration = dateGroup.get('duration')?.value;
    return date && startHour && duration;
  }

  updateEndHour(courseDateGroup: FormGroup) {
    const startHour = courseDateGroup.get('startHour').value;
    const duration = courseDateGroup.get('duration').value;

    if (startHour && duration) {
      const endHour = this.utilService.calculateEndHour(startHour, duration);
      courseDateGroup.get('endHour').setValue(endHour, { emitEvent: false });
    }
  }

  getMaxDate() {
    return moment(this.course.course_dates[this.course.course_dates.length - 1].date).toDate();
  }

  setupExtrasValueChanges() {
    // Iteramos sobre las fechas
    this.courseDates.controls.forEach((dateGroup: FormGroup, dateIndex: number) => {
      // Iteramos sobre los utilizadores de cada fecha
      const utilizersArray = this.getUtilizersArray(dateIndex);
      utilizersArray.controls.forEach((utilizerGroup: FormGroup, utilizerIndex: number) => {
        // Nos suscribimos al control 'extras' para detectar cambios
        const extrasControl = utilizerGroup.get('extras') as FormArray;
        extrasControl.valueChanges.subscribe((selectedExtras) => {
          // Recalcula el precio total de los extras
          const totalExtraPrice = this.calculateTotalExtraPrice(selectedExtras);
          // Actualiza el campo totalExtraPrice del utilizador
          utilizerGroup.get('totalExtraPrice').setValue(totalExtraPrice, { emitEvent: false });
        });
      });
    });
  }

  calculateTotalExtraPrice(selectedExtras: any[]): number {
    let total = 0;
    selectedExtras.forEach(extra => {
      total += Number(extra.price); // Asegúrate de que el precio sea un número
    });
    return total;
  }

  getMonitorsAvailable(dateGroup) {
    const index = this.courseDates.controls.indexOf(dateGroup);

    const rq = {
      sportId: this.course.sport_id,
      minimumDegreeId: this.sportLevel.id,
      startTime: dateGroup.get('startHour').value,
      endTime: this.utilService.calculateEndHour(dateGroup.get('startHour').value, dateGroup.get('duration').value),
      date: moment( dateGroup.get('date').value).format('YYYY-MM-DD'),
      clientIds: this.utilizers.map(utilizer => utilizer.id)
    };
    this.crudService.post("/admin/monitors/available", rq).subscribe((data) => {
      this.possibleMonitors[index] = data.data;
      if (data.data.length === 0) {
        this.snackbar.open(this.translateService.instant('snackbar.booking.no_match'),
          'OK', {duration:3000});
      } else {
        const selectedMonitor = data.data.find(m => m.id === dateGroup.get('monitor').value?.id);
        if (selectedMonitor) {
          dateGroup.get('monitor').patchValue(selectedMonitor);

          // If we also have a changeMonitorOption in externalData, set that too
          if (dateGroup.get('changeMonitorOption').value) {
            dateGroup.get('changeMonitorOption').patchValue( dateGroup.get('changeMonitorOption').value);
            // Make sure the control is enabled since we have a monitor value
            dateGroup.get('changeMonitorOption').enable();
          }
        }
      }

    });
  }


}
