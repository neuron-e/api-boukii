import { Injectable, inject } from '@angular/core';
import { Observable, from, throwError } from 'rxjs';
import { catchError, map } from 'rxjs/operators';
import { ApiService } from './api.service';

export interface Client {
  id: number;
  [key: string]: any;
}

export interface ClientsResponse {
  data: Client[];
  meta: {
    pagination: {
      page: number;
      limit: number;
      total: number;
      totalPages: number;
    };
  };
}

export interface GetClientsParams {
  school_id: number;
  search?: string;
  sport_id?: number;
  active?: boolean;
  page?: number;
}

// Client detail interfaces
export interface ClientDetail {
  id: number;
  email?: string;
  first_name: string;
  last_name: string;
  birth_date?: string;
  phone?: string;
  telephone?: string;
  address?: string;
  cp?: string;
  city?: string;
  province?: string;
  country?: string;
  image?: string;
  created_at: string;
  updated_at: string;
  utilizadores?: ClientUtilizador[];
  client_sports?: ClientSport[];
  observations?: ClientObservation[];
  booking_history?: BookingHistoryItem[];
}

// Request for creating a new client
export interface CreateClientRequest {
  // Identity
  first_name: string;
  last_name: string;
  date_of_birth?: string; // ISO date
  gender?: 'male' | 'female' | 'other' | 'prefer_not_to_say';
  nationality?: string;
  preferred_language?: 'es' | 'en' | 'fr' | 'de' | 'it';

  // Contact
  email?: string;
  phone?: string;
  telephone?: string;
  status?: 'active' | 'inactive' | 'blocked' | 'pending';

  // Address
  address?: {
    street?: string;
    city?: string;
    postal_code?: string;
    country?: string;
  };

  // Emergency contact
  emergency_contact?: {
    name?: string;
    phone?: string;
    relationship?: string;
  };

  // Preferences
  preferences?: {
    level?: 'Principiante' | 'Intermedio' | 'Avanzado' | 'Experto';
    instructor_gender?: 'male' | 'female' | 'no_preference';
    group_size?: 'private' | 'small_group' | 'large_group' | 'no_preference';
    communication_method?: 'email' | 'sms' | 'phone' | 'whatsapp';
  };

  // Misc
  notes?: string;
  tags?: string[];
  avatar?: string; // URL returned from upload
}

export interface ClientUtilizador {
  id: number;
  client_id: number;
  first_name: string;
  last_name: string;
  birth_date?: string;
  image?: string;
  created_at: string;
  updated_at: string;
}

export interface ClientSport {
  id: number;
  client_id: number;
  person_type: 'client' | 'utilizador';
  person_id: number;
  sport_id: number;
  degree_id?: number;
  sport?: {
    id: number;
    name: string;
  };
  degree?: {
    id: number;
    name: string;
    level?: number;
    color?: string;
  };
  created_at: string;
  updated_at: string;
}

export interface ClientObservation {
  id: number;
  client_id: number;
  title: string;
  content: string;
  created_at: string;
  updated_at: string;
}

export interface BookingHistoryItem {
  id: number;
  client_id: number;
  type: 'booking' | 'course';
  status: 'completed' | 'active' | 'confirmed' | 'cancelled' | 'pending';
  title: string;
  description?: string;
  service?: string;
  instructor?: string;
  date: string;
  time?: string;
  courseName?: string;
  amount?: number;
  duration_hours?: number;
}

// Request/Response interfaces for CRUD operations
export interface UpdateClientRequest {
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string;
  telephone?: string;
  address?: string;
  cp?: string;
  city?: string;
  province?: string;
  country?: string;
  birth_date?: string;
}

export interface CreateUtilizadorRequest {
  first_name: string;
  last_name: string;
  birth_date?: string;
}

export interface UpdateUtilizadorRequest {
  first_name?: string;
  last_name?: string;
  birth_date?: string;
}

export interface CreateClientSportRequest {
  person_type: 'client' | 'utilizador';
  person_id: number;
  sport_id: number;
  degree_id?: number;
}

