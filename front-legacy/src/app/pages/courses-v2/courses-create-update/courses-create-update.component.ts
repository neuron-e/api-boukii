import { Component, OnInit } from '@angular/core';
import {AbstractControl, FormArray, FormGroup, UntypedFormBuilder, UntypedFormGroup, Validators} from '@angular/forms';
import { MatDialog } from '@angular/material/dialog';
import {map, forkJoin, mergeMap, throwError, catchError} from 'rxjs';
import { fadeInUp400ms } from 'src/@vex/animations/fade-in-up.animation';
import { stagger20ms } from 'src/@vex/animations/stagger.animation';
import { ApiCrudService } from 'src/service/crud.service';
import { ActivatedRoute, Router } from '@angular/router';
import { SchoolService } from 'src/service/school.service';
import { CoursesService } from 'src/service/courses.service';
import {TranslateService} from '@ngx-translate/core';
import {MatSnackBar} from '@angular/material/snack-bar';

@Component({
  selector: 'vex-courses-create-update',
  templateUrl: './courses-create-update.component.html',
  styleUrls: ['./courses-create-update.component.scss',],
  animations: [fadeInUp400ms, stagger20ms]
})
export class CoursesCreateUpdateComponent implements OnInit {
  dataSource: any;
  editingIndex: number | null = null;

  ModalFlux: number = +this.activatedRoute.snapshot.queryParamMap['params'].step || 0
  ModalProgress: { Name: string, Modal: number }[] = [
    { Name: "sport", Modal: 0 },
    { Name: "details", Modal: 1 },
    { Name: "dates", Modal: 2 },
    { Name: "details", Modal: 3 },
    { Name: "extras", Modal: 4 },
    { Name: "langs", Modal: 5 },
  ]
  Translate: { Code: string, Name: string }[] = [
    { Code: "fr", Name: "French" },
    { Code: "de", Name: "German" },
    { Code: "en", Name: "English" },
    { Code: "it", Name: "Italian" },
    { Code: "es", Name: "Spanish" },
  ]

  PeriodoFecha: number = 0
  extrasFormGroup: UntypedFormGroup; //crear extras nuevas
  nowDate: Date = new Date()
  sportData: any = [];
  sportDataList: any = [];
  sportTypeData: any = [];
  stations: any = [];
  monitors: any = [];
  schoolData: any = [];
  extras: any = []

  mode: 'create' | 'update' = 'create';
  loading: boolean = true;
  extrasModal: boolean = false
  confirmModal: boolean = false
  editModal: boolean = false
  editFunctionName: string | null = null;
  editFunctionArgs: any[] = [];

  setEditFunction(functionName: string, ...args: any[]) {
    this.editFunctionName = functionName;
    this.editFunctionArgs = args.length === 1 && Array.isArray(args[0]) ? args[0] : args;
  }

  executeEditFunction() {
    if (this.editFunctionName && typeof this[this.editFunctionName] === 'function') {
      this[this.editFunctionName](...this.editFunctionArgs);
    }
    this.editModal = false;
  }

  translateExpandedIndex: number = 0
  user: any;
  id: any = null;
  // Array simple para intervalos
  intervals: any[] = [];
  useMultipleIntervals = false;
  mustBeConsecutive = false;
  mustStartFromFirst = false;

  constructor(private fb: UntypedFormBuilder, public dialog: MatDialog,
              private crudService: ApiCrudService, private activatedRoute: ActivatedRoute,
              public router: Router, private schoolService: SchoolService,
              private snackBar: MatSnackBar,
    public translateService: TranslateService,
    public courses: CoursesService
  ) {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.id = this.activatedRoute.snapshot.params.id;
    this.ModalFlux = +this.activatedRoute.snapshot.queryParamMap['params'].step || 0
  }
  detailData: any = { degrees: [], course_dates: [] }

  ngOnInit() {
    this.initializeExtras();
    this.mode = this.id ? 'update' : 'create';

    const requests = {
      sports: this.getSports(),
      stations: this.getStations(),
      ...(this.mode === "update" && { monitors: this.getMonitors() }),
    };

    forkJoin(requests).subscribe(({ sports, stations, monitors }) => {
      this.sportData = sports;
      this.stations = stations;
      if (this.mode === "update") {
        this.monitors = monitors;
        this.loadCourseData();
      } else {
        this.setupCreateMode();
      }
      this.initializeExtrasForm();
      this.loadSchoolData();
    });
  }

  private initializeExtras() {
    try {
      const storedUser = localStorage.getItem("boukiiUser");
      const user = storedUser ? JSON.parse(storedUser) : null;
      const settings =  typeof user?.schools?.[0]?.settings === 'string' ? JSON.parse(user.schools[0].settings) : null;
      this.extras = settings?.extras
        ? [...settings.extras.food, ...settings.extras.forfait, ...settings.extras.transport]
        : [];
    } catch (error) {
      console.error("Error loading extras from localStorage:", error);
      this.extras = [];
    }
  }

