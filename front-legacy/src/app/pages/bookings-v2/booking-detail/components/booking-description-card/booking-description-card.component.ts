import { Component, EventEmitter, Input, Output } from '@angular/core';
import { ChangeMonitorOption } from "src/app/static-data/changeMonitorOptions";
import { LangService } from "src/service/langService";
import { UtilsService } from "src/service/utils.service";
import { MatDialog } from '@angular/material/dialog';
import { FormDetailsPrivateComponent } from '../form-details-private/form-details-private.component';
import { FormDetailsColectiveFlexComponent } from '../form-details-colective-flex/form-details-colective-flex.component';
import { FormDetailsColectiveFixComponent } from '../form-details-colective-fix/form-details-colective-fix.component';
import { StepObservationsComponent } from '../step-observations/step-observations.component';
import {TranslateService} from '@ngx-translate/core';
import {BookingService} from '../../../../../../service/bookings.service';
import {ApiCrudService} from '../../../../../../service/crud.service';
import {Router} from '@angular/router';
import {MatSnackBar} from '@angular/material/snack-bar';

export interface BookingDescriptionCardDate {
  date: string;
  startHour: string;
  endHour: string;
  price: string;
  currency: string;
  changeMonitorOption?: ChangeMonitorOption;
  monitor?: Record<string, any>;
  utilizer?: Record<string, any>[];
  utilizers?: Record<string, any>[];
  booking_users?: any[];
  extras?: Record<string, any>[];
}

@Component({
  selector: "booking-detail-description-card",
  templateUrl: "./booking-description-card.component.html",
  styleUrls: ["./booking-description-card.component.scss"],
})
export class BookingDescriptionCard {
  @Output() editActivity = new EventEmitter();
  @Output() deleteActivity = new EventEmitter();

  @Input() utilizers: any;
  @Input() sport: any;
  @Input() sportLevel: any;
  @Input() allLevels: any;
  @Input() course: any;
  @Input()
  set dates(value: any[]) {
    this._dates = value || [];
    this.extractUniqueMonitors();
  }

  get dates(): any[] {
    return this._dates;
  }
  @Input() monitors: any;
  @Input() clientObs: any;
  @Input() schoolObs: any;
  @Input() groupedActivities: any;
  @Input() total: any;
  @Input() summaryMode = false;
  @Input() isDetail = false;
  @Input() status = 1;
  @Input() index: number = 1;
  uniqueMonitors: any[] = []; // Monitores únicos
  private _dates: any[] = [];

  constructor(
    public translateService: TranslateService,
    public bookingService: BookingService,
    protected langService: LangService,
    protected utilsService: UtilsService,
    public dialog: MatDialog
  ) {
    this.extractUniqueMonitors();
  }

  formatDate(date: string) {
    return this.utilsService.formatDate(date);
  }

  private extractUniqueMonitors() {
    if (this.dates.length) {
      const allMonitors = this.dates.map((date) => date.monitor).filter((monitor) => !!monitor);
      this.uniqueMonitors = allMonitors.filter(
        (monitor, index, self) => self.findIndex((m) => m.id === monitor.id) === index
      );
    } else {
      this.uniqueMonitors = [];
    }
  }

  hasExtrasForDate(date: any): boolean {
    // Verifica si hay utilizadores para la fecha y si al menos uno tiene extras
    return date.utilizers?.some((utilizer: any) => utilizer.extras && utilizer.extras.length > 0) || false;
  }

  calculateDiscountedPrice(date: any, index: number): number {
    let price = this.bookingService.calculateDatePrice(this.course, date, true); // Asegúrate de convertir el precio a número

    if (this.course && this.course.discounts && !Array.isArray(this.course.discounts)) {
      const discounts = [];
      try {
        const discounts = JSON.parse(this.course.discounts);
        console.log("Discounts parseado correctamente:", discounts);
      } catch (error) {
        console.error("Error al parsear discounts:", error);
      }

      discounts.forEach(discount => {
        if (discount.date === index + 1) { // Index + 1 porque los índices en arrays comienzan en 0
          price -= (price * (discount.percentage / 100));
        }
      });
    }

    return price;
  }

  shouldShowPrice(course: any, date: any, index: number): boolean {
    // Si es course_type !== 1 y no es flexible, mostrar solo en la primera fecha
    if (course.course_type === 1 && !course.is_flexible) {
      return index === 0;
    }

    // En otros casos, mostrar el precio normalmente
    return true;
  }


