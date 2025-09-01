import { Component, Input, OnInit, Output, EventEmitter } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { debounceTime, map, startWith, switchMap } from 'rxjs/operators';
import { Observable } from 'rxjs';
import { ApiCrudService } from 'src/service/crud.service';
import { UtilsService } from 'src/service/utils.service';

@Component({
  selector: 'booking-step-three',
  templateUrl: './step-three.component.html',
  styleUrls: ['./step-three.component.scss'],
})
export class StepThreeComponent implements OnInit {
  @Input() initialData: any;
  @Input() utilizers: any;
  @Output() stepCompleted = new EventEmitter<FormGroup>();
  @Output() prevStep = new EventEmitter();

  selectedLevelColor: string = ''; // Variable para almacenar el color del nivel seleccionado
  stepForm: FormGroup;
  sportData: any[] = [];
  filteredLevels: Observable<any[]>;
  levels: any[] = [];

  constructor(
    private fb: FormBuilder,
    private crudService: ApiCrudService,
    private utilsService: UtilsService
  ) {}

  ngOnInit(): void {
    this.initializeForm();
    this.loadSports();
  }

  initializeForm() {
    this.stepForm = this.fb.group({
      sport: [null, Validators.required],
      sportLevel: [null, Validators.required],
    });

    // Preseleccionar el deporte si existe `initialData`
    if (this.initialData?.sport) {
      this.stepForm.patchValue({
        sport: this.initialData.sport,
      });
      this.loadLevels(this.initialData.sport); // Cargar niveles para el deporte preseleccionado
    }

    // Cuando se selecciona un deporte, se cargan los grados (niveles)
    this.stepForm.get('sport').valueChanges.subscribe((sport) => {
      if (sport) {
        this.stepForm.get('sportLevel').reset(); // Resetear el nivel al cambiar el deporte
        this.loadLevels(sport);
      }
    });

    this.stepForm.get('sportLevel').valueChanges.subscribe((level) => {
      if (level && level.color) {
        this.selectedLevelColor = level.color; // Guardar color del nivel seleccionado
      } else {
        this.selectedLevelColor = ''; // Resetear si no hay nivel seleccionado
      }
    });

    // Filtrar niveles en función de la búsqueda en el input
    this.filteredLevels = this.stepForm.get('sportLevel').valueChanges.pipe(
      startWith(''),
      debounceTime(300),
      map((value) => (typeof value === 'string' ? value : this.formatLevel(value))),
      map((value) => this.filterLevels(value || ''))
    );
  }

  formatLevel(level: any): string {
    // Asegurarse de que league, level, y name existen y no son nulos
    const league = level?.league || '';
    const levelName = level?.level || '';
    const name = level?.name || '';

    return `${league} ${levelName} ${name}`.trim();
  }

  // Método para cargar los deportes
  loadSports() {
    this.crudService
      .list(
        '/school-sports',
        1,
        10000,
        'asc',
        'id',
        `&school_id=${this.getUserSchoolId()}`,
        null,
        null,
        null,
        ['sport.degrees']
      )
      .subscribe((response) => {
        this.sportData = response.data;
      });
  }

  // Método para cargar los niveles (grados) según el deporte seleccionado
  loadLevels(selectedSport) {
    if (selectedSport?.degrees) {
      this.levels = selectedSport.degrees;

      // Filtrar niveles según la edad máxima del utilizer más grande
      const maxUtilizerAge = this.getMaxUtilizerAge();
      this.levels = this.levels.filter(
        (level) =>
          maxUtilizerAge >= level.age_min && maxUtilizerAge <= level.age_max
          && level.school_id == this.getUserSchoolId()
          && level.active
      );
      if (!this.initialData?.sportLevel) {
        const lowestDegree = this.getLowestDegreeForSport(selectedSport.id);
        if (lowestDegree) {
          this.stepForm.patchValue({
            sportLevel: lowestDegree,
          });
        }
      }
      // Preseleccionar el nivel si existe en `initialData`
      if (this.initialData?.sportLevel) {
        this.stepForm.patchValue({
          sportLevel: this.initialData.sportLevel,
        });
      }
    } else {
      this.levels = []; // Si no hay grados, vaciar el array
    }
  }

  getLowestDegreeForSport(sportId: number) {
    if (!this.utilizers || this.utilizers.length === 0) return null;

    const schoolId = this.utilsService.getSchoolData()?.id; // Obtener el ID de la escuela actual
    if (!schoolId) return null; // Asegurar que hay un schoolId válido

    const degrees = this.utilizers
      .flatMap(utilizer => utilizer.client_sports) // Obtener todos los client_sports
      .filter(cs => cs.sport_id === sportId && cs.school_id === schoolId) // Filtrar por sport y school
      .map(cs => cs.degree) // Obtener los degrees
      .filter(degree => degree); // Eliminar posibles `null`

    return degrees.length ? degrees.reduce((min, degree) => degree.id < min.id ? degree : min) : null;
  }

  // Filtro de niveles para el autocompletado
  filterLevels(value: string) {
    const filterValue = value.toLowerCase();
    return this.levels.filter(option =>
      (option.league && option.league.toLowerCase().includes(filterValue)) ||
      (option.level && option.level.toLowerCase().includes(filterValue)) ||
      (option.name && option.name.toLowerCase().includes(filterValue))
    );
  }

  // Obtener la edad máxima entre los utilizadores
  getMaxUtilizerAge(): number {
    return Math.max(
      ...this.utilizers.map((utilizer) =>
        this.utilsService.calculateYears(utilizer.birth_date)
      )
    );
  }

  // Obtener el ID de la escuela del usuario
  getUserSchoolId(): number {
    const user = JSON.parse(localStorage.getItem('boukiiUser'));
    return user.schools[0].id;
  }

  displayFnLevel(level: any): string {
    return level ? `${level.league} - ${level.name}` : '';
  }

  isFormValid() {
    return this.stepForm.valid;
  }

  handlePrevStep() {
    this.prevStep.emit();
  }

  completeStep() {
    if (this.isFormValid()) {
      this.stepCompleted.emit(this.stepForm);
    }
  }
}
