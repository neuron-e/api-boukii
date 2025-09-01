import {Component, Inject, OnInit, Optional} from '@angular/core';
import { ApiCrudService } from '../../../../service/crud.service';
import { ActivatedRoute, Router } from '@angular/router';
import { CoursesService } from '../../../../service/courses.service';
import { TranslateService } from '@ngx-translate/core';
import { TableColumn } from 'src/@vex/interfaces/table-column.interface';
import {MonitorsCreateUpdateComponent} from '../../monitors/monitors-create-update/monitors-create-update.component';
import moment from 'moment';
import {MAT_DIALOG_DATA, MatDialog} from '@angular/material/dialog';

@Component({
  selector: 'vex-course-detail',
  templateUrl: './course-detail.component.html',
  styleUrls: ['./course-detail.component.scss']
})
export class CourseDetailComponent implements OnInit {
  minDate = new Date(2000, 1, 1);
  nowDate = new Date()
  maxDate = new Date(2099, 12, 31);
  user: any;
  settings: any;
  id: number;
  loading: boolean = true;
  shortDescription: string = '';
  courseName: string = '';
  firstCombinationValue: number | null = null;
  description: string = '';
  ageRange: { age_min: number, age_max: number } = { age_min: 0, age_max: 0 };
  shortestDuration: string | null = null;
  sendEmailModal: boolean = false
  toggleClaimText: boolean = false
  selectedFrom = null;
  selectedTo = null;
  selectedMonitorId = null;
  filter = '';
  totalPriceSell = 0;

  constructor(private crudService: ApiCrudService, private activatedRoute: ActivatedRoute,
              public dialog: MatDialog,
              private router: Router, public courses: CoursesService, public TranslateService: TranslateService,
              @Optional() @Inject(MAT_DIALOG_DATA) public incData: any) {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.settings = JSON.parse(this.user.schools[0].settings);
    this.id = this.activatedRoute.snapshot.params.id;
  }

  detailData: any

  ngOnInit(): void {
    if (!this.incData) this.id = this.activatedRoute.snapshot.params.id;
    else this.id = this.incData.id;
    this.crudService.get('/admin/courses/' + this.id,
      ['courseGroups.degree', 'courseGroups.courseDates.courseSubgroups.bookingUsers.client',
        'sport' ,'courseExtras'])
      .subscribe((data: any) => {
        this.detailData = data.data
        this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order', '&school_id=' + this.detailData.school_id + '&sport_id=' + this.detailData.sport_id)
          .subscribe((data) => {
            this.detailData.degrees = [];
            data.data.forEach((element: any) => {
              if (element.active) this.detailData.degrees.push({ ...element, });
            });
            this.detailData.degrees.forEach((level: any) => {
              level.active = false;
              this.detailData.course_dates.forEach((cs: any) => {
                cs.course_groups.forEach((group: any) => {
                  if (group.degree_id === level.id) {
                    level.active = true;
                    level.old = true;
                  } level.visible = false;
                });
              });
            });
            this.crudService.list('/stations', 1, 10000, 'desc', 'id', '&school_id=' + this.detailData.school_id)
              .subscribe((st: any) => {
                st.data.forEach((element: any) => {
                  if (element.id === this.detailData.station_id) this.detailData.station = element
                });
                this.crudService.list('/booking-users', 1, 10000, 'desc', 'id', '&school_id=' + this.detailData.school_id + '&course_id=' + this.detailData.id)
                  .subscribe((bookingUser) => {
                    this.detailData.users = [];
                    this.detailData.users = bookingUser.data;
                    this.courses.settcourseFormGroup(this.detailData)
                    this.toggleClaimText = Boolean(this.courses.courseFormGroup.controls['claim_text'].value)
                    setTimeout(() => this.loading = false, 0);
                  })
              })
          });
      })
  }

  updateCourses() {
    const courseFormGroup = this.courses.courseFormGroup.getRawValue()
    courseFormGroup.translations = JSON.stringify(this.courses.courseFormGroup.controls['translations'].value)
    courseFormGroup.course_type === 1 ? delete courseFormGroup.settings : courseFormGroup.settings = JSON.stringify(this.courses.courseFormGroup.controls['settings'].value)
    this.crudService.update('/admin/courses', courseFormGroup, this.id).subscribe()
  }

