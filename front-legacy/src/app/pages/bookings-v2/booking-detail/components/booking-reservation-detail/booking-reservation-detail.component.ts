import {Component, EventEmitter, Input, OnInit, Output} from '@angular/core';
import {LangService} from '../../../../../../service/langService';
import {UtilsService} from '../../../../../../service/utils.service';
import {MatDialog} from '@angular/material/dialog';
import {BookingService} from '../../../../../../service/bookings.service';
import {
  AddReductionModalComponent
} from '../../../bookings-create-update/components/add-reduction/add-reduction.component';
import {
  AddDiscountBonusModalComponent
} from '../../../bookings-create-update/components/add-discount-bonus/add-discount-bonus.component';
import {Observable, Subscription} from 'rxjs';
import {Router} from '@angular/router';
import {MatSnackBar} from '@angular/material/snack-bar';
import {TranslateService} from '@ngx-translate/core';
import {ApiCrudService} from '../../../../../../service/crud.service';

@Component({
  selector: 'booking-detail-reservation-detail',
  templateUrl: './booking-reservation-detail.component.html',
  styleUrls: ['./booking-reservation-detail.component.scss'],
})
export class BookingReservationDetailComponent implements OnInit {
  @Input() client: any;
  @Input() activities: any;
  @Input() isModal = false;
  @Input() hideBotton = false;
  @Input() bookingData: any;
  @Input() allLevels: any;
  @Output() endClick = new EventEmitter();
  @Output() deleteActivity = new EventEmitter();
  @Output() editClick = new EventEmitter();
  @Output() payClick = new EventEmitter();
  @Output() addClick = new EventEmitter();
  @Output() closeClick = new EventEmitter();
  @Input() activitiesChanged: Observable<void>;  // Recibimos el observable

  private activitiesChangedSub: Subscription;

  cancellationInsurancePercent: number;
  price_tva: number;
  price_boukii_care: number;
  school: any;
  settings: any;

  constructor(
    protected langService: LangService,
    protected utilsService: UtilsService,
    private snackbar: MatSnackBar,
    private crudService: ApiCrudService,
    private translateService: TranslateService,
    private router: Router,
    private dialog: MatDialog,
    private bookingService: BookingService
  ) {
    this.school = this.utilsService.getSchoolData();
    this.settings = JSON.parse(this.school.settings);
    this.cancellationInsurancePercent = parseFloat(this.settings?.taxes?.cancellation_insurance_percent);
    this.price_boukii_care = parseInt(this.settings?.taxes?.boukii_care_price, 10);
    this.price_tva = parseFloat(this.settings?.taxes?.tva);
  }

  ngOnInit(): void {
    this.loadExistingVouchers();
    //this.bookingData = this.bookingService.getBookingData() || this.initializeBookingData();
    this.recalculateBonusPrice();
    this.updateBookingData();
    this.activitiesChangedSub = this.activitiesChanged.subscribe((res: any) => {
      if (res) {
        this.bookingData = res;
      }
      this.loadExistingVouchers();
      this.recalculateBonusPrice();
      this.updateBookingData();
    });
  }

  goTo(route: string) {
    this.router.navigate([route]);
  }

