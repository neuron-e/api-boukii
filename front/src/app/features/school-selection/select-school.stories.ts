import type { Meta, StoryObj } from '@storybook/angular';
import { moduleMetadata, applicationConfig } from '@storybook/angular';
import { provideAnimations } from '@angular/platform-browser/animations';
import { provideRouter } from '@angular/router';
import { of, throwError, BehaviorSubject } from 'rxjs';
import { signal } from '@angular/core';

import { SelectSchoolPageComponent } from './select-school.page';
import { SchoolService, SchoolsResponse } from '@core/services/school.service';
import { School } from '@core/services/context.service';
import { SessionService } from '@core/services/session.service';
import { TranslationService } from '@core/services/translation.service';

// Mock data
const mockSchools: School[] = [
  {
    id: 1,
    name: 'Escuela de Natación Aqua Sport',
    slug: 'aqua-sport',
    active: true,
    createdAt: '2024-01-15T10:00:00Z',
    updatedAt: '2024-03-01T15:30:00Z'
  },
  {
    id: 2,
    name: 'Centro Deportivo Elite Swimming',
    slug: 'elite-swimming',
    active: true,
    createdAt: '2024-02-10T09:15:00Z',
    updatedAt: '2024-03-05T11:45:00Z'
  },
  {
    id: 3,
    name: 'Academia de Natación Marina',
    active: true,
    createdAt: '2024-01-20T14:20:00Z',
    updatedAt: '2024-02-28T16:10:00Z'
  },
  {
    id: 4,
    name: 'Club Natación Neptuno (Inactivo)',
    slug: 'neptuno-inactive',
    active: false,
    createdAt: '2023-12-01T08:30:00Z',
    updatedAt: '2024-01-15T12:00:00Z'
  }
];

const mockSchoolsResponse: SchoolsResponse = {
  data: mockSchools,
  meta: {
    total: 4,
    page: 1,
    perPage: 20,
    lastPage: 1,
    from: 1,
    to: 4
  }
};

const mockPaginatedResponse: SchoolsResponse = {
  data: Array.from({ length: 10 }, (_, i) => ({
    id: i + 1,
    name: `Escuela de Deportes ${i + 1}`,
    slug: `school-${i + 1}`,
    active: i % 5 !== 4, // Make every 5th school inactive
    createdAt: '2024-01-01T00:00:00Z',
    updatedAt: '2024-03-01T00:00:00Z'
  })),
  meta: {
    total: 45,
    page: 1,
    perPage: 10,
    lastPage: 5,
    from: 1,
    to: 10
  }
};

// Mock services
class MockTranslationService {
  private lang = signal('es');
  
  currentLanguage = this.lang.asReadonly();
  
  get(key: string): string {
    const translations: Record<string, string> = {
      'schools.selectSchool.title': 'Seleccionar Escuela',
      'schools.selectSchool.subtitle': 'Elige la escuela con la que deseas trabajar',
      'schools.selectSchool.breadcrumb': 'Cuenta / Seleccionar escuela',
      'schools.selectSchool.searchPlaceholder': 'Buscar escuelas...',
      'schools.selectSchool.useThisSchool': 'Usar esta escuela',
      'schools.selectSchool.noSchools': 'No se encontraron escuelas',
      'schools.selectSchool.noSchoolsMessage': 'No tienes acceso a ninguna escuela. Contacta al administrador.',
      'schools.selectSchool.loadingSchools': 'Cargando escuelas...',
      'schools.selectSchool.errorLoading': 'Error al cargar las escuelas',
      'schools.selectSchool.errorSelecting': 'Error al seleccionar la escuela',
      'schools.status.active': 'Activa',
      'schools.status.inactive': 'Inactiva',
      'schools.pagination.showing': 'Mostrando',
      'schools.pagination.to': 'a',
      'schools.pagination.of': 'de',
      'schools.pagination.results': 'resultados',
      'schools.pagination.page': 'Página',
      'schools.pagination.previous': 'Anterior',
      'schools.pagination.next': 'Siguiente',
      'common.loading': 'Cargando...',
      'common.error': 'Error',
      'common.retry': 'Reintentar',
      'common.noResults': 'No se encontraron resultados'
    };
    return translations[key] || key;
  }

  setLanguage(lang: string): void {
    this.lang.set(lang as any);
  }
}

class MockSessionService {
  selectSchool(school: School): void {
    console.log('Mock: School selected:', school.id);
  }

  // Simple BehaviorSubject for compatibility if needed
  currentSchool$ = new BehaviorSubject<School | null>(null);
}

class MockSchoolService {
  private shouldError = false;
  private shouldReturnEmpty = false;
  private shouldReturnPaginated = false;

  setErrorMode(enabled: boolean): void {
    this.shouldError = enabled;
  }

  setEmptyMode(enabled: boolean): void {
    this.shouldReturnEmpty = enabled;
  }

  setPaginatedMode(enabled: boolean): void {
    this.shouldReturnPaginated = enabled;
  }

  getMySchools() {
    if (this.shouldError) {
      return throwError(() => new Error('Failed to load schools'));
    }

    if (this.shouldReturnEmpty) {
      return of({
        data: [],
        meta: {
          total: 0,
          page: 1,
          perPage: 20,
          lastPage: 1,
          from: 0,
          to: 0
        }
      });
    }

    if (this.shouldReturnPaginated) {
      return of(mockPaginatedResponse);
    }

    return of(mockSchoolsResponse);
  }

