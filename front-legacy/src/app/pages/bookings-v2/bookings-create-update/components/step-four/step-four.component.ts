import {
  Component,
  Input,
  Output,
  EventEmitter,
  ViewChild,
} from "@angular/core";
import { FormBuilder, FormGroup, Validators } from "@angular/forms";
import {
  MatCalendar,
  MatCalendarCellCssClasses,
} from "@angular/material/datepicker";
import moment from "moment";
import { ApiCrudService } from "src/service/crud.service";
import { CustomHeader } from "../calendar/custom-header/custom-header.component";
import { CalendarService } from "../../../../../../service/calendar.service";
import {UtilsService} from '../../../../../../service/utils.service';

@Component({
  selector: "booking-step-four",
  templateUrl: "./step-four.component.html",
  styleUrls: ["./step-four.component.scss"],
})
export class StepFourComponent {
  @Input() initialData: any;
  @Input() client: any;
  @Input() utilizers: any;
  @Input() activitiesBooked: any;
  @Input() sportLevel: any;
  @Input() selectedForm: any;
  @Output() stepCompleted = new EventEmitter<FormGroup>();
  @Output() prevStep = new EventEmitter();
  @ViewChild("secondCalendar") secondCalendar: MatCalendar<Date>;

  stepForm: FormGroup;
  selectedDate;
  nextMonthDate: Date;
  selectedSubGroup: any;
  selectedCourse;
  courseTypeId: number = 1;
  selectedIndex: number = 1;
  user;
  courses = [];
  minDate;
  coursesDate = [];
  cursesInSelectedDate = [];
  isLoading = true;
  selectedDateMoment;
  showTwoMonths: boolean = true;
  selectedSubGroups = []; // Array para almacenar los subgrupos filtrados


  tabs = [
    { label: "course_colective", courseTypeId: 1, class: "yellow" },
    { label: "course_private", courseTypeId: 2, class: "green" }
  ];

  constructor(
    private fb: FormBuilder,
    private crudService: ApiCrudService,
    private calendarService: CalendarService,
    protected utilsService: UtilsService
  ) {


  }

  ngOnInit(): void {
    this.selectedCourse = this.initialData?.selectedCourse;
    this.selectedDate = this.initialData?.selectedDate || this.minDate;
    this.minDate = new Date();
    this.selectedDateMoment = this.selectedDate
      ? moment(this.selectedDate)
      : moment(this.minDate);
    this.updateNextMonth();
    if(!this.initialData?.selectedDate) {
      this.autoSelectFirstDayIfCurrentMonth();
    }
    this.updateTabs();
    this.user = JSON.parse(localStorage.getItem("boukiiUser"));
    this.stepForm = this.fb.group({
      date: [this.selectedDate || this.minDate, Validators.required],
      course: [this.selectedCourse, Validators.required],
      selectedSubGroup: [null], // Campo para el subgrupo seleccionado
    });
    this.getCourses(this.sportLevel);
    this.calendarService.monthChanged$.subscribe((newDate: Date) => {
      this.selectedDate = newDate;
      this.updateNextMonth();
      this.autoSelectFirstDayIfCurrentMonth();
      this.getCourses(this.sportLevel);
    });
  }

  handleCourseSelection(course: any): void {
    if (course.course_type === 1) {
      // Filtrar los subgrupos que coinciden con el `degree` seleccionado
      let group = course.course_dates[0].course_groups.filter(
        (group) => group.degree_id === this.sportLevel.id
      );
      this.selectedSubGroups = group[0].course_subgroups.filter(
        (subgroup) => subgroup.booking_users.length < subgroup.max_participants
      );
    } else {
      this.selectedSubGroups = []; // Si no es colectivo, limpiar los subgrupos
    }

    // Guardar el curso seleccionado
    this.selectedCourse = course;
    this.stepForm.get('course').setValue(course);
  }

  selectSubGroup(group: any): void {
    this.selectedSubGroup = group;
    this.stepForm.get('selectedSubGroup').setValue(group);
  }

