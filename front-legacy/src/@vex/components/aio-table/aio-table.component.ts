import {
  AfterViewInit,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  EventEmitter,
  Input,
  OnInit,
  Output,
  ViewChild,
  inject,
  SimpleChanges, OnChanges
} from '@angular/core';
import { Observable, ReplaySubject } from 'rxjs';
import { map, startWith } from 'rxjs/operators';
import { MatTableDataSource } from '@angular/material/table';
import { MatPaginator, PageEvent } from '@angular/material/paginator';
import { MatSort, Sort } from '@angular/material/sort';
import { MatDialog } from '@angular/material/dialog';
import { SelectionModel } from '@angular/cdk/collections';
import { MAT_FORM_FIELD_DEFAULT_OPTIONS, MatFormFieldDefaultOptions } from '@angular/material/form-field';
import { UntilDestroy } from '@ngneat/until-destroy';
import { MatSelectChange } from '@angular/material/select';
import { fadeInUp400ms } from 'src/@vex/animations/fade-in-up.animation';
import { stagger40ms } from 'src/@vex/animations/stagger.animation';
import { TableColumn } from 'src/@vex/interfaces/table-column.interface';
import { ActivatedRoute, Router } from '@angular/router';
import { FormControl, UntypedFormControl } from '@angular/forms';
import { ApiCrudService } from 'src/service/crud.service';
import { MOCK_COUNTRIES } from 'src/app/static-data/countries-data';
import { MOCK_PROVINCES } from 'src/app/static-data/province-data';
import moment from 'moment';
import { ConfirmModalComponent } from 'src/app/pages/monitors/monitor-detail/confirm-dialog/confirm-dialog.component';
import { TranslateService } from '@ngx-translate/core';

import { jsPDF } from 'jspdf';
import * as QRCode from 'qrcode';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ExcelExportService } from '../../../service/excel.service';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';

@UntilDestroy()
@Component({
  selector: 'vex-aio-table',
  templateUrl: './aio-table.component.html',
  styleUrls: ['./aio-table.component.scss'],
  animations: [
    fadeInUp400ms,
    stagger40ms
  ],
  providers: [
    {
      provide: MAT_FORM_FIELD_DEFAULT_OPTIONS,
      useValue: {
        appearance: 'fill'
      } as MatFormFieldDefaultOptions
    },
  ]
})
export class AioTableComponent implements OnInit, AfterViewInit, OnChanges {
  private readonly destroyRef: DestroyRef = inject(DestroyRef);

  layoutCtrl = new UntypedFormControl('boxed');
  subject$: ReplaySubject<any[]> = new ReplaySubject<any[]>(1);
  data$: Observable<any[]> = this.subject$.asObservable();
  data: any[];

  @Input() columns: TableColumn<any>[] = [];
  @Input() entity: string;
  @Input() deleteEntity: string;
  @Input() updatePage: string = 'update';
  @Input() title: string;
  @Input() sectionIcon: string;
  @Input() route: string;
  @Input() withHeader: boolean = true;
  @Input() withFilters: boolean = true;
  @Input() canDelete: boolean = false;
  @Input() canDeactivate: boolean = false;
  @Input() canDuplicate: boolean = false;
  @Input() createOnModal: boolean = false;
  @Input() widthModal?: string = '90vw';
  @Input() heigthModal?: string = '90vh';
  @Input() createComponent: any;
  @Input() showDetail: boolean = false;
  @Input() filterField: any = null;
  @Input() filterColumn: any = null;
  @Input() with: any = '';
  @Input() search: any = '';
  @Output() showDetailEvent = new EventEmitter<any>();
  @Output() dataLoaded = new EventEmitter<any[]>();
  pageIndex = 1;
  pageSize = 10;
  filter = '';
  totalRecords = 1000;
  pageSizeOptions: number[] = [10, 25, 50];
  dataSource: MatTableDataSource<any> | null;
  selection = new SelectionModel<any>(true, []);
  searchCtrl = new UntypedFormControl('');
  imageAvatar = '../../../assets/img/avatar.png';
  loading = true;
  user: any;
  schoolId: any;
  clients: any = [];
  monitors: any = [];
  languages: any = [];
  sports: any = [];
  allLevels: any = [];
  countries = MOCK_COUNTRIES;
  provinces = MOCK_PROVINCES;
  sportsControl = new FormControl();
  filteredSports: Observable<any[]>;
  selectedSports: any[] = [];
  openFilters: boolean = false;
  selectedFrom = null;
  selectedTo = null;
  gift = 0;
  today = new Date();

  @ViewChild(MatPaginator, { static: true }) paginator: MatPaginator;
  @ViewChild(MatSort, { static: true }) sort: MatSort;

  reservationTypeSingle = false;
  reservationTypeMultiple = false;
  trashed = false;

  courseColective = false;
  coursePrivate = false;
  courseActivity = false;

  bookingPayed = false;
  bookingNoPayed = false;

  activeCourse = false;
  inActiveCourse = false;
  finishedCourse = false;
  allCourse = true;

