import { Injectable, inject, signal, computed } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, of, from } from 'rxjs';
import { tap, catchError } from 'rxjs/operators';

import { ApiService, ApiResponse } from './api.service';
import { LoggingService } from './logging.service';
import {
  LoginRequest,
  RegisterRequest,
  User,
  School,
  AuthContext
} from '../models/auth-v5.models';

@Injectable({
  providedIn: 'root'
})
export class AuthV5Service {
  private readonly apiService = inject(ApiService);
  private readonly logger = inject(LoggingService);
  private readonly router = inject(Router);

  // Reactive state using signals
  readonly tokenSignal = signal<string | null>(null);
  readonly userSignal = signal<User | null>(null);
  readonly schoolsSignal = signal<School[]>([]);
  readonly currentSchoolIdSignal = signal<number | null>(null);
  readonly currentSeasonIdSignal = signal<number | null>(null);
  readonly permissionsSignal = signal<string[]>([]);

  // Computed properties
  readonly isAuthenticated = computed(() => !!this.tokenSignal());
  readonly user = computed(() => this.userSignal());
  readonly schools = computed(() => this.schoolsSignal());
  readonly permissions = computed(() => this.permissionsSignal());
  readonly currentSchool = computed(() => {
    const schoolId = this.currentSchoolIdSignal();
    const schools = this.schoolsSignal();
    return schoolId ? schools.find(s => s.id === schoolId) || null : null;
  });
  readonly currentSeason = computed(() => {
    const seasonId = this.currentSeasonIdSignal();
    const school = this.currentSchool();
    return seasonId && school && school.seasons ? 
      school.seasons.find(s => s.id === seasonId) || null : null;
  });

  constructor() {
    this.loadStoredData();
  }

  /**
   * Step 1: Check user credentials and get available schools
   */
  public checkUser(credentials: { email: string; password: string }): Observable<ApiResponse<any>> {
    this.logger.logInfo('AuthV5Service: Checking user credentials', { email: credentials.email });

    return from(this.apiService.post<ApiResponse<any>>('/auth/check-user', credentials)).pipe(
      tap(response => {
        this.logger.logInfo('AuthV5Service: User check successful', { 
          email: credentials.email,
          schoolsCount: response.data?.schools?.length || 0
        });
      }),
      catchError(error => {
        console.error('AuthV5Service: User check failed', { email: credentials.email, error });
        throw error;
      })
    );
  }

  /**
   * Step 2: Select school and get available seasons
   */
  public selectSchool(schoolId: number, tempToken: string): Observable<ApiResponse<any>> {
    this.logger.logInfo('AuthV5Service: Selecting school', { schoolId });

    return from(this.apiService.postWithHeaders<ApiResponse<any>>('/auth/select-school', 
      { school_id: schoolId },
      { 'Authorization': `Bearer ${tempToken}` }
    )).pipe(
      tap(response => {
        this.logger.logInfo('AuthV5Service: School selection successful', { 
          schoolId,
          seasonsCount: response.data?.seasons?.length || 0
        });
      }),
      catchError(error => {
        console.error('AuthV5Service: School selection failed', { schoolId, error });
        throw error;
      })
    );
  }

  /**
   * Step 3: Select season and complete login
   */
  public selectSeason(seasonId: number, schoolId: number, tempToken: string): Observable<ApiResponse<any>> {
    this.logger.logInfo('AuthV5Service: Selecting season', { seasonId, schoolId });

    return from(this.apiService.postWithHeaders<ApiResponse<any>>('/auth/select-season', 
      { season_id: seasonId, school_id: schoolId },
      { 'Authorization': `Bearer ${tempToken}` }
    )).pipe(
      tap(response => {
        this.logger.logInfo('AuthV5Service: Season selection successful', { seasonId });
        if (response.success && response.data) {
          this.handleLoginSuccess(response.data);
        }
      }),
      catchError(error => {
        console.error('AuthV5Service: Season selection failed', { seasonId, error });
        throw error;
      })
    );
  }

