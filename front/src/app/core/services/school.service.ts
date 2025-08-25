import { Injectable, inject } from '@angular/core';
import { Observable, from, map } from 'rxjs';
import { ApiService } from './api.service';
import { School } from './context.service';

export interface SchoolsResponse {
  data: School[];
  meta: {
    total: number;
    page: number;
    perPage: number;
    lastPage: number;
    from: number;
    to: number;
  };
}

export interface GetSchoolsParams {
  page?: number;
  perPage?: number;
  search?: string;
  active?: boolean;
  orderBy?: 'name' | 'createdAt' | 'updatedAt';
  orderDirection?: 'asc' | 'desc';
}

@Injectable({
  providedIn: 'root'
})
export class SchoolService {
  private readonly apiHttp = inject(ApiService);

  /**
   * Get schools for the current authenticated user
   */
  getMySchools(params: GetSchoolsParams = {}): Observable<SchoolsResponse> {
    // Set default parameters
    const defaultParams: Required<GetSchoolsParams> = {
      page: 1,
      perPage: 20,
      search: '',
      active: true,
      orderBy: 'name',
      orderDirection: 'asc'
    };

    // Merge with provided parameters
    const finalParams = { ...defaultParams, ...params };

    // Build query parameters object
    const queryParams: Record<string, string | number | boolean> = {};
    Object.entries(finalParams).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        queryParams[key] = value;
      }
    });

    return from(this.apiHttp.get<SchoolsResponse>('/me/schools', queryParams));
  }

  /**
   * Get a specific school by ID
   */
  getSchoolById(id: number): Observable<School> {
    return from(this.apiHttp.get<School>(`/schools/${id}`));
  }

  /**
   * Get all schools for the current user (without pagination)
   * Useful for simple lists and school selection
   */
  getAllMySchools(): Observable<School[]> {
    return from(
      this.apiHttp.get<SchoolsResponse>('/me/schools', { all: true })
    ).pipe(map(response => response.data));
  }

  /**
   * List all schools (super admin only)
   */
  listAll(params: GetSchoolsParams = {}): Observable<School[]> {
    // Set default parameters with high perPage
    const defaultParams: Required<GetSchoolsParams> = {
      page: 1,
      perPage: 1000,
      search: '',
      active: true,
      orderBy: 'name',
      orderDirection: 'asc'
    };

    const finalParams = { ...defaultParams, ...params };

    // Build query parameters
    const queryParams: Record<string, string | number | boolean> = {};
    Object.entries(finalParams).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        queryParams[key] = value;
      }
    });

    return from(
      this.apiHttp.get<SchoolsResponse>('/schools', queryParams)
    ).pipe(map(response => response.data));
  }

  /**
   * Search schools by name
   */
  searchSchools(query: string, limit: number = 10): Observable<School[]> {
    const queryParams = {
      search: query,
      perPage: limit,
      active: true
    };

    return from(this.apiHttp.get<School[]>('/schools/search', queryParams));
  }
}