import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTabsModule } from '@angular/material/tabs';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatChipsModule } from '@angular/material/chips';
import { MatBadgeModule } from '@angular/material/badge';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';

import { PageLayoutComponent } from '../../shared/components/layout/page-layout.component';

@Component({
  selector: 'app-communications-center',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    ReactiveFormsModule,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatTabsModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatChipsModule,
    MatBadgeModule,
    MatSlideToggleModule,
    PageLayoutComponent,
  ],
  template: `
    <app-page-layout>
      <div class="page-header">
        <div class="page-title">
          <h1 class="text-2xl font-bold">Centro de Comunicaciones</h1>
          <p class="text-gray-600">Gestión de comunicaciones y marketing</p>
        </div>
        
        <div class="page-actions">
          <button mat-stroked-button [routerLink]="'/communications/templates'">
            <mat-icon>email</mat-icon>
            Plantillas
          </button>
          <button mat-stroked-button [routerLink]="'/communications/campaigns/create'">
            <mat-icon>campaign</mat-icon>
            Nueva Campaña
          </button>
          <button mat-raised-button color="primary" [routerLink]="'/communications/send'">
            <mat-icon>send</mat-icon>
            Enviar Mensaje
          </button>
        </div>
      </div>

      <!-- Estadísticas de comunicación -->
      <div class="stats-grid mb-6">
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-blue-600">email</mat-icon>
              <div>
                <div class="stat-value">{{ stats.emails_sent }}</div>
                <div class="stat-label">Emails Enviados</div>
                <div class="stat-sublabel">{{ stats.email_open_rate }}% apertura</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-green-600">sms</mat-icon>
              <div>
                <div class="stat-value">{{ stats.sms_sent }}</div>
                <div class="stat-label">SMS Enviados</div>
                <div class="stat-sublabel">{{ stats.sms_delivery_rate }}% entregados</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-orange-600">campaign</mat-icon>
              <div>
                <div class="stat-value">{{ stats.active_campaigns }}</div>
                <div class="stat-label">Campañas Activas</div>
                <div class="stat-sublabel">{{ stats.total_campaigns }} totales</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
        
        <mat-card class="stat-card">
          <mat-card-content>
            <div class="stat-content">
              <mat-icon class="stat-icon text-purple-600">group</mat-icon>
              <div>
                <div class="stat-value">{{ stats.subscribers }}</div>
                <div class="stat-label">Suscriptores</div>
                <div class="stat-sublabel">{{ stats.unsubscribe_rate }}% baja</div>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
      </div>

      <div class="content-grid">
        <div class="main-content">
          <mat-tab-group>
            <!-- Tab: Mensajes -->
            <mat-tab label="Mensajes">
              <div class="tab-content">
                <!-- Filtros de mensajes -->
                <mat-card class="mb-4">
                  <mat-card-content>
                    <form [formGroup]="messageFilters" class="filters-row">
                      <mat-form-field>
                        <mat-label>Buscar</mat-label>
                        <input matInput formControlName="search" placeholder="Buscar mensajes...">
                      </mat-form-field>

                      <mat-form-field>
                        <mat-label>Tipo</mat-label>
                        <mat-select formControlName="type">
                          <mat-option value="">Todos</mat-option>
                          <mat-option value="email">Email</mat-option>
                          <mat-option value="sms">SMS</mat-option>
                          <mat-option value="push">Push</mat-option>
                          <mat-option value="chat">Chat</mat-option>
                        </mat-select>
                      </mat-form-field>

                      <mat-form-field>
                        <mat-label>Estado</mat-label>
                        <mat-select formControlName="status">
                          <mat-option value="">Todos</mat-option>
                          <mat-option value="draft">Borrador</mat-option>
                          <mat-option value="sent">Enviado</mat-option>
                          <mat-option value="delivered">Entregado</mat-option>
                          <mat-option value="failed">Fallido</mat-option>
                        </mat-select>
                      </mat-form-field>

                      <button mat-raised-button color="primary" type="submit">
                        Filtrar
                      </button>
                    </form>
                  </mat-card-content>
                </mat-card>

                <!-- Lista de mensajes -->
                <mat-card>
                  <mat-card-content>
                    <div class="messages-list">
                      <div *ngFor="let message of messages" class="message-item" [class.unread]="!message.read">
                        <div class="message-header">
                          <div class="message-meta">
                            <mat-icon class="message-type-icon">{{ getMessageTypeIcon(message.type) }}</mat-icon>
                            <span class="message-subject">{{ message.subject }}</span>
                            <mat-chip [color]="getStatusColor(message.status)" selected>
                              {{ getStatusLabel(message.status) }}
                            </mat-chip>
                          </div>
                          <div class="message-actions">
                            <button mat-icon-button (click)="viewMessage(message.id)">
                              <mat-icon>visibility</mat-icon>
                            </button>
                            <button mat-icon-button (click)="replyMessage(message.id)" *ngIf="message.type !== 'campaign'">
                              <mat-icon>reply</mat-icon>
                            </button>
                            <button mat-icon-button (click)="archiveMessage(message.id)">
                              <mat-icon>archive</mat-icon>
                            </button>
                          </div>
                        </div>
                        
                        <div class="message-body">
                          <div class="message-preview">{{ message.preview }}</div>
                          <div class="message-details">
                            <span class="message-sender">{{ message.sender || 'Sistema' }}</span>
                            <span class="message-recipients">{{ message.recipients_count }} destinatarios</span>
                            <span class="message-timestamp">{{ message.sent_at | date:'dd/MM/yyyy HH:mm' }}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>
              </div>
            </mat-tab>

            <!-- Tab: Campañas -->
            <mat-tab label="Campañas">
              <div class="tab-content">
                <div class="section-header">
                  <h3>Campañas de Marketing</h3>
                  <button mat-stroked-button [routerLink]="'/communications/campaigns'">
                    Ver todas
                  </button>
                </div>

                <div class="campaigns-grid">
                  <mat-card *ngFor="let campaign of campaigns" class="campaign-card">
                    <mat-card-header>
                      <mat-card-title>{{ campaign.name }}</mat-card-title>
                      <mat-card-subtitle>
                        <mat-chip [color]="getCampaignStatusColor(campaign.status)" selected>
                          {{ getCampaignStatusLabel(campaign.status) }}
                        </mat-chip>
                      </mat-card-subtitle>
                    </mat-card-header>
                    
                    <mat-card-content>
                      <div class="campaign-stats">
                        <div class="stat-item">
                          <span class="stat-label">Enviados:</span>
                          <span class="stat-value">{{ campaign.sent_count }}</span>
                        </div>
                        <div class="stat-item">
                          <span class="stat-label">Aperturas:</span>
                          <span class="stat-value">{{ campaign.open_rate }}%</span>
                        </div>
                        <div class="stat-item">
                          <span class="stat-label">Clicks:</span>
                          <span class="stat-value">{{ campaign.click_rate }}%</span>
                        </div>
                      </div>
                    </mat-card-content>
                    
                    <mat-card-actions>
                      <button mat-button [routerLink]="['/communications/campaigns', campaign.id]">
                        Ver detalles
                      </button>
                      <button mat-button (click)="duplicateCampaign(campaign.id)">
                        Duplicar
                      </button>
                    </mat-card-actions>
                  </mat-card>
                </div>
              </div>
            </mat-tab>

            <!-- Tab: Plantillas -->
            <mat-tab label="Plantillas">
              <div class="tab-content">
                <div class="section-header">
                  <h3>Plantillas de Mensaje</h3>
                  <button mat-stroked-button [routerLink]="'/communications/templates/create'">
                    Nueva plantilla
                  </button>
                </div>

                <div class="templates-grid">
                  <mat-card *ngFor="let template of templates" class="template-card">
                    <mat-card-header>
                      <mat-card-title>{{ template.name }}</mat-card-title>
                      <mat-card-subtitle>{{ template.type | uppercase }}</mat-card-subtitle>
                    </mat-card-header>
                    
                    <mat-card-content>
                      <div class="template-preview">{{ template.preview }}</div>
                      <div class="template-meta">
                        <span class="usage-count">{{ template.usage_count }} usos</span>
                        <span class="last-updated">{{ template.updated_at | date:'dd/MM/yyyy' }}</span>
                      </div>
                    </mat-card-content>
                    
                    <mat-card-actions>
                      <button mat-button (click)="useTemplate(template.id)">
                        Usar
                      </button>
                      <button mat-button [routerLink]="['/communications/templates', template.id, 'edit']">
                        Editar
                      </button>
                    </mat-card-actions>
                  </mat-card>
                </div>
              </div>
            </mat-tab>

            <!-- Tab: Automatización -->
            <mat-tab label="Automatización">
              <div class="tab-content">
                <mat-card>
                  <mat-card-header>
                    <mat-card-title>Flujos Automáticos</mat-card-title>
                  </mat-card-header>
                  <mat-card-content>
                    <div class="automation-flows">
                      <div *ngFor="let flow of automationFlows" class="flow-item">
                        <div class="flow-info">
                          <div class="flow-name">{{ flow.name }}</div>
                          <div class="flow-description">{{ flow.description }}</div>
                        </div>
                        <div class="flow-status">
                          <mat-slide-toggle [checked]="flow.active" (change)="toggleFlow(flow.id, $event.checked)">
                            {{ flow.active ? 'Activo' : 'Inactivo' }}
                          </mat-slide-toggle>
                        </div>
                        <div class="flow-stats">
                          <span>{{ flow.triggers_count }} activaciones</span>
                        </div>
                      </div>
                    </div>
                  </mat-card-content>
                </mat-card>
              </div>
            </mat-tab>
          </mat-tab-group>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
          <mat-card class="mb-4">
            <mat-card-header>
              <mat-card-title>Acciones Rápidas</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="quick-actions">
                <button mat-stroked-button class="w-full" [routerLink]="'/communications/broadcast'">
                  <mat-icon>broadcast_on_personal</mat-icon>
                  Mensaje Masivo
                </button>
                <button mat-stroked-button class="w-full" [routerLink]="'/communications/newsletter'">
                  <mat-icon>newspaper</mat-icon>
                  Newsletter
                </button>
                <button mat-stroked-button class="w-full" [routerLink]="'/communications/promotions'">
                  <mat-icon>local_offer</mat-icon>
                  Promociones
                </button>
                <button mat-stroked-button class="w-full" [routerLink]="'/communications/surveys'">
                  <mat-icon>quiz</mat-icon>
                  Encuestas
                </button>
              </div>
            </mat-card-content>
          </mat-card>

          <mat-card class="mb-4">
            <mat-card-header>
              <mat-card-title>Pendientes</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="pending-items">
                <div class="pending-item">
                  <mat-icon>schedule_send</mat-icon>
                  <div>
                    <div class="pending-title">{{ pendingMessages }} mensajes programados</div>
                    <div class="pending-subtitle">Próximo en 2h</div>
                  </div>
                </div>
                <div class="pending-item">
                  <mat-icon>drafts</mat-icon>
                  <div>
                    <div class="pending-title">{{ draftCampaigns }} campañas en borrador</div>
                    <div class="pending-subtitle">Listas para enviar</div>
                  </div>
                </div>
              </div>
            </mat-card-content>
          </mat-card>

          <mat-card>
            <mat-card-header>
              <mat-card-title>Rendimiento Reciente</mat-card-title>
            </mat-card-header>
            <mat-card-content>
              <div class="performance-metrics">
                <div class="metric-item">
                  <span class="metric-label">Tasa apertura:</span>
                  <span class="metric-value">{{ recentPerformance.open_rate }}%</span>
                </div>
                <div class="metric-item">
                  <span class="metric-label">Tasa clicks:</span>
                  <span class="metric-value">{{ recentPerformance.click_rate }}%</span>
                </div>
                <div class="metric-item">
                  <span class="metric-label">Rebotes:</span>
                  <span class="metric-value">{{ recentPerformance.bounce_rate }}%</span>
                </div>
              </div>
            </mat-card-content>
          </mat-card>
        </div>
      </div>
    </app-page-layout>
  `,
  styles: [`
    .page-header {
      @apply flex justify-between items-start mb-6;
    }

    .stats-grid {
      @apply grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4;
    }

    .stat-content {
      @apply flex items-center gap-4;
    }

    .stat-icon {
      @apply text-3xl;
    }

    .stat-value {
      @apply text-2xl font-bold;
    }

    .stat-label {
      @apply text-sm font-medium text-gray-700;
    }

    .stat-sublabel {
      @apply text-xs text-gray-500;
    }

    .content-grid {
      @apply grid grid-cols-1 xl:grid-cols-3 gap-6;
    }

    .main-content {
      @apply xl:col-span-2;
    }

    .tab-content {
      @apply p-4;
    }

    .filters-row {
      @apply flex gap-4 items-end flex-wrap;
    }

    .section-header {
      @apply flex justify-between items-center mb-4;
    }

    .section-header h3 {
      @apply text-lg font-medium;
    }

    .messages-list {
      @apply space-y-3;
    }

    .message-item {
      @apply border border-gray-200 rounded p-4;
    }

    .message-item.unread {
      @apply border-blue-300 bg-blue-50;
    }

    .message-header {
      @apply flex justify-between items-start mb-2;
    }

    .message-meta {
      @apply flex items-center gap-2;
    }

    .message-subject {
      @apply font-medium;
    }

    .message-actions {
      @apply flex gap-1;
    }

    .message-body {
      @apply space-y-2;
    }

    .message-preview {
      @apply text-gray-600 text-sm;
    }

    .message-details {
      @apply flex gap-4 text-xs text-gray-500;
    }

    .campaigns-grid, .templates-grid {
      @apply grid grid-cols-1 md:grid-cols-2 gap-4;
    }

    .campaign-stats, .template-meta {
      @apply space-y-2;
    }

    .stat-item {
      @apply flex justify-between text-sm;
    }

    .template-preview {
      @apply text-sm text-gray-600 mb-2;
    }

    .automation-flows {
      @apply space-y-4;
    }

    .flow-item {
      @apply flex items-center justify-between p-3 border border-gray-200 rounded;
    }

    .flow-description {
      @apply text-sm text-gray-500;
    }

    .quick-actions {
      @apply space-y-2;
    }

    .pending-items {
      @apply space-y-3;
    }

    .pending-item {
      @apply flex gap-3;
    }

    .pending-title {
      @apply font-medium text-sm;
    }

    .pending-subtitle {
      @apply text-xs text-gray-500;
    }

    .performance-metrics {
      @apply space-y-2;
    }

    .metric-item {
      @apply flex justify-between;
    }

    .metric-label {
      @apply text-sm text-gray-600;
    }

    .metric-value {
      @apply font-medium;
    }
  `]
})
export class CommunicationsCenterPage implements OnInit {
  private fb = inject(FormBuilder);