  getAllMySchools() {
    return of(mockSchools);
  }
}

const meta: Meta<SelectSchoolPageComponent> = {
  title: 'Pages/SelectSchool',
  component: SelectSchoolPageComponent,
  decorators: [
    moduleMetadata({
      imports: [SelectSchoolPageComponent],
    }),
    applicationConfig({
      providers: [
        provideAnimations(),
        provideRouter([]),
        { provide: SchoolService, useClass: MockSchoolService },
        { provide: SessionService, useClass: MockSessionService },
        { provide: TranslationService, useClass: MockTranslationService },
      ],
    }),
  ],
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component: 'Página de selección de escuela con búsqueda, paginación y estados de carga/error.',
      },
    },
  },
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<SelectSchoolPageComponent>;

// Default state with schools
export const Default: Story = {
  name: '🏫 Estado por Defecto',
  parameters: {
    docs: {
      description: {
        story: 'Estado normal con lista de escuelas disponibles.',
      },
    },
  },
};

// Loading state
export const Loading: Story = {
  name: '⏳ Cargando',
  play: async ({ canvasElement }) => {
    // Simulate loading state by not providing schools immediately
    const component = canvasElement.querySelector('app-select-school') as any;
    if (component) {
      component._isLoading.set(true);
    }
  },
  parameters: {
    docs: {
      description: {
        story: 'Estado de carga inicial mientras se obtienen las escuelas.',
      },
    },
  },
};

// Empty state
export const Empty: Story = {
  name: '📭 Sin Escuelas',
  decorators: [
    applicationConfig({
      providers: [
        provideAnimations(),
        provideRouter([]),
        { 
          provide: SchoolService, 
          useFactory: () => {
            const service = new MockSchoolService();
            service.setEmptyMode(true);
            return service;
          }
        },
        { provide: SessionService, useClass: MockSessionService },
        { provide: TranslationService, useClass: MockTranslationService },
      ],
    }),
  ],
  parameters: {
    docs: {
      description: {
        story: 'Estado cuando el usuario no tiene acceso a ninguna escuela.',
      },
    },
  },
};

// Error state
export const Error: Story = {
  name: '❌ Error de Carga',
  decorators: [
    applicationConfig({
      providers: [
        provideAnimations(),
        provideRouter([]),
        { 
          provide: SchoolService, 
          useFactory: () => {
            const service = new MockSchoolService();
            service.setErrorMode(true);
            return service;
          }
        },
        { provide: SessionService, useClass: MockSessionService },
        { provide: TranslationService, useClass: MockTranslationService },
      ],
    }),
  ],
  parameters: {
    docs: {
      description: {
        story: 'Estado de error cuando falla la carga de escuelas.',
      },
    },
  },
};

// Paginated results
export const Paginated: Story = {
  name: '📄 Con Paginación',
  decorators: [
    applicationConfig({
      providers: [
        provideAnimations(),
        provideRouter([]),
        { 
          provide: SchoolService, 
          useFactory: () => {
            const service = new MockSchoolService();
            service.setPaginatedMode(true);
            return service;
          }
        },
        { provide: SessionService, useClass: MockSessionService },
        { provide: TranslationService, useClass: MockTranslationService },
      ],
    }),
  ],
  parameters: {
    docs: {
      description: {
        story: 'Estado con múltiples páginas de escuelas y controles de paginación.',
      },
    },
  },
};

// School selection in progress
export const Selecting: Story = {
  name: '🎯 Seleccionando Escuela',
  play: async ({ canvasElement }) => {
    // Wait for component to load
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Find and click the first school button
    const firstButton = canvasElement.querySelector('.select-school-button') as HTMLButtonElement;
    if (firstButton) {
      firstButton.click();
    }
  },
  parameters: {
    docs: {
      description: {
        story: 'Estado durante la selección de una escuela (botón con spinner).',
      },
    },
  },
};

// Dark theme
export const DarkTheme: Story = {
  name: '🌙 Tema Oscuro',
  decorators: [
    (story) => {
      document.body.classList.add('dark');
      return story();
    },
  ],
  parameters: {
    docs: {
      description: {
        story: 'Página en tema oscuro usando design tokens.',
      },
    },
  },
};

// Mobile viewport
export const Mobile: Story = {
  name: '📱 Vista Móvil',
  parameters: {
    viewport: { defaultViewport: 'mobile1' },
    docs: {
      description: {
        story: 'Vista optimizada para dispositivos móviles.',
      },
    },
  },
};

// Interactive demo with all states
export const InteractiveDemo: Story = {
  name: '🎮 Demo Interactivo',
  render: () => ({
    template: `
      <div style="padding: 20px; background: var(--bg);">
        <h2 style="color: var(--text-1); margin-bottom: 20px;">Demo Interactivo</h2>
        <p style="color: var(--text-2); margin-bottom: 20px;">
          Esta historia permite probar todas las funcionalidades:
        </p>
        <ul style="color: var(--text-2); margin-bottom: 20px;">
          <li>Buscar escuelas escribiendo en el campo de búsqueda</li>
          <li>Hacer clic en "Usar esta escuela" para seleccionar</li>
          <li>Navegar entre páginas si hay paginación</li>
          <li>Ver diferentes estados activos/inactivos</li>
        </ul>
        <app-select-school></app-select-school>
      </div>
    `
  }),
  parameters: {
    docs: {
      description: {
        story: 'Demo completo con todas las funcionalidades disponibles para testing manual.',
      },
    },
  },
};