  filterData() {

    let filter = '';

    if (this.selectedFrom) {
      filter = filter + '&start_date=' + moment(this.selectedFrom).format('YYYY-MM-DD');
    }
    if (this.selectedTo) {
      filter = filter + '&end_date=' + moment(this.selectedTo).format('YYYY-MM-DD');
    }
    if (this.selectedMonitorId) {
      filter = filter + '&monitor_id=' + this.selectedMonitorId;
    }

    this.filter = filter;

  }

  calculateTotal(data) {
    this.totalPriceSell = data.reduce((sum, item) => sum + item.total_cost, 0);
  }

  goTo(route: string, query: any = null) {
    this.router.navigate([route], { queryParams: query });
  }

  DateDiff = (value1: string, value2: string): number => Math.round((new Date(value2).getTime() - new Date(value1).getTime()) / 1000 / 60 / 60 / 24)
  count = (array: any[], key: string) => Boolean(array.map((a: any) => a[key]).find((a: any) => a))

  columns: TableColumn<any>[] = [
    { label: 'Id', property: 'id', type: 'id', visible: true, cssClasses: ['font-medium'] },
    { label: 'type', property: 'sport', type: 'booking_users_image', visible: true },
    { label: 'course', property: 'booking_users', type: 'booking_users', visible: true },
    { label: 'client', property: 'client_main', type: 'client', visible: true },
    { label: 'dates', property: 'dates', type: 'booking_dates', visible: true },
    { label: 'register', property: 'created_at', type: 'date', visible: true },
    //{ label: 'Options', property: 'options', type: 'text', visible: true },
    { label: 'op_rem_abr', property: 'has_cancellation_insurance', type: 'light', visible: true },
    { label: 'B. Care', property: 'has_boukii_care', type: 'light', visible: true },
    { label: 'price', property: 'price_total', type: 'price', visible: true },
    { label: 'method_paiment', property: 'payment_method', type: 'payment_method', visible: true },
    { label: 'bonus', property: 'bonus', type: 'light', visible: true },
    { label: 'paid', property: 'paid', type: 'payment_status', visible: true },
    { label: 'status', property: 'status', type: 'cancelation_status', visible: true },
    { label: 'Actions', property: 'actions', type: 'button', visible: true }
  ];

  columnsSalesCollective: TableColumn<any>[] = [
    { label: 'type', property: 'icon', type: 'booking_users_image', visible: true },
    { label: 'name', property: 'name', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'level', property: 'group_name', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'availability', property: 'available_places', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'sold', property: 'booked_places', type: 'text', visible: true },
    { label: 'cash', property: 'cash', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'other', property: 'other', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'T.Boukii', property: 'boukii', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'T.Boukii Web', property: 'boukii_web', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'Link', property: 'online', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'no_paid', property: 'no_paid', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'admin', property: 'admin', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'web', property: 'web', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'vouchers', property: 'vouchers', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'extras', property: 'extras', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'total', property: 'total_cost', type: 'price', visible: true },
  ];

  icon = '../../../assets/img/icons/cursos.svg';

  columnsSalesPrivate: TableColumn<any>[] = [
    { label: 'type', property: 'icon', type: 'booking_users_image', visible: true },
    { label: 'name', property: 'name', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'availability', property: 'available_places', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'sold', property: 'booked_places', type: 'text', visible: true },
    { label: 'cash', property: 'cash', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'other', property: 'other', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'T.Boukii', property: 'boukii', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'T.Boukii Web', property: 'boukii_web', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'Link', property: 'online', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'no_paid', property: 'no_paid', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'admin', property: 'admin', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'web', property: 'web', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'vouchers', property: 'vouchers', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'extras', property: 'extras', type: 'price', visible: true, cssClasses: ['font-medium'] },
    { label: 'total', property: 'total_cost', type: 'price', visible: true },
  ];

  protected readonly createComponent = MonitorsCreateUpdateComponent;
}
