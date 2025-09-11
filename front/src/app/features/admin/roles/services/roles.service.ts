import { inject, Injectable } from '@angular/core';
import { Observable, from } from 'rxjs';
import { ApiService } from '../../../../core/services/api.service';
import { Role, RolesListResponse } from '../types/role.types';

@Injectable({
  providedIn: 'root'
})
export class RolesService {
  private readonly api = inject(ApiService);

  /**
   * Get all available roles
   */
  getRoles(): Observable<RolesListResponse> {
    return from(this.api.get<RolesListResponse>('/roles'));
  }

  /**
   * Get role by ID
   */
  getRoleById(id: number): Observable<Role> {
    return from(this.api.get<Role>(`/roles/${id}`));
  }

  /**
   * Create new role (optional for v1)
   */
  createRole(role: Omit<Role, 'id'>): Observable<Role> {
    return from(this.api.post<Role>('/roles', role));
  }

  /**
   * Update existing role (optional for v1)
   */
  updateRole(id: number, role: Partial<Omit<Role, 'id'>>): Observable<Role> {
    return from(this.api.put<Role>(`/roles/${id}`, role));
  }

  /**
   * Delete role (optional for v1)
   */
  deleteRole(id: number): Observable<void> {
    return from(this.api.delete<void>(`/roles/${id}`));
  }
}