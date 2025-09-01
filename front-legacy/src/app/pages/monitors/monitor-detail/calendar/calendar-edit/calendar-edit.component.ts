import { Component, Inject, OnInit } from '@angular/core';
import { FormControl, FormsModule, ReactiveFormsModule, UntypedFormBuilder, UntypedFormGroup } from '@angular/forms';
import {
  MAT_DIALOG_DATA,
  MatDialog,
  MatDialogModule,
  MatDialogRef
} from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatInputModule } from '@angular/material/input';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatTabsModule } from '@angular/material/tabs';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatSelectModule } from '@angular/material/select';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { ApiCrudService } from 'src/service/crud.service';
import { CommonModule } from '@angular/common';
import { Observable, map, startWith } from 'rxjs';
import * as moment from 'moment';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { DateAdapter } from '@angular/material/core';
import {
  BookingsCreateUpdateV2Component
} from '../../../../bookings-v2/bookings-create-update/bookings-create-update.component';

@Component({
  selector: 'vex-calendar-edit',
  templateUrl: './calendar-edit.component.html',
  styleUrls: ['./calendar-edit.component.scss'],
  standalone: true,
  imports: [
    MatDialogModule,
    FormsModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatDatepickerModule,
    MatButtonModule,
    MatTabsModule,
    MatAutocompleteModule,
    MatSelectModule,
    MatCheckboxModule,
    CommonModule,
    TranslateModule
  ]
})
export class CalendarEditComponent implements OnInit {
  selectedBlockage: any = null;
  myControlStations = new FormControl();
  filteredStations: Observable<any[]>;
  blockageSelected = null;
  selectedIndex = 0;
  times: string[] = this.generateTimes();
  type = 0;
  form: UntypedFormGroup;

  defaults = {
    start_date: null,
    end_date: null,
    start_time: null,
    end_time: null,
    monitor_id: null,
    school_id: null,
    station_id: null,
    full_day: false,
    range_dates: false,
    description: null,
    color: null,
    price:null,
    user_nwd_subtype_id: null,
    default: false
  };
  filteredTimes: any = [];
  blockages: any = [];
  stations: any = [];
  user: any;
  loading = true;
  constructor(
    private dialogRef: MatDialogRef<CalendarEditComponent>,
    @Inject(MAT_DIALOG_DATA) public event: any,
    private fb: UntypedFormBuilder, private crudService: ApiCrudService, private dialog: MatDialog,
    private translateService: TranslateService,
    private dateAdapter: DateAdapter<Date>
  ) {
    this.dateAdapter.setLocale(this.translateService.getDefaultLang());
    this.dateAdapter.getFirstDayOfWeek = () => { return 1; }
  }

  ngOnInit() {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.dateAdapter.setLocale(this.translateService.getDefaultLang());
    this.dateAdapter.getFirstDayOfWeek = () => { return 1; }
    this.defaults.start_time = this.event.hour_start;
    this.defaults.start_date = moment(this.event.date_param, 'YYYY-MM-DD').toDate();

    this.form = this.fb.group({
      startAvailable: this.defaults.start_date,
      endAvailable: null,
      startNonAvailable: this.defaults.start_date,
      endNonAvailable: null,
      startPayedBlock: this.defaults.start_date,
      endPayedBlock: null,
      startNonPayedBlock: this.defaults.start_date,
      endNonPayedBlock: null,
      description: null,
      station: null,
      blockage: null,
      full_day: false,
      range_dates: false
    });



    this.getStations();
    this.getBlockages();

  }

  onStartTimeChange() {
    const index = this.times.indexOf(this.defaults.start_time);
    if (index !== -1 && index < this.times.length - 1) {
      this.filteredTimes = this.times.slice(index + 1);
    } else {
      this.filteredTimes = [];
    }
  }

  save() {
    if (this.type === 0 || this.type === 2) {
      this.defaults.color = '#89add1';
    } else {
      this.defaults.color = this.blockageSelected.color;
    }

    this.defaults.user_nwd_subtype_id = this.type + 1;
    this.defaults.default = false;
    this.defaults.school_id = this.user.schools[0].id;
    this.defaults.start_date = moment(this.defaults.start_date).format('YYYY-MM-DD');
    if (this.defaults.end_date && moment(this.defaults.end_date).isAfter(this.defaults.start_date)) {
      this.defaults.end_date = moment(this.defaults.end_date).format('YYYY-MM-DD');
    } else {
      this.defaults.end_date = this.defaults.start_date;
    }
    if (this.form.get('full_day').value) {
      this.defaults.full_day = this.form.get('full_day').value;
    }
    else {
      this.defaults.full_day = false;
    }
    if (this.form.get('range_dates').value) {
      this.defaults.range_dates = this.form.get('range_dates').value;
    }
    else {
      this.defaults.range_dates = false;
    }

    this.dialogRef.close({
      ...this.event,
      ...this.defaults
    });
  }