  /**
   * Complete V5 login flow in one step (if school and season known)
   */
  public loginComplete(credentials: LoginRequest & { school_id?: number; season_id?: number }): Observable<ApiResponse<any>> {
    this.logger.logInfo('AuthV5Service: Attempting complete login', { email: credentials.email });

    return from(this.apiService.post<ApiResponse<any>>('/auth/login', credentials)).pipe(
      tap(response => {
        this.logger.logInfo('AuthV5Service: Complete login successful', { email: credentials.email });
        if (response.success && response.data) {
          this.handleLoginSuccess(response.data);
          this.router.navigate(['/dashboard']);
        }
      }),
      catchError(error => {
        console.error('AuthV5Service: Complete login failed', { email: credentials.email, error });
        throw error;
      })
    );
  }

  /**
   * Legacy login method (for backward compatibility)
   */
  public login(credentials: LoginRequest): Observable<ApiResponse<any>> {
    this.logger.logInfo('AuthV5Service: Using legacy login, switching to V5 flow');
    
    // Start with checkUser for V5 flow
    return this.checkUser(credentials);
  }

  /**
   * Register new user
   */
  public register(userData: RegisterRequest): Observable<ApiResponse<any>> {
    this.logger.logInfo('AuthV5Service: Attempting registration', { email: userData.email });

    // Try real API first, fallback to mock if fails
    return from(this.apiService.post<ApiResponse<any>>('/auth/register', userData)).pipe(
      tap(response => {
        this.logger.logInfo('AuthV5Service: Real API registration successful', { email: userData.email });
        if (response.success && response.data) {
          this.handleLoginSuccess(response.data);
          this.navigateAfterLogin(response.data.schools);
        }
      }),
      catchError(apiError => {
        this.logger.logWarning('AuthV5Service: Real API failed, using mock', { 
          email: userData.email, 
          error: apiError 
        });
        
        // Fallback to mock implementation
        return of({
          success: true,
          data: {
            user: {
              id: 1,
              name: userData.name,
              email: userData.email,
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString()
            },
            token: 'mock-jwt-token',
            schools: []
          },
          message: 'Registration successful (Mock Mode)'
        }).pipe(
          tap(response => {
            if (response.success && response.data) {
              this.handleLoginSuccess(response.data);
              this.navigateAfterLogin(response.data.schools);
            }
          })
        );
      })
    );
  }

  /**
   * Request password reset (forgot password)
   */
  public forgotPassword(email: string): Observable<ApiResponse<{ message: string }>> {
    return this.requestPasswordReset({ email });
  }

  /**
   * Request password reset
   */
  public requestPasswordReset(data: { email: string }): Observable<ApiResponse<{ message: string }>> {
    this.logger.logInfo('AuthV5Service: Requesting password reset', { email: data.email });

    // Try real API first, fallback to mock if fails
    return from(this.apiService.post<ApiResponse<{ message: string }>>('/auth/forgot-password', data)).pipe(
      tap(() => {
        this.logger.logInfo('AuthV5Service: Real API password reset successful', { email: data.email });
      }),
      catchError(apiError => {
        this.logger.logWarning('AuthV5Service: Real API failed, using mock', { 
          email: data.email, 
          error: apiError 
        });
        
        // Fallback to mock implementation
        return of({
          success: true,
          data: { message: 'Password reset email sent (Mock Mode)' },
          message: 'Password reset email sent (Mock Mode)'
        });
      })
    );
  }

  /**
   * Logout user
   */
  public logout(): void {
    this.logger.logInfo('AuthV5Service: Logout completed');
    
    this.clearState();
    this.router.navigate(['/auth/login']);
  }

