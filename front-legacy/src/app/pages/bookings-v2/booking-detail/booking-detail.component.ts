import { Component, Inject, OnInit, Optional } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { MAT_DIALOG_DATA, MatDialog } from '@angular/material/dialog';
import { BookingService } from '../../../../service/bookings.service';
import { ApiCrudService } from '../../../../service/crud.service';
import {ActivatedRoute, Router} from '@angular/router';
import { BehaviorSubject, Subject } from 'rxjs';
import { MatSnackBar } from '@angular/material/snack-bar';
import { BookingDialogComponent } from '../bookings-create-update/components/booking-dialog/booking-dialog.component';
import {
  CancelPartialBookingModalComponent
} from '../../bookings/cancel-partial-booking/cancel-partial-booking.component';
import {CancelBookingModalComponent} from '../../bookings/cancel-booking/cancel-booking.component';
import {BookingDetailDialogComponent} from './components/booking-dialog/booking-dialog.component';

@Component({
  selector: 'booking-detail-v2',
  templateUrl: './booking-detail.component.html',
  styleUrls: ['./booking-detail.component.scss']
})
export class BookingDetailV2Component implements OnInit {
  payModal: boolean = false;
  deleteModal: boolean = false
  deleteFullModal: boolean = false
  endModal: boolean = false
  deleteIndex: number = 1
  mainClient: any;
  allLevels: any;
  isMobile = false;
  showMobileDetail = false;
  bookingData$ = new BehaviorSubject<any>(null);
  bookingData:any;
  groupedActivities: any[] = [];
  id: number;
  user: any;
  paymentMethod: number = 1; // Valor por defecto
  step: number = 1;  // Paso inicial
  selectedPaymentOption: string = 'Tarjeta';
  isPaid = false;
  paymentOptions: any[] = [
    { type: 'Tarjeta', value: 4, translation: this.translateService.instant('credit_card') },
    { type: 'Efectivo', value: 1,  translation: this.translateService.instant('payment_cash') },
    { type: 'Boukii Pay', value: 2, translation: 'Boukii Pay' }
  ];

  private activitiesChangedSubject = new Subject<void>();

  activitiesChanged$ = this.activitiesChangedSubject.asObservable();

  constructor(
    public translateService: TranslateService,
    public dialog: MatDialog,
    private activatedRoute: ActivatedRoute,
    public bookingService: BookingService,
    private crudService: ApiCrudService,
    private router: Router,
    private snackBar: MatSnackBar,
    @Optional() @Inject(MAT_DIALOG_DATA) public incData: any
  ) {

  }

  ngOnInit(): void {
    this.isMobile = window.innerWidth <= 768;
    this.user = JSON.parse(localStorage.getItem("boukiiUser"));
    if (!this.incData) this.id = this.activatedRoute.snapshot.params.id;
    else this.id = this.incData.id;
    this.getDegrees();
    this.getBooking();
  }

  openDetailBookingDialog() {
    const isMobile = window.innerWidth < 768;

    const dialogRef = this.dialog.open(BookingDetailDialogComponent, {
      panelClass: ["customBookingDialog", isMobile ? "mobile-dialog" : ""],
      position: isMobile ? {
        bottom: "0",
        right: "0",
        top: "0",
        left: "0"
      } : {
        bottom: "24px",
        right: "24px",
        top: "24px",
        left: "24px"
      },
      maxWidth: isMobile ? "100vw" : "900px", // En lugar de -webkit-fill-available
      width: isMobile ? "100%" : "90%",
      height: isMobile ? "100%" : "auto",
      maxHeight: isMobile ? "100vh" : "90vh",
      data: {
        mainClient: this.mainClient,
        groupedActivities: this.groupedActivities,
        allLevels: this.allLevels,
        bookingData$: this.bookingData$,
        activitiesChanged$: this.activitiesChanged$,
        isMobile: isMobile
      },
    });

    dialogRef.componentInstance.deleteActivity.subscribe(() => {
      dialogRef.close()
      this.processFullDelete();
    });

    dialogRef.componentInstance.payActivity.subscribe(() => {
      dialogRef.close()
      this.payModal = true;
    });

    dialogRef.componentInstance.closeClick.subscribe(() => {
      dialogRef.close();
    });
  }