  private setupCreateMode() {
    this.courses.resetcourseFormGroup();
    this.courses.courseFormGroup.patchValue({
      sport_id: this.sportData[0]?.sport_id || null,
      station_id: this.stations[0]?.id || null,
      duration: this.courses.duration[0] || null,
      school_id: this.user.schools?.[0]?.id || null,
      hour_min: this.courses.hours[0] || null,
      hour_max: this.courses.hours[4] || null,
    });
    this.Confirm(0);
    this.loading = false
   //setTimeout(() => (), 0);
  }

  private loadCourseData() {
    this.crudService
      .get(`/admin/courses/${this.id}`, [
        "courseGroups.degree",
        "courseGroups.courseDates.courseSubgroups.bookingUsers.client",
        "sport",
      ])
      .subscribe((response: any) => {
        this.detailData = response.data;
        this.detailData.station = this.detailData.station || null;
        this.mergeCourseExtras();
        let hasMultipleIntervals = false;

        if (this.detailData.settings) {
          try {
            const settings = typeof this.detailData.settings === 'string'
              ? JSON.parse(this.detailData.settings)
              : this.detailData.settings;

            if (settings.multipleIntervals) {
              hasMultipleIntervals = true;
              this.useMultipleIntervals = true;
              this.mustBeConsecutive = settings.mustBeConsecutive || false;
              this.mustStartFromFirst = settings.mustStartFromFirst || false;
            }
          } catch (error) {
            console.error("Error parsing settings:", error);
          }
        }
        if (this.detailData?.settings?.periods?.length > 1) {
          this.PeriodoFecha = 0;
        }
        this.courses.settcourseFormGroup(this.detailData);
        this.courses.courseFormGroup.patchValue({ extras: this.detailData.course_extras || [] });
        this.getDegrees();
        // Si tiene intervalos múltiples, cargarlos
        if (hasMultipleIntervals && this.detailData.course_type === 1) {
          // Cargar los intervalos después de que el FormGroup esté listo
          this.loadIntervalsFromCourse(this.detailData, this);
        }
        this.loading = false
       // setTimeout(() => (this.loading = false), 0);
      });
  }


  /**
   * Método seguro para obtener el array de intervalos desde el FormGroup principal
   * Este método garantiza que siempre se devuelva un FormArray, incluso si aún no está inicializado
   */
  getIntervalsArray(): FormArray {
    // Verificar si el FormGroup principal existe
    if (!this.courses.courseFormGroup) {
      console.warn('courseFormGroup no está inicializado. Devolviendo un FormArray vacío.');
      return this.fb.array([]);
    }

    // Intentar obtener el FormArray de intervals_ui
    const intervals = this.courses.courseFormGroup.get('intervals_ui');

    // Si el control no existe o no es un FormArray, devolver uno vacío
    if (!intervals || !(intervals instanceof FormArray)) {
      console.warn('intervals_ui no está inicializado o no es un FormArray. Devolviendo un FormArray vacío.');

      // Si el control no existe pero el FormGroup sí, podemos intentar inicializarlo
      if (this.courses.courseFormGroup) {
        const emptyArray = this.fb.array([]);
        this.courses.courseFormGroup.setControl('intervals_ui', emptyArray);
        return emptyArray;
      }

      // Como fallback, devolvemos un array vacío
      return this.fb.array([]);
    }

    // Si todo está bien, devolver el FormArray
    return intervals as FormArray;
  }

  private mergeCourseExtras() {
    // Formatear extras de configuración
    const formattedSettingsExtras = (this.extras || []).map(extra => ({
      id: extra.id.toString(),
      name: extra.name,
      product: extra.product,
      price: parseFloat(extra.price) || 0,
      tva: extra.tva || 0,
      status: extra.status || false,
      active: false,
    }));

    // Formatear extras del curso
    const formattedCourseExtras = (this.detailData.course_extras || []).map(extra => ({
      id: extra.id.toString(),
      name: extra.name,
      product: extra.name,
      price: parseFloat(extra.price) || 0,
      tva: 0,
      status: true,
      active: true,
    }));

    // Unir sin duplicados
    this.extras = [...formattedSettingsExtras, ...formattedCourseExtras].reduce((acc, extra) => {
      if (!acc.some(e => e.id === extra.id)) {
        acc.push(extra);
      }
      return acc;
    }, []);
  }

  private initializeExtrasForm() {
    this.extrasFormGroup = this.fb.group({
      id: ["", Validators.required],
      product: ["", Validators.required],
      name: ["", Validators.required],
      price: [1, Validators.required],
      tva: [21, Validators.required],
      status: [true, Validators.required],
    });
  }

  private loadSchoolData() {
    this.schoolService.getSchoolData().subscribe(data => {
      this.schoolData = data.data;
    });
  }


  createExtras() {
    const formData = this.extrasFormGroup.getRawValue();
    formData.id = formData.id || "aFOR-" + formData.name + formData.product + formData.price;

    if (this.editingIndex !== null) {
      this.extras[this.editingIndex] = formData; // Actualiza el extra en lugar de crear uno nuevo
    } else {
      this.extras.push(formData); // Agrega un nuevo extra
    }

    this.extrasModal = false;
    this.resetExtraForm();
  }