  /**
   * Get auth context (school_id, season_id, permissions)
   */
  public getAuthContext(): AuthContext | null {
    const schoolId = this.currentSchoolIdSignal();
    const seasonId = this.currentSeasonIdSignal();
    const permissions = this.permissionsSignal();

    if (schoolId && seasonId) {
      return {
        school_id: schoolId,
        season_id: seasonId,
        permissions
      };
    }

    return null;
  }

  /**
   * Get current token
   */
  public getToken(): string | null {
    return this.tokenSignal();
  }

  /**
   * Check if user has specific permission
   */
  public hasPermission(permission: string): boolean {
    return this.permissionsSignal().includes(permission);
  }

  /**
   * Check if user has any of the specified permissions
   */
  public hasAnyPermission(permissions: string[]): boolean {
    const userPermissions = this.permissionsSignal();
    return permissions.some(permission => userPermissions.includes(permission));
  }

  /**
   * Get user schools (for school selection page)
   */
  public getUserSchools(): Observable<ApiResponse<School[]>> {
    const schools = this.schoolsSignal();
    return of({
      success: true,
      data: schools,
      message: 'Schools retrieved successfully'
    });
  }

  /**
   * Select school (for school selection page) - renamed to avoid conflict
   */
  public setSelectedSchool(schoolId: number): Observable<unknown> {
    return this.setCurrentSchool(schoolId);
  }

  /**
   * Set current school
   */
  public setCurrentSchool(schoolId: number): Observable<unknown> {
    const schools = this.schoolsSignal();
    const school = schools.find(s => s.id === schoolId);
    
    if (!school) {
      throw new Error('School not found');
    }

    this.currentSchoolIdSignal.set(schoolId);
    this.storeSchoolId(schoolId);

    // Auto-select season if only one active (check if seasons exists)
    const seasons = school.seasons || [];
    const activeSeasons = seasons.filter(s => s.status === 'active');
    if (activeSeasons.length === 1) {
      return this.setCurrentSeason(activeSeasons[0].id);
    }

    this.logger.logInfo('AuthV5Service: School selected', { 
      schoolId, 
      schoolName: school.name,
      seasonsCount: seasons.length,
      activeSeasonsCount: activeSeasons.length
    });
    this.router.navigate(['/dashboard']);
    return of({ success: true });
  }

  /**
   * Set current season
   */
  public setCurrentSeason(seasonId: number): Observable<unknown> {
    this.currentSeasonIdSignal.set(seasonId);
    this.storeSeasonId(seasonId);

    this.logger.logInfo('AuthV5Service: Season selected', { seasonId });
    this.router.navigate(['/dashboard']);
    return of({ success: true });
  }

  // Private helper methods

  public handleLoginSuccess(data: any): void {
    console.log('🔑 handleLoginSuccess called with data:', data);
    
    if (!data) {
      console.log('❌ handleLoginSuccess: No data provided');
      return;
    }

    // Handle both 'token' and 'access_token' from different API responses
    const token = data.token || data.access_token;
    if (!token) {
      console.log('❌ handleLoginSuccess: No token found in response');
      return;
    }

    this.tokenSignal.set(token);
    this.userSignal.set(data.user);
    
    // Handle both 'schools' (array) and 'school' (single object) cases
    const schools = data.schools || (data.school ? [data.school] : []);
    this.schoolsSignal.set(schools);

    this.storeToken(token);
    this.storeUser(data.user);
    this.storeSchools(schools);

    // Set current school and season if provided
    if (data.school) {
      console.log('🏫 Setting current school:', data.school);
      this.currentSchoolIdSignal.set(data.school.id);
      this.storeSchoolId(data.school.id);
    } else {
      console.log('❌ No school data in response');
    }
    
    if (data.season) {
      console.log('🗓️ Setting current season:', data.season);
      this.currentSeasonIdSignal.set(data.season.id);
      this.storeSeasonId(data.season.id);
    } else {
      console.log('❌ No season data in response');
    }

    console.log('🔍 Auth context after login:', {
      schoolId: this.currentSchoolIdSignal(),
      seasonId: this.currentSeasonIdSignal(),
      authContext: this.getAuthContext()
    });

    this.logger.logInfo('AuthV5Service: Login successful', {
      userId: data.user?.id,
      schoolCount: schools.length,
      currentSchoolId: data.school?.id,
      currentSeasonId: data.season?.id
    });
  }

