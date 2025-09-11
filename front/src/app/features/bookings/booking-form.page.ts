import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, ActivatedRoute } from '@angular/router';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatStepperModule } from '@angular/material/stepper';
import { MatChipsModule } from '@angular/material/chips';

import { BookingsService } from './services/bookings.service';
import { ClientsService } from '../clients/services/clients.service';
import { CoursesService } from '../courses/services/courses.service';
import { PageLayoutComponent } from '../../shared/components/layout/page-layout.component';
import { LoaderComponent } from '../../shared/components/ui/loader/loader.component';
import { JoinPipe } from '../../shared/pipes/join.pipe';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-booking-form',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatDatepickerModule,
    MatAutocompleteModule,
    MatCheckboxModule,
    MatStepperModule,
    MatChipsModule,
    PageLayoutComponent,
    LoaderComponent,
    JoinPipe,
    FormsModule,
  ],
  template: `
    <app-page-layout>
      <div class="page-header">
        <div class="page-title">
          <div class="breadcrumb">
            <a routerLink="/bookings" class="text-blue-600 hover:underline">Reservas</a>
            <span class="mx-2">/</span>
            <span>{{ isEdit ? 'Editar' : 'Nueva' }} reserva</span>
          </div>
          <h1 class="text-2xl font-bold">{{ isEdit ? 'Editar' : 'Crear' }} reserva</h1>
        </div>
      </div>

      <form [formGroup]="bookingForm" (ngSubmit)="onSubmit()">
        <mat-stepper #stepper linear>
          <!-- Paso 1: Cliente -->
          <mat-step [stepControl]="clientFormGroup">
            <ng-template matStepLabel>Cliente</ng-template>
            <div [formGroup]="clientFormGroup" class="step-content">
              <mat-card>
                <mat-card-header>
                  <mat-card-title>Seleccionar cliente</mat-card-title>
                </mat-card-header>
                <mat-card-content>
                  <div class="form-grid">
                    <mat-form-field class="full-width">
                      <mat-label>Buscar cliente</mat-label>
                      <input 
                        matInput 
                        formControlName="client"
                        [matAutocomplete]="clientAuto"
                        placeholder="Nombre, email o teléfono">
                      <mat-autocomplete #clientAuto="matAutocomplete" [displayWith]="displayClientFn">
                        <mat-option *ngFor="let client of filteredClients" [value]="client">
                          <div class="client-option">
                            <div class="font-medium">{{ client.full_name }}</div>
                            <div class="text-sm text-gray-500">{{ client.email }}</div>
                          </div>
                        </mat-option>
                      </mat-autocomplete>
                    </mat-form-field>

                    <button 
                      mat-raised-button 
                      color="accent" 
                      type="button"
                      (click)="createNewClient()">
                      <mat-icon>person_add</mat-icon>
                      Nuevo cliente
                    </button>
                  </div>

                  <!-- Cliente seleccionado -->
                  <div class="selected-client mt-4" *ngIf="selectedClient">
                    <mat-card>
                      <mat-card-content>
                        <div class="client-info">
                          <h3>{{ selectedClient.full_name }}</h3>
                          <p>{{ selectedClient.email }} | {{ selectedClient.phone }}</p>
                          <div class="client-stats">
                            <span class="stat">{{ selectedClient.total_bookings || 0 }} reservas</span>
                            <span class="stat">Cliente desde {{ selectedClient.created_at | date:'yyyy' }}</span>
                          </div>
                        </div>
                      </mat-card-content>
                    </mat-card>
                  </div>
                </mat-card-content>
              </mat-card>

              <div class="step-actions">
                <button mat-raised-button color="primary" matStepperNext [disabled]="!clientFormGroup.valid">
                  Siguiente
                </button>
              </div>
            </div>
          </mat-step>

          <!-- Paso 2: Curso/Actividad -->
          <mat-step [stepControl]="courseFormGroup">
            <ng-template matStepLabel>Curso</ng-template>
            <div [formGroup]="courseFormGroup" class="step-content">
              <mat-card>
                <mat-card-header>
                  <mat-card-title>Seleccionar curso o actividad</mat-card-title>
                </mat-card-header>
                <mat-card-content>
                  <div class="form-grid">
                    <mat-form-field>
                      <mat-label>Buscar curso</mat-label>
                      <input 
                        matInput 
                        formControlName="course"
                        [matAutocomplete]="courseAuto"
                        placeholder="Nombre del curso o actividad">
                      <mat-autocomplete #courseAuto="matAutocomplete" [displayWith]="displayCourseFn">
                        <mat-option *ngFor="let course of filteredCourses" [value]="course">
                          <div class="course-option">
                            <div class="font-medium">{{ course.title }}</div>
                            <div class="text-sm text-gray-500">
                              {{ course.dates | join:', ' }} | {{ course.price | currency:'EUR':'symbol':'1.2-2' }}
                            </div>
                          </div>
                        </mat-option>
                      </mat-autocomplete>
                    </mat-form-field>

                    <mat-form-field>
                      <mat-label>Categoría</mat-label>
                      <mat-select formControlName="category">
                        <mat-option value="course">Cursos</mat-option>
                        <mat-option value="activity">Actividades</mat-option>
                        <mat-option value="private">Clases privadas</mat-option>
                      </mat-select>
                    </mat-form-field>
                  </div>

                  <!-- Curso seleccionado -->
                  <div class="selected-course mt-4" *ngIf="selectedCourse">
                    <mat-card>
                      <mat-card-content>
                        <div class="course-info">
                          <h3>{{ selectedCourse.title }}</h3>
                          <p>{{ selectedCourse.description }}</p>
                          
                          <div class="course-details">
                            <div class="detail-item">
                              <mat-icon>event</mat-icon>
                              <span>{{ selectedCourse.dates | join:', ' }}</span>
                            </div>
                            <div class="detail-item">
                              <mat-icon>schedule</mat-icon>
                              <span>{{ selectedCourse.schedule }}</span>
                            </div>
                            <div class="detail-item">
                              <mat-icon>euro</mat-icon>
                              <span>{{ selectedCourse.price | currency:'EUR':'symbol':'1.2-2' }}</span>
                            </div>
                            <div class="detail-item">
                              <mat-icon>group</mat-icon>
                              <span>{{ selectedCourse.available_spots }} plazas disponibles</span>
                            </div>
                          </div>
                        </div>
                      </mat-card-content>
                    </mat-card>
                  </div>
                </mat-card-content>
              </mat-card>

              <div class="step-actions">
                <button mat-button matStepperPrevious>Anterior</button>
                <button mat-raised-button color="primary" matStepperNext [disabled]="!courseFormGroup.valid">
                  Siguiente
                </button>
              </div>
            </div>
          </mat-step>

          <!-- Paso 3: Participantes -->
          <mat-step [stepControl]="participantsFormGroup">
            <ng-template matStepLabel>Participantes</ng-template>
            <div [formGroup]="participantsFormGroup" class="step-content">
              <mat-card>
                <mat-card-header>
                  <mat-card-title>Información de participantes</mat-card-title>
                </mat-card-header>
                <mat-card-content>
                  <div class="participants-section">
                    <div class="participant-form" *ngFor="let participant of participants; let i = index">
                      <h4>Participante {{ i + 1 }}</h4>
                      <div class="form-grid">
                        <mat-form-field>
                          <mat-label>Nombre completo</mat-label>
                          <input matInput [(ngModel)]="participant.name" required>
                        </mat-form-field>

                        <mat-form-field>
                          <mat-label>Edad</mat-label>
                          <input matInput type="number" [(ngModel)]="participant.age" required>
                        </mat-form-field>

                        <mat-form-field>
                          <mat-label>Nivel</mat-label>
                          <mat-select [(ngModel)]="participant.level">
                            <mat-option value="beginner">Principiante</mat-option>
                            <mat-option value="intermediate">Intermedio</mat-option>
                            <mat-option value="advanced">Avanzado</mat-option>
                          </mat-select>
                        </mat-form-field>

                        <mat-form-field>
                          <mat-label>Observaciones médicas</mat-label>
                          <textarea matInput [(ngModel)]="participant.medical_notes"></textarea>
                        </mat-form-field>
                      </div>

                      <button 
                        mat-icon-button 
                        color="warn" 
                        (click)="removeParticipant(i)"
                        *ngIf="participants.length > 1">
                        <mat-icon>delete</mat-icon>
                      </button>
                    </div>

                    <button mat-stroked-button (click)="addParticipant()" type="button">
                      <mat-icon>person_add</mat-icon>
                      Añadir participante
                    </button>
                  </div>
                </mat-card-content>
              </mat-card>

              <div class="step-actions">
                <button mat-button matStepperPrevious>Anterior</button>
                <button mat-raised-button color="primary" matStepperNext>
                  Siguiente
                </button>
              </div>
            </div>
          </mat-step>

          <!-- Paso 4: Extras y equipamiento -->
          <mat-step [stepControl]="extrasFormGroup">
            <ng-template matStepLabel>Extras</ng-template>
            <div [formGroup]="extrasFormGroup" class="step-content">
              <!-- Equipamiento -->
              <mat-card class="mb-4">
                <mat-card-header>
                  <mat-card-title>Alquiler de equipamiento</mat-card-title>
                </mat-card-header>
                <mat-card-content>
                  <div class="equipment-section">
                    <mat-checkbox *ngFor="let equipment of availableEquipment" class="equipment-item">
                      {{ equipment.name }} - {{ equipment.price | currency:'EUR':'symbol':'1.2-2' }}/día
                    </mat-checkbox>
                  </div>
                </mat-card-content>
              </mat-card>

              <!-- Extras del curso -->
              <mat-card class="mb-4">
                <mat-card-header>
                  <mat-card-title>Servicios adicionales</mat-card-title>
                </mat-card-header>
                <mat-card-content>
                  <div class="extras-section">
                    <mat-checkbox *ngFor="let extra of availableExtras" class="extra-item">
                      {{ extra.name }} - {{ extra.price | currency:'EUR':'symbol':'1.2-2' }}
                    </mat-checkbox>
                  </div>
                </mat-card-content>
              </mat-card>

              <!-- Descuentos -->
              <mat-card>
                <mat-card-header>
                  <mat-card-title>Descuentos y promociones</mat-card-title>
                </mat-card-header>
                <mat-card-content>
                  <mat-form-field class="full-width">
                    <mat-label>Código promocional</mat-label>
                    <input matInput formControlName="promoCode">
                    <button mat-icon-button matSuffix (click)="applyPromoCode()">
                      <mat-icon>check</mat-icon>
                    </button>
                  </mat-form-field>

                  <div class="discount-applied" *ngIf="appliedDiscount">
                    <mat-chip color="accent" selected>
                      {{ appliedDiscount.name }}: -{{ appliedDiscount.amount | currency:'EUR':'symbol':'1.2-2' }}
                    </mat-chip>
                  </div>
                </mat-card-content>
              </mat-card>

              <div class="step-actions">
                <button mat-button matStepperPrevious>Anterior</button>
                <button mat-raised-button color="primary" matStepperNext>
                  Siguiente
                </button>
              </div>
            </div>
          </mat-step>

          <!-- Paso 5: Confirmación -->
          <mat-step>
            <ng-template matStepLabel>Confirmación</ng-template>
            <div class="step-content">
              <mat-card class="mb-4">
                <mat-card-header>
                  <mat-card-title>Resumen de la reserva</mat-card-title>
                </mat-card-header>
                <mat-card-content>
                  <div class="booking-summary">
                    <!-- Cliente -->
                    <div class="summary-section">
                      <h3>Cliente</h3>
                      <p>{{ selectedClient?.full_name }}</p>
                      <p class="text-sm text-gray-500">{{ selectedClient?.email }}</p>
                    </div>

                    <!-- Curso -->
                    <div class="summary-section">
                      <h3>Curso/Actividad</h3>
                      <p>{{ selectedCourse?.title }}</p>
                      <p class="text-sm text-gray-500">{{ selectedCourse?.dates | join:', ' }}</p>
                    </div>

                    <!-- Participantes -->
                    <div class="summary-section">
                      <h3>Participantes ({{ participants.length }})</h3>
                      <ul>
                        <li *ngFor="let participant of participants">
                          {{ participant.name }} ({{ participant.age }} años, {{ participant.level }})
                        </li>
                      </ul>
                    </div>

                    <!-- Precio total -->
                    <div class="price-summary">
                      <div class="price-row">
                        <span>Precio base:</span>
                        <span>{{ calculateBasePrice() | currency:'EUR':'symbol':'1.2-2' }}</span>
                      </div>
                      <div class="price-row" *ngIf="calculateExtrasPrice() > 0">
                        <span>Extras:</span>
                        <span>{{ calculateExtrasPrice() | currency:'EUR':'symbol':'1.2-2' }}</span>
                      </div>
                      <div class="price-row" *ngIf="appliedDiscount">
                        <span>Descuento:</span>
                        <span class="text-green-600">-{{ appliedDiscount.amount | currency:'EUR':'symbol':'1.2-2' }}</span>
                      </div>
                      <div class="price-row total">
                        <span>Total:</span>
                        <span>{{ calculateTotalPrice() | currency:'EUR':'symbol':'1.2-2' }}</span>
                      </div>
                    </div>
                  </div>
                </mat-card-content>
              </mat-card>

              <!-- Observaciones finales -->
              <mat-card class="mb-4">
                <mat-card-header>
                  <mat-card-title>Observaciones</mat-card-title>
                </mat-card-header>
                <mat-card-content>
                  <mat-form-field class="full-width">
                    <mat-label>Notas adicionales</mat-label>
                    <textarea matInput formControlName="notes" rows="3"></textarea>
                  </mat-form-field>
                </mat-card-content>
              </mat-card>

              <div class="step-actions">
                <button mat-button matStepperPrevious>Anterior</button>
                <button 
                  mat-raised-button 
                  color="primary" 
                  type="submit"
                  [disabled]="loading">
                  <mat-icon>save</mat-icon>
                  {{ isEdit ? 'Actualizar' : 'Crear' }} reserva
                </button>
              </div>
            </div>
          </mat-step>
        </mat-stepper>
      </form>

      <app-loader *ngIf="loading"></app-loader>
    </app-page-layout>
  `,
  styles: [`
    .step-content {
      @apply mt-4;
    }

    .step-actions {
      @apply flex justify-end gap-2 mt-6;
    }

    .form-grid {
      @apply grid grid-cols-1 md:grid-cols-2 gap-4;
    }

    .full-width {
      @apply w-full;
    }

    .client-option, .course-option {
      @apply space-y-1;
    }

    .selected-client, .selected-course {
      border: 2px solid #e3f2fd;
      border-radius: 8px;
    }

    .client-info, .course-info {
      @apply space-y-2;
    }

    .client-stats {
      @apply flex gap-4 text-sm text-gray-500;
    }

    .course-details {
      @apply space-y-2 mt-3;
    }

    .detail-item {
      @apply flex items-center gap-2;
    }

    .participants-section {
      @apply space-y-4;
    }

    .participant-form {
      @apply relative p-4 border border-gray-200 rounded-lg;
    }

    .equipment-section, .extras-section {
      @apply space-y-2;
    }

    .equipment-item, .extra-item {
      @apply block;
    }

    .discount-applied {
      @apply mt-2;
    }

    .booking-summary {
      @apply space-y-6;
    }

    .summary-section h3 {
      @apply font-medium text-lg mb-2;
    }

    .price-summary {
      @apply border-t pt-4 space-y-2;
    }

    .price-row {
      @apply flex justify-between;
    }

    .price-row.total {
      @apply font-bold text-lg border-t pt-2;
    }
  `]
})
export class BookingFormPage implements OnInit {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private bookingsService = inject(BookingsService);
  private clientsService = inject(ClientsService);
  private coursesService = inject(CoursesService);