  activeBooking = true;
  finishedBooking = false;
  allBookings = false;
  cancelledBookings = false;

  activeMonitor = true;
  inactiveMonitor = false;
  allMonitors = false;

  constructor(private dialog: MatDialog, public router: Router, private crudService: ApiCrudService,
    private excelExportService: ExcelExportService, private routeActive: ActivatedRoute,
    private cdr: ChangeDetectorRef, public translateService: TranslateService, private snackbar: MatSnackBar) {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.schoolId = this.user.schools[0].id;
  }

  get visibleColumns() {
    return this.columns.filter(column => column.visible).map(column => column.property);
  }

  exportTableToExcel = () => this.excelExportService.exportAsExcelFile(this.dataSource.data, 'YourTableData');

  ngOnInit() {
    this.searchCtrl.valueChanges
      .pipe(
        debounceTime(500), // Espera 300 ms tras cada cambio
        distinctUntilChanged() // Solo dispara si el valor realmente cambia
      )
      .subscribe(() => {
        this.pageIndex = 1;
        this.getFilteredData(this.pageIndex, this.pageSize, this.filter);
      });
    this.routeActive.queryParams.subscribe(params => {
      this.gift = +params['isGift'] || 0;
      if (this.entity.includes('vouchers')) this.filter += this.gift ? '&is_gift=1' : '&is_gift=0';
    });
    this.getLanguages();
    this.getDegrees();
    this.getSports();
    /*this.getMonitors();
    this.getClients();*/
  }

  // Detecta cambios en las propiedades de entrada
  ngOnChanges(changes: SimpleChanges): void {
    if (changes['search']) this.getFilteredData(1, 99999, this.filter);
  }

  getLanguages = () => this.crudService.list('/languages', 1, 1000).subscribe((data) => this.languages = data.data.reverse())

  onButtonGroupClick($event) {
    let clickedElement = $event.target || $event.srcElement;

    if (clickedElement.nodeName === "BUTTON") {

      let isCertainButtonAlreadyActive = clickedElement.parentElement.querySelector(".active");
      // if a Button already has Class: .active
      if (isCertainButtonAlreadyActive) isCertainButtonAlreadyActive.classList.remove("active");

      clickedElement.className += " active";
    }

  }

  Sort: Sort = { active: 'id', direction: 'desc' }

  filterData(all: boolean = false, pageIndex: number = this.pageIndex, pageSize: number = this.pageSize) {
    let filter = '';
    this.pageIndex = pageIndex;
    this.pageSize = pageSize;
    if (!all) {
      if (this.entity.includes('booking')) {
        // Filtrar por tipo de curso (colectivo, privado, actividad)
        const courseTypes = [];
        // Filtrar por tipo de curso (colectivo, privado, actividad)
        if (this.courseColective && !this.coursePrivate && !this.courseActivity) {
          filter = filter + '&course_type=1';
        } else if (!this.courseColective && this.coursePrivate && !this.courseActivity) {
          filter = filter + '&course_type=2';
        } else if (!this.courseColective && !this.coursePrivate && this.courseActivity) {
          filter = filter + '&course_type=3';
        } else {
          if (this.courseColective) courseTypes.push(1);
          if (this.coursePrivate) courseTypes.push(2);
          if (this.courseActivity) courseTypes.push(3);
          // Añadir los tipos de curso al filtro si existen
          if (courseTypes.length > 0) filter = filter + '&course_types[]=' + courseTypes.join('&course_types[]=');
        }
        // Filtrar por estado de pago
        if (this.bookingPayed && !this.bookingNoPayed) filter = filter + '&paid=1';
        else if (!this.bookingPayed && this.bookingNoPayed) filter = filter + '&paid=0';

        // Filtrar por tipo de reserva (individual o múltiple)
        if (this.reservationTypeSingle && !this.reservationTypeMultiple) filter = filter + '&isMultiple=0';
        else if (!this.reservationTypeSingle && this.reservationTypeMultiple) filter = filter + '&isMultiple=1';

        // Filtrar por estado de finalización
        filter = this.finishedBooking ? filter + '&finished=0' : filter + '&finished=1';
        if (this.allBookings) filter = filter + '&all=1';
        if (this.cancelledBookings) filter = filter + '&status=2';

      }

      if (this.entity.includes('courses')) {
        const courseTypes = [];

        // Filtrar por tipo de curso (colectivo, privado, actividad)
        if (this.courseColective && !this.coursePrivate && !this.courseActivity) {
          filter = filter + '&course_type=1';
        } else if (!this.courseColective && this.coursePrivate && !this.courseActivity) {
          filter = filter + '&course_type=2';
        } else if (!this.courseColective && !this.coursePrivate && this.courseActivity) {
          filter = filter + '&course_type=3';
        } else {
          if (this.courseColective) {
            courseTypes.push(1); // Colectivo
          }
          if (this.coursePrivate) {
            courseTypes.push(2); // Privado
          }
          if (this.courseActivity) {
            courseTypes.push(3); // Actividad
          }
          // Añadir los tipos de curso al filtro si existen
          if (courseTypes.length > 0) {
            filter = filter + '&course_types[]=' + courseTypes.join('&course_types[]=');
          }
        }
        if (!this.allCourse) {
          if (this.finishedCourse) {
            filter = filter + '&finished=1';
          } else {
            filter = filter + '&finished=0';
          }
        }

        if (this.activeCourse && !this.inActiveCourse) {
          filter = filter + '&active=1';
        } else if (!this.activeCourse && this.inActiveCourse) {
          filter = filter + '&active=0';
        }

        if (this.sportsControl?.value?.length !== this.sports?.length) {
          const ids = [];
          this.sportsControl?.value?.forEach(element => {
            ids.push(element.id);
          });
          if (ids.length > 1) {
            filter = filter + '&sports_id[]=' + ids.join('&sports_id[]=');
          } else if (ids.length == 1) {
            filter = filter + '&sport_id=' + ids[0];
          }
        }
      }

      if (this.entity.includes('monitor')) {
        if (this.activeMonitor && !this.inactiveMonitor) {
          filter = filter + '&school_active=1';
        } else if (!this.activeMonitor && this.inactiveMonitor) {
          filter = filter + '&school_active=0';
        }
        if (this.sportsControl?.value?.length !== this.sports?.length) {
          const ids = [];
          this.sportsControl?.value?.forEach(element => {
            ids.push(element.id);
          });
          if (ids.length) {
            filter = filter + '&sports_id[]=' + ids.join('&sports_id[]=');
          }


        }
      }
      if (this.entity.includes('statistics')) {
        if (this.selectedFrom) {
          filter = filter + '&start_date=' + moment(this.selectedFrom).format('YYYY-MM-DD');
        }
        if (this.selectedTo) {
          filter = filter + '&start_to=' + moment(this.selectedTo).format('YYYY-MM-DD');
        }
      }
      if (this.entity.includes('vouchers')) {
        if (this.gift) {
          filter = filter + '&is_gift=1';
        } else {
          filter = filter + '&is_gift=0';
        }
        if (this.trashed) {
          filter = filter + '&onlyTrashed=true';
        }
      }
    }
    this.filter = filter;
    this.getFilteredData(pageIndex, pageSize, filter);
  }