  unreadMessages = 5;
  pendingMessages = 8;
  draftCampaigns = 3;

  messageFilters = this.fb.group({
    search: [''],
    type: [''],
    status: ['']
  });

  stats = {
    emails_sent: 2450,
    email_open_rate: 68,
    sms_sent: 890,
    sms_delivery_rate: 95,
    active_campaigns: 6,
    total_campaigns: 24,
    subscribers: 1280,
    unsubscribe_rate: 2.1
  };

  messages = [
    {
      id: 1,
      type: 'email',
      subject: 'Confirmación de reserva - Curso de Esquí',
      preview: 'Tu reserva para el curso de esquí ha sido confirmada...',
      sender: 'Sistema',
      recipients_count: 1,
      sent_at: new Date(),
      status: 'delivered',
      read: false
    },
    {
      id: 2,
      type: 'sms',
      subject: 'Recordatorio de clase',
      preview: 'Te recordamos que tienes clase mañana a las 10:00...',
      sender: 'Sistema',
      recipients_count: 1,
      sent_at: new Date(),
      status: 'delivered',
      read: true
    }
  ];

  campaigns = [
    {
      id: 1,
      name: 'Promoción Temporada Navidad',
      status: 'active',
      sent_count: 1200,
      open_rate: 72,
      click_rate: 18
    },
    {
      id: 2,
      name: 'Newsletter Febrero',
      status: 'scheduled',
      sent_count: 0,
      open_rate: 0,
      click_rate: 0
    }
  ];