  isDiscounted(date: any, index: number): boolean {
    const price = parseFloat(date.price);
    if (this.course && this.course.discounts && !Array.isArray(this.course.discounts) ) {
      const discounts = [];
      try {
        const discounts = JSON.parse(this.course.discounts);
        console.log("Discounts parseado correctamente:", discounts);
      } catch (error) {
        console.error("Error al parsear discounts:", error);
      }
      return discounts.some(discount => discount.date === index + 1); // Index + 1 porque los índices en arrays comienzan en 0
    }
    return false;
  }

  getExtraDescription(dateExtra) {
    return dateExtra.map((extra) => extra?.description).join(", ");
  }

  getExtraName(dateExtra) {
    return dateExtra.map((extra) => extra?.name).join(", ");
  }

  getExtraPrice(dateExtra) {
    return dateExtra.map((extra) => extra?.price).join(", ");
  }

  sendEditForm(dates: any, course: any, utilizers: any = []) {
    if (course.course_type == 2) {
      this.openPrivateDatesForm(dates, course, utilizers);
    } else if (course.course_type == 1) {
      if (course.is_flexible) {
        this.openCollectiveFlexDatesForm(dates, course, utilizers)
      } else {
        this.openCollectiveFixDatesForm(dates, course, utilizers)
      }
    }
  }



  private openPrivateDatesForm(dates: any, course: any, utilizers: any = []) {
    const dialogRef = this.dialog.open(FormDetailsPrivateComponent, {
      width: "800px",
      panelClass: "customBookingDialog",
      data: {
        utilizers: utilizers,
        sport: course.sport,
        sportLevel: this.sportLevel,
        course: course,
        groupedActivities: this.groupedActivities,
        initialData: dates
      },
    });
    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        // Aquí manejas los datos actualizados que provienen del modal
        this.dates = result.course_dates;
        this.total = this.dates[0].price
        result.total = this.total;
        this.editActivity.emit(result);
        // Aquí puedes tomar los datos y hacer lo que necesites con ellos
        // Por ejemplo, enviarlos al backend o actualizar la UI
        //this.updateBooking(result);
      } else {
        this.editActivity.emit(result);
      }
    });
  }

  private openCollectiveFlexDatesForm(dates: any, course: any, utilizers: any = []) {
    const dialogRef = this.dialog.open(FormDetailsColectiveFlexComponent, {
      width: "800px",
      height: "800px",
      panelClass: "customBookingDialog",
      data: {
        utilizer: utilizers[0],
        sport: course.sport,
        sportLevel: this.sportLevel,
        course: course,
        groupedActivities: this.groupedActivities,
        initialData: dates
      },
    });
    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        // Aquí manejas los datos actualizados que provienen del modal
        this.dates = result.course_dates;
        this.total = this.dates.reduce((acc, date) =>
          acc + parseFloat(date.price), 0).toFixed(2);
        result.total = this.total;
        this.editActivity.emit(result);
        // Aquí puedes tomar los datos y hacer lo que necesites con ellos
        // Por ejemplo, enviarlos al backend o actualizar la UI
        //this.updateBooking(result);
      } else {
        this.editActivity.emit(result);
      }
    });
  }

  private openCollectiveFixDatesForm(dates: any, course: any, utilizers: any = []) {
    const dialogRef = this.dialog.open(FormDetailsColectiveFixComponent, {
      width: "800px",
      panelClass: "customBookingDialog",
      data: {
        utilizer: utilizers[0],
        sport: course.sport,
        sportLevel: this.sportLevel,
        course: course,
        groupedActivities: this.groupedActivities,
        initialData: dates
      },
    });
    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        // Aquí manejas los datos actualizados que provienen del modal
        this.dates = result.course_dates;
        this.total = this.dates.reduce((acc, date) =>
          acc + parseFloat(date.price), 0).toFixed(2);
        result.total = this.total;
        this.editActivity.emit(result);
        // Aquí puedes tomar los datos y hacer lo que necesites con ellos
        // Por ejemplo, enviarlos al backend o actualizar la UI
        //this.updateBooking(result);
      } else {
        this.editActivity.emit(result);
      }
    });
  }

  openObservationsForm(clientObs: any, schoolObs: any) {
    const dialogRef = this.dialog.open(StepObservationsComponent, {
      width: "800px",
      panelClass: "customBookingDialog",
      data: {
        initialData: {
          clientObs: clientObs,
          schoolObs: schoolObs
        }
      },
    });
    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        // Aquí manejas los datos actualizados que provienen del modal
        this.schoolObs = result.schoolObs;
        this.clientObs = result.clientObs;
        this.editActivity.emit(result);
        //this.updateBooking(result);
      }
    });
  }



  protected readonly parseFloat = parseFloat;
}