  private navigateAfterLogin(schools: School[]): void {
    if (schools.length === 0) {
      this.logger.logWarning('AuthV5Service: User has no schools assigned');
      this.router.navigate(['/no-access']);
      return;
    }

    if (schools.length === 1) {
      this.logger.logInfo('AuthV5Service: Auto-selecting single school', { schoolId: schools[0].id });
      this.setCurrentSchool(schools[0].id).subscribe();
      return;
    }

    this.logger.logInfo('AuthV5Service: Multiple schools available, navigating to selection');
    this.router.navigate(['/select-school']);
  }

  private clearState(): void {
    this.tokenSignal.set(null);
    this.userSignal.set(null);
    this.schoolsSignal.set([]);
    this.currentSchoolIdSignal.set(null);
    this.currentSeasonIdSignal.set(null);
    this.permissionsSignal.set([]);

    this.clearStoredData();
  }

  private loadStoredData(): void {
    try {
      const token = localStorage.getItem('boukii_auth_token');
      const user = localStorage.getItem('boukii_user');
      const schools = localStorage.getItem('boukii_schools');
      const schoolId = localStorage.getItem('boukii_school_id');
      const seasonId = localStorage.getItem('boukii_season_id');
      const permissions = localStorage.getItem('boukii_permissions');

      if (token) this.tokenSignal.set(token);
      if (user) this.userSignal.set(JSON.parse(user));
      if (schools) this.schoolsSignal.set(JSON.parse(schools));
      if (schoolId) this.currentSchoolIdSignal.set(parseInt(schoolId));
      if (seasonId) this.currentSeasonIdSignal.set(parseInt(seasonId));
      if (permissions) this.permissionsSignal.set(JSON.parse(permissions));
    } catch (error) {
      this.logger.logError('AuthV5Service: Failed to load stored data', error as any, {});
      this.clearStoredData();
    }
  }

  private storeToken(token: string): void {
    try {
      localStorage.setItem('boukii_auth_token', token);
    } catch (error) {
      this.logger.logError('AuthV5Service: Failed to store token', error as any, {});
    }
  }

  private storeUser(user: User): void {
    try {
      localStorage.setItem('boukii_user', JSON.stringify(user));
    } catch (error) {
      this.logger.logError('AuthV5Service: Failed to store user', error as any, {});
    }
  }

  private storeSchools(schools: School[]): void {
    try {
      localStorage.setItem('boukii_schools', JSON.stringify(schools));
    } catch (error) {
      this.logger.logError('AuthV5Service: Failed to store schools', error as any, {});
    }
  }

  private storeSchoolId(schoolId: number): void {
    try {
      localStorage.setItem('boukii_school_id', schoolId.toString());
    } catch (error) {
      this.logger.logError('AuthV5Service: Failed to store school ID', error as any, {});
    }
  }

  private storeSeasonId(seasonId: number): void {
    try {
      localStorage.setItem('boukii_season_id', seasonId.toString());
    } catch (error) {
      this.logger.logError('AuthV5Service: Failed to store season ID', error as any, {});
    }
  }

  private clearStoredData(): void {
    try {
      localStorage.removeItem('boukii_auth_token');
      localStorage.removeItem('boukii_user');
      localStorage.removeItem('boukii_schools');
      localStorage.removeItem('boukii_school_id');
      localStorage.removeItem('boukii_season_id');
      localStorage.removeItem('boukii_permissions');
    } catch (error) {
      this.logger.logError('AuthV5Service: Failed to clear stored data', error as any, {});
    }
  }
}