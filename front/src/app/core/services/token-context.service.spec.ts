import { TestBed } from '@angular/core/testing';
import { BehaviorSubject } from 'rxjs';
import { expect } from '@jest/globals';

import { TokenContextService } from './token-context.service';
import { SessionService } from './session.service';
import { ContextService } from './context.service';

describe('TokenContextService', () => {
  let service: TokenContextService;
  let mockSession: { currentSchool$: BehaviorSubject<any> };
  let mockContext: {
    getSelectedSchoolId: jest.Mock<number | null, []>;
    getSelectedSeasonId: jest.Mock<number | null, []>;
  };

  beforeEach(() => {
    mockSession = {
      currentSchool$: new BehaviorSubject<any>({ id: 1 })
    };
    mockContext = {
      getSelectedSchoolId: jest.fn().mockReturnValue(1),
      getSelectedSeasonId: jest.fn().mockReturnValue(2)
    } as any;

    TestBed.configureTestingModule({
      providers: [
        TokenContextService,
        { provide: SessionService, useValue: mockSession },
        { provide: ContextService, useValue: mockContext }
      ]
    });

    service = TestBed.inject(TokenContextService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('returns token from localStorage', () => {
    localStorage.setItem('boukii_auth_token', 'abc123');
    expect(service.getToken()).toBe('abc123');
  });

  it('returns auth context when ids available', () => {
    expect(service.getAuthContext()).toEqual({ school_id: 1, season_id: 2 });
  });

  it('returns null when context incomplete', () => {
    mockContext.getSelectedSeasonId.mockReturnValue(null);
    expect(service.getAuthContext()).toBeNull();
  });
});
