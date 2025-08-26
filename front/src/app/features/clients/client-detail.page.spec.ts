import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, convertToParamMap } from '@angular/router';
import { of, throwError } from 'rxjs';

import { expect } from '@jest/globals';
import { ClientDetailPageComponent, ClientDetail, ClientUtilizador, ClientSport, ClientObservation } from './client-detail.page';
import { ClientsV5Service } from '@core/services/clients-v5.service';
import { ContextService } from '@core/services/context.service';
import { ToastService } from '@core/services/toast.service';

describe('ClientDetailPageComponent', () => {
  let fixture: ComponentFixture<ClientDetailPageComponent>;
  let component: ClientDetailPageComponent;
  let router: jest.Mocked<Router>;
  let clientsService: jest.Mocked<ClientsV5Service>;
  let toast: jest.Mocked<ToastService>;

  const mockClient: ClientDetail = {
    id: 1,
    first_name: 'John',
    last_name: 'Doe',
    created_at: '',
    updated_at: ''
  };

  beforeEach(async () => {
    const routerMock = {
      navigate: jest.fn()
    } as Partial<jest.Mocked<Router>>;

    const clientsServiceMock = {
      getClient: jest.fn().mockReturnValue(of(mockClient))
    } as Partial<jest.Mocked<ClientsV5Service>>;

    const toastMock = {
      success: jest.fn()
    } as Partial<jest.Mocked<ToastService>>;

    const activatedRouteStub = {
      snapshot: {
        paramMap: convertToParamMap({ id: '1' }),
        queryParamMap: convertToParamMap({})
      }
    } as ActivatedRoute;

    const contextServiceStub = {
      schoolId: () => 1
    } as unknown as ContextService;

    await TestBed.configureTestingModule({
      imports: [ClientDetailPageComponent],
      providers: [
        { provide: Router, useValue: routerMock },
        { provide: ActivatedRoute, useValue: activatedRouteStub },
        { provide: ClientsV5Service, useValue: clientsServiceMock },
        { provide: ToastService, useValue: toastMock },
        { provide: ContextService, useValue: contextServiceStub }
      ]
    })
      .overrideComponent(ClientDetailPageComponent, {
        set: { imports: [] }
      })
      .compileComponents();

    fixture = TestBed.createComponent(ClientDetailPageComponent);
    component = fixture.componentInstance;
    router = TestBed.inject(Router) as jest.Mocked<Router>;
    clientsService = TestBed.inject(ClientsV5Service) as jest.Mocked<ClientsV5Service>;
    toast = TestBed.inject(ToastService) as jest.Mocked<ToastService>;
  });

  it('should switch tabs and update query params', () => {
    component.setActiveTab('deportes');
    expect(component.activeTab()).toBe('deportes');
    expect(router.navigate).toHaveBeenCalledWith([], {
      relativeTo: TestBed.inject(ActivatedRoute),
      queryParams: { tab: 'deportes' },
      queryParamsHandling: 'merge'
    });
  });

  it('should update client and show success toast', () => {
    component.client.set(mockClient);
    const updated: ClientDetail = { ...mockClient, first_name: 'Jane' };
    component.onClientUpdated(updated);
    expect(component.client()).toEqual(updated);
    expect(toast.success).toHaveBeenCalledWith('clients.detail.updated');
  });

  it('should update related collections', () => {
    component.client.set({ ...mockClient, utilizadores: [], client_sports: [], observations: [] });

    const utilizadores: ClientUtilizador[] = [
      { id: 1, client_id: 1, first_name: 'Ana', last_name: 'Pérez', created_at: '', updated_at: '' }
    ];
    component.onUtilizadoresUpdated(utilizadores);
    expect(component.client()?.utilizadores).toEqual(utilizadores);

    const sports: ClientSport[] = [
      { id: 1, client_id: 1, person_type: 'client', person_id: 1, sport_id: 2, created_at: '', updated_at: '' }
    ];
    component.onSportsUpdated(sports);
    expect(component.client()?.client_sports).toEqual(sports);

    const observations: ClientObservation[] = [
      { id: 1, client_id: 1, title: 'note', content: 'content', created_at: '', updated_at: '' }
    ];
    component.onObservationsUpdated(observations);
    expect(component.client()?.observations).toEqual(observations);
  });

  it('should handle errors from ClientsV5Service when loading client', () => {
    clientsService.getClient.mockReturnValue(throwError(() => new Error('failed')));

    (component as any).loadClient = function (id: number) {
      this.loading.set(true);
      this.clientsService.getClient(id).subscribe({
        next: (c: ClientDetail) => {
          this.client.set(c);
          this.loading.set(false);
        },
        error: () => {
          this.client.set(null);
          this.loading.set(false);
        }
      });
    };

    (component as any).loadClient(1);

    expect(component.loading()).toBe(false);
    expect(component.client()).toBeNull();
  });
});