  resetExtraForm() {
    this.extrasFormGroup.reset({
      id: "",
      product: "",
      name: "",
      price: 1,
      tva: 21,
      status: true,
    });
    this.editingIndex = null;
  }

  editExtra(index: number) {
    this.editingIndex = index;
    this.extrasFormGroup.setValue(this.extras[index]);
    this.extrasModal = true;
  }

  getSportsType = () => this.crudService.list('/sport-types', 1, 1000).pipe(map(data => data.data));
  getMonitors = () => this.crudService.list('/monitors', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id).pipe(map(data => data.data));
  getSports = () => this.crudService.list('/school-sports', 1, 10000, 'asc', 'id', '&school_id=' + this.user.schools[0].id, null, null, null, ['sport']).pipe(map(sport => sport.data));

  getStations = () => this.crudService.list('/stations-schools', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id).pipe(
    map(station => station.data),
    mergeMap(stations => forkJoin(stations.map((element: any) => this.crudService.get('/stations/' + element.station_id).pipe(map(data => data.data)))))
  );

  getDegrees = () => this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order', '&school_id=' + this.courses.courseFormGroup.controls['school_id'].value + '&sport_id=' + this.courses.courseFormGroup.controls['sport_id'].value).subscribe((data) => {
    this.detailData.degrees = [];
    data.data.forEach((element: any) => {
      if (element.active) this.detailData.degrees.push({ ...element, }); //Subgrupo: this.getSubGroups(element.id)
    });
    const levelGrop = []


    if (this.detailData.course_dates && Array.isArray(this.detailData.course_dates)) {
      this.detailData.degrees.forEach((level: any) => {
        level.active = false;

        this.detailData.course_dates.forEach((cs: any) => {
          if (cs.course_groups && Array.isArray(cs.course_groups)) {
            cs.course_groups.forEach((group: any) => {
              if (group.degree_id === level.id) {
                level.active = true;
                level.old = true;
                group.age_min = level.age_min;
                group.age_max = level.age_max;

                if (group.course_subgroups && Array.isArray(group.course_subgroups) && group.course_subgroups.length > 0) {
                  level.max_participants = group.course_subgroups[0].max_participants;
                  level.course_subgroups = group.course_subgroups;
                }

                level.visible = false;
              }
            });
          }
        });

        levelGrop.push({ ...level });
      });

      levelGrop.sort((a: any) => (a.active ? -1 : 1));
    }