export interface UpdateClientSportRequest {
  person_type?: 'client' | 'utilizador';
  person_id?: number;
  sport_id?: number;
  degree_id?: number;
}

export interface CreateObservationRequest {
  title: string;
  content: string;
}

export interface UpdateObservationRequest {
  title?: string;
  content?: string;
}

export interface SchoolSport {
  id: number;
  name: string;
  description?: string;
}

export interface SportDegree {
  id: number;
  name: string;
  level: number;
  color?: string;
}

@Injectable({
  providedIn: 'root'
})
export class ClientsV5Service {
  private readonly apiHttp = inject(ApiService);

  /**
   * Get clients list
   * Falls back to v4 endpoint if v5 is unavailable
   */
  getClients(params: GetClientsParams): Observable<ClientsResponse> {
    const queryParams: Record<string, string | number | boolean> = {};
    Object.entries(params).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        queryParams[key] = value;
      }
    });

    return from(this.apiHttp.get<ClientsResponse>('/clients', queryParams)).pipe(
      catchError(err => {
        if (err?.status === 404) {
          return from(this.apiHttp.get<any>('/api/v4/clients', queryParams)).pipe(
            map((v4Res: any) => {
              const p = v4Res?.meta?.pagination || v4Res?.meta || {};
              const pagination = {
                page: p.page ?? p.current_page ?? 1,
                limit: p.limit ?? p.per_page ?? 0,
                total: p.total ?? 0,
                totalPages: p.totalPages ?? p.total_pages ?? p.last_page ?? 0,
              };
              return { data: v4Res.data, meta: { pagination } } as ClientsResponse;
            })
          );
        }
        return throwError(() => err);
      })
    );
  }

  // =============================================================================
  // CLIENT DETAIL OPERATIONS
  // =============================================================================

  /**
   * Get client by ID with all related data
   * GET /api/v5/clients/{id}
   */
  getClient(clientId: number): Observable<ClientDetail> {
    return from(this.apiHttp.get<{ data: ClientDetail }>(`/clients/${clientId}`)).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error fetching client:', err);
        return throwError(() => err);
      })
    );
  }

  /**
   * Create client
   * POST /api/v5/clients
   */
  createClient(data: CreateClientRequest): Observable<ClientDetail> {
    return from(this.apiHttp.post<{ data: ClientDetail }>(`/clients`, data)).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error creating client:', err);
        return throwError(() => err);
      })
    );
  }

  /**
   * Update client basic information
   * PATCH /api/v5/clients/{id}
   */
  updateClient(clientId: number, data: UpdateClientRequest): Observable<ClientDetail> {
    return from(this.apiHttp.patch<{ data: ClientDetail }>(`/clients/${clientId}`, data)).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error updating client:', err);
        return throwError(() => err);
      })
    );
  }

  // =============================================================================
  // UTILIZADORES OPERATIONS
  // =============================================================================

  /**
   * Create a new utilizador for a client
   * POST /api/v5/clients/{id}/utilizadores
   */
  createUtilizador(clientId: number, data: CreateUtilizadorRequest): Observable<ClientUtilizador> {
    return from(this.apiHttp.post<{ data: ClientUtilizador }>(`/clients/${clientId}/utilizadores`, data)).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error creating utilizador:', err);
        return throwError(() => err);
      })
    );
  }

  /**
   * Update an existing utilizador
   * PATCH /api/v5/clients/{clientId}/utilizadores/{utilizadorId}
   */
  updateUtilizador(clientId: number, utilizadorId: number, data: UpdateUtilizadorRequest): Observable<ClientUtilizador> {
    return from(this.apiHttp.patch<{ data: ClientUtilizador }>(`/clients/${clientId}/utilizadores/${utilizadorId}`, data)).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error updating utilizador:', err);
        return throwError(() => err);
      })
    );
  }

  /**
   * Delete a utilizador
   * DELETE /api/v5/clients/{clientId}/utilizadores/{utilizadorId}
   */
  deleteUtilizador(clientId: number, utilizadorId: number): Observable<void> {
    return from(this.apiHttp.delete(`/clients/${clientId}/utilizadores/${utilizadorId}`)).pipe(
      map(() => void 0),
      catchError(err => {
        console.error('Error deleting utilizador:', err);
        return throwError(() => err);
      })
    );
  }

  // =============================================================================
  // SPORTS OPERATIONS
  // =============================================================================

  /**
   * Get available sports for current school
   * GET /api/v5/school-sports
   */
  getSchoolSports(): Observable<SchoolSport[]> {
    return from(this.apiHttp.get<{ data: SchoolSport[] }>('/school-sports')).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error fetching school sports:', err);
        return throwError(() => err);
      })
    );
  }

  /**
   * Get degrees/levels for a specific sport
   * GET /api/v5/degrees?sport_id={sportId}
   */
  getSportDegrees(sportId: number): Observable<SportDegree[]> {
    return from(this.apiHttp.get<{ data: SportDegree[] }>('/degrees', { sport_id: sportId })).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error fetching sport degrees:', err);
        return throwError(() => err);
      })
    );
  }

  /**
   * Add sport to client/utilizador
   * POST /api/v5/clients/{id}/sports
   */
  createClientSport(clientId: number, data: CreateClientSportRequest): Observable<ClientSport> {
    return from(this.apiHttp.post<{ data: ClientSport }>(`/clients/${clientId}/sports`, data)).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error creating client sport:', err);
        return throwError(() => err);
      })
    );
  }

  /**
   * Update client sport (change level/degree)
   * PATCH /api/v5/clients/{clientId}/sports/{sportId}
   */
  updateClientSport(clientId: number, sportId: number, data: UpdateClientSportRequest): Observable<ClientSport> {
    return from(this.apiHttp.patch<{ data: ClientSport }>(`/clients/${clientId}/sports/${sportId}`, data)).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error updating client sport:', err);
        return throwError(() => err);
      })
    );
  }

  /**
   * Remove sport from client/utilizador
   * DELETE /api/v5/clients/{clientId}/sports/{sportId}
   */
  deleteClientSport(clientId: number, sportId: number): Observable<void> {
    return from(this.apiHttp.delete(`/clients/${clientId}/sports/${sportId}`)).pipe(
      map(() => void 0),
      catchError(err => {
        console.error('Error deleting client sport:', err);
        return throwError(() => err);
      })
    );
  }

  // =============================================================================
  // OBSERVATIONS OPERATIONS
  // =============================================================================

  /**
   * Create a new observation for a client
   * POST /api/v5/clients/{id}/observations
   */
  createObservation(clientId: number, data: CreateObservationRequest): Observable<ClientObservation> {
    return from(this.apiHttp.post<{ data: ClientObservation }>(`/clients/${clientId}/observations`, data)).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error creating observation:', err);
        return throwError(() => err);
      })
    );
  }

  /**
   * Update an existing observation
   * PATCH /api/v5/clients/{clientId}/observations/{observationId}
   */
  updateObservation(clientId: number, observationId: number, data: UpdateObservationRequest): Observable<ClientObservation> {
    return from(this.apiHttp.patch<{ data: ClientObservation }>(`/clients/${clientId}/observations/${observationId}`, data)).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error updating observation:', err);
        return throwError(() => err);
      })
    );
  }

  /**
   * Delete an observation
   * DELETE /api/v5/clients/{clientId}/observations/{observationId}
   */
  deleteObservation(clientId: number, observationId: number): Observable<void> {
    return from(this.apiHttp.delete(`/clients/${clientId}/observations/${observationId}`)).pipe(
      map(() => void 0),
      catchError(err => {
        console.error('Error deleting observation:', err);
        return throwError(() => err);
      })
    );
  }

  // =============================================================================
  // BOOKING HISTORY OPERATIONS
  // =============================================================================

  /**
   * Get booking history for a client
   * GET /api/v5/clients/{id}/history
   */
  getClientHistory(clientId: number): Observable<BookingHistoryItem[]> {
    return from(this.apiHttp.get<{ data: BookingHistoryItem[] }>(`/clients/${clientId}/history`)).pipe(
      map(response => response.data),
      catchError(err => {
        console.error('Error fetching client history:', err);
        return throwError(() => err);
      })
    );
  }
}