  isEdit = false;
  loading = false;
  bookingId: string | null = null;

  // Form groups
  bookingForm: FormGroup;
  clientFormGroup: FormGroup;
  courseFormGroup: FormGroup;
  participantsFormGroup: FormGroup;
  extrasFormGroup: FormGroup;

  // Data
  filteredClients: any[] = [];
  filteredCourses: any[] = [];
  selectedClient: any = null;
  selectedCourse: any = null;
  participants: any[] = [{ name: '', age: '', level: 'beginner', medical_notes: '' }];
  availableEquipment: any[] = [];
  availableExtras: any[] = [];
  appliedDiscount: any = null;

  constructor() {
    this.bookingForm = this.fb.group({
      notes: ['']
    });

    this.clientFormGroup = this.fb.group({
      client: ['', Validators.required]
    });

    this.courseFormGroup = this.fb.group({
      course: ['', Validators.required],
      category: ['course']
    });

    this.participantsFormGroup = this.fb.group({});

    this.extrasFormGroup = this.fb.group({
      promoCode: ['']
    });
  }

  ngOnInit() {
    this.bookingId = this.route.snapshot.params['id'];
    this.isEdit = !!this.bookingId;

    if (this.isEdit) {
      this.loadBooking(this.bookingId!);
    }
  }

