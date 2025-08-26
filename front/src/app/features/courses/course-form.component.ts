import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { animate, style, transition, trigger } from '@angular/animations';

interface Course {
  id?: number;
  name: string;
  type: 'ski' | 'snowboard' | 'both';
  level: 'beginner' | 'intermediate' | 'advanced' | 'expert';
  duration: number;
  price: number;
  capacity: number;
  monitorId?: number;
  startDate?: string;
  endDate?: string;
  schedule?: string;
  description?: string;
  prerequisites?: string;
  materials?: string;
  notes?: string;
  active: boolean;
}

@Component({
  selector: 'app-course-form',
  standalone: true,
  imports: [CommonModule, FormsModule],
  animations: [
    trigger('slideIn', [
      transition(':enter', [
        style({ opacity: 0, transform: 'translateX(100%)' }),
        animate('300ms ease-out', style({ opacity: 1, transform: 'translateX(0)' }))
      ]),
      transition(':leave', [
        animate('300ms ease-in', style({ opacity: 0, transform: 'translateX(100%)' }))
      ])
    ])
  ],
  template: `
    <div class="form-overlay" @slideIn>
      <div class="form-container">
        <header class="form-header">
          <div class="header-content">
            <h2 class="form-title">
              <svg class="title-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16v2H4zM4 8h16v2H4zM4 12h16v6H4z"></path>
              </svg>
              {{ isEditMode ? 'Editar Curso' : 'Nuevo Curso' }}
            </h2>
            <p class="form-subtitle">
              {{ isEditMode ? 'Modifica los detalles del curso' : 'Crea un nuevo curso para la escuela' }}
            </p>
          </div>
          <button class="close-btn" type="button" (click)="onCancel()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </header>

        <form class="course-form" (ngSubmit)="onSubmit()" #courseForm="ngForm">
          <!-- Basic Information -->
          <section class="form-section">
            <h3 class="section-title">Información Básica</h3>

            <div class="form-field">
              <label class="field-label" for="name">Nombre del Curso *</label>
              <input
                id="name"
                type="text"
                class="field-input"
                [(ngModel)]="course.name"
                name="name"
                required
                placeholder="Ej: Curso de Esquí para Principiantes"
                #nameField="ngModel"
              />
              <div class="field-error" *ngIf="nameField.invalid && nameField.touched">
                El nombre es obligatorio
              </div>
            </div>

            <div class="form-field">
              <label class="field-label" for="description">Descripción</label>
              <textarea
                id="description"
                class="field-textarea"
                [(ngModel)]="course.description"
                name="description"
                rows="3"
                placeholder="Descripción del curso"
              ></textarea>
            </div>

            <div class="form-field">
              <label class="field-label" for="type">Tipo *</label>
              <select
                id="type"
                class="field-select"
                [(ngModel)]="course.type"
                name="type"
                required
                #typeField="ngModel"
              >
                <option value="">Seleccionar tipo</option>
                <option value="ski">Esquí</option>
                <option value="snowboard">Snowboard</option>
                <option value="both">Ambos</option>
              </select>
              <div class="field-error" *ngIf="typeField.invalid && typeField.touched">
                El tipo es obligatorio
              </div>
            </div>
          </section>

          <!-- Configuration -->
          <section class="form-section">
            <h3 class="section-title">Configuración</h3>

            <div class="form-row">
              <div class="form-field">
                <label class="field-label" for="level">Nivel *</label>
                <select
                  id="level"
                  class="field-select"
                  [(ngModel)]="course.level"
                  name="level"
                  required
                  #levelField="ngModel"
                >
                  <option value="">Seleccionar nivel</option>
                  <option value="beginner">Principiante</option>
                  <option value="intermediate">Intermedio</option>
                  <option value="advanced">Avanzado</option>
                  <option value="expert">Experto</option>
                </select>
                <div class="field-error" *ngIf="levelField.invalid && levelField.touched">
                  El nivel es obligatorio
                </div>
              </div>

              <div class="form-field">
                <label class="field-label" for="duration">Duración (horas)</label>
                <input
                  id="duration"
                  type="number"
                  class="field-input"
                  [(ngModel)]="course.duration"
                  name="duration"
                  min="1"
                  placeholder="Ej: 2"
                  #durationField="ngModel"
                />
                <div class="field-error" *ngIf="durationField.invalid && durationField.touched">
                  Duración inválida
                </div>
              </div>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label class="field-label" for="price">Precio por persona (€)</label>
                <div class="input-with-addon">
                  <input
                    id="price"
                    type="number"
                    class="field-input"
                    [(ngModel)]="course.price"
                    name="price"
                    min="0"
                    step="0.01"
                    placeholder="0"
                    #priceField="ngModel"
                  />
                  <span class="input-addon">€</span>
                </div>
                <div class="field-error" *ngIf="priceField.invalid && priceField.touched">
                  Precio inválido
                </div>
              </div>

              <div class="form-field">
                <label class="field-label" for="capacity">Capacidad máxima</label>
                <input
                  id="capacity"
                  type="number"
                  class="field-input"
                  [(ngModel)]="course.capacity"
                  name="capacity"
                  min="1"
                  placeholder="Ej: 10"
                  #capacityField="ngModel"
                />
                <div class="field-error" *ngIf="capacityField.invalid && capacityField.touched">
                  Capacidad inválida
                </div>
              </div>
            </div>
          </section>

          <!-- Monitor -->
          <section class="form-section">
            <h3 class="section-title">Monitor Asignado</h3>
            <div class="form-field">
              <label class="field-label" for="monitor">Monitor</label>
              <select
                id="monitor"
                class="field-select"
                [(ngModel)]="course.monitorId"
                name="monitorId"
              >
                <option value="">Seleccionar monitor</option>
                <option *ngFor="let m of monitors" [value]="m.id">{{ m.name }}</option>
              </select>
            </div>
          </section>

          <!-- Scheduling -->
          <section class="form-section">
            <h3 class="section-title">Programación</h3>
            <div class="form-row">
              <div class="form-field">
                <label class="field-label" for="startDate">Fecha inicio</label>
                <input
                  id="startDate"
                  type="date"
                  class="field-input"
                  [(ngModel)]="course.startDate"
                  name="startDate"
                />
              </div>
              <div class="form-field">
                <label class="field-label" for="endDate">Fecha fin</label>
                <input
                  id="endDate"
                  type="date"
                  class="field-input"
                  [(ngModel)]="course.endDate"
                  name="endDate"
                />
              </div>
            </div>

            <div class="form-field">
              <label class="field-label" for="schedule">Horarios</label>
              <input
                id="schedule"
                type="text"
                class="field-input"
                [(ngModel)]="course.schedule"
                name="schedule"
                placeholder="Ej: 09:00 - 11:00"
              />
            </div>
          </section>

          <!-- Additional Notes -->
          <section class="form-section">
            <h3 class="section-title">Notas Adicionales</h3>

            <div class="form-field">
              <label class="field-label" for="prerequisites">Requisitos previos</label>
              <textarea
                id="prerequisites"
                class="field-textarea"
                [(ngModel)]="course.prerequisites"
                name="prerequisites"
                rows="2"
              ></textarea>
            </div>

            <div class="form-field">
              <label class="field-label" for="materials">Material incluido</label>
              <textarea
                id="materials"
                class="field-textarea"
                [(ngModel)]="course.materials"
                name="materials"
                rows="2"
              ></textarea>
            </div>

            <div class="form-field">
              <label class="field-label" for="notes">Notas adicionales</label>
              <textarea
                id="notes"
                class="field-textarea"
                [(ngModel)]="course.notes"
                name="notes"
                rows="3"
              ></textarea>
            </div>

            <div class="form-field">
              <label class="checkbox-field">
                <input
                  type="checkbox"
                  class="checkbox-input"
                  [(ngModel)]="course.active"
                  name="active"
                />
                <span class="checkbox-label">Curso activo</span>
              </label>
            </div>
          </section>

          <!-- Form Actions -->
          <div class="form-actions">
            <button type="button" class="btn btn--secondary" (click)="onCancel()">
              Cancelar
            </button>
            <button type="submit" class="btn btn--primary" [disabled]="courseForm.invalid || isSubmitting">
              <svg *ngIf="!isSubmitting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20,6 9,17 4,12"></polyline>
              </svg>
              <svg *ngIf="isSubmitting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spinner">
                <path d="M21,12a9,9 0 1,1-6.219-8.56"></path>
              </svg>
              {{ isSubmitting ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  `,
  styleUrl: './course-form.component.scss'
})
export class CourseFormComponent {
  @Input() course: Partial<Course> = {};
  @Output() save = new EventEmitter<Partial<Course>>();
  @Output() cancel = new EventEmitter<void>();

  isSubmitting = false;

  monitors = [
    { id: 1, name: 'Ana García' },
    { id: 2, name: 'Juan Pérez' },
    { id: 3, name: 'Lucía Fernández' }
  ];

  get isEditMode(): boolean {
    return !!this.course.id;
  }

  ngOnInit() {
    if (!this.course.id) {
      this.course = {
        name: '',
        type: '' as any,
        level: '' as any,
        duration: 1,
        price: 0,
        capacity: 1,
        monitorId: undefined,
        startDate: '',
        endDate: '',
        schedule: '',
        description: '',
        prerequisites: '',
        materials: '',
        notes: '',
        active: true
      };
    }
  }

  onSubmit() {
    if (this.isSubmitting) return;
    this.isSubmitting = true;
    setTimeout(() => {
      this.save.emit(this.course);
      this.isSubmitting = false;
    }, 1000);
  }

  onCancel() {
    this.cancel.emit();
  }
}