  private _filterStations(value: string): string[] {
    const filterValue = value.toLowerCase();
    return this.stations.filter(option => option.name.toLowerCase().includes(filterValue));
  }


  generateTimes(): string[] {
    let times = [];
    let dt = new Date(2023, 0, 1, 8, 0, 0, 0);
    const end = new Date(2023, 0, 1, 17, 55, 0, 0);

    while (dt <= end) {
      const time = ('0' + dt.getHours()).slice(-2) + ':' + ('0' + dt.getMinutes()).slice(-2);
      times.push(time);
      dt.setMinutes(dt.getMinutes() + 5); // Incrementa en 5 minutos
    }
    return times;
  }


  getBlockages() {
    this.crudService.list('/school-colors', 1, 10000, 'desc', 'id', '&default=1&school_id=' + this.user.schools[0].id)
      .subscribe((data) => {
        this.blockages = data.data;

        if (this.event && this.event.start) {
          this.defaults.start_date = this.event.start;
          this.defaults.end_date = this.event.end;
          this.defaults.start_time = this.event.start_time.substring(0, this.event.start_time.length - 3);;
          this.defaults.end_time = this.event.end_time.substring(0, this.event.end_time.length - 3);;
          this.defaults.color = this.event.color;
          this.defaults.full_day = this.event.allDay;
          this.defaults.user_nwd_subtype_id = this.event.user_nwd_subtype_id;
          this.selectedIndex = this.event.user_nwd_subtype_id;
          this.type = this.event.user_nwd_subtype_id;

          this.blockageSelected = this.blockages.find((b) => b.color === this.event.color);
          this.onStartTimeChange();
        }
        this.onStartTimeChange();
        this.loading = false;
      })
  }

  getStations() {
    this.crudService.list('/stations-schools', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id)
      .subscribe((station) => {
        station.data.forEach(element => {
          this.crudService.get('/stations/' + element.station_id)
            .subscribe((data) => {
              this.stations.push(data.data);
              this.filteredStations = this.myControlStations.valueChanges
                .pipe(
                  startWith(''),
                  map(value => this._filterStations(value))
                );

              this.myControlStations.valueChanges.subscribe(value => {
                this.form.get('station').setValue(value);
              });
            })
        });
      })
  }

  onBlockageChange(selected: any) {
    if (selected) {
      this.defaults.color = selected.color;
      this.defaults.description = selected.name;

      // Si tiene un precio, lo asignamos
      if (selected.price) {
        this.defaults.price = selected.price;
      }
    }
  }

  onTabChanged(event: any) {

    this.form.reset();
    this.type = event.index;

    if (event.index === 3) {
      this.openCreateBooking();
    } else {
      if (this.event && this.event.date_param) {
        this.defaults.start_date = moment(this.event.date_param, 'YYYY-MM-DD').toDate();
        //this.defaults.end_date = this.event.end;
        this.defaults.start_time = this.event.hour_start;
        //this.defaults.end_time = this.event.end_time.substring(0, this.event.end_time.length-3);;
        this.defaults.color = this.event.color;
        this.defaults.full_day = this.event.allDay;
        this.defaults.default = false;
        this.defaults.user_nwd_subtype_id = this.event.user_nwd_subtype_id;
        this.blockageSelected = this.blockages.find((b) => b.color === this.event.color);

        this.onStartTimeChange();
      } else {
        this.defaults = {
          start_date: null,
          end_date: null,
          start_time: null,
          end_time: null,
          monitor_id: null,
          school_id: null,
          station_id: null,
          full_day: false,
          range_dates: false,
          description: null,
          price: null,
          color: null,
          default: false,
          user_nwd_subtype_id: this.type,
        };
      }
    }


  }

  calculateIfCanNewBooking() {
    // Obtener la fecha y hora actuales
    const now = moment();

    // Convertir la fecha del curso y la hora de inicio/fin a objetos moment
    const courseDate = moment(this.event.date_param, 'YYYY-MM-DD').format('YYYY-MM-DD')
    const start = moment(this.event.hour_start, 'HH:mm:ss');

    // Primero, comprueba si es el mismo día
    if (!now.isSame(courseDate, 'day')) {
      return true; // Si no es el mismo día, no es necesario comprobar la hora
    }

    // Si es el mismo día, comprueba si la hora actual es después de la hora proporcionada
    // y si la hora proporcionada está entre la hora de inicio y fin del curso
    return now.isBefore(start);
  }

  openCreateBooking() {
    const dialogRef = this.dialog.open(BookingsCreateUpdateV2Component, {
      width: '50%',
      height: '1200px',
      maxWidth: '90vw',
      data: {
        onlyPrivate: true,
        monitorId: this.event.monitor_id,
        monitor: this.event.monitor,
        hour: this.event.hour_start,
        date: moment(this.event.date_param, 'YYYY-MM-DD').format('YYYY-MM-DD')
      }
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
        this.dialogRef.close();
      } else {
        this.selectedIndex = 0;
      }

      this.dialogRef.close();
    });
  }
}
