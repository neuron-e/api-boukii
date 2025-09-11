import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTabsModule } from '@angular/material/tabs';
import { MatTableModule } from '@angular/material/table';
import { MatChipsModule } from '@angular/material/chips';
import { MatBadgeModule } from '@angular/material/badge';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatMenuModule } from '@angular/material/menu';
import { MatTooltipModule } from '@angular/material/tooltip';

import { PageLayoutComponent } from '../../shared/components/layout/page-layout.component';
import { ModuleSubscription, ModuleCatalog, SubscriptionTier, ModuleStatus } from './models/module.interface';

@Component({
  selector: 'app-modules-management',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    ReactiveFormsModule,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatTabsModule,
    MatTableModule,
    MatChipsModule,
    MatBadgeModule,
    MatProgressBarModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatSlideToggleModule,
    MatMenuModule,
    MatTooltipModule,
    PageLayoutComponent,
  ],
  template: `
    <app-page-layout>
      <div class="page-header">
        <div class="page-title">
          <h1 class="text-2xl font-bold">Gestión de Módulos</h1>
          <p class="text-gray-600">Administra tus suscripciones y módulos disponibles</p>
        </div>
        
        <div class="page-actions">
          <button mat-stroked-button [routerLink]="'/modules/catalog'">
            <mat-icon>store</mat-icon>
            Catálogo
          </button>
          <button mat-stroked-button (click)="openBilling()">
            <mat-icon>receipt</mat-icon>
            Facturación
          </button>
          <button mat-raised-button color="primary" (click)="openUpgradeDialog()">
            <mat-icon>upgrade</mat-icon>
            Mejorar Plan
          </button>
        </div>
      </div>

      <!-- Plan Overview -->
      <div class="plan-overview mb-6">
        <mat-card class="plan-card">
          <mat-card-content>
            <div class="plan-header">
              <div class="plan-info">
                <h2 class="plan-name">Plan {{ currentPlan.name }}</h2>
                <p class="plan-description">{{ currentPlan.description }}</p>
              </div>
              <div class="plan-pricing">
                <div class="plan-price">{{ currentPlan.price | currency:'EUR':'symbol':'1.0-0' }}</div>
                <div class="plan-period">/ mes</div>
              </div>
            </div>
            
            <div class="plan-usage mt-4">
              <div class="usage-metric">
                <span class="usage-label">Módulos activos:</span>
                <span class="usage-value">{{ getActiveModulesCount() }} / {{ currentPlan.max_modules === -1 ? '∞' : currentPlan.max_modules }}</span>
                <mat-progress-bar 
                  mode="determinate" 
                  [value]="getModulesUsagePercentage()"
                  color="primary">
                </mat-progress-bar>
              </div>
              
              <div class="usage-metric">
                <span class="usage-label">Usuarios incluidos:</span>
                <span class="usage-value">{{ currentPlan.max_users === -1 ? 'Ilimitados' : currentPlan.max_users + ' usuarios' }}</span>
              </div>
              
              <div class="usage-metric">
                <span class="usage-label">Almacenamiento:</span>
                <span class="usage-value">{{ currentPlan.storage_gb === -1 ? 'Ilimitado' : currentPlan.storage_gb + ' GB' }}</span>
              </div>
            </div>
          </mat-card-content>
        </mat-card>
      </div>

      <mat-tab-group>
        <!-- Active Modules Tab -->
        <mat-tab label="Módulos Activos">
          <div class="tab-content">
            <div class="modules-grid">
              <mat-card *ngFor="let module of activeModules" class="module-card">
                <mat-card-header>
                  <div mat-card-avatar class="module-avatar">
                    <mat-icon>{{ module.icon }}</mat-icon>
                  </div>
                  <mat-card-title>{{ module.name }}</mat-card-title>
                  <mat-card-subtitle>
                    <mat-chip [class]="'tier-' + module.subscription_tier">
                      {{ getTierLabel(module.subscription_tier) }}
                    </mat-chip>
                  </mat-card-subtitle>
                  
                  <button mat-icon-button [matMenuTriggerFor]="moduleMenu">
                    <mat-icon>more_vert</mat-icon>
                  </button>
                  
                  <mat-menu #moduleMenu="matMenu">
                    <button mat-menu-item (click)="configureModule(module)">
                      <mat-icon>settings</mat-icon>
                      Configurar
                    </button>
                    <button mat-menu-item (click)="upgradeModule(module)">
                      <mat-icon>upgrade</mat-icon>
                      Actualizar Plan
                    </button>
                    <button mat-menu-item (click)="viewUsage(module)">
                      <mat-icon>analytics</mat-icon>
                      Ver Uso
                    </button>
                    <button mat-menu-item (click)="pauseModule(module)" class="text-orange-600">
                      <mat-icon>pause</mat-icon>
                      Pausar
                    </button>
                    <button mat-menu-item (click)="cancelModule(module)" class="text-red-600">
                      <mat-icon>cancel</mat-icon>
                      Cancelar
                    </button>
                  </mat-menu>
                </mat-card-header>
                
                <mat-card-content>
                  <p class="module-description">{{ module.description }}</p>
                  
                  <div class="module-stats">
                    <div class="stat-item">
                      <mat-icon class="stat-icon">event</mat-icon>
                      <span>Activo desde {{ module.activated_at | date:'dd/MM/yyyy' }}</span>
                    </div>
                    
                    <div *ngIf="module.expires_at" class="stat-item">
                      <mat-icon class="stat-icon text-orange-600">schedule</mat-icon>
                      <span>Expira el {{ module.expires_at | date:'dd/MM/yyyy' }}</span>
                    </div>
                    
                    <div *ngIf="module.usage_stats" class="stat-item">
                      <mat-icon class="stat-icon">bar_chart</mat-icon>
                      <span>{{ module.usage_stats.monthly_usage }}% uso mensual</span>
                    </div>
                  </div>
                  
                  <div class="module-features">
                    <h4 class="features-title">Funcionalidades incluidas:</h4>
                    <div class="features-list">
                      <mat-chip *ngFor="let feature of module.features" class="feature-chip">
                        {{ feature }}
                      </mat-chip>
                    </div>
                  </div>
                </mat-card-content>
                
                <mat-card-actions>
                  <button mat-stroked-button [routerLink]="getModuleRoute(module.module_slug)">
                    <mat-icon>launch</mat-icon>
                    Abrir
                  </button>
                  <button mat-stroked-button (click)="viewModuleDetails(module)">
                    <mat-icon>info</mat-icon>
                    Detalles
                  </button>
                </mat-card-actions>
              </mat-card>
            </div>
          </div>
        </mat-tab>

        <!-- Available Modules Tab -->
        <mat-tab label="Módulos Disponibles">
          <div class="tab-content">
            <div class="catalog-filters mb-4">
              <mat-form-field>
                <mat-label>Categoría</mat-label>
                <mat-select [(value)]="selectedCategory" (selectionChange)="filterCatalog()">
                  <mat-option value="">Todas</mat-option>
                  <mat-option value="core">Principales</mat-option>
                  <mat-option value="management">Gestión</mat-option>
                  <mat-option value="communication">Comunicación</mat-option>
                  <mat-option value="analytics">Análisis</mat-option>
                </mat-select>
              </mat-form-field>
              
              <mat-form-field class="ml-4">
                <mat-label>Plan mínimo</mat-label>
                <mat-select [(value)]="selectedTier" (selectionChange)="filterCatalog()">
                  <mat-option value="">Todos</mat-option>
                  <mat-option value="free">Gratuito</mat-option>
                  <mat-option value="basic">Básico</mat-option>
                  <mat-option value="premium">Premium</mat-option>
                  <mat-option value="enterprise">Enterprise</mat-option>
                </mat-select>
              </mat-form-field>
            </div>

            <div class="modules-grid">
              <mat-card *ngFor="let module of filteredCatalog" class="module-card catalog-card">
                <mat-card-header>
                  <div mat-card-avatar class="module-avatar">
                    <mat-icon>{{ module.icon }}</mat-icon>
                  </div>
                  <mat-card-title>{{ module.name }}</mat-card-title>
                  <mat-card-subtitle>{{ module.category }}</mat-card-subtitle>
                  
                  <mat-chip *ngIf="module.is_new" class="new-badge">Nuevo</mat-chip>
                </mat-card-header>
                
                <mat-card-content>
                  <p class="module-description">{{ module.description }}</p>
                  
                  <div class="pricing-tiers">
                    <div *ngFor="let tier of module.pricing_tiers" class="tier-option">
                      <div class="tier-info">
                        <span class="tier-name">{{ getTierLabel(tier.tier) }}</span>
                        <span class="tier-price">{{ tier.price | currency:'EUR':'symbol':'1.0-0' }}/mes</span>
                      </div>
                      <div class="tier-features">
                        <span *ngFor="let feature of tier.features; let last = last" class="tier-feature">
                          {{ feature }}<span *ngIf="!last">, </span>
                        </span>
                      </div>
                    </div>
                  </div>
                </mat-card-content>
                
                <mat-card-actions>
                  <button 
                    mat-raised-button 
                    color="primary"
                    [disabled]="!canSubscribeToModule(module)"
                    (click)="subscribeToModule(module)">
                    <mat-icon>add</mat-icon>
                    Suscribirse
                  </button>
                  <button mat-stroked-button (click)="startTrial(module)">
                    <mat-icon>schedule</mat-icon>
                    Prueba Gratuita
                  </button>
                  <button mat-stroked-button (click)="viewModulePreview(module)">
                    <mat-icon>preview</mat-icon>
                    Vista Previa
                  </button>
                </mat-card-actions>
              </mat-card>
            </div>
          </div>
        </mat-tab>

        <!-- Usage & Analytics Tab -->
        <mat-tab label="Uso y Análisis">
          <div class="tab-content">
            <div class="usage-summary mb-6">
              <mat-card>
                <mat-card-header>
                  <mat-card-title>Resumen de Uso - {{ currentMonth }}</mat-card-title>
                </mat-card-header>
                <mat-card-content>
                  <div class="usage-metrics">
                    <div class="metric-card">
                      <div class="metric-value">{{ totalUsage.api_calls | number }}</div>
                      <div class="metric-label">Llamadas API</div>
                      <mat-progress-bar 
                        [value]="(totalUsage.api_calls / totalUsage.api_limit) * 100"
                        color="primary">
                      </mat-progress-bar>
                    </div>
                    
                    <div class="metric-card">
                      <div class="metric-value">{{ totalUsage.storage_used | number:'1.1-1' }} GB</div>
                      <div class="metric-label">Almacenamiento</div>
                      <mat-progress-bar 
                        [value]="(totalUsage.storage_used / totalUsage.storage_limit) * 100"
                        color="primary">
                      </mat-progress-bar>
                    </div>
                    
                    <div class="metric-card">
                      <div class="metric-value">{{ totalUsage.active_users | number }}</div>
                      <div class="metric-label">Usuarios Activos</div>
                      <mat-progress-bar 
                        [value]="(totalUsage.active_users / totalUsage.user_limit) * 100"
                        color="primary">
                      </mat-progress-bar>
                    </div>
                  </div>
                </mat-card-content>
              </mat-card>
            </div>

            <!-- Per-module usage -->
            <div class="module-usage">
              <mat-card *ngFor="let usage of moduleUsage" class="usage-card">
                <mat-card-header>
                  <div mat-card-avatar class="module-avatar">
                    <mat-icon>{{ usage.module_icon }}</mat-icon>
                  </div>
                  <mat-card-title>{{ usage.module_name }}</mat-card-title>
                  <mat-card-subtitle>{{ getTierLabel(usage.subscription_tier) }}</mat-card-subtitle>
                </mat-card-header>
                
                <mat-card-content>
                  <div class="usage-details">
                    <div class="usage-row">
                      <span>Sesiones activas:</span>
                      <span>{{ usage.active_sessions }}</span>
                    </div>
                    <div class="usage-row">
                      <span>Datos procesados:</span>
                      <span>{{ usage.data_processed | number:'1.1-1' }} MB</span>
                    </div>
                    <div class="usage-row">
                      <span>Última actividad:</span>
                      <span>{{ usage.last_activity | date:'dd/MM/yyyy HH:mm' }}</span>
                    </div>
                  </div>
                </mat-card-content>
              </mat-card>
            </div>
          </div>
        </mat-tab>
      </mat-tab-group>
    </app-page-layout>
  `,
  styles: [`
    .page-header {
      @apply flex justify-between items-start mb-6;
    }

    .plan-overview {
      @apply w-full;
    }

    .plan-card {
      @apply w-full;
    }

    .plan-header {
      @apply flex justify-between items-start;
    }

    .plan-name {
      @apply text-xl font-bold;
    }

    .plan-description {
      @apply text-gray-600;
    }

    .plan-pricing {
      @apply text-right;
    }

    .plan-price {
      @apply text-2xl font-bold;
    }

    .plan-period {
      @apply text-sm text-gray-600;
    }

    .plan-usage {
      @apply space-y-4;
    }

    .usage-metric {
      @apply space-y-2;
    }

    .usage-label {
      @apply text-sm font-medium;
    }

    .usage-value {
      @apply float-right text-sm;
    }

    .tab-content {
      @apply p-4;
    }

    .modules-grid {
      @apply grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6;
    }

    .module-card {
      @apply h-fit;
    }

    .module-avatar {
      @apply bg-blue-100 text-blue-600;
    }

    .module-description {
      @apply text-gray-600 mb-4;
    }

    .module-stats {
      @apply space-y-2 mb-4;
    }

    .stat-item {
      @apply flex items-center gap-2 text-sm;
    }

    .stat-icon {
      @apply text-lg;
    }

    .module-features {
      @apply mb-4;
    }

    .features-title {
      @apply text-sm font-medium mb-2;
    }

    .features-list {
      @apply flex flex-wrap gap-1;
    }

    .feature-chip {
      @apply text-xs;
    }

    .catalog-filters {
      @apply flex gap-4;
    }

    .catalog-card {
      @apply border-l-4 border-blue-500;
    }

    .new-badge {
      @apply bg-green-100 text-green-800 ml-auto;
    }

    .pricing-tiers {
      @apply space-y-3 mb-4;
    }

    .tier-option {
      @apply border border-gray-200 rounded p-3;
    }

    .tier-info {
      @apply flex justify-between items-center mb-1;
    }

    .tier-name {
      @apply font-medium;
    }

    .tier-price {
      @apply font-bold;
    }

    .tier-features {
      @apply text-xs text-gray-600;
    }

    .tier-feature {
      @apply inline-block;
    }

    /* Tier colors */
    .tier-free {
      @apply bg-gray-100 text-gray-800;
    }

    .tier-basic {
      @apply bg-blue-100 text-blue-800;
    }

    .tier-premium {
      @apply bg-purple-100 text-purple-800;
    }

    .tier-enterprise {
      @apply bg-gold-100 text-gold-800;
    }

    /* Usage section */
    .usage-summary {
      @apply w-full;
    }

    .usage-metrics {
      @apply grid grid-cols-1 md:grid-cols-3 gap-4;
    }

    .metric-card {
      @apply text-center space-y-2;
    }

    .metric-value {
      @apply text-2xl font-bold;
    }

    .metric-label {
      @apply text-sm text-gray-600;
    }

    .module-usage {
      @apply grid grid-cols-1 md:grid-cols-2 gap-4;
    }

    .usage-card {
      @apply h-fit;
    }

    .usage-details {
      @apply space-y-2;
    }

    .usage-row {
      @apply flex justify-between text-sm;
    }
  `]
})
export class ModulesManagementPage implements OnInit {
  private fb = inject(FormBuilder);