  updateTabs(): void {
    // Si utilizers tiene más de 1 usuario, eliminamos el tab "course_colective"
    if ((this.utilizers && this.utilizers.length > 1) || this.initialData?.onlyPrivate) {
      this.tabs = [
        { label: "course_private", courseTypeId: 2, class: "green" },
       /* { label: "activity", courseTypeId: 3, class: "blue" }*/
      ];
      this.courseTypeId = 2;
      this.selectedIndex = this.courseTypeId - 2;
    } else {
      // Si solo hay 1 user, mostramos las 3 tabs
      this.tabs = [
        { label: "course_colective", courseTypeId: 1, class: "yellow" },
        { label: "course_private", courseTypeId: 2, class: "green" }
      ];
      this.selectedIndex = this.courseTypeId - 1;
    }
  }

  isFormValid() {
    return this.stepForm.valid;
  }

  handlePrevStep() {
    this.prevStep.emit();
  }

  completeStep() {
    if (this.isFormValid()) {
/*      if(this.initialData.selectedDate != this.selectedDate) {
        this.selectedForm.get('step5').patchValue({})
      }*/
      this.stepCompleted.emit(this.stepForm);
    }
  }

  autoSelectFirstDayIfCurrentMonth() {
    const currentMonth = moment();
    const selectedMonth = moment(this.selectedDate);

    if (selectedMonth.isSame(currentMonth, "month")) {
      this.selectedDate = new Date();
      this.selectedDateMoment = moment(this.selectedDate );
    }
  }

  updateNextMonth() {
    // Calculamos la fecha del último día del próximo mes
    this.nextMonthDate = moment(this.selectedDate)
      .add(1, "month")
      .endOf("month")
      .toDate();

    if (this.secondCalendar) {
      this.secondCalendar.activeDate = this.nextMonthDate;
    }
  }

  filterCoursesCollective() {
    this.courses = this.courses.filter(course => {
      if (!course.is_flexible) {
        // Filtrar si todas las fechas son iguales o posteriores a hoy
        const hasPastDate = course.course_dates.some(d => {
          const courseDateMoment = moment(d.date, "YYYY-MM-DD");
          return courseDateMoment.isBefore(moment(), "day");
        });

        return !hasPastDate; // Solo incluir cursos sin fechas pasadas
      }

      // Los cursos flexibles siempre pasan el filtro
      return true;
    });
  }

  handleDateChange(event: any) {
    this.selectedDate = event;
    this.selectedDateMoment = moment(event);
    this.stepForm.get("date").patchValue(this.selectedDateMoment);
    this.selectedCourse = null;
    this.stepForm.get('course').setValue(null);
    this.cursesInSelectedDate = this.courses.filter(course =>
      course.course_dates.some(d => {
        const courseDateMoment = moment(d.date, "YYYY-MM-DD");
        const currentTime = moment(); // Definir la hora actual aquí

        if (courseDateMoment.isSame(moment(), "day")) {
          if(course.course_type == 1) {
            const hourStart = moment(d.hour_start, "HH:mm");
            return this.selectedDateMoment.isSame(courseDateMoment, 'day') && currentTime.isBefore(hourStart);
          } else {
            const hourEnd = moment(d.hour_end, "HH:mm");
            return this.selectedDateMoment.isSame(courseDateMoment, 'day') && currentTime.isBefore(hourEnd);
          }
        } else {
          return this.selectedDateMoment.isSame(courseDateMoment, 'day');
        }
      })
    );
  }

  compareCourseDates() {
    let ret = [];
    const currentTime = moment(); // Hora actual

    this.courses.forEach((course) => {
      course.course_dates.forEach((courseDate) => {
        const courseDateMoment = moment(courseDate.date, "YYYY-MM-DD");

        // Si la fecha del curso es hoy, comprobar las horas
        if (courseDateMoment.isSame(moment(), "day")) {
          if(course.course_type == 1) {
          const hourStart = moment(courseDate.hour_start, "HH:mm");

          // Solo añadir la fecha si el curso aún no ha empezado
          if (currentTime.isBefore(hourStart)) {
            ret.push(courseDateMoment.format("YYYY-MM-DD"));
          }
          } else {
            const hourEnd = moment(courseDate.hour_end, "HH:mm");
            if (currentTime.isBefore(hourEnd)) {
              ret.push(courseDateMoment.format("YYYY-MM-DD"));
            }
          }
        } else {
          // Si la fecha no es hoy, añadirla sin comprobación de hora
          ret.push(courseDateMoment.format("YYYY-MM-DD"));
        }
      });
    });

    this.coursesDate = ret;
  }

