import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatChipsModule } from '@angular/material/chips';
import { MatTabsModule } from '@angular/material/tabs';
import { MatListModule } from '@angular/material/list';
import { MatDividerModule } from '@angular/material/divider';
import { MatMenuModule } from '@angular/material/menu';

import { BookingsService } from './services/bookings.service';
import { Booking } from './models/booking.interface';
import { PageLayoutComponent } from '../../shared/components/layout/page-layout.component';
import { LoaderComponent } from '../../shared/components/ui/loader/loader.component';
import { JoinPipe } from '../../shared/pipes/join.pipe';

@Component({
  selector: 'app-booking-detail',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatChipsModule,
    MatTabsModule,
    MatListModule,
    MatDividerModule,
    MatMenuModule,
    PageLayoutComponent,
    LoaderComponent,
    JoinPipe,
  ],
  template: `
    <app-page-layout>
      <div class="page-header" *ngIf="booking">
        <div class="page-title">
          <div class="breadcrumb">
            <a routerLink="/bookings" class="text-blue-600 hover:underline">Reservas</a>
            <span class="mx-2">/</span>
            <span>Reserva #{{ booking.id }}</span>
          </div>
          <h1 class="text-2xl font-bold">{{ booking.client.full_name }}</h1>
          <p class="text-gray-600">{{ booking.course.title }}</p>
        </div>
        
        <div class="page-actions">
          <button mat-icon-button [matMenuTriggerFor]="menu">
            <mat-icon>more_vert</mat-icon>
          </button>
          <mat-menu #menu="matMenu">
            <button mat-menu-item [routerLink]="['/bookings', booking.id, 'edit']">
              <mat-icon>edit</mat-icon>
              Editar reserva
            </button>
            <button mat-menu-item (click)="duplicateBooking()">
              <mat-icon>content_copy</mat-icon>
              Duplicar reserva
            </button>
            <mat-divider></mat-divider>
            <button mat-menu-item (click)="confirmBooking()" *ngIf="booking.status === 'pending'">
              <mat-icon>check</mat-icon>
              Confirmar reserva
            </button>
            <button mat-menu-item (click)="markAsPaid()" *ngIf="booking.status === 'confirmed'">
              <mat-icon>payment</mat-icon>
              Marcar como pagada
            </button>
            <button mat-menu-item (click)="cancelBooking()" *ngIf="booking.status !== 'cancelled'">
              <mat-icon>cancel</mat-icon>
              Cancelar reserva
            </button>
          </mat-menu>

          <button mat-raised-button color="primary" (click)="sendConfirmation()">
            <mat-icon>email</mat-icon>
            Enviar confirmación
          </button>
        </div>
      </div>

      <div class="content-grid" *ngIf="booking">
        <!-- Información principal -->
        <div class="main-content">
          <mat-tab-group>
            <!-- Tab: Detalles -->
            <mat-tab label="Detalles de la reserva">
              <div class="tab-content">
                <!-- Estado y fechas -->
                <mat-card class="mb-4">
                  <mat-card-header>
                    <mat-card-title>Estado de la reserva</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="status-info">
                      <mat-chip [color]="getStatusColor(booking.status)" selected class="status-chip">
                        {{ getStatusLabel(booking.status) }}
                      </mat-chip>
                      
                      <div class="status-dates">
                        <div class="date-item">
                          <span class="label">Creada:</span>
                          <span class="value">{{ booking.created_at | date:'dd/MM/yyyy HH:mm' }}</span>
                        </div>
                        <div class="date-item" *ngIf="booking.confirmed_at">
                          <span class="label">Confirmada:</span>
                          <span class="value">{{ booking.confirmed_at | date:'dd/MM/yyyy HH:mm' }}</span>
                        </div>
                        <div class="date-item" *ngIf="booking.paid_at">
                          <span class="label">Pagada:</span>
                          <span class="value">{{ booking.paid_at | date:'dd/MM/yyyy HH:mm' }}</span>
                        </div>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>

                <!-- Detalles del curso -->
                <mat-card class="mb-4">
                  <mat-card-header>
                    <mat-card-title>Curso/Actividad</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="course-details">
                      <h3 class="course-title">{{ booking.course.title }}</h3>
                      <p class="course-description">{{ booking.course.description }}</p>
                      
                      <div class="course-info-grid">
                        <div class="info-item">
                          <mat-icon>event</mat-icon>
                          <div>
                            <div class="label">Fechas</div>
                            <div class="value">{{ booking.course.dates | join:', ' }}</div>
                          </div>
                        </div>
                        
                        <div class="info-item">
                          <mat-icon>schedule</mat-icon>
                          <div>
                            <div class="label">Horario</div>
                            <div class="value">{{ booking.course.schedule }}</div>
                          </div>
                        </div>
                        
                        <div class="info-item">
                          <mat-icon>person</mat-icon>
                          <div>
                            <div class="label">Instructor</div>
                            <div class="value">{{ booking.course.instructor?.name || 'Sin asignar' }}</div>
                          </div>
                        </div>
                        
                        <div class="info-item">
                          <mat-icon>location_on</mat-icon>
                          <div>
                            <div class="label">Ubicación</div>
                            <div class="value">{{ booking.course.location }}</div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>

                <!-- Participantes -->
                <mat-card class="mb-4" *ngIf="booking.participants?.length">
                  <mat-card-header>
                    <mat-card-title>Participantes ({{ booking.participants.length }})</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <mat-list>
                      <mat-list-item *ngFor="let participant of booking.participants">
                        <div matListItemTitle>{{ participant.name }}</div>
                        <div matListItemLine>
                          Edad: {{ participant.age }} años | Nivel: {{ participant.level }}
                        </div>
                      </mat-list-item>
                    </mat-list>
                  </mat-card-content>
                </mat-card>

                <!-- Observaciones -->
                <mat-card *ngIf="booking.notes">
                  <mat-card-header>
                    <mat-card-title>Observaciones</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <p>{{ booking.notes }}</p>
                  </mat-card-content>
                </mat-card>
              </div>
            </mat-tab>

            <!-- Tab: Pagos -->
            <mat-tab label="Pagos">
              <div class="tab-content">
                <!-- Resumen económico -->
                <mat-card class="mb-4">
                  <mat-card-header>
                    <mat-card-title>Resumen económico</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="payment-summary">
                      <div class="summary-row">
                        <span>Precio base:</span>
                        <span>{{ booking.base_price | currency:'EUR':'symbol':'1.2-2' }}</span>
                      </div>
                      <div class="summary-row" *ngIf="booking.extras_price > 0">
                        <span>Extras:</span>
                        <span>{{ booking.extras_price | currency:'EUR':'symbol':'1.2-2' }}</span>
                      </div>
                      <div class="summary-row" *ngIf="booking.discount_amount > 0">
                        <span>Descuento:</span>
                        <span class="text-green-600">-{{ booking.discount_amount | currency:'EUR':'symbol':'1.2-2' }}</span>
                      </div>
                      <mat-divider class="my-2"></mat-divider>
                      <div class="summary-row total">
                        <span>Total:</span>
                        <span>{{ booking.total_price | currency:'EUR':'symbol':'1.2-2' }}</span>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>

                <!-- Historial de pagos -->
                <mat-card *ngIf="booking.payments?.length">
                  <mat-card-header>
                    <mat-card-title>Historial de pagos</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <mat-list>
                      <mat-list-item *ngFor="let payment of booking.payments">
                        <div matListItemTitle>
                          {{ payment.amount | currency:'EUR':'symbol':'1.2-2' }}
                          <mat-chip [color]="payment.status === 'completed' ? 'primary' : 'warn'" selected class="ml-2">
                            {{ payment.status }}
                          </mat-chip>
                        </div>
                        <div matListItemLine>
                          {{ payment.method }} | {{ payment.created_at | date:'dd/MM/yyyy HH:mm' }}
                        </div>
                      </mat-list-item>
                    </mat-list>
                  </mat-card-content>
                </mat-card>
              </div>
            </mat-tab>

            <!-- Tab: Equipamiento -->
            <mat-tab label="Equipamiento" *ngIf="booking.equipment?.length">
              <div class="tab-content">
                <mat-card>
                  <mat-card-header>
                    <mat-card-title>Material alquilado</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <mat-list>
                      <mat-list-item *ngFor="let equipment of booking.equipment">
                        <div matListItemTitle>{{ equipment.item.name }}</div>
                        <div matListItemLine>
                          Cantidad: {{ equipment.quantity }} | 
                          Talla: {{ equipment.size }} |
                          Precio: {{ equipment.price | currency:'EUR':'symbol':'1.2-2' }}
                        </div>
                      </mat-list-item>
                    </mat-list>
                  </mat-card-content>
                </mat-card>
              </div>
            </mat-tab>
          </mat-tab-group>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
          <!-- Información del cliente -->
          <mat-card class="mb-4">
            <mat-card-header>
              <mat-card-title>Cliente</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="client-info">
                <h3 class="font-medium">{{ booking.client.full_name }}</h3>
                <div class="contact-info">
                  <div class="contact-item">
                    <mat-icon>email</mat-icon>
                    <span>{{ booking.client.email }}</span>
                  </div>
                  <div class="contact-item" *ngIf="booking.client.phone">
                    <mat-icon>phone</mat-icon>
                    <span>{{ booking.client.phone }}</span>
                  </div>
                </div>
                
                <button mat-button color="primary" [routerLink]="['/clients', booking.client.id]" class="w-full mt-2">
                  Ver perfil completo
                </button>
              </div>
            </mat-card-content>
          </mat-card>

          <!-- Acciones rápidas -->
          <mat-card>
            <mat-card-header>
              <mat-card-title>Acciones rápidas</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="quick-actions">
                <button mat-stroked-button class="w-full mb-2" (click)="printBooking()">
                  <mat-icon>print</mat-icon>
                  Imprimir reserva
                </button>
                
                <button mat-stroked-button class="w-full mb-2" (click)="exportBooking()">
                  <mat-icon>download</mat-icon>
                  Exportar PDF
                </button>
                
                <button mat-stroked-button class="w-full mb-2" (click)="sendReminder()">
                  <mat-icon>notification_important</mat-icon>
                  Enviar recordatorio
                </button>
                
                <button mat-stroked-button class="w-full" (click)="openChat()">
                  <mat-icon>chat</mat-icon>
                  Abrir chat
                </button>
              </div>
            </mat-card-content>
          </mat-card>
        </div>
      </div>

      <app-loader *ngIf="loading"></app-loader>
    </app-page-layout>
  `,
  styles: [`
    .page-header {
      @apply flex justify-between items-start mb-6;
    }

    .breadcrumb {
      @apply text-sm text-gray-500 mb-2;
    }

    .content-grid {
      @apply grid grid-cols-1 lg:grid-cols-3 gap-6;
    }

    .main-content {
      @apply lg:col-span-2;
    }

    .tab-content {
      @apply p-4;
    }

    .status-info {
      @apply flex flex-col md:flex-row md:items-center gap-4;
    }

    .status-dates {
      @apply space-y-2;
    }

    .date-item {
      @apply flex gap-2;
    }

    .date-item .label {
      @apply font-medium text-gray-600;
    }

    .course-details h3 {
      @apply text-lg font-medium mb-2;
    }

    .course-info-grid {
      @apply grid grid-cols-1 md:grid-cols-2 gap-4 mt-4;
    }

    .info-item {
      @apply flex items-start gap-3;
    }

    .info-item .label {
      @apply text-sm text-gray-500;
    }

    .info-item .value {
      @apply font-medium;
    }

    .payment-summary {
      @apply space-y-2;
    }

    .summary-row {
      @apply flex justify-between;
    }

    .summary-row.total {
      @apply font-bold text-lg;
    }

    .client-info {
      @apply space-y-3;
    }

    .contact-info {
      @apply space-y-2;
    }

    .contact-item {
      @apply flex items-center gap-2 text-sm;
    }

    .quick-actions {
      @apply space-y-2;
    }
  `]
})
export class BookingDetailPage implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private bookingsService = inject(BookingsService);

  booking: Booking | null = null;
  loading = false;

  ngOnInit() {
    const bookingId = this.route.snapshot.params['id'];
    this.loadBooking(bookingId);
  }

  loadBooking(id: string) {
    this.loading = true;
    // TODO: Implement with BookingsService
    this.loading = false;
  }

  getStatusColor(status: string) {
    const colors: { [key: string]: string } = {
      pending: 'warn',
      confirmed: 'accent',
      paid: 'primary',
      cancelled: '',
      completed: 'primary'
    };
    return colors[status] || '';
  }

  getStatusLabel(status: string) {
    const labels: { [key: string]: string } = {
      pending: 'Pendiente',
      confirmed: 'Confirmada',
      paid: 'Pagada',
      cancelled: 'Cancelada',
      completed: 'Completada'
    };
    return labels[status] || status;
  }

  confirmBooking() {
    // TODO: Implement
  }

  markAsPaid() {
    // TODO: Implement
  }

  cancelBooking() {
    // TODO: Implement
  }

  duplicateBooking() {
    // TODO: Implement
  }

  sendConfirmation() {
    // TODO: Implement
  }

  printBooking() {
    // TODO: Implement
  }

  exportBooking() {
    // TODO: Implement
  }

  sendReminder() {
    // TODO: Implement
  }

  openChat() {
    // TODO: Implement
  }
}