import { ComponentFixture, TestBed } from '@angular/core/testing';
import { SelectSchoolPageComponent } from './select-school.page';
import { AuthV5Service } from '@core/services/auth-v5.service';
import { ContextService } from '@core/services/context.service';
import { ToastService } from '@core/services/toast.service';
import { TranslationService } from '@core/services/translation.service';
import { Router } from '@angular/router';
import { signal } from '@angular/core';
import { of } from 'rxjs';
import { expect } from '@jest/globals';

describe('SelectSchoolPageComponent', () => {
  let component: SelectSchoolPageComponent;
  let fixture: ComponentFixture<SelectSchoolPageComponent>;
  let authV5: AuthV5Service;
  let context: ContextService;
  let router: Router;

  const mockSchools = [
    { id: 1, name: 'School A', active: true },
    { id: 2, name: 'School B', active: true }
  ];

  beforeEach(async () => {
    const authV5Spy = {
      selectSchool: jest.fn().mockReturnValue(of({ success: true, data: { school: mockSchools[0] } })),
      user: signal({ schools: mockSchools } as any),
      tokenSignal: signal('temp-token')
    } as unknown as AuthV5Service;

    const contextSpy = { setSelectedSchool: jest.fn() } as unknown as ContextService;
    const routerSpy = { navigate: jest.fn() } as unknown as Router;
    const toastSpy = { success: jest.fn(), error: jest.fn() } as unknown as ToastService;
    const translationSpy = {
      get: jest.fn((k: string) => k),
      currentLanguage: jest.fn(() => 'en')
    } as unknown as TranslationService;

    await TestBed.configureTestingModule({
      imports: [SelectSchoolPageComponent],
      providers: [
        { provide: AuthV5Service, useValue: authV5Spy },
        { provide: ContextService, useValue: contextSpy },
        { provide: Router, useValue: routerSpy },
        { provide: ToastService, useValue: toastSpy },
        { provide: TranslationService, useValue: translationSpy }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(SelectSchoolPageComponent);
    component = fixture.componentInstance;
    authV5 = TestBed.inject(AuthV5Service);
    context = TestBed.inject(ContextService);
    router = TestBed.inject(Router);

    fixture.detectChanges();
  });

  it('should load schools from AuthV5Service', () => {
    expect(component.schools()).toEqual(mockSchools);
  });

  it('should persist school id on selection', () => {
    const setItemSpy = jest.spyOn(Storage.prototype, 'setItem');

    component.selectSchool(mockSchools[0] as any);

    expect(context.setSelectedSchool).toHaveBeenCalledWith(mockSchools[0] as any);
    expect(setItemSpy).toHaveBeenCalledWith('boukiiSchoolId', mockSchools[0].id.toString());
    expect(router.navigate).toHaveBeenCalledWith(['/select-season']);
  });
});