  dateClass() {
    return (date: Date): MatCalendarCellCssClasses => {
      const currentDate = moment(date, "YYYY-MM-DD").format("YYYY-MM-DD");
      if (
        this.coursesDate.indexOf(currentDate) !== -1 &&
        moment(this.minDate, "YYYY-MM-DD")
          .startOf("day")
          .isSameOrBefore(moment(date, "YYYY-MM-DD").startOf("day"))
      ) {
        const colorClass = this.tabs.find(
          (tab) => tab.courseTypeId === this.courseTypeId
        )?.class;
        return `with-course ${colorClass}`;
      } else {
        return;
      }
    };
  }

  getCourses(sportLevel: any) {
    this.isLoading = true;
    let minDate, maxDate;
    minDate = moment(this.selectedDate);
    maxDate = moment(this.nextMonthDate);
    const rq = {
      start_date: minDate.format("YYYY-MM-DD"),
      end_date: maxDate.format("YYYY-MM-DD"),
      course_type: this.courseTypeId,
      sport_id: sportLevel.sport_id,
      client_id: this.utilizers.map(item => item.id),
      /*degree_id: sportLevel.id,*/
      get_lower_degrees: false,
      school_id: this.user.schools[0].id,
    };
    this.crudService.post("/availability", rq).subscribe((data) => {
      this.courses = data.data;
      this.courses = this.courses.filter(course => {
        const isCourseBooked = this.activitiesBooked.some(activity => {
          const sameCourse = activity.course.course_type === 1 && activity.course.id === course.id;
          const hasCommonUtilizer = activity.utilizers.some(utilizer =>
            this.utilizers.some(selectedUtilizer => selectedUtilizer.id === utilizer.id)
          );
          const sameDegree = this.selectedForm?.value?.step3?.sportLevel?.id === activity.sportLevel?.id;

          // Verificamos si step5 está definido antes de acceder a course_dates
          const selectedFormDates = this.selectedForm?.value?.step5?.course_dates?.map(d => d.date) || [];
          const activityDates = activity.dates?.map(d => d.date) || [];
          const sameDates = JSON.stringify(selectedFormDates) === JSON.stringify(activityDates);

          // Si estamos editando, excluir esta actividad del filtrado
          const isEditing = !!this.selectedForm && sameCourse && hasCommonUtilizer && sameDegree && sameDates;

          return !isEditing && sameCourse && hasCommonUtilizer;
        });

        return !isCourseBooked;
      });


      if(this.courseTypeId == 1) {
        this.filterCoursesCollective();
      }
      this.compareCourseDates();

      this.cursesInSelectedDate = this.courses.filter(course =>
        course.course_dates.some(d => {
          const courseDateMoment = moment(d.date, "YYYY-MM-DD");
          const currentTime = moment(); // Definir la hora actual aquí

          // Comprobar si la fecha es hoy
          if (courseDateMoment.isSame(moment(), "day")) {
            if(course.course_type == 1) {
              const hourStart = moment(d.hour_start, "HH:mm");
              return this.selectedDateMoment.isSame(courseDateMoment, 'day') && currentTime.isBefore(hourStart);
            } else {
              const hourEnd = moment(d.hour_end, "HH:mm");
              return this.selectedDateMoment.isSame(courseDateMoment, 'day') && currentTime.isBefore(hourEnd);
            }
          } else {
            return this.selectedDateMoment.isSame(courseDateMoment, 'day');
          }
        })
      );
      this.stepForm.get("date").patchValue(this.selectedDateMoment);
      this.isLoading = false;
    });
  }

  onTabChange(event) {
    this.courseTypeId = this.tabs[event.index].courseTypeId;
    this.getCourses(this.sportLevel);
  }

  preventTabChange(event: Event) {
    if (this.isLoading) {
      event.stopPropagation(); // Evita el cambio de pestaña cuando isLoading es true
      event.preventDefault();
    }
  }

  protected readonly CustomHeader = CustomHeader;
}
