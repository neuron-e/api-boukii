import { ChangeDetectorRef, Component, Inject, OnInit } from '@angular/core';
import { FormControl, UntypedFormBuilder, UntypedFormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import moment from 'moment';
import { Observable, map, startWith } from 'rxjs';
import { fadeInUp400ms } from 'src/@vex/animations/fade-in-up.animation';
import { stagger20ms } from 'src/@vex/animations/stagger.animation';
import { ApiCrudService } from 'src/service/crud.service';
import { CourseUserTransferComponent } from '../course-user-transfer/course-user-transfer.component';
import { MAT_DIALOG_DATA, MatDialog } from '@angular/material/dialog';
import { MOCK_COUNTRIES } from 'src/app/static-data/countries-data';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ConfirmModalComponent } from '../../monitors/monitor-detail/confirm-dialog/confirm-dialog.component';
import { TranslateService } from '@ngx-translate/core';
import { CoursesCreateUpdateComponent } from '../courses-create-update/courses-create-update.component';
import { CoursesCreateUpdateModalComponent } from '../courses-create-update-modal/courses-create-update-modal.component';
import { TableColumn } from 'src/@vex/interfaces/table-column.interface';

@Component({
  selector: 'vex-course-detail-modal',
  templateUrl: './course-detail-modal.component.html',
  styleUrls: ['./course-detail-modal.component.scss'],
  animations: [fadeInUp400ms, stagger20ms]
})
export class CourseDetailModalComponent implements OnInit {
  userAvatar = '../../../../assets/img/avatar.png';

  mode: 'create' | 'update' = 'update';
  today = new Date();
  form: UntypedFormGroup;
  monitorsForm = new FormControl();

  filteredMonitors: Observable<any[]>;

  durations: string[] = [];

  groupedByColor = {};
  colorKeys: string[] = []; // Aquí almacenaremos las claves de colores

  user: any;
  id: any;

  defaults: any = {
    course_type: null,
    is_flexible: false,
    name: null,
    short_description: null,
    description: null,
    price: null,
    currency: '',
    date_start: null,
    date_end: null,
    date_start_res: null,
    date_end_res: null,
    confirm_attendance: false,
    active: true,
    online: true,
    image: null,
    translations: null,
    price_range: null,
    discounts: null,
    settings: {
      weekDays: {
        monday: false,
        tuesday: false,
        wednesday: false,
        thursday: false,
        friday: false,
        saturday: false,
        sunday: false
      }
    },
    sport_id: null,
    school_id: null,
    station_id: null,
    max_participants: null,
    duration: null,
    hour_min: null,
    hour_max: null,
    age_min: null,
    age_max: null,
    course_dates: []
  };

  defaults_course_date = {
    date: null,
    hour_start: null,
    hour_end: null,
  }

  defaults_groups = {
    course_id: null,
    course_date_id: null,
    degree_id: null,
    age_min: null,
    age_max: null,
    recommended_age: null,
    teachers_min: null,
    teachers_max: null,
    observations: null,
    auto: null
  }

  defaults_subgroups = {
    course_id: null,
    course_date_id: null,
    degree_id: null,
    course_group_id: null,
    monitor_id: null,
    max_participants:null,
  }

  loading: boolean = true;

  //NIVELES
  daySelectedIndex: any = 0;
  subGroupSelectedItemDate: any;
  subGroupSelectedIndex: any = 0;
  selectedDate: string;
  selectedItem: any;
  daysDates = [];
  daysDatesLevels = [];
  monitors = [];
  loadingMonitors = true;
  levels = [];
  courseUsers = [];
  clients = [];
  schoolSports: any = [];
  languages: any = [];
  countries = MOCK_COUNTRIES;
  rangeForm: UntypedFormGroup;
  showDetail: boolean = false;
  detailData: any;

  entity = '/booking-users';
  columns: TableColumn<any>[] = [
    { label: 'Id', property: 'id', type: 'text', visible: true, cssClasses: ['font-medium'] },
    { label: 'type', property: 'booking', type: 'booking_users_image_monitors', visible: true },
    { label: 'course', property: 'course', type: 'course_type_data', visible: true},
    { label: 'client', property: 'client_id', type: 'client', visible: true },
    { label: 'register', property: 'created_at', type: 'date', visible: true },
    //{ label: 'Options', property: 'options', type: 'text', visible: true },
    { label: 'bonus', property: 'bonus', type: 'light', visible: true },
    //{ label: 'OP. Rem', property: 'has_cancellation_insurance', type: 'light_data', visible: true },
    //{ label: 'B. Care', property: 'has_boukii_care', type: 'light_data', visible: true },
    { label: 'price', property: 'price', type: 'price', visible: true },
    //{ label: 'M. Paiment', property: 'payment_method', type: 'text', visible: true },
    //{ label: 'Status', property: 'paid', type: 'payment_status_data', visible: true },
    //{ label: 'Status 2', property: 'cancelation', type: 'cancelation_status', visible: true },
    { label: 'Actions', property: 'actions', type: 'button', visible: true }
  ];
  constructor(private fb: UntypedFormBuilder, private crudService: ApiCrudService, private activatedRoute: ActivatedRoute, private router: Router, private dialog: MatDialog,
    private snackbar: MatSnackBar, private translateService: TranslateService, @Inject(MAT_DIALOG_DATA) public externalData: any) {

    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.id = this.externalData.id;

    this.generateDurations();
    this.rangeForm = this.fb.group({
      minAge: ['', [Validators.required, Validators.min(3)]],
      maxAge: ['', [Validators.required, Validators.max(80)]]
    }, { validator: this.ageRangeValidator });
  }

