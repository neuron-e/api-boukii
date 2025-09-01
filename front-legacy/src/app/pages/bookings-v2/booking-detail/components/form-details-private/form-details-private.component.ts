import {Component, EventEmitter, Inject, Input, OnInit, Output} from '@angular/core';
import {changeMonitorOptions} from 'src/app/static-data/changeMonitorOptions';
import {FormArray, FormBuilder, FormGroup, Validators} from '@angular/forms';
import * as moment from 'moment/moment';
import {UtilsService} from '../../../../../../service/utils.service';
import {ApiCrudService} from '../../../../../../service/crud.service';
import {MatSnackBar} from '@angular/material/snack-bar';
import {TranslateService} from '@ngx-translate/core';
import {MAT_DIALOG_DATA, MatDialog, MatDialogRef} from '@angular/material/dialog';
import {pairwise, startWith} from 'rxjs';
import {BookingService} from '../../../../../../service/bookings.service';
import {
  CancelPartialBookingModalComponent
} from '../../../../bookings/cancel-partial-booking/cancel-partial-booking.component';


@Component({
  selector: "booking-detail-form-details-private",
  templateUrl: "./form-details-private.component.html",
  styleUrls: ["./form-details-private.component.scss"]

})
export class FormDetailsPrivateComponent implements OnInit {
  @Input() course: any;
  @Input() date: any;
  @Input() utilizers: any;
  @Input() sportLevel: any;
  @Input() initialData: any;
  activitiesBooked: any;
  @Output() stepCompleted = new EventEmitter<FormGroup>();
  @Output() prevStep = new EventEmitter();
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
  stepForm: FormGroup;