  getDegrees() {
    const user = JSON.parse(localStorage.getItem("boukiiUser"))
    this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order',
      '&school_id=' + user.schools[0].id + '&active=1')
      .subscribe((data) => this.allLevels = data.data)
  }

  getBooking() {
    this.crudService
      .get("/bookings/" + this.id, [
        "user",
        "clientMain.clientSports",
        "vouchersLogs.voucher",
        "bookingUsers.course.courseDates.courseGroups.courseSubgroups",
        "bookingUsers.course.courseExtras",
        "bookingUsers.bookingUserExtras.courseExtra",
        "bookingUsers.client.clientSports",
        "bookingUsers.courseDate",
        "bookingUsers.monitor.monitorSportsDegrees",
        "bookingUsers.degree",
        "payments",
        "bookingLogs"
      ])
      .subscribe((data) => {
        this.bookingData$.next(data.data);
        this.bookingData = data.data;
        this.groupedActivities  = data.data.grouped_activities;
        this.mainClient = data.data.client_main;
      });
  }

  closeModal() {
    this.dialog.closeAll();
  }

  groupBookingUsersByGroupId(booking: any) {
    this.mainClient = booking.client_main;
    const groupedActivities = Object.values(booking.booking_users.reduce((acc: any, user: any) => {
      const groupId = user.group_id;
      const courseType = user.course.course_type;

      if (!acc[groupId]) {
        acc[groupId] = {
          sport: user.course.sport,
          course: user.course,
          sportLevel: user.degree,
          dates: [],
          monitors: [],
          utilizers: [],
          clientObs: user.notes,
          schoolObs: user.notes_school,
          total: user.price,
          status: user.status,
          statusList: [] // Nuevo array para almacenar los status de los usuarios
        };
      }

      acc[groupId].statusList.push(user.status);

      // Determinar el nuevo status basado en los valores de statusList
      const uniqueStatuses = new Set(acc[groupId].statusList);

      if (uniqueStatuses.size === 1) {
        acc[groupId].status = [...uniqueStatuses][0]; // Si todos son iguales, asignamos ese mismo status
      } else {
        acc[groupId].status = 3; // Si hay mezcla de 1 y 2, el status del grupo es 3
      }

      const isUserAlreadyAdded = acc[groupId].utilizers.some(utilizer =>
        utilizer.first_name === user.client.first_name &&
        utilizer.last_name === user.client.last_name
      );

      if (!isUserAlreadyAdded) {
        acc[groupId].utilizers.push({
          id: user.client_id,
          first_name: user.client.first_name,
          last_name: user.client.last_name,
          image: user.client.image || null,
          birth_date: user.client.birth_date,
          language1_id: user.client.language1_id,
          country: user.client.country,
          extras: []
        });
      }
      const dateIndex = acc[groupId].dates.findIndex((date: any) =>
        date.id === user.course_date_id &&
        date.startHour === user.hour_start &&
        date.endHour === user.hour_end
      );
      if (dateIndex === -1) {
        acc[groupId].dates.push({
          id: user.course_date_id,
          date: user.course_date?.date,
          startHour: user.hour_start,
          endHour: user.hour_end,
          duration: user.formattedDuration,
          currency: booking.currency,
          monitor: user.monitor,
          utilizers: [],
          extras: [],
          booking_users: [],
        });
      }
      const currentDate = acc[groupId].dates.find((date: any) =>
        date.id === user.course_date_id &&
        date.startHour === user.hour_start &&
        date.endHour === user.hour_end
      );
      currentDate.booking_users.push(user);
      if (courseType !== 1) {
        const isUserAlreadyAdded = currentDate.utilizers.some(utilizer =>
          utilizer.first_name === user.client.first_name &&
          utilizer.last_name === user.client.last_name
        );

        if (!isUserAlreadyAdded) {
          currentDate.utilizers.push({
            id: user.client_id,
            first_name: user.client.first_name,
            last_name: user.client.last_name,
            image: user.client.image || null,
            birth_date: user.client.birth_date,
            language1_id: user.client.language1_id,
            country: user.client.country,
            extras: []
          });
        }
        const utilizer = currentDate.utilizers.find(utilizer =>
          utilizer.first_name === user.client.first_name &&
          utilizer.last_name === user.client.last_name
        );
        if (user.booking_user_extras && user.booking_user_extras.length > 0) utilizer.extras.push(...user.booking_user_extras.map((extra: any) => (extra.course_extra)));
      }
      if (courseType === 1 && user.booking_user_extras && user.booking_user_extras.length > 0) currentDate.extras.push(...user.booking_user_extras.map((extra: any) => (extra.course_extra)));

      if (user.monitor_id) acc[groupId].monitors.push(user.monitor_id);
      return acc;
    }, {}));
    groupedActivities.forEach((groupedActivity: any) => {
      groupedActivity.total = this.bookingService.calculateActivityPrice(groupedActivity);
    });

    return groupedActivities;
  }

  editActivity(data: any, index: any) {
    if (data && data.course_dates) {

      this.crudService.post('/admin/bookings/update',
        {
          dates: data.course_dates,
          total: data.total,
          group_id: this.groupedActivities[index].dates[0].booking_users[0].group_id,
          booking_id: this.id
        })
        .subscribe((response) => {
/*          this.bookingData$.next(response.data);
          this.bookingData = response.data;
          this.groupedActivities = [...this.groupBookingUsersByGroupId(response.data)];
          this.activitiesChangedSubject.next(response.data);*/
          this.getBooking();
          this.snackBar.open(this.translateService.instant('snackbar.booking_detail.update'), 'OK', { duration: 3000 });
        });

    }
    else if (data && (data.schoolObs || data.clientObs)) {
      this.groupedActivities[index].schoolObs = data.schoolObs;
      this.groupedActivities[index].clientObs = data.clientObs;
      this.editObservations(this.groupedActivities[index].dates[0].booking_users[0].id, data)
    } else {
      this.getBooking();
    }

  }

  editObservations(bookingUserId:number, data:any) {
    this.crudService
      .update("/booking-users", { notes: data.clientObs, notes_school: data.schoolObs }, bookingUserId)
      .subscribe(() => {
        this.snackBar.open(
          this.translateService.instant("snackbar.booking_detail.notes_client"),
          "OK",
          { duration: 3000 }
        );
      }, error => {
        console.error(error);
      });
  }

  hasOtherActiveGroups(currentGroup: any): boolean {
    // Cuenta cuántas actividades tienen status 1 (activas)
    const activeGroupsCount = this.groupedActivities.filter(
      group => group.status === 1
    ).length;

    // Si el grupo actual tiene status 1 y hay más de una actividad activa,
    // significa que hay otras actividades activas además de la actual
    return currentGroup.status === 1 && activeGroupsCount > 1;
  }

  processDelete(index) {
    this.deleteIndex = index;
    const group = this.groupedActivities[index];
    if(!this.hasOtherActiveGroups(group)) {
      this.processFullDelete();
    } else {
      if(this.bookingData.paid) {
        const dialogRef = this.dialog.open(CancelPartialBookingModalComponent, {
          width: "1000px", // Asegurarse de que no haya un ancho máximo
          panelClass: "full-screen-dialog", // Si necesitas estilos adicionales,
          data: {
            itemPrice: group.total,
            booking: this.bookingData,
          },
        });

        dialogRef.afterClosed().subscribe((data: any) => {
          if (data) {
            this.bookingService.processCancellation(
              data, this.bookingData, this.hasOtherActiveGroups(group), this.user, group)
              .subscribe({
                next: () => {
                  this.getBooking();
                  this.snackBar.open(
                    this.translateService.instant('snackbar.booking_detail.update'),
                    'OK',
                    { duration: 3000 }
                  );
                },
                error: (error) => {
                  console.error('Error processing cancellation:', error);
                  this.snackBar.open(
                    this.translateService.instant('snackbar.error'),
                    'OK',
                    { duration: 3000 }
                  );
                }
              });

          }
        });

      } else {
        this.deleteModal = true;
      }
    }


  }

  processFullDelete() {
      const dialogRef = this.dialog.open(CancelBookingModalComponent, {
        width: "1000px", // Asegurarse de que no haya un ancho máximo
        panelClass: "full-screen-dialog", // Si necesitas estilos adicionales,
        data: {
          itemPrice: this.bookingData.price_total,
          booking: this.bookingData,
        },
      });

      dialogRef.afterClosed().subscribe((data: any) => {
        if (data) {
          this.bookingService.processCancellation(
            data, this.bookingData, false, this.user, null,
            this.bookingData.booking_users.map(b => b.id), this.bookingData.price_total)
            .subscribe({
              next: () => {
                this.getBooking();
                this.snackBar.open(
                  this.translateService.instant('snackbar.booking_detail.update'),
                  'OK',
                  {duration: 3000}
                );
              },
              error: (error) => {
                console.error('Error processing cancellation:', error);
                this.snackBar.open(
                  this.translateService.instant('snackbar.error'),
                  'OK',
                  {duration: 3000}
                );
              }
            });

        }
      });

  }

  cancelFull() {
    const bookingUserIds = this.bookingData.booking_users.map(b => b.id)
    this.crudService.post('/admin/bookings/cancel',
      { bookingUsers: bookingUserIds })
      .subscribe((response) => {
        let bookingData = {
          ...response.data,
          vouchers: response.data.voucher_logs
        };
        this.bookingData$.next(bookingData);
        this.snackBar.open(this.translateService.instant('snackbar.booking_detail.delete'), 'OK', { duration: 3000 });
        this.deleteFullModal = false;
      });
  }

  cancelActivity(index: any) {
    const group = this.groupedActivities[index];
    const bookingUserIds = group.dates.flatMap(date =>
      date.booking_users.map(b => b.id)
    );
    this.crudService.post('/admin/bookings/cancel',
      { bookingUsers: bookingUserIds })
      .subscribe((response) => {
        this.groupedActivities[index].status = 2;
        let bookingData = {
          ...response.data,
          vouchers: response.data.voucher_logs
        };
        this.bookingData$.next(bookingData);
        this.snackBar.open(this.translateService.instant('snackbar.booking_detail.delete'), 'OK', { duration: 3000 });
        this.deleteModal = false;
      });
  }

  // Método para finalizar la reserva
  finalizeBooking(): void {
    let bookingData = this.bookingData;
    bookingData.selectedPaymentOption = this.selectedPaymentOption
    bookingData.payment_method_id = this.paymentMethod
    bookingData.paid = false
    bookingData.paid_total = 0

    // bookingData.cart = this.bookingService.setCart(this.groupedActivities.flatMap(activity => activity.dates), this.bookingService.getBookingData());

    if(this.paymentMethod === 1) {
      // Mapear la opción seleccionada con el método de pago
      if (this.selectedPaymentOption === 'Efectivo') {
        bookingData.payment_method_id = 1;
      } else if (this.selectedPaymentOption === 'Boukii Pay') {
        bookingData.payment_method_id = 2;
      } else if (this.selectedPaymentOption === 'Tarjeta') {
        bookingData.payment_method_id = 4;
      }
    }

    if (this.bookingService.calculatePendingPrice() === 0) {
      bookingData.paid = true;
      bookingData.paid_total = bookingData.price_total - this.calculateTotalVoucherPrice();
    }
    // Si es pago en efectivo o tarjeta, guardar si fue pagado
    if (bookingData.payment_method_id === 1 || bookingData.payment_method_id === 4) {
      bookingData.paid_total = bookingData.price_total - this.calculateTotalVoucherPrice();
      bookingData.paid = true;
    }


    // Enviar la reserva a la API
    this.crudService.post(`/admin/bookings/update/${this.id}/payment`, bookingData)
      .subscribe(
        (result: any) => {
          // Manejar pagos en línea
          if (bookingData.payment_method_id === 2 || bookingData.payment_method_id === 3) {
            this.crudService.post(`/admin/bookings/payments/${this.id}`, result.data.basket)
              .subscribe(
                (paymentResult: any) => {
                  if (bookingData.payment_method_id === 2) {
                    window.open(paymentResult.data, "_self");
                  } else {
                    this.snackBar.open(this.translateService.instant('snackbar.booking_detail.send_mail'),
                      'OK', { duration: 1000 });
                  }
                },
                (error) => {
                  this.showErrorSnackbar(this.translateService.instant('snackbar.booking.payment.error'));
                }
              );
          } else {
            this.snackBar.open(this.translateService.instant('snackbar.booking_detail.update'),
              'OK', { duration: 3000 });
            this.payModal = false;
            this.bookingData$.next(result.data);
            this.bookingData = result.data;
          }
        },
        (error) => {
          this.showErrorSnackbar(this.translateService.instant('snackbar.booking.payment.error'));
        }
      );
  }

  calculateTotalVoucherPrice(): number {
    return this.bookingData.vouchers ? this.bookingData.vouchers.reduce( (e, i) => e + parseFloat(i.bonus.reducePrice), 0) : 0
  }


  onPaymentMethodChange(event: any) {
    // Lógica para manejar el cambio de método de pago
    if (event.value === 1) {
      // Si se selecciona 'Pago directo', establecer un valor predeterminado o comportamiento necesario
      this.selectedPaymentOption = null; // Resetear la opción de pago seleccionada si es necesario
    } else {
      // Para otros métodos de pago, puedes asignar flags específicos
      this.selectedPaymentOption = event.value; // Ejemplo: asignar el método seleccionado
    }
  }
  cancelPaymentStep() {
    if(this.step == 1) {
      this.payModal = false;
    }
    this.step = 1;  // Regresar al paso 1
    this.isPaid = false;  // Resetear isPaid
  }


  showErrorSnackbar(message: string): void {
    this.snackBar.open(message, "OK", {
      duration: 3000,
      panelClass: ['error-snackbar']
    });
  }


}