  ageRangeValidator(group: UntypedFormGroup): { [key: string]: any } | null {
    const minAge = group.get('minAge').value;
    const maxAge = group.get('maxAge').value;
    return minAge && maxAge && minAge < maxAge ? null : { 'ageRange': true };
  }

  get minAge() {
    return this.rangeForm.get('minAge');
  }

  get maxAge() {
    return this.rangeForm.get('maxAge');
  }

  ngOnInit() {
    this.getData();
  }

  getData() {
    this.reset();
    this.getLanguages();
    this.getClients();
    this.getMonitors();

    this.crudService.get('/admin/courses/'+this.id)
      .subscribe((data) => {
        this.defaults = data.data;
        this.getStations();

        this.getSeparatedDates(this.defaults.course_dates, true);

        this.crudService.list('/booking-users', 1, 10000, 'desc', 'id', '&course_id='+this.defaults.id)
            .subscribe((result) => {
              this.courseUsers = result.data;
              this.loading = false;

            })

      })
  }

  getStations() {
    this.crudService.list('/stations', 1, 10000,  'desc', 'id', '&school_id=' + this.user.schools[0].id)
      .subscribe((st) => {
        st.data.forEach(element => {
          if (element.id === this.defaults.station_id) {
            this.defaults.station = element;
          }
        });
      })
  }

  generateDurations() {
    let minutes = 15;
    const maxMinutes = 7 * 60; // 7 horas en minutos

    while (minutes <= maxMinutes) {
      const hours = Math.floor(minutes / 60);
      const remainingMinutes = minutes % 60;

      const durationString = `${hours ? hours + 'h ' : ''}${remainingMinutes}min`;
      this.durations.push(durationString);

      minutes += 15;
    }
  }

  displayFnMoniteurs(monitor: any): string {
    return monitor && monitor.first_name && monitor.last_name ? monitor.first_name + ' ' + monitor.last_name : '';
  }

  reset() {
    this.filteredMonitors;
    this.durations = [];

    this.groupedByColor = {};
    this.colorKeys = []; // Aquí almacenaremos las claves de colores

    this.daySelectedIndex = 0;
    this.subGroupSelectedItemDate;
    this.subGroupSelectedIndex = 0;
    this.selectedDate;
    this.selectedItem;
    this.daysDates = [];
    this.daysDatesLevels = [];
    this.monitors = [];
    this.levels = [];
    this.courseUsers = [];
    this.clients = [];
    this.languages = [];
    this.schoolSports = [];
  }

  openUpdateCourse() {
    const dialogRef = this.dialog.open(CoursesCreateUpdateModalComponent, {
      width: '100%',
      height: '1200px',
      maxWidth: '90vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales
      data: {id: this.id}
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {

      }
    });
  }

  goTo(route: string) {
    this.router.navigate([route]);
  }

