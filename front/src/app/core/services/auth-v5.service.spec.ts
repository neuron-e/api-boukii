import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { expect } from '@jest/globals';
import { AuthV5Service } from './auth-v5.service';
import { ApiService } from './api.service';
import { LoggingService } from './logging.service';
import { ROLE_SUPERADMIN } from '../constants/roles';

describe('AuthV5Service', () => {
  let service: AuthV5Service;
  let mockRouter: jest.Mocked<Router>;
  let mockApiService: jest.Mocked<ApiService>;
  let mockLoggingService: jest.Mocked<LoggingService>;

  beforeEach(() => {
    const routerSpy = {
      navigate: jest.fn()
    };
    
    const apiServiceSpy = {
      post: jest.fn(),
      get: jest.fn(),
      put: jest.fn(),
      patch: jest.fn(),
      delete: jest.fn(),
      uploadFile: jest.fn(),
      downloadFile: jest.fn()
    };
    
    const loggingServiceSpy = {
      logInfo: jest.fn(),
      logError: jest.fn(),
      logWarning: jest.fn(),
      getRecentLogs: jest.fn(),
      clearLogs: jest.fn(),
      exportLogs: jest.fn(),
      setLogLevel: jest.fn(),
      getLogLevel: jest.fn()
    };

    TestBed.configureTestingModule({
      providers: [
        AuthV5Service,
        { provide: Router, useValue: routerSpy },
        { provide: ApiService, useValue: apiServiceSpy },
        { provide: LoggingService, useValue: loggingServiceSpy }
      ]
    });

    service = TestBed.inject(AuthV5Service);
    mockRouter = TestBed.inject(Router) as any;
    mockApiService = TestBed.inject(ApiService) as any;
    mockLoggingService = TestBed.inject(LoggingService) as any;
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  describe('isAuthenticated', () => {
    it('should return false when no token is present', () => {
      expect(service.isAuthenticated()).toBe(false);
    });

    it('should return true when token is present', () => {
      service.tokenSignal.set('test-token');
      expect(service.isAuthenticated()).toBe(true);
    });
  });

  describe('isSuperAdmin', () => {
    it('should return true when role is superadmin', () => {
      service.roleSignal.set(ROLE_SUPERADMIN);
      expect(service.isSuperAdmin()).toBe(true);
    });
  });

  describe('checkUser', () => {
    it('should call checkUser with correct credentials', (done) => {
      const credentials = { email: 'test@example.com', password: 'password123' };
      const mockResponse = {
        success: true,
        data: {
          user: { id: 1, email: credentials.email, name: 'Test User', created_at: '2025-01-01', updated_at: '2025-01-01' },
          token: 'test-token',
          schools: []
        }
      };

      // Mock the API service to return a promise
      mockApiService.post.mockReturnValue(Promise.resolve(mockResponse));

      service.checkUser(credentials).subscribe(response => {
        expect(response.success).toBe(true);
        expect(response.data?.user.email).toBe(credentials.email);
        done();
      });
    });

    it('should log checkUser attempt', (done) => {
      const credentials = { email: 'test@example.com', password: 'password123' };
      const mockResponse = {
        success: true,
        data: {
          user: { id: 1, email: credentials.email, name: 'Test User', created_at: '2025-01-01', updated_at: '2025-01-01' },
          token: 'test-token',
          schools: []
        }
      };

      // Mock the API service to return a promise
      mockApiService.post.mockReturnValue(Promise.resolve(mockResponse));

      service.checkUser(credentials).subscribe(() => {
        expect(mockLoggingService.logInfo).toHaveBeenCalledWith(
          'AuthV5Service: Checking user credentials',
          { email: credentials.email }
        );
        done();
      });
    });
  });

  describe('register', () => {
    it('should call register with correct user data', (done) => {
      const userData = { 
        name: 'Test User',
        email: 'test@example.com', 
        password: 'password123' 
      };
      const mockResponse = {
        success: true,
        data: {
          user: { id: 1, email: userData.email, name: userData.name, created_at: '2025-01-01', updated_at: '2025-01-01' },
          token: 'test-token'
        }
      };
      
      // Mock the API service to return a promise
      mockApiService.post.mockReturnValue(Promise.resolve(mockResponse));
      
      service.register(userData).subscribe(response => {
        expect(response.success).toBe(true);
        expect(response.data?.user.email).toBe(userData.email);
        expect(response.data?.user.name).toBe(userData.name);
        done();
      });
    });
  });

  describe('logout', () => {
    it('should clear authentication state and navigate to login', () => {
      // Set up authenticated state
      service.tokenSignal.set('test-token');
      service.userSignal.set({
        id: 1,
        name: 'Test User',
        email: 'test@example.com',
        is_active: true,
        created_at: '2025-01-01',
        updated_at: '2025-01-01'
      });

      service.logout();

      expect(service.isAuthenticated()).toBe(false);
      expect(service.user()).toBe(null);
      expect(mockRouter.navigate).toHaveBeenCalledWith(['/auth/login']);
    });
  });

  describe('getAuthContext', () => {
    it('should return null when no context is set', () => {
      expect(service.getAuthContext()).toBe(null);
    });

    it('should return context when school and season are set', () => {
      service.currentSchoolIdSignal.set(1);
      service.currentSeasonIdSignal.set(2);
      service.permissionsSignal.set(['read', 'write']);
      service.userSignal.set({
        id: 1,
        name: 'Test User',
        email: 'test@example.com',
        is_active: true,
        created_at: '2025-01-01',
        updated_at: '2025-01-01',
        roles: []
      });

      const context = service.getAuthContext();

      expect(context).not.toBe(null);
      expect(context?.school_id).toBe(1);
      expect(context?.season_id).toBe(2);
      expect(context?.permissions).toEqual(['read', 'write']);
    });
  });

  describe('hasPermission', () => {
    it('should return false for permission not in list', () => {
      service.permissionsSignal.set(['read']);
      expect(service.hasPermission('write')).toBe(false);
    });

    it('should return true for permission in list', () => {
      service.permissionsSignal.set(['read', 'write']);
      expect(service.hasPermission('write')).toBe(true);
    });
  });

  describe('hasAnyPermission', () => {
    it('should return false when user has none of the required permissions', () => {
      service.permissionsSignal.set(['read']);
      expect(service.hasAnyPermission(['write', 'admin'])).toBe(false);
    });

    it('should return true when user has at least one of the required permissions', () => {
      service.permissionsSignal.set(['read', 'write']);
      expect(service.hasAnyPermission(['write', 'admin'])).toBe(true);
    });
  });

  describe('getUserSchools', () => {
    it('should return current schools as observable', (done) => {
      const schools = [
        {
          id: 1,
          name: 'Test School',
          slug: 'test-school',
          status: 'active' as const,
          created_at: '2025-01-01',
          updated_at: '2025-01-01',
          seasons: []
        }
      ];
      
      service.schoolsSignal.set(schools);
      
      service.getUserSchools().subscribe(response => {
        expect(response.success).toBe(true);
        expect(response.data).toEqual(schools);
        done();
      });
    });
  });

  describe('setCurrentSchool', () => {
    it('should set current school and navigate to dashboard', (done) => {
      const schools = [
        {
          id: 1,
          name: 'Test School',
          slug: 'test-school',
          status: 'active' as const,
          created_at: '2025-01-01',
          updated_at: '2025-01-01',
          seasons: [
            {
              id: 1,
              school_id: 1,
              name: 'Test Season',
              slug: 'test-season',
              start_date: '2025-01-01',
              end_date: '2025-12-31',
              status: 'active' as const,
              is_current: true,
              created_at: '2025-01-01',
              updated_at: '2025-01-01'
            }
          ]
        }
      ];
      
      service.schoolsSignal.set(schools);
      
      service.setCurrentSchool(1).subscribe(_result => {
        expect(service.currentSchoolIdSignal()).toBe(1);
        expect(service.currentSeasonIdSignal()).toBe(1); // Auto-selected
        expect(mockRouter.navigate).toHaveBeenCalledWith(['/dashboard']);
        done();
      });
    });

    it('should throw error for non-existent school', () => {
      service.schoolsSignal.set([]);
      
      expect(() => service.setCurrentSchool(999)).toThrowError('School not found');
    });
  });
});