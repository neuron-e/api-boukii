import { ComponentFixture, TestBed } from '@angular/core/testing';
import { SelectSchoolPageComponent } from './select-school.page';
import { AuthV5Service } from '@core/services/auth-v5.service';
import { SessionService } from '@core/services/session.service';
import { ToastService } from '@core/services/toast.service';
import { TranslationService } from '@core/services/translation.service';
import { Router } from '@angular/router';
import { signal } from '@angular/core';
import { of } from 'rxjs';
import { expect } from '@jest/globals';
import { SchoolService, SchoolsResponse } from '@core/services/school.service';

describe('SelectSchoolPageComponent', () => {
  let component: SelectSchoolPageComponent;
  let fixture: ComponentFixture<SelectSchoolPageComponent>;
  let authV5Spy: any;
  let sessionSpy: any;
  let routerSpy: any;
  let toastSpy: any;
  let translationSpy: any;
  let schoolServiceSpy: any;

  const mockSchools = [
    { id: 1, name: 'School A', active: true },
    { id: 2, name: 'School B', active: true }
  ];

  const createComponent = () => {
    fixture = TestBed.createComponent(SelectSchoolPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  };

  beforeEach(async () => {
    authV5Spy = {
      selectSchool: jest.fn().mockReturnValue(of({ success: true, data: { school: mockSchools[0] } })),
      user: signal({ schools: mockSchools } as any),
      tokenSignal: signal('temp-token'),
      isSuperAdmin: signal(false)
    } as unknown as AuthV5Service;

    sessionSpy = { selectSchool: jest.fn() } as unknown as SessionService;
    routerSpy = { navigate: jest.fn() } as unknown as Router;
    toastSpy = { success: jest.fn(), error: jest.fn() } as unknown as ToastService;
    translationSpy = {
      get: jest.fn((k: string) => k),
      currentLanguage: jest.fn(() => 'en')
    } as unknown as TranslationService;
    schoolServiceSpy = { listAll: jest.fn() } as unknown as SchoolService;

    await TestBed.configureTestingModule({
      imports: [SelectSchoolPageComponent],
      providers: [
        { provide: AuthV5Service, useValue: authV5Spy },
        { provide: SessionService, useValue: sessionSpy },
        { provide: Router, useValue: routerSpy },
        { provide: ToastService, useValue: toastSpy },
        { provide: TranslationService, useValue: translationSpy },
        { provide: SchoolService, useValue: schoolServiceSpy }
      ]
    }).compileComponents();
  });

  it('should load schools from AuthV5Service', () => {
    createComponent();
    expect(component.schools()).toEqual(mockSchools);
  });

  it('should persist school id on selection', () => {
    createComponent();
    component.selectSchool(mockSchools[0] as any);
    expect(sessionSpy.selectSchool).toHaveBeenCalledWith(mockSchools[0] as any);
    expect(routerSpy.navigate).toHaveBeenCalledWith(['/select-season']);
  });

  describe('super admin', () => {
    beforeEach(() => {
      authV5Spy.isSuperAdmin = signal(true);
      authV5Spy.user = signal({} as any);
    });

    it('should fetch first page of schools', () => {
      const response: SchoolsResponse = {
        data: mockSchools,
        meta: { total: 2, page: 1, perPage: 20, lastPage: 1, from: 1, to: 2 }
      };
      schoolServiceSpy.listAll.mockReturnValue(of(response));

      createComponent();

      expect(schoolServiceSpy.listAll).toHaveBeenCalledWith({ page: 1, perPage: 20, search: '' });
      expect(component.schools()).toEqual(mockSchools);
    });

    it('should load next page of schools', () => {
      const firstPage: SchoolsResponse = {
        data: [mockSchools[0]],
        meta: { total: 2, page: 1, perPage: 20, lastPage: 2, from: 1, to: 1 }
      };
      const secondPage: SchoolsResponse = {
        data: [mockSchools[1]],
        meta: { total: 2, page: 2, perPage: 20, lastPage: 2, from: 2, to: 2 }
      };
      schoolServiceSpy.listAll
        .mockReturnValueOnce(of(firstPage))
        .mockReturnValueOnce(of(secondPage));

      createComponent();
      component.loadMore();

      expect(schoolServiceSpy.listAll).toHaveBeenLastCalledWith({ page: 2, perPage: 20, search: '' });
      expect(component.schools()).toEqual([...firstPage.data, ...secondPage.data]);
    });

    it('should search schools via API', () => {
      jest.useFakeTimers();
      const firstPage: SchoolsResponse = {
        data: mockSchools,
        meta: { total: 2, page: 1, perPage: 20, lastPage: 1, from: 1, to: 2 }
      };
      const searchPage: SchoolsResponse = {
        data: [mockSchools[1]],
        meta: { total: 1, page: 1, perPage: 20, lastPage: 1, from: 1, to: 1 }
      };
      schoolServiceSpy.listAll
        .mockReturnValueOnce(of(firstPage))
        .mockReturnValueOnce(of(searchPage));

      createComponent();
      component.searchQuery = 'School B';
      component.onSearchInput();
      jest.advanceTimersByTime(300);

      expect(schoolServiceSpy.listAll).toHaveBeenNthCalledWith(2, { page: 1, perPage: 20, search: 'School B' });
      expect(component.schools()).toEqual([mockSchools[1]]);
      jest.useRealTimers();
    });
  });
});

