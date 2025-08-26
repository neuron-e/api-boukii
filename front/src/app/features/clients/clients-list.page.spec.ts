import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, convertToParamMap } from '@angular/router';
import { of } from 'rxjs';

import { expect } from '@jest/globals';
import { ClientsListPageComponent, ClientListItem } from './clients-list.page';
import { ClientsV5Service, ClientsResponse } from '@core/services/clients-v5.service';
import { ContextService } from '@core/services/context.service';
import { TranslationService } from '@core/services/translation.service';

describe('ClientsListPageComponent', () => {
  let component: ClientsListPageComponent;
  let fixture: ComponentFixture<ClientsListPageComponent>;
  let clientsService: jest.Mocked<ClientsV5Service>;
  let router: jest.Mocked<Router>;

  beforeEach(async () => {
    const mockResponse: ClientsResponse = {
      data: [],
      meta: { pagination: { page: 1, limit: 0, total: 0, totalPages: 0 } }
    };

    const clientsServiceMock = {
      getClients: jest.fn().mockReturnValue(of(mockResponse))
    } as Partial<jest.Mocked<ClientsV5Service>>;

    const routerMock = {
      navigate: jest.fn()
    } as Partial<jest.Mocked<Router>>;

    const contextServiceStub = { schoolId: () => 1 } as unknown as ContextService;

    const activatedRouteStub = {
      snapshot: {
        queryParamMap: convertToParamMap({ q: 'john', sport_id: '2', active: 'true' })
      }
    } as ActivatedRoute;

    const translationServiceStub = {
      get: () => '',
      currentLanguage: () => 'en',
      instant: () => '',
      formatDate: () => '',
      formatNumber: () => ''
    } as Partial<TranslationService>;

    await TestBed.configureTestingModule({
      imports: [ClientsListPageComponent],
      providers: [
        { provide: ClientsV5Service, useValue: clientsServiceMock },
        { provide: Router, useValue: routerMock },
        { provide: ActivatedRoute, useValue: activatedRouteStub },
        { provide: ContextService, useValue: contextServiceStub },
        { provide: TranslationService, useValue: translationServiceStub }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(ClientsListPageComponent);
    component = fixture.componentInstance;
    clientsService = TestBed.inject(ClientsV5Service) as jest.Mocked<ClientsV5Service>;
    router = TestBed.inject(Router) as jest.Mocked<Router>;

    fixture.detectChanges();
  });

  it('should restore filters from query params on init', () => {
    expect(component.filtersForm.value).toEqual({ q: 'john', sport_id: '2', active: 'true' });
    expect(clientsService.getClients).toHaveBeenCalledWith({
      school_id: 1,
      q: 'john',
      sport_id: 2,
      active: true,
      page: 1
    });
  });

  it('should update query params and fetch clients when filters change', () => {
    clientsService.getClients.mockClear();
    router.navigate.mockClear();

    component.filtersForm.setValue({ q: 'jane', sport_id: '3', active: 'false' });

    expect(router.navigate).toHaveBeenCalledWith([], {
      queryParams: { q: 'jane', sport_id: '3', active: 'false', page: 1 },
      queryParamsHandling: 'merge'
    });
    expect(clientsService.getClients).toHaveBeenCalledWith({
      school_id: 1,
      q: 'jane',
      sport_id: 3,
      active: false,
      page: 1
    });
  });

  it('should open and close preview', () => {
    const c = { id: 1 } as ClientListItem;
    component.openPreview(c);
    expect(component.selectedClient).toBe(c);
    component.closePreview();
    expect(component.selectedClient).toBeNull();
  });

  it('should close preview on escape key', () => {
    const c = { id: 2 } as ClientListItem;
    component.openPreview(c);
    component.handleEscape(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(component.selectedClient).toBeNull();
  });

  it('should navigate to edit page', () => {
    const c = { id: 5 } as ClientListItem;
    component.editClient(c);
    expect(router.navigate).toHaveBeenCalledWith(['/clients', 5, 'edit']);
  });

  it('should show confirmation dialog on delete', () => {
    const c = { id: 6 } as ClientListItem;
    const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);
    component.deleteClient(c);
    expect(confirmSpy).toHaveBeenCalled();
    confirmSpy.mockRestore();
  });
});

