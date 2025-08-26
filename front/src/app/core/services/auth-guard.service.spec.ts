import { TestBed } from '@angular/core/testing';
import { BehaviorSubject } from 'rxjs';
import { expect } from '@jest/globals';

import { AuthGuardService } from './auth-guard.service';
import { SessionService } from './session.service';
import { ContextService } from './context.service';

describe('AuthGuardService', () => {
  let service: AuthGuardService;
  let mockSession: { currentSchool$: BehaviorSubject<any> };
  let mockContext: { context: jest.Mock<any, []> };

  beforeEach(() => {
    mockSession = { currentSchool$: new BehaviorSubject<any>({ id: 1 }) };
    mockContext = { context: jest.fn().mockReturnValue({ schoolId: 1, seasonId: 2 }) } as any;

    TestBed.configureTestingModule({
      providers: [
        AuthGuardService,
        { provide: SessionService, useValue: mockSession },
        { provide: ContextService, useValue: mockContext }
      ]
    });

    service = TestBed.inject(AuthGuardService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('detects authentication token in localStorage', () => {
    localStorage.setItem('boukii_auth_token', 'token123');
    expect(service.isAuthenticated()).toBe(true);
  });

  it('returns auth context when both ids present', () => {
    expect(service.getAuthContext()).toEqual({ school_id: 1, season_id: 2 });
  });

  it('checks permissions from storage', () => {
    localStorage.setItem('boukii_permissions', JSON.stringify(['a', 'b']));
    expect(service.hasAnyPermission(['b', 'c'])).toBe(true);
  });
});