  navigateWithParam(route: string, param: string) {
    this.router.navigate([route], { queryParams: { isGift: param } });
  }

  /**
   * Example on how to get data and pass it to the table - usually you would want a dedicated service with a HTTP request for this
   * We are simulating this request here.
   */
  getFilteredData(pageIndex: number, pageSize: number, filter: any) {
    //this.loading = true;
    // Asegúrate de que pageIndex y pageSize se pasan correctamente.
    // Puede que necesites ajustar pageIndex según cómo espera tu backend que se paginen los índices (base 0 o base 1).
    this.crudService.list(
      this.entity,
      pageIndex,
      pageSize,
      this.Sort.direction,
      this.Sort.active,
      this.searchCtrl.value + filter + '&school_id=' + this.user.schools[0].id + this.search +
      (this.filterField !== null ? '&' + this.filterColumn + '=' + this.filterField : ''),
      '',
      null,
      this.searchCtrl.value,
      this.with)
      .subscribe((response: any) => {
        this.pageIndex = pageIndex;
        this.pageSize = pageSize;
        this.data = response.data;
        this.dataLoaded.emit(response.data); // Emitimos los datos al componente padre
        //this.dataSource.data = []; // Reinicializa el dataSource para eliminar los datos antiguos
        this.dataSource.data = response.data;
        this.dataSource.connect();
        this.totalRecords = response.total;
        if (this.paginator) {
          this.paginator.pageIndex = pageIndex - 1;
          this.paginator.pageSize = pageSize;
        }
        this.loading = false;
      });
  }
  /**
   * Example on how to get data and pass it to the table - usually you would want a dedicated service with a HTTP request for this
   * We are simulating this request here.
   */
  getData(pageIndex: number, pageSize: number) {
    this.loading = true;
    this.filterData(false, pageIndex, pageSize);
  }

  onPageChange(event: PageEvent) {
    // La API puede esperar la primera página como 1, no como 0.
    this.getData(event.pageIndex + 1, event.pageSize);
  }

  sortData(sort: Sort) {
    this.Sort = sort
    this.filterData(false, this.pageIndex, this.pageSize)
  }

  ngAfterViewInit() {
    this.dataSource = new MatTableDataSource();
    this.dataSource.sort = this.sort;
    this.getData(this.pageIndex, this.pageSize);
  }

  create() {
    if (!this.createOnModal) {
      const route = '/' + this.route + '/create';
      this.router.navigate([route]);
    } else this.createModal();
  }