    this.courses.courseFormGroup.patchValue({ levelGrop });
  });

  Confirm(add: number) {
    this.courses.courseFormGroup.markAsUntouched()
    if ( this.courses.courseFormGroup.controls['course_type'].value === 2 &&
      !this.courses.courseFormGroup.controls['is_flexible'].value && this.ModalFlux === 2) {
      add = add + 1;
    }
    this.ModalFlux += add
    if (this.ModalFlux === 1) {
      if (!this.courses.courseFormGroup.controls["course_type"].value) this.courses.courseFormGroup.patchValue({ course_type: 1 })
      this.courses.courseFormGroup.patchValue({
        icon: this.sportData.find((a: any) => a.sport_id === this.courses.courseFormGroup.controls['sport_id'].value).sport.icon_unselected
      })
      this.getDegrees();
    } else if (this.ModalFlux === 2) {
      if (
        this.courses.courseFormGroup.controls["name"].status === 'VALID' &&
        this.courses.courseFormGroup.controls["short_description"].status === 'VALID' &&
        this.courses.courseFormGroup.controls["description"].status === 'VALID' &&
        this.courses.courseFormGroup.controls["price"].status === 'VALID' &&
        this.courses.courseFormGroup.controls["max_participants"].status === 'VALID' &&
        (
          this.courses.courseFormGroup.controls['course_type'].value > 1 &&
          this.courses.courseFormGroup.controls["age_min"].status === 'VALID' &&
          this.courses.courseFormGroup.controls["age_max"].status === 'VALID' ||
          this.courses.courseFormGroup.controls['course_type'].value === 1
        )
      ) {
        if (this.mode === 'create') {

          setTimeout(async () => {
            const languages = ['fr', 'en', 'de', 'es', 'it'];
            const { name, short_description, description } = this.courses.courseFormGroup.controls;

            // Inicializamos el objeto de traducciones con valores vacíos
            const translations: Record<string, any> = {};
            languages.forEach(lang => {
              translations[lang] = {
                name: '',
                short_description: '',
                description: ''
              };
            });

            try {
              const translationResults = await Promise.allSettled(
                languages.map(async (lang) => {
                  try {
                    const translatedName = await this.crudService.translateText(name.value, lang.toUpperCase()).toPromise();
                    const translatedShortDescription = await this.crudService.translateText(short_description.value, lang.toUpperCase()).toPromise();
                    const translatedDescription = await this.crudService.translateText(description.value, lang.toUpperCase()).toPromise();

                    return {
                      lang,
                      name: translatedName?.data?.translations?.[0]?.text || '',
                      short_description: translatedShortDescription?.data?.translations?.[0]?.text || '',
                      description: translatedDescription?.data?.translations?.[0]?.text || '',
                    };
                  } catch (error) {
                    console.error(`Error translating to ${lang}:`, error);
                    return { lang, name: '', short_description: '', description: '' }; // Retorna un objeto vacío si hay error
                  }
                })
              );

              // Asignamos los valores traducidos (si existen)
              translationResults.forEach((result) => {
                if (result.status === "fulfilled" && result.value) {
                  translations[result.value.lang] = {
                    name: result.value.name,
                    short_description: result.value.short_description,
                    description: result.value.description,
                  };
                }
              });

              this.courses.courseFormGroup.patchValue({ translations });

            } catch (error) {
              console.error("Unexpected error in translation process:", error);
            }
          }, 1000);
        }
      } else {
        this.courses.courseFormGroup.markAllAsTouched()
        this.ModalFlux -= add
      }
    } else if (this.ModalFlux === 3) {
      if (
        this.courses.courseFormGroup.controls["date_start"].status === 'VALID' &&
        this.courses.courseFormGroup.controls["date_end"].status === 'VALID'
      ) {
      } else {
        this.courses.courseFormGroup.markAllAsTouched()
        this.ModalFlux -= add
      }
      if (this.courses.courseFormGroup.controls['course_type'].value === 2) {
        let durations = this.courses.getFilteredDuration();
        let Range = this.generarIntervalos(
          this.courses.courseFormGroup.controls["max_participants"].value,
          durations.length,
          durations
        );

        const settings = JSON.parse(this.user.schools[0].settings);
        const priceRanges = settings.prices_range.prices.map(p => ({
          ...p,
          intervalo: p.intervalo.replace(/^(\d+)h$/, "$1h 0min") // Convierte "1h" en "1h0min" para que coincida con durations
        }));

        // Asignar los precios a los intervalos correctos
        Range = Range.map(intervalo => {
          const matchingPrice = priceRanges.find(p => p.intervalo === intervalo.intervalo);
          return matchingPrice ? { ...intervalo, ...matchingPrice } : intervalo;
        });

        this.courses.courseFormGroup.patchValue({ price_range: Range });
      }
    }
    else if (this.ModalFlux === 4) {
      if (this.courses.courseFormGroup.controls['course_type'].value === 1) {
        if (this.courses.courseFormGroup.controls['levelGrop'].value.some((item: any) => item.active)) {
        } else {
          this.ModalFlux -= add
        }
      } else if (this.courses.courseFormGroup.controls['course_type'].value === 2) {
      } else {
        const groups = this.courses.courseFormGroup.controls['settings'].value.groups;
        if (groups.every((group: any) => group.groupName && group.ageMin > 0 && group.ageMax > 0 && group.price > 0)) {
        } else {
          this.courses.courseFormGroup.controls['settings'].markAllAsTouched()
          this.ModalFlux -= add
        }
      }
    }
    else if (this.ModalFlux === 6) {
      this.ModalFlux--
      this.confirmModal = true
    }
  }

  async translateCourse(lang: string): Promise<void> {
    this.loading = true;
    try {
      const translations = this.courses.courseFormGroup.controls['translations'].value || {};
      const currentTranslation = translations[lang] || {};

      const translatedName = await this.crudService.translateText(this.courses.courseFormGroup.value.name, lang.toUpperCase()).toPromise();
      const translatedShortDescription = await this.crudService.translateText(this.courses.courseFormGroup.value.short_description, lang.toUpperCase()).toPromise();
      const translatedDescription = await this.crudService.translateText(this.courses.courseFormGroup.value.description, lang.toUpperCase()).toPromise();

      // Actualizar solo los valores traducidos sin afectar los demás idiomas
      this.courses.courseFormGroup.patchValue({
        translations: {
          ...translations,
          [lang]: {
            name: translatedName?.data?.translations?.[0]?.text || currentTranslation.name,
            short_description: translatedShortDescription?.data?.translations?.[0]?.text || currentTranslation.short_description,
            description: translatedDescription?.data?.translations?.[0]?.text || currentTranslation.description,
          },
        },
      });

    } catch (error) {
      console.error(`Error translating to ${lang}:`, error);
    } finally {
      this.loading = false;
    }
  }

  find = (array: any[], key: string, value: string | boolean) => array.find((a: any) => value ? a[key] === value : a[key])
  filter = (array: any[], key: string, value: string | boolean) => array.filter((a: any) => value ? a[key] === value : a[key])

  selectLevel = (event: any, i: number) => {
    const levelGrop = this.courses.courseFormGroup.controls['levelGrop'].value
    const course_dates = this.courses.courseFormGroup.controls['course_dates'].value
    levelGrop[i].active = event.target.checked
    if (event.target.checked) {
/*      levelGrop[i].age_min = this.courses.courseFormGroup.controls['age_min'].value
      levelGrop[i].age_max = this.courses.courseFormGroup.controls['age_max'].value*/
      levelGrop[i].max_participants = this.courses.courseFormGroup.controls['max_participants'].value
      for (const course of course_dates) {
        if (this.mode === "create") {
          course.course_groups = [...course.course_groups, { ...levelGrop[i], degree_id: levelGrop[i].id, course_subgroups: [] }]
          course.groups = [...course.groups, { ...levelGrop[i], degree_id: levelGrop[i].id, subgroups: [] }]
        }
        else {
          course.course_groups = [...course.course_groups, { ...levelGrop[i], degree_id: levelGrop[i].id, course_id: this.courses.courseFormGroup.controls['id'].value, course_subgroups: [] }]
        }
      }
    } else {
      for (const course of course_dates) {
        course.course_groups = course.course_groups.filter((a: any) => a.id !== levelGrop[i].id)
        if (this.mode === "create") course.groups = course.groups.filter((a: any) => a.id !== levelGrop[i].id)
      }
    }
    this.courses.courseFormGroup.patchValue({ levelGrop, course_dates })
    if (event.target.checked) this.addLevelSubgroup(levelGrop[i], 0, true)
  }

  addLevelSubgroup = (level: any, j: number, add: boolean) => {
    const course_dates = this.courses.courseFormGroup.controls['course_dates'].value.map((course: any) => {
      // Verificamos en course_groups
      for (const group in course.course_groups) {
        if (course.course_groups[group].degree_id === level.id) {
          if (add) {
            if (this.mode === "create") {
              course.groups[group].subgroups = [
                ...course.groups[group].subgroups,
                { degree_id: level.id, max_participants: level.max_participants, monitor: null, monitor_id: null }
              ];
            }
            course.course_groups[group].course_subgroups = [
              ...course.course_groups[group].course_subgroups,
              { degree_id: level.id, max_participants: level.max_participants, monitor: null, monitor_id: null }
            ];
          } else {
            if (this.mode === "create") {
              course.groups[group].subgroups = course.groups[group].subgroups.filter((_, index: number) => index !== j);
            }
            course.course_groups[group].course_subgroups = course.course_groups[group].course_subgroups.filter((_, index: number) => index !== j);
          }
        }
      }

      // Eliminamos también en course_subgroups del propio course_date
      if (!add && course.course_subgroups) {
        course.course_subgroups = course.course_subgroups.filter((_, index: number) => index !== j);
      }

      return course; // Retornamos el course modificado
    });

    this.courses.courseFormGroup.patchValue({ course_dates });
  };

  selectExtra = (event: any, item: any, i: number) => {
    if (this.courses.courseFormGroup.controls['course_type'].value === 3) {
      this.courses.courseFormGroup.controls['settings'].value.groups = JSON.parse(JSON.stringify(this.courses.courseFormGroup.controls['settings'].value.groups))
      if (event.checked || !this.courses.courseFormGroup.controls['settings'].value.groups[i].extras.find((a: any) => a.id === item.id)) this.courses.courseFormGroup.controls['settings'].value.groups[i].extras.push(item)
      else this.courses.courseFormGroup.controls['settings'].value.groups[i].extras = this.courses.courseFormGroup.controls['settings'].value.groups[i].extras.filter((a: any) => a.id !== item.id)
    } else {
      const extras = this.courses.courseFormGroup.controls['extras'].value
      if (event.checked || !extras.find((a: any) => a.id === item.id)) this.courses.courseFormGroup.patchValue({ extras: [...extras, item] })
      else this.courses.courseFormGroup.patchValue({ extras: extras.filter((a: any) => a.id !== item.id) })
    }
  }

  selectWeek = (day: string, event: any) => {
    const settings = this.courses.courseFormGroup.controls['settings'].value
    if (day === "0") settings.weekDays = { monday: event.checked, tuesday: event.checked, wednesday: event.checked, thursday: event.checked, friday: event.checked, saturday: event.checked, sunday: event.checked }
    else settings.weekDays[day] = event.checked
    this.courses.courseFormGroup.patchValue({ settings: settings })
  }

  setModalProgress() {
    const courseFormGroup = this.courses.courseFormGroup.getRawValue()
    if (courseFormGroup.course_type === 1) {
      this.ModalProgress = [
         { Name: "sport", Modal: 0 },
        { Name: "data", Modal: 1 },
        { Name: "dates", Modal: 2 },
        { Name: "levels", Modal: 3 },
        { Name: "extras", Modal: 4 },
        { Name: "langs", Modal: 5 },
      ];
    } else if (courseFormGroup.course_type === 2) {
      this.ModalProgress = [
        { Name: "sport", Modal: 0 },
        { Name: "data", Modal: 1 },
        { Name: "dates", Modal: 2 },
      ];
      if (courseFormGroup.is_flexible) {
        this.ModalProgress.push({ Name: "details", Modal: 3 });
        this.ModalProgress.push({ Name: "extras", Modal: 4 });
        this.ModalProgress.push({ Name: "langs", Modal: 5 });
      } else {
        this.ModalProgress.push({ Name: "extras", Modal: 4 });
        this.ModalProgress.push({ Name: "langs", Modal: 5 });
      }
    }
  }

  // Opción 3: Método genérico para obtener cualquier FormArray de un FormGroup
  getFormArray(formGroup: AbstractControl, name: string): AbstractControl[] {
    const formArray = formGroup?.get(name) as FormArray;
    return formArray?.controls || [];
  }

  endCourse() {
    const courseFormGroup = this.courses.courseFormGroup.getRawValue()
    if (courseFormGroup.course_type === 1 && this.useMultipleIntervals) {
      // Configurar los intervalos en settings
      const intervals = [];

      this.intervalsUI.controls.forEach((intervalControl) => {
        const interval = intervalControl as FormGroup;
        intervals.push({
          id: interval.get('id').value,
          name: interval.get('name').value
        });
      });

      // Actualizar settings con la configuración de intervalos
      courseFormGroup.settings = {
        ...courseFormGroup.settings,
        multipleIntervals: true,
        intervals: this.intervals,
        mustStartFromFirst: this.mustStartFromFirst,
        mustBeConsecutive: this.mustBeConsecutive
      };
    }

    if (courseFormGroup.course_type === 1 && courseFormGroup.course_dates && courseFormGroup.levelGrop) {
      courseFormGroup.course_dates.forEach((courseDate: any) => {
        if (courseDate.course_groups) {
          courseDate.course_groups.forEach((group: any) => {
            // Buscar en levelGrop el que tenga el mismo degree_id que el id del grupo
            const matchingLevel = courseFormGroup.levelGrop.find((level: any) => level.id === group.degree_id);

            if (matchingLevel) {
              // Asignar los valores de age_min y age_max del levelGrop al grupo
              group.age_min = parseInt(matchingLevel.age_min);
              group.age_max = parseInt(matchingLevel.age_max);
            }
          });
        }
      });
    }
    courseFormGroup.translations = JSON.stringify(this.courses.courseFormGroup.controls['translations'].value)
    courseFormGroup.course_type === 1 ? courseFormGroup.settings : courseFormGroup.settings = this.courses.courseFormGroup.controls['settings'].value
    if (this.mode === "create") {
      this.crudService.create('/admin/courses', courseFormGroup)
        .pipe(
          catchError((error) => {
            console.error("Error al crear el curso:", error);
            this.showErrorMessage("Hubo un problema al crear el curso. Inténtalo de nuevo.");
            return throwError(() => error);
          })
        )
        .subscribe((data:any) => {
          if (data.success) {
            this.router.navigate(["/courses/detail/" + data.data.id]);
          } else {
            this.showErrorMessage(data.message || "No se pudo crear el curso.");
          }
        });
    } else {
      this.crudService.update('/admin/courses', courseFormGroup, this.id)
        .pipe(
          catchError((error:any) => {
            console.error("Error al actualizar el curso:", error);
            this.showErrorMessage("Hubo un problema al actualizar el curso. Inténtalo de nuevo.");
            return throwError(() => error);
          })
        )
        .subscribe((data) => {
          if (data.success) {
            this.router.navigate(["/courses/detail/" + data.data.id]);
          } else {
            this.showErrorMessage(data.message || "No se pudo actualizar el curso.");
          }
        });
    }
  }

  showErrorMessage(message: string) {
    this.snackBar.open(message, 'Cerrar', { duration: 5000, panelClass: ['error-snackbar'] });
  }

  getNumberArray = (num: number): any[] => ['intervalo', ...Array.from({ length: num }, (_, i) => `${i + 1}`)];

  generarIntervalos = (personas: number, intervalo: number, duracion: string[]): any[] => {
    const resultado = [];
    for (let i = 0; i < intervalo; i++) {
      const obj = { intervalo: duracion[i] };
      for (let j = 1; j <= personas; j++) obj[j] = null
      resultado.push(obj);
    } return resultado;
  }

  addCategory = () => this.courses.courseFormGroup.controls['settings'].value.groups.push({ ...this.courses.default_activity_groups })

  addCourseDate = () => {
    const course_date = this.courses.courseFormGroup.controls['course_dates'].value
    const data = JSON.parse(JSON.stringify(course_date[course_date.length - 1]))
    delete data.id
    const newDate = new Date(course_date[course_date.length - 1].date);
    newDate.setDate(newDate.getDate() + 1);
    course_date.push({ ...data, date: newDate })
    this.courses.courseFormGroup.patchValue({ course_dates: course_date })
  }
  createIntervalUI(): FormGroup {
    return this.fb.group({
      id: [Date.now().toString()],
      name: ['Intervalo ' + (this.intervalsUI.length + 1)], // Nombre predeterminado significativo
      dates: this.fb.array([])
    });
  }

  get intervalsUI(): FormArray {
    return this.courses.courseFormGroup ?
      (this.courses.courseFormGroup.get('intervals_ui') as FormArray) :
      null;
  }

  createCourseDate(): FormGroup {
    return this.fb.group({
      date: [new Date().toISOString().split('T')[0], Validators.required],
      hour: [this.courses.hours[0], Validators.required],
      interval_id: [''],
      order: [0]
    });
  }

  updateDuration(event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    this.courses.courseFormGroup.patchValue({duration: value});
    this.syncIntervalsToCourseFormGroup();
  }

  syncIntervalsToCourseDates() {
    if (!this.useMultipleIntervals) return;

    const courseDates = [];
    const intervals = this.intervalsUI.controls;

    intervals.forEach((intervalControl) => {
      const interval = intervalControl as FormGroup;
      const intervalId = interval.get('id').value;
      const intervalName = interval.get('name').value;
      const datesArray = interval.get('dates') as FormArray;

      // Convertir las fechas del intervalo a course_dates
      datesArray.controls.forEach((dateControl, j) => {
        const date = dateControl.get('date').value;
        const hour = dateControl.get('hour').value;

        if (date && hour) {
          courseDates.push({
            date: date,
            hour_start: hour,
            hour_end: this.courses.addMinutesToTime(hour, this.courses.courseFormGroup.get('duration').value),
            duration: this.courses.courseFormGroup.get('duration').value,
            interval_id: intervalId,
            interval_name: intervalName,
            order: j + 1,
            course_groups: this.getCourseDateGroups()
          });
        }
      });
    });

    // Actualizar el course_dates en el formulario
    if (courseDates.length > 0) {
      this.courses.courseFormGroup.patchValue({ course_dates: courseDates });
    }
  }


  onMultipleIntervalsChange() {
    if (this.useMultipleIntervals) {
      // Si no hay intervalos, inicializar con uno vacío
      if (this.intervals.length === 0) {
        this.addIntervalUI(0);
      }
    } else {
      // Si se desactiva, mantener el array de course_dates normal y vaciar los intervalos
      this.resetToSingleInterval();
    }

    // Sincronizar con course_dates
    this.syncIntervalsToCourseFormGroup();
  }

  // Añadir un nuevo intervalo
  addIntervalUI(i:number) {
    const newInterval = {
      id: Date.now().toString(),
      name: `${this.translateService.instant('interval')} ${this.intervals.length + 1}`,
      dates: []
    };

    this.intervals.push(newInterval);

    this.addCourseDateToInterval(i);

  }

  // Eliminar un intervalo
  removeIntervalUI(index: number) {
    if (index >= 0 && index < this.intervals.length) {
      this.intervals.splice(index, 1);
      this.syncIntervalsToCourseFormGroup();
    }
  }

  // Añadir una fecha a un intervalo
  addCourseDateToInterval(intervalIndex: number) {
    if (intervalIndex < 0 || intervalIndex >= this.intervals.length) return;

    const interval = this.intervals[intervalIndex];
    const now = new Date();
    let newDate = now.toISOString().split('T')[0];

    // Si ya hay fechas, generar la siguiente fecha
    if (interval.dates.length > 0) {
      const lastDateStr = interval.dates[interval.dates.length - 1].date;
      if (lastDateStr) {
        const lastDate = new Date(lastDateStr);
        lastDate.setDate(lastDate.getDate() + 1);
        newDate = lastDate.toISOString().split('T')[0];
      }
    }

    // Crear objeto de fecha
    interval.dates.push({
      date: newDate,
      hour_start: this.courses.hours[0],
      duration: this.courses.duration[0],
      interval_id: interval.id,
      order: interval.dates.length + 1
    });

    this.syncIntervalsToCourseFormGroup();
  }

  // Eliminar una fecha de un intervalo
  removeCourseDateFromInterval(intervalIndex: number, dateIndex: number) {
    if (intervalIndex < 0 || intervalIndex >= this.intervals.length) return;

    const interval = this.intervals[intervalIndex];
    if (dateIndex < 0 || dateIndex >= interval.dates.length) return;

    interval.dates.splice(dateIndex, 1);
    this.syncIntervalsToCourseFormGroup();
  }

  // Sincronizar datos de intervalos con el FormGroup del curso
  syncIntervalsToCourseFormGroup() {
    if (!this.useMultipleIntervals || !this.courses.courseFormGroup) return;

    const courseDates = [];

    // Recorrer todos los intervalos y sus fechas
    for (const interval of this.intervals) {
      for (const dateObj of interval.dates) {
        if (dateObj.date && dateObj.hour_start) {
          courseDates.push({
            date: dateObj.date,
            hour_start: dateObj.hour_start,
            hour_end: this.courses.addMinutesToTime(dateObj.hour_start, dateObj.duration),
            duration: dateObj.duration,
            interval_id: interval.id,
            order: dateObj.order,
            course_groups: this.getCourseDateGroups(),
            groups: this.getCourseDateGroups()
          });
        }
      }
    }

    // Actualizar el curso con las fechas generadas
    if (courseDates.length > 0) {
      this.courses.courseFormGroup.patchValue({
        course_dates: courseDates
      });
    }
  }

  // Obtener los grupos de curso para cada fecha nueva
  getCourseDateGroups() {
    const existingDates = this.courses.courseFormGroup.get('course_dates').value;
    if (existingDates && existingDates.length > 0 && existingDates[0].course_groups) {
      return JSON.parse(JSON.stringify(existingDates[0].course_groups));
    }
    return [];
  }

  // Resetear a un solo intervalo
  resetToSingleInterval() {
    // Limpiar los intervalos
    this.intervals = [];

    // Asegurarnos de que course_dates tiene al menos una fecha
    const courseDates = this.courses.courseFormGroup.get('course_dates').value;
    if (!courseDates || courseDates.length === 0) {
      this.courses.courseFormGroup.patchValue({
        course_dates: [{ ...this.courses.default_course_dates }]
      });
    }
  }

  // Cargar intervalos desde un curso existente
  loadIntervalsFromCourse(courseData: any, component: any) {
    // Comprobar si el curso tiene configuración de intervalos múltiples
    if (courseData.settings) {
      try {
        const settings = courseData.settings;

        // Si tiene configuración de intervalos múltiples
        if (settings.multipleIntervals) {
          // Activar el switch en el componente
          component.useMultipleIntervals = true;
          component.mustBeConsecutive = settings.mustBeConsecutive || false;
          component.mustStartFromFirst = settings.mustStartFromFirst || false;

          // Agrupar las fechas por intervalos
          const courseDates = courseData.course_dates || [];
          const intervalMap: { [key: string]: any } = {};

          // Agrupar por interval_id
          courseDates.forEach(date => {
            const intervalId = date.interval_id || 'default';

            if (!intervalMap[intervalId]) {
              const matchingInterval = settings.intervals?.find(i => i.id === intervalId);

              intervalMap[intervalId] = {
                id: intervalId,
                name: date.interval_name || matchingInterval?.name || 'Intervalo',
                order: matchingInterval?.order || 0,
                dates: []
              };
            }

            intervalMap[intervalId].dates.push({
              date: date.date,
              hour_start: date.hour_start,
              hour_end: date.hour_end,
              duration: date.duration,
              order: date.order || 0
            });
          });

          // Convertir a array ordenado por `order`
          const intervalGroups = Object.values(intervalMap).sort((a: any, b: any) => a.order - b.order);

          // Actualizar el array de fechas en el formulario
          const datesArray = this.courses.courseFormGroup.controls['course_dates'] as FormArray;

          // Limpiar fechas actuales
          while (datesArray.length > 0) {
            datesArray.removeAt(0);
          }

          this.intervals = intervalGroups;

          // Añadir fechas agrupadas por intervalos
/*          Object.values(intervalGroups).forEach((group: any, groupIndex) => {
            // Ordenar fechas por orden
            const sortedDates = [...group.dates].sort((a, b) => a.order - b.order);

            // Añadir cada fecha al FormArray
            sortedDates.forEach((dateInfo, dateIndex) => {
              // Crear un nuevo objeto de fecha
              const newDate = {
                date: typeof dateInfo.date === 'string' ? dateInfo.date : new Date(dateInfo.date).toISOString().split('T')[0],
                hour_start: dateInfo.hour_start,
                hour_end: dateInfo.hour_end,
                interval_id: group.id,
                order: dateInfo.order || dateIndex
              };

              // Añadir al FormArray
              datesArray.push(this.fb.control(newDate));
            });
          });*/

          // Actualizar settings en el formulario
          const updatedSettings = {
            ...this.courses.courseFormGroup.controls['settings'].value,
            multipleIntervals: true,
            mustBeConsecutive: component.mustBeConsecutive,
            mustStartFromFirst: component.mustStartFromFirst,
            intervals: Object.values(intervalGroups).map((group:any) => ({
              id: group.id,
              name: group.name
            }))
          };

          this.courses.courseFormGroup.patchValue({
            settings: updatedSettings
          });
        }
      } catch (error) {
        console.error("Error parsing course settings:", error);
      }
    } else {
      // Si no tiene intervalos, inicializar con los valores por defecto
      //this.initializeDefaultInterval();
    }
  }


  monitorSelect(event: any, level: any, j: number) {
    let course_dates = this.courses.courseFormGroup.controls['course_dates'].value
    course_dates[event.i].course_groups[course_dates[event.i].course_groups.findIndex((a: any) => a.degree_id === level.id)].course_subgroups[j].monitor = event.monitor
    course_dates[event.i].course_groups[course_dates[event.i].course_groups.findIndex((a: any) => a.degree_id === level.id)].course_subgroups[j].monitor_id = event.monitor.id
    this.courses.courseFormGroup.patchValue({ course_dates })
  }
  deleteCourseDate(i: number) {
    this.courses.courseFormGroup.controls['course_dates'].value.splice(i, 1)
  }
}