  constructor(private fb: FormBuilder,  private snackbar: MatSnackBar,
              public translateService: TranslateService,
              private crudService: ApiCrudService, public utilService: UtilsService,
              private bookingService: BookingService,
              public dialog: MatDialog,
              @Inject(MAT_DIALOG_DATA) public data: any,
              private dialogRef: MatDialogRef<FormDetailsPrivateComponent>) {
    this.possibleMonitors = [];
    this.course = data.course;
    this.date = data.date;
    this.utilizers = data.utilizers;
    this.sportLevel = data.sportLevel;
    this.initialData = data.initialData;
    this.activitiesBooked = data.groupedActivities;
    this.stepForm = this.fb.group({});
    this.addDateEvent = data.addDateEvent;
    this.removeDateEvent = data.removeDateEvent;
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
    this.possibleHours = this.utilService.generateCourseHours(this.course.hour_min, this.course.hour_max, this.course.minDuration, '5min');
    this.possibleDurations = this.utilService.generateCourseDurations(this.course.hour_min, this.course.hour_max, this.course);
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
    if (this.initialData && this.initialData.length > 0) {
      // Si hay datos iniciales, usamos esos datos para restaurar los valores seleccionados
      this.initialData.forEach((data: any, index: number) => {
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

  isPast(date) {
    return  moment(date).isBefore(moment(), 'day');
  }

  addCourseDate(initialData: any = null) {
    let disabled = initialData?.disabled || (initialData?.booking_users?.[0]?.status == 2);
    const isDatePast = initialData.date && moment(initialData.date).isBefore(moment(), 'day');
    const utilizerArray = this.fb.array(this.utilizers.map(utilizer =>
      this.createUtilizer(utilizer,
        initialData?.utilizers?.find(
          u => u.first_name === utilizer.first_name &&
            u.last_name === utilizer.last_name)?.extras || [], disabled || isDatePast)
    ));

    let formattedDate = moment().format('YYYY-MM-DD'); // Fecha por defecto: hoy

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


    const courseDateGroup = this.fb.group({
      selected: [initialData ? disabled : null],
      booking_users: [initialData ? initialData.booking_users : null],
      date: [{value: initialData ? initialData.date : formattedDate, disabled: disabled  }, Validators.required],
      startHour: [{value: initialData ? initialData.startHour : null, disabled: disabled }, Validators.required],
      endHour: [initialData ? initialData.endHour : null, Validators.required],
      duration: [{value: initialData ? initialData.duration :
        (!this.course.is_flexible ? this.course.duration : null), disabled: disabled }, Validators.required],
      price: [initialData ? initialData.price : null],
      currency: this.course.currency,
      monitor: [{value: initialData ? initialData.monitor : null, disabled: disabled }],
      changeMonitorOption: [{value: initialData ? initialData.changeMonitorOption : null, disabled: disabled }],
      utilizers: utilizerArray,
      originalData: [initialData]
    });

    this.courseDates.push(courseDateGroup);

    if(initialData) {
      this.getMonitorsAvailable(courseDateGroup)
    }


    this.subscribeToFormChanges(courseDateGroup);
  }

  createUtilizer(utilizer: any, initialExtras: any[] = [], disabled = false): FormGroup {
    const group = this.fb.group({
      id: [utilizer.id],
      first_name: [utilizer.first_name],
      last_name: [utilizer.last_name],
      extras: [{value: [], disabled: !this.possibleExtras || !this.possibleExtras.length || disabled}], // Carga los extras iniciales
      totalExtraPrice: [0] // Para almacenar el precio total de los extras
    });
    if (initialExtras.length > 0) {
      // Mapeamos los extras iniciales para obtener los objetos exactos de possibleExtras
      const validExtras = this.possibleExtras.filter(extra =>
        initialExtras.some(initialExtra => initialExtra.id === extra.id)
      );

      // Actualizamos el FormControl con los objetos mapeados de possibleExtras
      group.get('extras')?.patchValue(validExtras);

      // Calculamos el precio total de los extras seleccionados
      const totalPrice = validExtras.reduce((sum, extra) => sum + parseFloat(extra.price), 0);
      group.get('totalExtraPrice')?.setValue(totalPrice);
    }

    return group;
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
        bookingUserIds: this.initialData.flatMap(data => data.booking_users.map(user => user.id))

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
      // Verificamos si alguno de los utilizers de bookingUsers está en los utilizers de normalizedDates
      for (let bookingUser of bookingUsers) {
        const matchingUtilizer = normalized.utilizers.find(
          (utilizer: any) => utilizer.id === bookingUser.client_id
        );

        // Si encontramos un utilizer coincidente, verificamos las fechas
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

        if (matchingUtilizer) {
          for (let normalizedDate of normalized.dates) {

            const currentOriginalData = currentFormGroup.get('originalData')?.value;
            if (normalizedDate === currentOriginalData) {
              continue;
            }

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
      }
    }
    // Verificamos si alguno de los utilizers de bookingUsers está en los utilizers de normalizedDates
    return false; // Si no encontramos solapamientos, retornamos false
  }

  subscribeToFormChanges(courseDateGroup: FormGroup) {
    // Suscribirse a cambios en 'startHour'
    courseDateGroup.get('startHour').valueChanges
      .pipe(
        startWith(courseDateGroup.get('startHour').value), // Inicializar con el valor actual
        pairwise() // Devuelve [valor_anterior, valor_nuevo]
      )
      .subscribe(([previousStartHour, newStartHour]) => {
      this.updateEndHour(courseDateGroup);

      if (this.course.is_flexible) {
        // Generar duraciones posibles basadas en la nueva 'startHour'
        // this.possibleDurations = this.utilService.generateCourseDurations(courseDateGroup.get('startHour').value, this.course.hour_max, this.course);

        // Verificar si la duración actual está dentro de las posibles duraciones
        const currentDuration = courseDateGroup.get('duration').value;
        if (!this.possibleDurations.includes(currentDuration)) {
          // Si la duración no está en las posibles, setearla en null
          //courseDateGroup.get('duration').setValue(null);
          this.snackbar.open(this.translateService.instant('no_available'),
            'OK', {duration:3000});
        }
        if(courseDateGroup.get('duration').value && courseDateGroup.get('startHour').value) {
          this.checkAval(courseDateGroup).then((isAvailable) => {
            if (isAvailable) {
              this.getMonitorsAvailable(courseDateGroup)
            } else {
              // Si no hay disponibilidad, deshabilitamos la fecha de nuevo
              courseDateGroup.get('startHour').setValue(previousStartHour);
            }
          });
        }
      }
    });

    courseDateGroup.get('date').valueChanges.pipe(
      startWith(courseDateGroup.get('date').value), // Inicializar con el valor actual
      pairwise() // Devuelve [valor_anterior, valor_nuevo]
    )
      .subscribe(([previousDate, newDate]) => {
      if(courseDateGroup.get('duration').value && courseDateGroup.get('startHour').value) {
        this.checkAval(courseDateGroup).then((isAvailable) => {
          if (isAvailable) {
            this.getMonitorsAvailable(courseDateGroup)
          } else {
            // Si no hay disponibilidad, deshabilitamos la fecha de nuevo
            courseDateGroup.get('date').setValue(previousDate);

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
      const dateTotal = parseFloat(this.course.price); // Precio por número de utilizadores
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
      const bookingUsersIds = this.courseDates.at(index).value.booking_users.map(i => i.id);
      const bookingData = this.bookingService.getBookingData();
      const price = this.calculatePrice(this.courseDates.at(index).value);

      if (bookingData.paid) {
        const dialogRef = this.dialog.open(CancelPartialBookingModalComponent, {
          width: "1000px",
          panelClass: "full-screen-dialog",
          data: {
            itemPrice: price,
            booking: bookingData,
          },
        });

        dialogRef.afterClosed().subscribe((data: any) => {
          if (data) {
            this.bookingService.processCancellation(
              data, bookingData, true, this.user, null, bookingUsersIds, price)
              .subscribe({
                next: () => {
                  const oldDateData = this.courseDates.at(index).value;
                  this.courseDates.removeAt(index); // Eliminamos la fecha
                  this.addCourseDate({ ...oldDateData, disabled: true }); // La volvemos a agregar deshabilitada
                  this.snackbar.open(
                    this.translateService.instant('snackbar.booking_detail.update'),
                    'OK',
                    { duration: 3000 }
                  );
                },
                error: (error) => {
                  console.error('Error processing cancellation:', error);
                  this.snackbar.open(
                    this.translateService.instant('snackbar.error'),
                    'OK',
                    { duration: 3000 }
                  );
                }
              });
          }
        });
      } else {
        this.courseDates.removeAt(index);
      }
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
    const monitor = dateGroup.get('monitor').value;
    const index = this.courseDates.controls.indexOf(dateGroup);

    const startTime = dateGroup.get('startHour').value;
    const endTime = this.utilService.calculateEndHour(startTime, dateGroup.get('duration').value);
    const date = moment(dateGroup.get('date').value).format('YYYY-MM-DD');

    const rq = {
      sportId: this.course.sport_id,
      minimumDegreeId: this.sportLevel.id,
      startTime,
      endTime,
      date,
      clientIds: this.utilizers.map(utilizer => utilizer.id)
    };
    this.crudService.post("/admin/monitors/available", rq).subscribe((data) => {
      this.possibleMonitors[index] = data.data;
      if(monitor) {
        this.possibleMonitors[index].push(monitor);
      }
      dateGroup.patchValue({monitor: monitor});
      if (data.data.length === 0) {
        const message = this.translateService.instant('snackbar.booking.no_match') +
          ` (${date} ${startTime} - ${endTime})`;
        this.snackbar.open(message, 'OK', { duration: 3000 });
      }
    });
  }

  submitForm() {
    if (this.stepForm.valid) {

      // Recorrer las fechas y calcular el precio para cada una
      // Usar getRawValue() para obtener todos los valores, incluso los deshabilitados
      const course_dates = this.stepForm.get('course_dates').getRawValue();

      let totalPrice = 0;
      // Calcular el precio para cada fecha y acumular el total
      course_dates.forEach((date, index) => {
        if(!date.selected) {
          const datePrice = this.calculatePrice(date) +
            date.utilizers.reduce((sum, utilizer) => sum + parseFloat(utilizer.totalExtraPrice), 0);

          course_dates[index].price = datePrice; // Asignar el precio a la fecha actual
          totalPrice += datePrice; // Acumular el precio total
        }
      });

      // Asignar el precio total a todas las fechas
      course_dates.forEach((date, index) => {
        course_dates[index].price = totalPrice;
        course_dates[index].course_date_id = this.course.course_dates.find(d =>
          moment(d.date).format('YYYY-MM-DD') == moment(date.date).format('YYYY-MM-DD')).id;
      });

      // Actualizar el formulario con las fechas que ahora tienen los precios calculados
      this.stepForm.patchValue({
        course_dates: course_dates
      });

      // Cerrar el diálogo pasando los valores del formulario
      this.dialogRef.close({course_dates: course_dates});

    } else {
      this.snackbar.open('Por favor completa todos los campos requeridos', 'OK', { duration: 3000 });
    }

  }

  isFormValid() {
    return this.stepForm.valid;
  }

  cancel() {
    this.dialogRef.close();
  }
}