  currentPlan = {
    name: 'Premium',
    description: 'Plan avanzado con todas las funcionalidades',
    price: 89,
    max_modules: 10,
    max_users: 50,
    storage_gb: 100
  };

  selectedCategory = '';
  selectedTier = '';
  currentMonth = new Date().toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });

  activeModules: ModuleSubscription[] = [
    {
      id: 1,
      module_slug: 'bookings',
      name: 'Gestión de Reservas',
      description: 'Sistema completo de reservas y clases',
      icon: 'event',
      subscription_tier: 'premium',
      status: 'active',
      activated_at: new Date('2025-01-01'),
      expires_at: new Date('2026-01-01'),
      features: ['Reservas online', 'Gestión de clases', 'Calendarios', 'Notificaciones'],
      usage_stats: { monthly_usage: 78 }
    },
    {
      id: 2,
      module_slug: 'finance',
      name: 'Finanzas',
      description: 'Control financiero y facturación',
      icon: 'account_balance',
      subscription_tier: 'premium',
      status: 'active',
      activated_at: new Date('2025-01-01'),
      expires_at: new Date('2026-01-01'),
      features: ['Facturación', 'Informes', 'Pagos online', 'Conciliación'],
      usage_stats: { monthly_usage: 45 }
    },
    {
      id: 3,
      module_slug: 'communications',
      name: 'Comunicaciones',
      description: 'Centro de comunicación con clientes',
      icon: 'message',
      subscription_tier: 'basic',
      status: 'active',
      activated_at: new Date('2025-01-15'),
      expires_at: new Date('2026-01-15'),
      features: ['Mensajes', 'Notificaciones', 'Newsletter'],
      usage_stats: { monthly_usage: 23 }
    }
  ];

  catalogModules: ModuleCatalog[] = [
    {
      slug: 'analytics',
      name: 'Análisis Avanzado',
      description: 'Informes detallados y análisis de rendimiento',
      icon: 'analytics',
      category: 'analytics',
      is_new: true,
      pricing_tiers: [
        {
          tier: 'basic',
          price: 19,
          features: ['Informes básicos', 'Exportación CSV']
        },
        {
          tier: 'premium',
          price: 39,
          features: ['Informes avanzados', 'Dashboard personalizable', 'API de datos']
        }
      ],
      dependencies: []
    },
    {
      slug: 'inventory',
      name: 'Gestión de Inventario',
      description: 'Control de material y equipamiento',
      icon: 'inventory',
      category: 'management',
      is_new: false,
      pricing_tiers: [
        {
          tier: 'basic',
          price: 15,
          features: ['Inventario básico', 'Alertas stock']
        },
        {
          tier: 'premium',
          price: 29,
          features: ['Inventario avanzado', 'Trazabilidad', 'Mantenimiento predictivo']
        }
      ],
      dependencies: []
    }
  ];

  filteredCatalog: ModuleCatalog[] = [];

  totalUsage = {
    api_calls: 15750,
    api_limit: 25000,
    storage_used: 45.8,
    storage_limit: 100,
    active_users: 23,
    user_limit: 50
  };

  moduleUsage = [
    {
      module_name: 'Gestión de Reservas',
      module_icon: 'event',
      subscription_tier: 'premium' as SubscriptionTier,
      active_sessions: 12,
      data_processed: 125.6,
      last_activity: new Date()
    },
    {
      module_name: 'Finanzas',
      module_icon: 'account_balance',
      subscription_tier: 'premium' as SubscriptionTier,
      active_sessions: 8,
      data_processed: 89.2,
      last_activity: new Date(Date.now() - 2 * 60 * 60 * 1000)
    }
  ];

  ngOnInit() {
    this.filteredCatalog = this.catalogModules;
  }

  getActiveModulesCount(): number {
    return this.activeModules.filter(m => m.status === 'active').length;
  }

  getModulesUsagePercentage(): number {
    if (this.currentPlan.max_modules === -1) return 0;
    return (this.getActiveModulesCount() / this.currentPlan.max_modules) * 100;
  }

  getTierLabel(tier: SubscriptionTier): string {
    const labels = {
      free: 'Gratuito',
      basic: 'Básico',
      premium: 'Premium',
      enterprise: 'Enterprise'
    };
    return labels[tier];
  }

  getModuleRoute(slug: string): string {
    return `/${slug}`;
  }

  filterCatalog() {
    this.filteredCatalog = this.catalogModules.filter(module => {
      const categoryMatch = !this.selectedCategory || module.category === this.selectedCategory;
      const tierMatch = !this.selectedTier || 
        module.pricing_tiers.some(tier => tier.tier === this.selectedTier);
      
      return categoryMatch && tierMatch;
    });
  }

  canSubscribeToModule(module: ModuleCatalog): boolean {
    // Check if already subscribed
    return !this.activeModules.some(active => active.module_slug === module.slug);
  }

  subscribeToModule(module: ModuleCatalog) {
    // TODO: Open subscription dialog
    console.log('Subscribe to module:', module);
  }

  startTrial(module: ModuleCatalog) {
    // TODO: Start trial for module
    console.log('Start trial for:', module);
  }

  viewModulePreview(module: ModuleCatalog) {
    // TODO: Open module preview
    console.log('Preview module:', module);
  }

  configureModule(module: ModuleSubscription) {
    // TODO: Open module configuration
    console.log('Configure module:', module);
  }

  upgradeModule(module: ModuleSubscription) {
    // TODO: Open upgrade dialog
    console.log('Upgrade module:', module);
  }

  viewUsage(module: ModuleSubscription) {
    // TODO: Show detailed usage for module
    console.log('View usage for:', module);
  }

  pauseModule(module: ModuleSubscription) {
    // TODO: Pause module subscription
    console.log('Pause module:', module);
  }

  cancelModule(module: ModuleSubscription) {
    // TODO: Cancel module subscription
    console.log('Cancel module:', module);
  }

  viewModuleDetails(module: ModuleSubscription) {
    // TODO: Show module details
    console.log('View details for:', module);
  }

  openUpgradeDialog() {
    // TODO: Open plan upgrade dialog
    console.log('Open upgrade dialog');
  }

  openBilling() {
    // TODO: Navigate to billing section
    console.log('Open billing');
  }
}