  templates = [
    {
      id: 1,
      name: 'Confirmación de Reserva',
      type: 'email',
      preview: 'Confirmamos tu reserva para el día...',
      usage_count: 156,
      updated_at: new Date()
    },
    {
      id: 2,
      name: 'Recordatorio de Clase',
      type: 'sms',
      preview: 'No olvides tu clase de mañana...',
      usage_count: 89,
      updated_at: new Date()
    }
  ];

  automationFlows = [
    {
      id: 1,
      name: 'Bienvenida Nuevos Clientes',
      description: 'Secuencia de emails de bienvenida',
      active: true,
      triggers_count: 45
    },
    {
      id: 2,
      name: 'Recuperación Carritos Abandonados',
      description: 'Recordatorio para completar reserva',
      active: false,
      triggers_count: 23
    }
  ];

  recentPerformance = {
    open_rate: 68,
    click_rate: 15,
    bounce_rate: 3.2
  };

  ngOnInit() {
    // TODO: Load communications data
  }

  getMessageTypeIcon(type: string): string {
    const icons: { [key: string]: string } = {
      email: 'email',
      sms: 'sms',
      push: 'notifications',
      chat: 'chat'
    };
    return icons[type] || 'message';
  }

  getStatusColor(status: string) {
    const colors: { [key: string]: string } = {
      draft: '',
      sent: 'accent',
      delivered: 'primary',
      failed: 'warn'
    };
    return colors[status] || '';
  }

  getStatusLabel(status: string): string {
    const labels: { [key: string]: string } = {
      draft: 'Borrador',
      sent: 'Enviado',
      delivered: 'Entregado',
      failed: 'Fallido'
    };
    return labels[status] || status;
  }

  getCampaignStatusColor(status: string) {
    const colors: { [key: string]: string } = {
      active: 'primary',
      scheduled: 'accent',
      completed: '',
      draft: 'warn'
    };
    return colors[status] || '';
  }

  getCampaignStatusLabel(status: string): string {
    const labels: { [key: string]: string } = {
      active: 'Activa',
      scheduled: 'Programada',
      completed: 'Completada',
      draft: 'Borrador'
    };
    return labels[status] || status;
  }

  viewMessage(id: number) {
    // TODO: Open message view
  }

  replyMessage(id: number) {
    // TODO: Open reply composer
  }

  archiveMessage(id: number) {
    // TODO: Archive message
  }

  duplicateCampaign(id: number) {
    // TODO: Duplicate campaign
  }

  useTemplate(id: number) {
    // TODO: Use template in composer
  }

  toggleFlow(id: number, active: boolean) {
    // TODO: Toggle automation flow
  }
}