  sendMailInfo() {
    this.crudService
      .post("/admin/bookings/mail/" + this.bookingData.id, {
        paid: this.bookingData.paid,
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

  loadExistingVouchers() {
    this.bookingData.vouchers = [];
    if (this.bookingData.vouchers_logs && Array.isArray(this.bookingData.vouchers_logs)) {
      this.bookingData.vouchers_logs.forEach(log => {
        this.bookingData.vouchers.push({
          bonus: {
            id: log.id,
            voucher_id: log.voucher_id,
            reducePrice: parseFloat(log.amount),
            code: log.voucher.code,
            remaining_balance: log.voucher.remaining_balance,
            is_old: true
          }
        });
      });
    }
  }

  sumActivityTotal(): number {
    return this.activities.reduce((acc: any, item: any) => {
      // Solo suma si el status es 1
      if (item.status != 2) {
        const numericValue = typeof item.total === 'number'
          ? item.total
          : parseFloat(item.total.toString().replace(/[^\d.-]/g, '')) || 0;

        return acc + numericValue;
      }
      return acc; // Si no es status 1, retorna el acumulador sin sumar
    }, 0);
  }

  updateBookingData() {
    this.bookingData.price_total = this.calculateTotal();
    this.bookingService.setBookingData(this.bookingData);
  }

  calculateRem(event: any) {
    if (event.source.checked) {
      this.bookingData.price_cancellation_insurance = this.sumActivityTotal() * this.cancellationInsurancePercent;
      this.bookingData.has_cancellation_insurance = event.source.checked;
    } else {
      this.bookingData.price_cancellation_insurance = 0;
      this.bookingData.has_cancellation_insurance = event.source.checked;
    }
    this.updateBookingData();
    this.recalculateBonusPrice();
  }

  recalculateBonusPrice() {
    let remainingPrice = parseFloat(this.bookingData.price_total) - this.calculateTotalVoucherPrice();
    if (remainingPrice !== 0) {
      this.bookingData.vouchers.forEach(voucher => {
        if (!voucher.bonus.is_old) {
          const availableBonus = voucher.bonus.remaining_balance - voucher.bonus.reducePrice;

          if (remainingPrice > 0) {
            if (availableBonus >= remainingPrice) {
              voucher.bonus.reducePrice += remainingPrice;
              remainingPrice = 0;
            } else {
              voucher.bonus.reducePrice += availableBonus;
              remainingPrice -= availableBonus;
            }
          } else {
            const adjustedReducePrice = voucher.bonus.reducePrice + remainingPrice;

            if (adjustedReducePrice >= 0) {
              voucher.bonus.reducePrice = adjustedReducePrice;
              remainingPrice = 0;
            } else {
              remainingPrice -= voucher.bonus.reducePrice; // Reduce remainingPrice solo por lo que hay en reducePrice.
              voucher.bonus.reducePrice = 0; // Aseguramos que nunca sea negativo.
            }
          }
        }
      });
    }

    this.updateBookingData();
  }


  recalculateTva() {
    const basePrice = this.sumActivityTotal()
      + parseFloat(this.bookingData.price_cancellation_insurance)
      - parseFloat(this.bookingData.price_reduction)
      + parseFloat(this.bookingData.price_boukii_care);

    this.bookingData.price_tva = basePrice * this.price_tva;
  }

  calculateTotal() {
    this.recalculateTva();
    return this.sumActivityTotal() + parseFloat(this.bookingData.price_cancellation_insurance)
      - parseFloat(this.bookingData.price_reduction)
      + parseFloat(this.bookingData.price_boukii_care)
      + parseFloat(this.bookingData.price_tva);
  }

  calculateTotalVoucherPrice(): number {

    if(this.bookingData.vouchers) {
      return this.bookingData.vouchers.reduce((acc, item) => acc + parseFloat(item.bonus.reducePrice), 0);
    }
    return 0
  }

  addBonus(): void {
    const dialogRef = this.dialog.open(AddDiscountBonusModalComponent, {
      width: '600px',
      data: {
        client_id: this.client.id,
        school_id: this.school.id,
        currentPrice: this.bookingData.price_total - this.calculateTotalVoucherPrice(),
        appliedBonus: this.bookingData.vouchers,
        currency: this.activities[0].course.currency,
      },
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.bookingData.vouchers.push(result);
        this.updateBookingData();
        this.recalculateBonusPrice();
      }
    });
  }

  addReduction(): void {
    const dialogRef = this.dialog.open(AddReductionModalComponent, {
      width: '530px',
      data: { currentPrice: this.bookingData.price_total, currency: this.activities[0].course.currency },
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.bookingData.reduction = result;
        this.bookingData.has_reduction = true;
        this.bookingData.reduction.appliedPrice = result.totalDiscount;
        this.bookingData.price_reduction = this.bookingData.reduction.appliedPrice;
        this.updateBookingData();
        this.recalculateBonusPrice();
      }
    });
  }

  deleteReduction(): void {
    this.bookingData.reduction = null;
    this.bookingData.price_reduction = 0;
    this.updateBookingData();
    this.recalculateBonusPrice();
  }

  deleteBonus(index: number): void {
    this.bookingData.vouchers.splice(index, 1);
    this.updateBookingData();
    this.recalculateBonusPrice();
  }

  private calculateReduction(): number {
    return this.bookingData.reduction.type === 1
      ? (this.sumActivityTotal() * this.bookingData.reduction.discount) / 100
      : Math.min(this.bookingData.reduction.discount, this.sumActivityTotal());
  }

  protected readonly isNaN = isNaN;
  protected readonly parseFloat = parseFloat;
  protected readonly Math = Math;
}