  loadBooking(id: string) {
    // TODO: Implement with BookingsService
  }

  displayClientFn = (client: any): string => {
    return client ? client.full_name : '';
  };

  displayCourseFn = (course: any): string => {
    return course ? course.title : '';
  };

  createNewClient() {
    // TODO: Open client creation modal
  }

  addParticipant() {
    this.participants.push({ name: '', age: '', level: 'beginner', medical_notes: '' });
  }

  removeParticipant(index: number) {
    this.participants.splice(index, 1);
  }

  applyPromoCode() {
    // TODO: Implement promo code validation
  }

  calculateBasePrice(): number {
    return this.selectedCourse?.price * this.participants.length || 0;
  }

  calculateExtrasPrice(): number {
    // TODO: Calculate selected extras price
    return 0;
  }

  calculateTotalPrice(): number {
    const base = this.calculateBasePrice();
    const extras = this.calculateExtrasPrice();
    const discount = this.appliedDiscount?.amount || 0;
    return base + extras - discount;
  }

  onSubmit() {
    if (this.bookingForm.valid) {
      this.loading = true;
      
      const bookingData = {
        client: this.selectedClient,
        course: this.selectedCourse,
        participants: this.participants,
        notes: this.bookingForm.get('notes')?.value,
        total_price: this.calculateTotalPrice()
      };

      // TODO: Implement save logic with BookingsService
      
      setTimeout(() => {
        this.loading = false;
        this.router.navigate(['/bookings']);
      }, 2000);
    }
  }
}