  createModal() {

    const dialogRef = this.dialog.open(this.createComponent, {
      width: this.widthModal,
      height: this.heigthModal,
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog'  // Si necesitas estilos adicionales
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) this.getData(this.pageIndex, this.pageSize);
    });
  }

  update(row: any) {
    if (!this.createOnModal) this.router.navigate(['/' + this.route + '/' + this.updatePage + '/' + row.id]);
    else this.updateModal(row);
  }

  updateModal(row: any) {
    const dialogRef = this.dialog.open(this.createComponent, {
      width: this.widthModal,
      height: this.heigthModal,
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales
      data: row
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) this.getData(this.pageIndex, this.pageSize);
    });
  }

  showDetailFn(row: any) {
    this.showDetailEvent.emit({ showDetail: !this.showDetail, item: row });
  }

  async delete(item: any) {

    const dialogRef = this.dialog.open(ConfirmModalComponent, {
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales,
      data: { message: this.translateService.instant('delete_text'), title: this.translateService.instant('delete_title') }
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) this.crudService.delete(this.deleteEntity, item.id).subscribe(() => this.getData(this.pageIndex, this.pageSize))
    });
  }

  async restore(item: any) {
    const dialogRef = this.dialog.open(ConfirmModalComponent, {
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales,
      data: {
        message: this.translateService.instant('restore_text'),
        title: this.translateService.instant('restore_title')
      }
    });

    dialogRef.afterClosed().subscribe((confirmed: boolean) => {
      if (confirmed) this.crudService.restore(this.deleteEntity, item.id).subscribe(() => this.getData(this.pageIndex, this.pageSize));
    });
  }

  deactivate(item: any) {

    const dialogRef = this.dialog.open(ConfirmModalComponent, {
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales,
      data: { message: this.translateService.instant('delete_text'), title: this.translateService.instant('delete_title') }
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
        if (this.entity.includes('monitor')) {
          const monitorSchool = item.monitors_schools.find((c) => c.school_id === this.user.schools[0].id);
          this.crudService.update('/monitors-schools', {
            monitor_id: monitorSchool.monitor_id,
            school_id: monitorSchool.school_id, active_school: !item.active
          }, monitorSchool.id).subscribe(() => this.getData(this.pageIndex, this.pageSize))
        } else if (this.entity.includes('clients')) {
          const clientSchool = item.clients_schools.find((c) => c.school_id === this.user.schools[0].id);

          this.crudService.update('/clients-schools', { client_id: clientSchool.client_id, school_id: clientSchool.school_id, accepted_at: clientSchool.accepted_at !== null ? null : moment().format('YYYY-MM-DD HH:mm:ss') }, clientSchool.id)
            .subscribe(() => {
              this.getData(this.pageIndex, this.pageSize);
            })

        } else if (this.entity.includes('courses')) {
          this.crudService.update('/courses', {
            active: false
          }, item.id)
            .subscribe(() => {
              this.getData(this.pageIndex, this.pageSize);
            })

        }

      }
    });
  }
  deleteMultiple(items: any[]) {
    /**
     * Here we are updating our local array.
     * You would probably make an HTTP request here.
     */
    items.forEach(c => this.delete(c));
  }

  onFilterChange(value: string) {
    if (!this.dataSource) {
      return;
    }
    value = value.trim();
    value = value.toLowerCase();
    this.dataSource.filter = value;
  }

  toggleColumnVisibility(column, event) {
    event.stopPropagation();
    event.stopImmediatePropagation();
    column.visible = !column.visible;
  }

  /** Whether the number of selected elements matches the total number of rows. */
  isAllSelected() {
    const numSelected = this.selection.selected.length;
    const numRows = this.dataSource.data.length;
    return numSelected === numRows;
  }

  /** Selects all rows if they are not all selected; otherwise clear selection. */
  masterToggle() {
    this.isAllSelected() ?
      this.selection.clear() :
      this.dataSource.data.forEach(row => this.selection.select(row));
  }

  trackByProperty<T>(index: number, column: TableColumn<T>) {
    return column.property;
  }

  onLabelChange(change: MatSelectChange, row: any) {
    const index = this.data.findIndex(c => c === row);
    this.data[index].labels = change.value;
    this.subject$.next(this.data);
  }

  transformDates(dates: any) {
    let ret = "";
    if (dates) {
      dates.forEach((element, idx) => {
        if (idx < 2) ret = ret + '<b>' + element + '</b>' + '<br>';
        else if (idx === 2) ret = ret + element + '-';
        else ret = ret + element;
      });
    }
    return ret;
  }

  transformRegisterDates(dates: any) {
    let ret = "";
    if (dates) {

      dates.forEach((element, idx) => {
        if (idx === 0) {
          ret = ret + '<b>' + element + '</b>' + '<br>';
        } else {
          ret = ret + element;
        }
      });
    }

    return ret;
  }

  calculateAge(birthDateString) {
    if (birthDateString && birthDateString !== null) {
      const today = new Date();
      const birthDate = new Date(birthDateString);
      let age = today.getFullYear() - birthDate.getFullYear();
      const m = today.getMonth() - birthDate.getMonth();

      if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
      return age;
    } return 0;
  }

  calculateMaxBookings(data: any) {
    let ret = 0;
    if (data.is_flexible && data.course_type === 1) {
      data.course_dates.forEach((courseDate: any) => {
        courseDate.course_groups.forEach((group: any) => {
          group.course_subgroups.forEach((sb: any) => {
            ret = ret + sb.max_participants;
          });
        });

      });
    }
    return data.max_participants * data.course_dates.length;
  }

  getSportNames(data: any) {
    let ret = '';
    data.forEach((element: any, idx: any) => ret = (element?.sport?.name || element?.name) + (idx + 1 === data.length ? '' : ', '));
    return ret;
  }

  getBookingCourse(data: any) {
    if (data.length === 1 || this.checkIfCourseIdIsSame(data)) {
      return this.getTrad(data[0].course.translations, data[0].course.name);
    } else {
      return 'MULTIPLE';
    }
  }

  checkIfCourseIdIsSame(data: any[]): boolean {
    if (data.length === 0) return false;
    const firstCourseId = data[0].course.id;
    return data.every(item => item.course.id === firstCourseId);
  }

  getBookingCourseMonitorClient(data: any) {
    return data.name;
  }

  getMinMaxDates(data: any[]): { minDate: string, maxDate: string, days: number } {
    let days = 0;

    // Verificar si data está vacío
    if (data.length === 0) return { minDate: '', maxDate: '', days: days };

    // Validar y convertir las fechas
    const parseDate = (dateString: string): Date | null => {
      if (!dateString) return null; // Manejo de cadenas vacías o nulas
      try {
        return new Date(dateString); // El constructor de Date debería aceptar ISO 8601
      } catch {
        return null; // Si falla, retornar null
      }
    };

    let minDate: Date | null = parseDate(data[0].date);
    let maxDate: Date | null = parseDate(data[0].date);

    if (!minDate || !maxDate) {
      console.error('Invalid initial date:', data[0].date);
      return { minDate: '', maxDate: '', days: days };
    }

    // Iterar sobre los elementos de data
    data.forEach(item => {
      const currentDate = parseDate(item.date);
      if (!currentDate) {
        console.error('Invalid date found in data:', item.date);
        return;
      }

      if (currentDate < minDate!) minDate = currentDate;
      if (currentDate > maxDate!) maxDate = currentDate;
      days++;
    });

    return {
      minDate: minDate!.toISOString(), // Convertir a ISO para evitar problemas
      maxDate: maxDate!.toISOString(),
      days
    };
  }



  getMinMaxHours(data: any[]): { minHour: string, maxHour: string } {
    if (data.length === 0) return { minHour: '', maxHour: '' };
    let minHour = null;
    let maxHour = null;
    if (data[0].course.course_type === 2) {
      minHour = data[0].hour_start;
      maxHour = data[0].hour_end.replace(':00', '');
    } else {
      minHour = data[0].hour_start;
      maxHour = data[0].hour_end.replace(':00', '');
      data.forEach(item => {
        if (item.hour_start < minHour) minHour = item.hour_start;
        if (item.hour_end > maxHour) maxHour = item.hour_end.replace(':00', '');
      })
    }
    minHour = minHour.replace(':00', '');
    return { minHour, maxHour };
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

  getCourseType(data: any) {
    //if (data.length === 1) {
    return data.course_type === 1 ? 'collectif' : 'prive'
    /*} else {
      return 'MULTIPLE';
    }*/
  }

  getBookingType(data: any) {
    //if (data.length === 1) {
    return data?.course?.course_type === 1 ? 'collectif' : 'prive'
    /*} else {
      return 'MULTIPLE';
    }*/
  }

  getCourseImage(data: any) {
    //if (data.length === 1) {
    const ret = this.sports.find((s) => s.id === data.sport_id);
    return ret ? ret.name.toLowerCase() : '';
    /* } else {
       return 'MULTIPLE';
     }*/
  }

  getBookingImage(data: any) {
    //if (data.length === 1) {
    const ret = this.sports.find((s) => s.id === data.course.sport_id);
    return ret ? ret.name.toLowerCase() : '';
    /* } else {
       return 'MULTIPLE';
     }*/
  }

  getClient(id: number) {
    if (id && id !== null) {

      const client = this.clients.find((m) => m.id === id);

      return client;
    }
  }

  getMonitors() {
    this.crudService.list('/monitors', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id)
      .subscribe((monitor) => {
        this.monitors = monitor.data;
      })
  }

  getClients() {
    this.crudService.list('/clients', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id)
      .subscribe((data: any) => {
        this.clients = data.data;

      })
  }


  getSports() {
    this.crudService.list('/school-sports', 1, 10000, 'desc', 'id',
      '&school_id=' + this.user.schools[0].id, '', null, null, ['sport'])
      .subscribe((data) => {
        this.sports = data.data.map(item => item.sport);
        this.sportsControl.patchValue(this.sports);

        this.filteredSports = this.sportsControl.valueChanges.pipe(
          startWith(''),
          map((sport: string | null) => sport ? this._filterSports(sport) : this.sports.slice())
        );

      })
  }

  getCountry(id: any) {
    const country = this.countries.find((c) => c.id == +id);
    return country ? country.name : 'NDF';
  }

  getProvince(id: any) {
    const province = this.provinces.find((c) => c.id == +id);
    return province ? province.name : 'NDF';
  }

  getLanguage(id: any) {
    const lang = this.languages.find((c) => c.id == +id);
    return lang ? lang.code.toUpperCase() : 'NDF';
  }

  getSelectedSportsNames(): string {
    return this.sportsControl.value?.map(sport => sport.name).join(', ') || '';
  }

  private _filterSports(value: any): any[] {
    const filterValue = typeof value === 'string' ? value?.toLowerCase() : value?.name.toLowerCase();
    return this.sports.filter(sport => sport?.name.toLowerCase().indexOf(filterValue) === 0);
  }

  toggleSelection(sport: any): void {
    const index = this.selectedSports.findIndex(s => s.sport_id === sport.sport_id);
    if (index >= 0) {
      this.selectedSports.splice(index, 1);
    } else {
      this.selectedSports.push(sport);
    }

    // Crear una nueva referencia para el array
    this.selectedSports = [...this.selectedSports];

    // Detectar cambios manualmente para asegurarse de que Angular reconozca los cambios
    this.cdr.detectChanges();
  }


  getPaymentMethod(id: number): string {
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

  calculateFormattedDuration(hourStart: string, hourEnd: string): string {
    const start = moment(hourStart.replace(': 00', ''), "HH:mm");
    const end = moment(hourEnd.replace(': 00', ''), "HH:mm");
    const duration = moment.duration(end.diff(start));
    let formattedDuration = "";
    if (duration.hours() > 0) formattedDuration += duration.hours() + "h ";
    if (duration.minutes() > 0) formattedDuration += duration.minutes() + "m";
    return formattedDuration.trim();
  }

  countActives = (dates: any): number => dates.filter((objeto: any) => objeto.active === 1 || objeto.active === true).length;

  findFirstActive(dates: any[]) {
    if (dates.length > 0) {
      let min = dates.find((objeto: any) => objeto.active === 1 || objeto.active === true);
      let max = dates.slice().reverse().find((objeto: any) => objeto.active === 1 || objeto.active === true);

      return {
        min: min ? min.date : null,
        max: max ? max.date : null
      };
    } else {
      return { min: null, max: null };
    }
  }

  /* EXPORT QR */

  hexToRgb(hex: string) {
    const rgb = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return rgb ? {
      r: parseInt(rgb[1], 16),
      g: parseInt(rgb[2], 16),
      b: parseInt(rgb[3], 16)
    } : null;
  }

  exportQR(id: any) {
    this.crudService.get('/admin/clients/course/' + id)
      .subscribe(async (data) => {
        const clientsData = data.data;

        if (clientsData && clientsData.length) {
          const doc = new jsPDF();
          const pageWidth = doc.internal.pageSize.getWidth();
          const colWidth = pageWidth / 2;
          const lineHeight = 6;
          const qrSize = 48;
          let y = 10;

          for (let i = 0; i < clientsData.length; i++) {
            const client = clientsData[i];
            const isLeftColumn = i % 2 === 0;
            const baseX = isLeftColumn ? 10 : colWidth + 6;
            const qrX = baseX + 48;
            let y_text = y;
            const maxWidthText = 48;

            doc.setTextColor(70, 70, 70);
            doc.setFontSize(16);
            doc.setFont('helvetica', 'bold');
            let lines = doc.splitTextToSize(`${client.client?.first_name} ${client.client?.last_name}`, maxWidthText);
            doc.text(lines, baseX, y_text);
            y_text += (lines.length + 0.4) * lineHeight;

            if (client.client?.phone || client.client?.telephone) {
              let clientPhone = '';
              if (client.client?.phone) { clientPhone = client.client.phone; }
              else { clientPhone = client.client.telephone; }
              doc.setFontSize(14);
              doc.setFont('helvetica', 'normal');
              lines = doc.splitTextToSize(`${clientPhone}`, maxWidthText);
              doc.text(lines, baseX, y_text);
              y_text += lines.length * lineHeight;
            }

            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            lines = doc.splitTextToSize(`${client.course?.name}`, maxWidthText);
            doc.text(lines, baseX, y_text);
            y_text += (lines.length * lineHeight) - 2;

            if (client.monitor) {
              doc.setFontSize(8);
              lines = doc.splitTextToSize(`Professeur - niveau`, maxWidthText);
              doc.text(lines, baseX, y_text);
              y_text += (lines.length * lineHeight) - 2;
              doc.setFontSize(11);
              doc.setFont('helvetica', 'bold');
              lines = doc.splitTextToSize(`${client.monitor?.first_name} ${client.monitor?.last_name}`, maxWidthText);
              doc.text(lines, baseX, y_text);
              y_text += (lines.length * lineHeight) + 3;
            }
            else {
              y_text += 6;
            }

            if (client.degree) {
              const rgbColor = this.hexToRgb(client.degree.color);
              doc.setFillColor(rgbColor.r, rgbColor.g, rgbColor.b);
              doc.setTextColor(255, 255, 255);
              doc.setFontSize(9);
              doc.setFont('helvetica', 'normal');

              const text = `${client.degree?.annotation} - ${client.degree?.name}`;
              lines = doc.splitTextToSize(text, maxWidthText);
              const textBoxHeight = (lines.length + 0.5) * lineHeight;

              doc.rect(baseX, y_text - lineHeight, maxWidthText, textBoxHeight, 'F');

              doc.text(lines, baseX + 1.5, y_text);
              doc.setTextColor(70, 70, 70);
              y_text += textBoxHeight;
            }

            // Generate QR code
            const qrData = await QRCode.toDataURL(client.client.id.toString());
            doc.addImage(qrData, 'JPEG', qrX, y - 10, qrSize, qrSize);

            //Next row if not left and not last
            if (!isLeftColumn || i === clientsData.length - 1) {
              y += qrSize + lineHeight * 4;
            }

            if (y >= doc.internal.pageSize.getHeight() - 20) {
              doc.addPage();
              y = 10;
            }
          }

          doc.save('clients.pdf');
        }
        //No clients
        else {
          this.snackbar.open(this.translateService.instant('course_without_clients'), 'OK', { duration: 3000 });
        }

      })
  }

  getActiveSchool(row: any): boolean {
    const school = row.find((s: any) => s.school_id === this.schoolId);
    return school?.active_school;
  }

  /* END EXPORT QR */
  @Output() searchChange = new EventEmitter<unknown>();

  encontrarPrimeraCombinacionConValores(data: any) {
    if (data) {
      for (const intervalo of data) {
        if (Object.keys(intervalo).some(key => key !== 'intervalo' && intervalo[key] !== null)) {
          return intervalo;
        }
      }
    } return null
  }

  encontrarPrimeraClaveConValor(obj: any): any {
    if (obj) {
      for (const clave of Object.keys(obj)) {
        if (obj[clave] !== null && clave !== 'intervalo') {
          return obj[clave];
        }
      }
    }
    return null;
  }

  findHighestDegreeIdElement(data: any) {
    if (!data || data.length === 0) {
      return null;
    }

    let highestDegree = null;

    for (const item of data) {
      if (item.monitor_sport_authorized_degrees && item.monitor_sport_authorized_degrees.length > 0) {
        const highestInCurrent = item.monitor_sport_authorized_degrees.reduce((prev, current) =>
          (prev.degree.degree_order > current.degree.degree_order) ? prev : current
        );
        if (!highestDegree || highestInCurrent.degree.degree_order > highestDegree.degree.degree_order) {
          highestDegree = highestInCurrent;
        }
      }
    }

    if (highestDegree) {
      return this.allLevels.find((l) => l.id === highestDegree.degree_id);
    }

    return null;
  }


  getDegrees() {
    this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order', '&school_id=' + this.user.schools[0].id + '&active=1')
      .subscribe((data) => {
        this.allLevels = data.data;
      })
  }

  getTrad(data: any, name: any) {
    let dataJ = typeof data === 'string' ?
      JSON.parse(data) : data;

    return data !== null && dataJ[this.translateService.currentLang].name !== null && dataJ[this.translateService.currentLang].name !== '' ? dataJ[this.translateService.currentLang].name : name
  }

  getShortestDuration(times) {
    let shortest = null;

    times.forEach(time => {
      const start = moment(time.hour_start, "HH:mm:ss");
      const end = moment(time.hour_end, "HH:mm:ss");
      const duration = moment.duration(end.diff(start));

      if (shortest === null || duration < shortest) {
        shortest = duration;
      }
    });

    if (shortest !== null) {
      const hours = shortest.hours();
      const minutes = shortest.minutes();
      return `${hours > 0 ? hours + 'h ' : ''}${minutes > 0 ? minutes + 'min' : ''}`.trim();
    } else {
      return "No_durations_found";
    }
  }

  getSportName(id) {
    return this.sports.find((s) => s.id === id)
  }

  checkClientStatus(data: any) {
    let ret = false;
    if(!data) {
      return ret
    }
    data.forEach(element => {
      if (element.school_id === this.user.schools[0].id) {
        ret = element.accepted_at !== null;
      }
    });

    return ret;
  }


  copy(item: any) {
    if (this.entity.includes('course')) {
      this.copyCourse(item);
    }
  }

  copyCourse(item: any) {

    let data: any = {};

    if (item.course_type === 1 && item.is_flexible) {
      data = {
        course_type: item.course_type,
        is_flexible: item.is_flexible,
        name: this.translateService.instant('copy') + ' - ' + item.name,
        short_description: item.short_description,
        description: item.description,
        price: item.price,
        currency: item.currency,//poner currency de reglajes
        date_start: moment(item.date_start_res).format('YYYY-MM-DD'),
        date_end: moment(item.date_end_res).format('YYYY-MM-DD'),
        date_start_res: moment(item.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(item.date_end_res).format('YYYY-MM-DD'),
        confirm_attendance: false,
        active: item.active,
        online: item.online,
        options: item.options,
        image: null,
        translations: item.translations,
        discounts: item.discounts,
        sport_id: item.sport_id,
        school_id: this.user.schools[0].id, //sacar del global
        station_id: item.station_id,
        max_participants: item.max_participants,
        course_dates: item.course_dates,
        user_id: item.user_id
      }

      delete data.course_dates;
      data.course_dates = [];

      item.course_dates.forEach(element => {
        const currentDate = {
          active: element.active,
          groups: element.course_groups,
          date: element.date,
          hour_end: element.hour_end,
          hour_start: element.hour_start,
        };

        data.course_dates.push(currentDate);
      });


      data.course_dates.forEach((element, dateIdx) => {
        element.groups.forEach((group, idx) => {

          group.subgroups = item.course_dates[dateIdx].course_groups[idx].course_subgroups;
          delete group.course_subgroups;

        });
      });
    } else if (item.course_type === 1 && !item.is_flexible) {
      data = {
        course_type: item.course_type,
        is_flexible: item.is_flexible,
        name: this.translateService.instant('copy') + ' - ' + item.name,
        short_description: item.short_description,
        description: item.description,
        price: item.price,
        currency: item.currency,//poner currency de reglajes
        date_start: moment(item.date_start_res).format('YYYY-MM-DD'),
        date_end: moment(item.date_end_res).format('YYYY-MM-DD'),
        date_start_res: moment(item.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(item.date_end_res).format('YYYY-MM-DD'),
        confirm_attendance: false,
        active: item.active,
        online: item.online,
        options: item.options,
        image: null,
        translations: item.translations,
        sport_id: item.sport_id,
        school_id: this.user.schools[0].id, //sacar del global
        station_id: item.station_id,
        max_participants: item.max_participants,
        course_dates: item.course_dates,
        user_id: item.user_id
      }

      delete data.course_dates;
      data.course_dates = [];

      item.course_dates.forEach(element => {
        const currentDate = {
          active: element.active,
          groups: element.course_groups,
          date: element.date,
          hour_end: element.hour_end,
          hour_start: element.hour_start,
        };

        data.course_dates.push(currentDate);
      });


      data.course_dates.forEach((element, dateIdx) => {
        element.groups.forEach((group, idx) => {

          group.subgroups = item.course_dates[dateIdx].course_groups[idx].course_subgroups;
          delete group.course_subgroups;

        });
      });
    } else if (item.course_type === 2 && item.is_flexible) {
      data = {
        course_type: item.course_type,
        is_flexible: item.is_flexible,
        name: this.translateService.instant('copy') + ' - ' + item.name,
        short_description: item.short_description,
        description: item.description,
        price: 0,
        currency: item.currency,
        date_start: item.unique ? moment(item.date_start).format('YYYY-MM-DD') : moment(item.date_start_res).format('YYYY-MM-DD'),
        date_end: item.unique ? moment(item.date_end).format('YYYY-MM-DD') : moment(item.date_end_res).format('YYYY-MM-DD'),
        date_start_res: moment(item.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(item.date_end_res).format('YYYY-MM-DD'),
        active: item.active,
        online: item.online,
        options: item.options,
        image: null,
        confirm_attendance: false,
        translations: item.translations,
        discounts: item.discounts,
        price_range: item.price_range,
        sport_id: item.sport_id,
        school_id: item.school_id,
        station_id: item.station_id,
        max_participants: item.max_participants,
        duration: item.duration,
        age_min: item.age_min,
        age_max: item.age_max,
        course_dates: item.course_dates,
        settings: item.settings,
        unique: item.unique,
        hour_min: item.hour_min,
        hour_max: item.hour_max,
        user_id: item.user_id
      };
    } else if (item.course_type === 2 && !item.is_flexible) {

      data = {
        course_type: item.course_type,
        is_flexible: item.is_flexible,
        name: this.translateService.instant('copy') + ' - ' + item.name,
        short_description: item.short_description,
        description: item.description,
        price: item.price,
        currency: item.currency,
        date_start_res: moment(item.date_start_res).format('YYYY-MM-DD'),
        date_end_res: moment(item.date_end_res).format('YYYY-MM-DD'),
        date_start: moment(item.date_start_res).format('YYYY-MM-DD'),
        date_end: moment(item.date_end_res).format('YYYY-MM-DD'),
        active: item.active,
        online: item.online,
        options: item.options,
        image: null,
        confirm_attendance: false,
        translations: item.translations,
        price_range: null,
        sport_id: item.sport_id,
        school_id: item.school_id,
        station_id: item.station_id,
        max_participants: item.max_participants,
        duration: item.duration,
        age_min: item.age_min,
        age_max: item.age_max,
        course_dates: item.course_dates,
        hour_min: item.hour_min,
        hour_max: item.hour_max,
        settings: item.settings,
        user_id: item.user_id
      };
    }

    data.school_id = this.user.schools[0].id;

    this.crudService.create('/admin/courses', data)
      .subscribe((res) => {
        this.getData(this.pageIndex, this.pageSize);
      })
  }
}