  openUserTransfer(group, subgroup, subgroupNumber) {
    const dialogRef = this.dialog.open(CourseUserTransferComponent, {
      width: '800px',
      height: '800px',
      maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
      panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales
      data: {group: group, subgroup: subgroup, colorKeys: this.colorKeys, groupedByColor: this.groupedByColor, id: this.id, subgroupNumber: subgroupNumber, currentDate: this.subGroupSelectedItemDate}
    });

    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {

      }
      this.reset();
      this.getData();
    });
  }

  deleteSubGroup(subgroup) {
    if (subgroup.booking_users.length === 0) {
      const dialogRef = this.dialog.open(ConfirmModalComponent, {
        maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
        panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales,
        data: {message: 'Do you want to remove this subgroup? This action will be permanetly', title: 'Delete subgroup'}
      });

      dialogRef.afterClosed().subscribe((data: any) => {
        if (data) {
          this.crudService.delete('/course-subgroups', subgroup.id)
            .subscribe(() => {
              this.snackbar.open(this.translateService.instant('snackbar.course.deleted_group'), 'OK', {duration: 3000});
              this.getData();
            })
        }
      });
    } else {
      this.snackbar.open(this.translateService.instant('snackbar.course.subgroup_with_bookings'), 'OK', {duration: 3000})
    }

  }


  //NIVELES

  getDegrees() {
    this.groupedByColor = {};
    this.colorKeys= [];
    this.crudService.list('/degrees', 1, 10000,'asc', 'degree_order', '&school_id=' + this.user.schools[0].id + '&sport_id='+ this.defaults.sport_id)
      .subscribe((data) => {

        if (this.defaults.course_type === 1) {
          data.data.forEach(element => {
            if(element.active) {
              this.levels.push(element);

            }
          });

          this.levels.forEach(level => {
            if (!this.groupedByColor[level.color]) {
              this.groupedByColor[level.color] = [];
            }
            level.active = false;


            this.defaults.course_dates.forEach(cs => {
              cs.course_groups.forEach(group => {
                if (group.degree_id === level.id) {
                  level.active = true;
                  level.old = true;
                }
                level.visible = false;
              });
            });
            this.selectedItem = this.daysDatesLevels[0].dateString;
            this.subGroupSelectedItemDate = moment(this.daysDatesLevels[0].date);
            this.groupedByColor[level.color].push(level);
          });

          this.colorKeys = Object.keys(this.groupedByColor);
        }

      })
  }

  generateGroups(level: any) {
    let ret = {};
    this.levels.forEach(element => {
      if (element.id === level.id){
        ret = {
          course_id: null,
          course_date_id: null,
          degree_id: element.id,
          age_min: null,
          age_max: null,
          recommended_age: null,
          teachers_min: null,
          teachers_max: null,
          observations: null,
          auto: null,
          subgroups: []
        }
      }

    });

    return ret;
  }

  hasMonitorAssigned(date, level, gIndex) {

    const courseDate = this.defaults.course_dates.find((c) => moment(c.date, 'YYYY-MM-DD').format('YYYY-MM-DD') === date.date);
    const group = courseDate.course_groups.find((c) => c.course_date_id === courseDate.id && c.degree_id === level.id);

    if (group) {
      const find = group.course_subgroups[gIndex]?.course_date_id === courseDate.id ? group.course_subgroups[gIndex] : null;
      return find && find?.monitor_id !== null;

    }
  }

  activeGroup(event: any, level: any) {

    if(event.source.checked) {
      this.selectedItem = this.daysDatesLevels[0].dateString;
      this.selectedDate = this.defaults.course_dates[0]?.date;
      level.active = event.source.checked;
      level.visible = event.source.checked;

      this.defaults.course_dates.forEach(element => {
        element.course_groups.push(this.generateGroups(level));
      });

      this.defaults.course_dates.forEach(element => {
        let prevGroup: any = {};
        element.course_groups.forEach(group => {
          if (group.degree_id === level.id) {
            group.active = event.source.checked;
            group.degree_id = level.id;
            group.teachers_min = level.id;
            group.course_date_id = prevGroup.course_date_id;
            group.course_id = prevGroup.course_id;
            group.age_min = level.age_min;
            group.age_max = level.age_max;
            group.course_subgroups.push({
              degree_id: level.id,
              monitor_id: null,
              max_participants: this.defaults.max_participants
            })
          } else {
            prevGroup = {
              course_date_id: group.course_date_id,
              course_id: group.course_id,
            }
          }

        });
      });
    } else {
      // eliminar el curso o desactivarlo

      let hasBookings = false;
      const groupsToDelete = [];
      this.defaults.course_dates.forEach(element => {
        element.course_groups.forEach(group => {
          if (group.degree_id === level.id) {
            groupsToDelete.push(group.id)
          }
        });
      });

      groupsToDelete.forEach(element => {
        if (!hasBookings) {
          this.defaults.course_dates.forEach(cs => {
            cs.course_groups.forEach(gs => {
              if (gs.degree_id === level.id) {

                if (groupsToDelete.find((g) => g === gs.id)) {
                  gs.course_subgroups.forEach(sgs => {
                    if (sgs.booking_users.length > 0) {
                      hasBookings = true;
                    }
                  });
                }
              }
            });
          });
        }

      });

      if (!hasBookings) {
        const dialogRef = this.dialog.open(ConfirmModalComponent, {
          maxWidth: '100vw',  // Asegurarse de que no haya un ancho máximo
          panelClass: 'full-screen-dialog',  // Si necesitas estilos adicionales,
          data: {message: 'Do you want to remove this group? This action will be permanetly', title: 'Delete group'}
        });

        dialogRef.afterClosed().subscribe((data: any) => {
          if (data) {
            groupsToDelete.forEach(element => {
              this.crudService.delete('/course-groups', element)
                .subscribe(() => {
                  this.snackbar.open(this.translateService.instant('snackbar.course.deleted_group'), 'OK', {duration: 3000});
                })
            });

            setTimeout(() => {
              this.getData();
            }, 500);
          }
        });
      } else {
        this.snackbar.open(this.translateService.instant('snackbar.course.subgroup_with_bookings'), 'OK', {duration: 3000})
      }
    }

  }

  checkIfExistInDate(daySelectedIndex, monitor, level) {

    let blocked = false;
    this.defaults.course_dates[daySelectedIndex].course_groups.forEach(gs => {
      if (!blocked) {
        gs.course_subgroups.forEach(sbs => {
          if (sbs.monitor_id === monitor.id) {
            blocked = true;
          }
        });
      }

    });

    return blocked;
  }

  disableActive(level: any) {
    let hasBookings = false;
      const groupsToDelete = [];
      this.defaults.course_dates.forEach(element => {
        element.course_groups.forEach(group => {
          if (group.degree_id === level.id) {
            groupsToDelete.push(group.id)
          }
        });
      });

      groupsToDelete.forEach(element => {
        if (!hasBookings) {
          this.defaults.course_dates.forEach(cs => {
            cs.course_groups.forEach(gs => {
              if (gs.degree_id === level.id) {

                if (groupsToDelete.find((g) => g === gs.id)) {
                  gs.course_subgroups.forEach(sgs => {
                    if (sgs.booking_users && sgs.booking_users.length > 0) {
                      hasBookings = true;
                    }
                  });
                }
              }
            });
          });
        }

      });

      return hasBookings;
  }

  addSubGroup(level: any) {
    this.defaults.course_dates.forEach(element => {
      element.course_groups.forEach(group => {
        if (level.id === group.degree_id) {
          const maxPax = element.course_subgroups.find((s) => s.degree_id === level.id);
          group.course_subgroups.push({
            degree_id: level.id,
            monitor_id: null,
            max_participants: maxPax ? maxPax.max_participants : null
          })
        }

      });
    });
  }

  readSubGroups(levelId: number) {

    let ret = [];
    this.defaults.course_dates[0].course_groups.forEach((group) => {
      if (group.degree_id === levelId) {
        ret = group.course_subgroups;
      }
    });

    return ret;
  }

  setLevelTeacher(event: any, level: any) {

    this.defaults.course_dates.forEach(element => {
      element.course_groups.forEach(group => {
        if (level.id === group.degree_id) {
          group.teachers_min = event.value.id;
        }

      });
    });
  }

  setMinAge(event: any, level: any) {
    if (+event.target.value > 3) {

      this.defaults.course_dates.forEach(element => {
        element.course_groups.forEach(group => {
          if (level.id === group.degree_id) {
            group.age_min = +event.target.value;
          }

        });
      });
    }
  }

  setMaxAge(event: any, level: any) {
    if (+event.target.value < 81) {
      this.defaults.course_dates.forEach(element => {
        element.course_groups.forEach(group => {
          if (level.id === group.degree_id) {
            group.age_max = +event.target.value;
          }

        });
      });
    }
  }

  getMonitorValue(level: any, subGroupIndex: number, daySelectedIndex: number) {

    let ret = '';
    if(!level.old) {
      this.defaults.course_dates.forEach(courseDate => {

          if (moment(courseDate.date,'YYYY-MM-DD').format('YYYY-MM-DD') === moment(this.selectedDate,'YYYY-MM-DD').format('YYYY-MM-DD')) {
            courseDate.course_groups.forEach(group => {
              if (group.degree_id === level.id) {
                  ret = group.course_subgroups[subGroupIndex]?.monitor;
              }
            });
          }
        });

        } else {
          this.defaults.course_dates[daySelectedIndex].course_groups.forEach(group => {
            if (group.degree_id === level.id) {
              ret = group.course_subgroups[subGroupIndex]?.monitor?.first_name + ' ' + group.course_subgroups[subGroupIndex]?.monitor?.last_name;
            }

          });
        }


      return ret === "undefined undefined" ? null : ret;
    }

    calculateMonitorLevel(level: any) {
      let ret = 0;
      this.defaults.course_dates.forEach(courseDate => {
        courseDate.course_groups.forEach(group => {
          if (level.id === group.degree_id) {
            ret = this.levels.find((l) => l.id === group.teachers_min);
          }
        });
      });
      return ret;
    }

    calculateSubGroupPaxes(level: any) {
      let ret = 0;

      this.defaults.course_dates.forEach(element => {
        element.course_groups.forEach(group => {
          if (level.id === group.degree_id) {
            group.course_subgroups.forEach(subgroup => {

              ret = ret + subgroup.max_participants;
            });
          }

        });
      });

      return ret;
    }

    setSubGroupMonitor(event: any, monitor: any, level: any, subGroupSelectedIndex: number, daySelectedIndex: number) {

      if (event.isUserInput) {
        if (daySelectedIndex === 0) {
          let monitorSet = false;

            if (!level.old) {
              this.defaults.course_dates.forEach(courseDate => {
                if (moment(courseDate.date).format('YYYY-MM-DD') === moment(this.selectedDate).format('YYYY-MM-DD')) {

                  this.crudService.post('/admin/monitors/available/'+monitor.id, {date: moment(courseDate.date,'YYYY-MM-DD'), hour_start: courseDate.hour_start, hour_end: courseDate.hour_end})
                    .subscribe((result: any) => {

                      if (result.data.available) {
                        courseDate.course_groups.forEach(group => {
                          if(group.degree_id === level.id && !monitorSet) {

                            group.course_subgroups[subGroupSelectedIndex].monitor_id = monitor.id;
                            group.course_subgroups[subGroupSelectedIndex].monitor = monitor.first_name + ' ' + monitor.last_name;
                            monitorSet = true;
                          }
                        });
                      }
                    })
                }
              });
            } else {
              this.defaults.course_dates.forEach((courseDate, idx) => {
                this.crudService.post('/admin/monitors/available/'+monitor.id, {date: moment(courseDate.date).format('YYYY-MM-DD'), hour_start: courseDate.hour_start, hour_end: courseDate.hour_end})
                  .subscribe((result: any) => {
                    if (result.data.available) {

                      this.defaults.course_dates[idx].course_groups.forEach(group => {
                        if (group.degree_id === level.id) {
                          group.course_subgroups[subGroupSelectedIndex].monitor = monitor;
                          group.course_subgroups[subGroupSelectedIndex].monitor_id = monitor.id;
                        }

                      });
                    }
                  })
              });
            }
        } else {
          let monitorSet = false;

            if (!level.old) {
              this.defaults.course_dates.forEach(courseDate => {
                if (moment(courseDate.date).format('YYYY-MM-DD') === moment(this.selectedDate).format('YYYY-MM-DD')) {
                  courseDate.course_groups.forEach(group => {
                    if(group.degree_id === level.id && !monitorSet) {

                      group.course_subgroups[subGroupSelectedIndex].monitor_id = monitor.id;
                      group.course_subgroups[subGroupSelectedIndex].monitor = monitor.first_name + ' ' + monitor.last_name;
                      monitorSet = true;
                    }
                  });
                }
              });
            } else {
              this.defaults.course_dates[daySelectedIndex].course_groups.forEach(group => {
                if (group.degree_id === level.id) {
                  group.course_subgroups[subGroupSelectedIndex].monitor = monitor;
                  group.course_subgroups[subGroupSelectedIndex].monitor_id = monitor.id;
                }

              });
            }
        }
      }
    }

  setSubGroupPax(event: any, level: any) {
    if (+event.target.value > this.defaults.max_participants) {
      this.snackbar.open(this.translateService.instant('snackbar.course.capacity'), 'OK', {duration: 3000});
    }

    level.max_participants = +event.target.value <= this.defaults.max_participants ? +event.target.value : this.defaults.max_participants;

    this.defaults.course_dates.forEach(element => {
      element.course_groups.forEach(group => {
        if (level.id === group.degree_id) {
          group.course_subgroups.forEach(subGroup => {
            subGroup.max_participants =level.max_participants;
          });
        }
      });
    });
  }

  selectItem(item: any, index: any, subGroupIndex: any, subgroup) {
    this.subGroupSelectedIndex = null;
    this.selectedItem = item.dateString;
    this.selectedDate = item.date;
    this.daySelectedIndex = index;
    this.subGroupSelectedIndex = subGroupIndex;
    this.subGroupSelectedItemDate = moment(item.date);
  }

  calculateAgeMin(level: any) {
    let ret = 0;
    this.defaults.course_dates.forEach(courseDate => {
      courseDate.course_groups.forEach(group => {
        if (level.id === group.degree_id) {
          ret = group.age_min;
        }
      });
    });

    return ret;
  }

  calculateAgeMax(level: any) {
    let ret = 0;
    this.defaults.course_dates.forEach(courseDate => {
      courseDate.course_groups.forEach(group => {
        if (level.id === group.degree_id) {
          ret = group.age_max;
        }
      });
    });

    return ret;
  }

  calculateMaxGroup(level: any) {
    let ret = 0;
    this.defaults.course_dates.forEach(courseDate => {
      courseDate.course_groups.forEach(group => {
        if (level.id === group.degree_id) {
          ret = group.course_subgroups[0]?.max_participants;
        }
      });
    });

    return ret;
  }

  calculateStudentsGroup(level: any) {
    let ret = 0;
    let group = null;
    this.defaults.course_dates.forEach(courseDate => {
      courseDate.course_groups.forEach(gr => {
        if (level.id === gr.degree_id) {
          group = gr;
        }
      });
    });

    if (group !== null) {
      this.courseUsers.forEach(courseUser => {
        if (courseUser.course_group_id === group.id) {
          ret = ret + 1;
        }
      });
    }


    return ret;
  }

  isInDay(date: any, courseUserId: any) {

    let ret = false;
    const course = this.defaults.course_dates.find((c) => moment(c.date).format('YYYY-MM-DD') === date);
    const courseUsers = this.courseUsers.filter((c) => c.client_id === courseUserId);
    if (course) {
      courseUsers.forEach(courseUser => {
        course.course_groups.forEach(group => {
          group.course_subgroups.forEach(element => {
            const exist = courseUser.course_date_id === element.course_date_id && courseUser.course_group_id === element.course_group_id && courseUser.course_subgroup_id === element.id

            if (exist) {
              ret = true;
            }
          });
        });
      });


    }

    return ret;
  }

  calculateFormattedDuration(hourStart: string, hourEnd: string): string {
    // Parsea las horas de inicio y fin
    let start = moment(hourStart, "HH:mm");
    let end = moment(hourEnd, "HH:mm");

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

  getClients() {
    this.crudService.list('/clients', 1, 100000, 'desc', 'id', '&school_id='+this.user.schools[0].id)
      .subscribe((data: any) => {
        this.clients = data.data;

      })
  }

  getClient(id: any) {
    if (id && id !== null) {
      return this.clients.find((c) => c.id === id);
    }
  }

  calculateAge(birthDateString) {
    if(birthDateString && birthDateString !== null) {
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

  getCountry(id: any) {
    const country = this.countries.find((c) => c.id == id);
    return country ? country.name : 'NDF';
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


  getSeparatedDates(dates: any, onLoad: boolean = false) {

    this.daysDates = [];
    this.daysDatesLevels = [];

    if (this.mode === 'create') {

      this.defaults.course_dates = [];
    }

    dates.forEach(element => {

      if (!onLoad) {
        const hour = element.hour;
        const duration = element.duration;
        const [hours, minutes] = duration.split(' ').reduce((acc, part) => {
          if (part.includes('h')) {
            acc[0] = parseInt(part, 10);
          } else if (part.includes('min')) {
            acc[1] = parseInt(part, 10);
          }
          return acc;
        }, [0, 0]);

        this.daysDatesLevels.push({date: moment(element.date, 'YYYY-MM-DD').format('YYYY-MM-DD'), dateString: moment(element.date, 'YYYY-MM-DD').locale('es').format('LLL').replace(' 0:00', ''), active: element.active});
        if (this.defaults.course_type === 2) {

          this.defaults.course_dates.push({
            date: moment(element.date, 'YYYY-MM-DD').format('YYYY-MM-DD'),
            hour_start: element.hour,
            hour_end: moment(hour, "HH:mm").add(hours, 'hours').add(minutes, 'minutes').format("HH:mm")
          })
        } else {

          this.defaults.course_dates.push({
            date: moment(element.date, 'YYYY-MM-DD').format('YYYY-MM-DD'),
            hour_start: element.hour,
            hour_end: moment(hour, "HH:mm").add(hours, 'hours').add(minutes, 'minutes').format("HH:mm"),
            groups: []
          })
        }
      } else {
        this.daysDatesLevels.push({date: moment(element.date, 'YYYY-MM-DD').format('YYYY-MM-DD'), dateString: moment(element.date, 'YYYY-MM-DD').locale('es').format('LLL').replace(' 0:00', ''), active: element.active});
      }

    });
    this.getDegrees();

  }

  getMonitors() {
    this.crudService.list('/monitors', 1, 10000, 'asc', 'first_name', '&school_id='+this.user.schools[0].id)
      .subscribe((data) => {
        this.monitors = data.data;

        this.filteredMonitors = this.monitorsForm.valueChanges.pipe(
          startWith(''),
          map((value: any) => typeof value === 'string' ? value : value?.full_name),
          map(full_name => full_name ? this._filterMonitor(full_name) : this.monitors.slice())
        );
      })
  }

  private _filterMonitor(name: string): any[] {
    const filterValue = name.toLowerCase();
    return this.monitors.filter(monitor => monitor.full_name.toLowerCase().includes(filterValue));
  }

  getCourseUsers(subGroup: any) {
    let ret = [];

    this.courseUsers.forEach(courseUser => {
      if(courseUser.course_group_id === subGroup.course_group_id && courseUser.course_subgroup_id === subGroup.id && courseUser.status === 1) {
        ret.push(courseUser);
      }
    });

    return ret;
  }

  save() {

    let data: any = [];

    if (this.defaults.course_type === 1) {
      this.defaults.course_dates.forEach(dates => {
        const group = [];
        dates.course_groups.forEach(dateGroup => {
          if (dateGroup.course_subgroups.length > 0) {
            group.push(dateGroup);
          }
        });
        dates.groups = group;
      });
    }

    if (this.defaults.course_type === 1 && this.defaults.is_flexible) {
      data = {
        course_type: this.defaults.course_type,
        is_flexible: this.defaults.is_flexible,
        name: this.defaults.name,
        short_description: this.defaults.short_description,
        description: this.defaults.description,
        price: this.defaults.price,
        currency: this.defaults.currency,//poner currency de reglajes
date_start: this.defaults.date_start_res
          ? this.formatDate(this.defaults.date_start_res)
          : '',
        date_end: this.defaults.date_end_res
          ? this.formatDate(this.defaults.date_end_res)
          : '',
        date_start_res: this.defaults.date_start_res
          ? this.formatDate(this.defaults.date_start_res)
          : '',
        date_end_res: this.defaults.date_end_res
          ? this.formatDate(this.defaults.date_end_res)
          : '',
        confirm_attendance: false,
        active: this.defaults.active,
        online: this.defaults.online,
        translations: null,
        sport_id: this.defaults.sport_id,
        school_id: this.defaults.school_id, //sacar del global
        station_id: this.defaults.station_id.id,
        max_participants: this.defaults.max_participants,
        course_dates: this.defaults.course_dates
      }
    } else if (this.defaults.course_type === 1 && !this.defaults.is_flexible) {
      data = {
        course_type: this.defaults.course_type,
        is_flexible: this.defaults.is_flexible,
        name: this.defaults.name,
        short_description: this.defaults.short_description,
        description: this.defaults.description,
        price: this.defaults.price,
        currency: this.defaults.currency,//poner currency de reglajes
date_start: this.defaults.date_start_res
          ? this.formatDate(this.defaults.date_start_res)
          : '',
        date_end: this.defaults.date_end_res
          ? this.formatDate(this.defaults.date_end_res)
          : '',
        date_start_res: this.defaults.date_start_res
          ? this.formatDate(this.defaults.date_start_res)
          : '',
        date_end_res: this.defaults.date_end_res
          ? this.formatDate(this.defaults.date_end_res)
          : '',
        confirm_attendance: false,
        active: this.defaults.active,
        online: this.defaults.online,
        translations: null,
        sport_id: this.defaults.sport_id,
        school_id: this.defaults.school_id, //sacar del global
        station_id: this.defaults.station_id.id,
        max_participants: this.defaults.max_participants,
        course_dates: this.defaults.course_dates
      }
    }
    this.crudService.update('/admin/courses', data, this.id)
      .subscribe((res) => {
        this.goTo('/courses');
      })
  }

  formatDate(dateString: string): string {
    const date = new Date(dateString); // Crea un objeto Date a partir del string
    const year = date.getFullYear(); // Obtiene el año
    const month = String(date.getMonth() + 1).padStart(2, '0'); // Obtiene el mes (0 indexado, por eso +1) y lo formatea con dos dígitos
    const day = String(date.getDate()).padStart(2, '0'); // Obtiene el día y lo formatea con dos dígitos
    return `${year}-${month}-${day}`; // Retorna en formato YYYY-MM-DD
  }

  checkAvailableMonitors(level: any) {
    this.loadingMonitors = true;
    let minDegree = 0;
    this.defaults.course_dates[this.daySelectedIndex].course_groups.forEach(element => {
      if (element.degree_id === level.id) {
        minDegree = element.teachers_min;
      }
    });
    const data = {
      sportId: this.defaults.sport_id,
      minimumDegreeId: minDegree,
      startTime: this.defaults.course_dates[this.daySelectedIndex].hour_start.replace(':00', ''),
      endTime: this.defaults.course_dates[this.daySelectedIndex].hour_end.replace(':00', ''),
      date: this.daysDatesLevels[this.daySelectedIndex].date
    };

    this.crudService.post('/admin/monitors/available', data)
      .subscribe((response) => {
        this.monitors = response.data;
        this.filteredMonitors = this.monitorsForm.valueChanges.pipe(
          startWith(''),
          map((value: any) => typeof value === 'string' ? value : value?.full_name),
          map(full_name => full_name ? this._filterMonitor(full_name) : this.monitors.slice())
        );
        this.loadingMonitors = false;
      })
  }

  getShotrDescription(course: any) {

    if (!course.translations || course.translations === null) {
      return course.short_description;
    } else {
      const translations = typeof course.translations === 'string' ?
        JSON.parse(course.translations) : course.translations;
      return translations[this.translateService.currentLang].short_description;
    }
  }

  getDescription(course: any) {

    if (!course.translations || course.translations === null) {
      return course.description;
    } else {
      const translations = typeof course.translations === 'string' ?
        JSON.parse(course.translations) : course.translations;
      return translations[this.translateService.currentLang].description;
    }
  }

  getCourseName(course: any) {
    if (!course.translations || course.translations === null) {
      return course.name;
    } else {
      const translations = typeof course.translations === 'string' ?
        JSON.parse(course.translations) : course.translations;
      return translations[this.translateService.currentLang].name;
    }
  }



  getDateIndex() {
    let ret = 0;
    if (this.detailData.course && this.detailData.course.course_dates) {
      this.detailData.course.course_dates.forEach((element, idx) => {
        if (moment(element.date).format('YYYY-MM-DD') === moment(this.detailData.date).format('YYYY-MM-DD')) {
          ret = idx +1;
        }
      });
    }

    return ret;
  }

  getGroupsQuantity() {
    let ret = 0;
    if (this.detailData.course && this.detailData.course.course_dates) {
      this.detailData.course.course_dates.forEach((element, idx) => {
        if (moment(element.date).format('YYYY-MM-DD') === moment(this.detailData.date).format('YYYY-MM-DD')) {
          ret = element.course_groups.length;
        }
      });
    }

    return ret;
  }


  getSubGroupsIndex() {
    let ret = 0;
    if (this.detailData.course && this.detailData.course.course_dates) {

      this.detailData.course.course_dates.forEach((element, idx) => {
        const group = element.course_groups.find((g) => g.id === this.detailData.course_group_id);

        if (group){
          group.course_subgroups.forEach((s, sindex) => {
            if (s.id === this.detailData.course_subgroup_id) {
              ret = sindex + 1;
            }
          });
        }
      });
    }
    return ret;
  }

  getHoursMinutes(hour_start:string, hour_end:string) {
    const parseTime = (time:string) => {
      const [hours, minutes] = time.split(':').map(Number);
      return { hours, minutes };
    };

    const startTime = parseTime(hour_start);
    const endTime = parseTime(hour_end);

    let durationHours = endTime.hours - startTime.hours;
    let durationMinutes = endTime.minutes - startTime.minutes;

    if (durationMinutes < 0) {
      durationHours--;
      durationMinutes += 60;
    }

    return `${durationHours}h${durationMinutes}m`;
  }

  getDateFormatLong(date:string) {
    return moment(date).format('dddd, D MMMM YYYY');
  }

  getHourRangeFormat(hour_start:string,hour_end:string) {
    return hour_start.substring(0, 5)+' - '+hour_end.substring(0, 5);
  }

  getAllLevelsBySport() {
    let ret = [];
    this.schoolSports.forEach(element => {
      if (element.sport_id === this.detailData.sport.id) {
        ret = element.degrees;
      }
    });

    return ret;
  }

  getClientDegree(sport_id:any,sports:any) {
    const sportObject = sports.find(sport => sport.sport_id === sport_id);
    if (sportObject) {
      return sportObject.degree_id;
    }
    else{
      return 0;
    }
  }

  getBirthYears(date:string) {
    const birthDate = moment(date);
    return moment().diff(birthDate, 'years');
  }

  getLanguageById(languageId: number): string {
    const language = this.languages.find(c => c.id === languageId);
    return language ? language.code.toUpperCase() : '';
  }

  getCountryById(countryId: number): string {
    const country = MOCK_COUNTRIES.find(c => c.id === countryId);
    return country ? country.code : 'Aucun';
  }

  getMonitor(id: number) {
    if (id && id !== null) {

      const monitor = this.monitors.find((m) => m.id === id);

      return monitor;
    }
  }

  showDetailEvent(event: any) {

    if (event.showDetail || (!event.showDetail && this.detailData !== null && this.detailData.id !== event.item.id)) {
      this.detailData = event.item;

      this.crudService.get('/admin/courses/'+this.detailData.course_id)
        .subscribe((course) => {
          this.detailData.course = course.data;
          this.crudService.get('/sports/'+this.detailData.course.sport_id)
          .subscribe((sport) => {
            this.detailData.sport = sport.data;
          });

          if (this.detailData.degree_id !== null) {
            this.crudService.get('/degrees/'+this.detailData.degree_id)
            .subscribe((degree) => {
              this.detailData.degree = degree.data;
            })
          }

      })

      this.crudService.list('/booking-users', 1, 10000, 'desc', 'id', '&booking_id='+this.detailData.booking.id)
        .subscribe((booking) => {
          this.detailData.users = [];

          booking.data.forEach((element, idx) => {
            if (moment(element.date).format('YYYY-MM-DD') === moment(this.detailData.date).format('YYYY-MM-DD')) {
              this.detailData.users.push(element);

                this.crudService.list('/client-sports', 1, 10000, 'desc', 'id', '&client_id='+element.client_id+"&school_id="+this.user.schools[0].id)
                .subscribe((cd) => {

                  if (cd.data.length > 0) {
                    element.sports= [];

                    cd.data.forEach(c => {
                      element.sports.push(c);
                    });
                  }


                })

            }
          });
          this.showDetail = true;

        });


    } else {

      this.showDetail = event.showDetail;
      this.detailData = null;
    }

  }

  calculateHourEnd(hour: any, duration: any) {
    if(duration.includes('h') && (duration.includes('min') || duration.includes('m'))) {
      const hours = duration.split(' ')[0].replace('h', '');
      const minutes = duration.split(' ')[1].replace('min', '').replace('m', '');

      return moment(hour, 'HH:mm').add(hours, 'h').add(minutes, 'm').format('HH:mm');
    } else if(duration.includes('h')) {
      const hours = duration.split(' ')[0].replace('h', '');

      return moment(hour, 'HH:mm').add(hours, 'h').format('HH:mm');
    } else {
      const minutes = duration.split(' ')[0].replace('min', '').replace('m', '');

      return moment(hour, 'HH:mm').add(minutes, 'm').format('HH:mm');
    }
  }

  getAgeRange(data: any[]): { age_min: number, age_max: number } {
    let age_min = Number.MAX_SAFE_INTEGER;
    let age_max = Number.MIN_SAFE_INTEGER;

    data.forEach(item => {
      if (item.age_min < age_min) {
        age_min = item.age_min;
      }
      if (item.age_max > age_max) {
        age_max = item.age_max;
      }
    });

    return { age_min, age_max };
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

  encontrarPrimeraCombinacionConValores(data: any) {
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
}
