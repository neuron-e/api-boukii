
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { ApiResponse } from 'src/app/interface/api-response';

@Injectable({
  providedIn: 'root'
})
export class ApiCrudService extends ApiService {

  public zonesIds: any = [];
  public reservationId: any;
  public user: any;
  public searchTerm: any = '';
  public currentPage: any = 1;

  constructor(http: HttpClient) {
    super(http);
    const parseJson = localStorage.getItem('boukiiUser');
    const user = parseJson !== null ? JSON.parse(localStorage.getItem('boukiiUser') || '') : null;
    this.user = user;
  }

  getFile(url: string) {
    return this.http.get(this.baseUrl + url, {
      headers: this.getHeaders(),
      responseType: 'blob', // Esto indica que la respuesta es un archivo
    });
  }

  get(url: string, relations: any[] = [], filters: any = {}) {
    const params = new URLSearchParams();

    // Añadir relaciones con with[]
    relations.forEach(relation => {
      params.append('with[]', relation);
    });

    // Añadir filtros (solo si no son null o undefined)
    Object.entries(filters).forEach(([key, value]:any) => {
      if (value !== null && value !== undefined) {
        if (Array.isArray(value)) {
          value.forEach(val => {
            params.append(key+'[]', val); // ✅ sin []
          });
        } else {
          params.append(key, value);
        }
      }
    });

    const queryString = params.toString();
    const fullUrl = queryString ? `${this.baseUrl}${url}?${queryString}` : `${this.baseUrl}${url}`;

    return this.http.get<ApiResponse>(fullUrl, { headers: this.getHeaders() });
  }


  getAll(url: string) {
    return this.http.get<ApiResponse>(this.baseUrl + url + '/all',
      { headers: this.getHeaders() });
  }

  list(model: string, numPage: number = 1, perPage: number = 10, order: string = 'desc', orderColumn: string = 'id',
    search: string = '', exclude: string = '', user: any = null, filter: string = '', relations: any = []): Observable<ApiResponse> {


    let relationsParams = '';
    if (relations.length > 0) {
      relationsParams = relations.map(relation => `&with[]=${relation}`).join('');
    }

    // Construir la URL completa
    const url = this.baseUrl + model + '?perPage=' + perPage + '&page=' + numPage + '&order=' + order
      + '&orderColumn=' + orderColumn + '&search=' + search + '&exclude=' + exclude + relationsParams;

    return this.http.get<ApiResponse>(url,
      { headers: this.getHeaders() });
  }


  login(model: string, data: any): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(this.baseUrl + model, data, { headers: this.getHeadersLogin() });
  }

  recoverPassword(model: string, data: any): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(this.baseUrl + model, data, { headers: this.getHeadersLogin() });
  }

  create(model: string, data: any): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(this.baseUrl + model, data, { headers: this.getHeaders() });
  }

  post(model: string, data: any): Observable<ApiResponse> {
    return this.http.post<ApiResponse>(this.baseUrl + model, data, { headers: this.getHeaders() });
  }

  update(model: string, data: any, id: any): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(this.baseUrl + model + '/' + id, data, { headers: this.getHeaders() });
  }

  restore(entity: string, id: number): Observable<any> {
    return this.http.post(`${this.baseUrl}${entity}/${id}/restore`, {});
  }

  massiveUpdate(model: string, data: any): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(this.baseUrl + model + '/multiple', data, { headers: this.getHeaders() });
  }

  delete(model: string, id: number): Observable<ApiResponse> {
    return this.http.delete<ApiResponse>(this.baseUrl + model + '/' + id, { headers: this.getHeaders() });
  }

  getById(model: string, id: any): Observable<ApiResponse> {
    return this.http.get<ApiResponse>(this.baseUrl + model + '/' + id, { headers: this.getHeaders() });
  }

  translateText(text: string, targetLanguage: string): Observable<any> {
    const params = {
      'text': text,
      'target_lang': targetLanguage
    }
    return this.http.post(this.baseUrl + '/translate', params, { headers: this.getHeaders() });
  }

}
