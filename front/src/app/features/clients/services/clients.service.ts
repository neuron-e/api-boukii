import { Injectable, inject } from '@angular/core';
import { Observable, BehaviorSubject } from 'rxjs';
import { HttpClient } from '@angular/common/http';

export interface Client {
  id: number;
  name: string;
  email: string;
  phone?: string;
  created_at?: Date;
  updated_at?: Date;
}

export interface ClientFilter {
  search?: string;
  status?: string;
}

@Injectable({
  providedIn: 'root'
})
export class ClientsService {
  private http = inject(HttpClient);
  private apiUrl = '/clients';
  
  private clientsSubject = new BehaviorSubject<Client[]>([]);
  public clients$ = this.clientsSubject.asObservable();

  getClients(filter?: ClientFilter): Observable<Client[]> {
    const params: any = {};
    
    if (filter) {
      if (filter.search) params.search = filter.search;
      if (filter.status) params.status = filter.status;
    }

    return this.http.get<Client[]>(this.apiUrl, { params });
  }

  getClient(id: number): Observable<Client> {
    return this.http.get<Client>(`${this.apiUrl}/${id}`);
  }

  createClient(clientData: Partial<Client>): Observable<Client> {
    return this.http.post<Client>(this.apiUrl, clientData);
  }

  updateClient(id: number, clientData: Partial<Client>): Observable<Client> {
    return this.http.put<Client>(`${this.apiUrl}/${id}`, clientData);
  }

  deleteClient(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }
}