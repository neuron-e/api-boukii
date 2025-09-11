import { Injectable, inject } from '@angular/core';
import { Observable, BehaviorSubject } from 'rxjs';
import { HttpClient } from '@angular/common/http';

import { Instructor, InstructorFilter, InstructorCreateRequest, InstructorUpdateRequest } from '../models/instructor.interface';

@Injectable({
  providedIn: 'root'
})
export class InstructorsService {
  private http = inject(HttpClient);
  private apiUrl = '/instructors';
  
  private instructorsSubject = new BehaviorSubject<Instructor[]>([]);
  public instructors$ = this.instructorsSubject.asObservable();

  getInstructors(filter?: InstructorFilter): Observable<Instructor[]> {
    const params: any = {};
    
    if (filter) {
      if (filter.search) params.search = filter.search;
      if (filter.status) params.status = filter.status;
      if (filter.specialties && filter.specialties.length > 0) {
        params.specialties = filter.specialties.join(',');
      }
      if (filter.availability) params.availability = filter.availability;
      if (filter.certification_level) params.certification_level = filter.certification_level;
    }

    return this.http.get<Instructor[]>(this.apiUrl, { params });
  }

  getInstructor(id: number): Observable<Instructor> {
    return this.http.get<Instructor>(`${this.apiUrl}/${id}`);
  }

  createInstructor(instructorData: InstructorCreateRequest): Observable<Instructor> {
    return this.http.post<Instructor>(this.apiUrl, instructorData);
  }

  updateInstructor(id: number, instructorData: InstructorUpdateRequest): Observable<Instructor> {
    return this.http.put<Instructor>(`${this.apiUrl}/${id}`, instructorData);
  }

  deleteInstructor(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }

  getInstructorAvailability(id: number, date?: string): Observable<{
    instructor_id: number;
    date: string;
    available_slots: Array<{ start_time: string; end_time: string; }>;
    booked_slots: Array<{ start_time: string; end_time: string; event_id: number; }>;
  }> {
    const params: any = {};
    if (date) params.date = date;
    
    return this.http.get<any>(`${this.apiUrl}/${id}/availability`, { params });
  }

  updateInstructorStatus(id: number, status: 'active' | 'inactive' | 'on_leave'): Observable<Instructor> {
    return this.http.patch<Instructor>(`${this.apiUrl}/${id}/status`, { status });
  }

  // Local state management
  refreshInstructors(filter?: InstructorFilter): void {
    this.getInstructors(filter).subscribe(instructors => {
      this.instructorsSubject.next(instructors);
    });
  }

  addInstructorToCache(instructor: Instructor): void {
    const current = this.instructorsSubject.value;
    this.instructorsSubject.next([...current, instructor]);
  }

  updateInstructorInCache(updatedInstructor: Instructor): void {
    const current = this.instructorsSubject.value;
    const index = current.findIndex(inst => inst.id === updatedInstructor.id);
    
    if (index > -1) {
      const updated = [...current];
      updated[index] = updatedInstructor;
      this.instructorsSubject.next(updated);
    }
  }

  removeInstructorFromCache(id: number): void {
    const current = this.instructorsSubject.value;
    const filtered = current.filter(inst => inst.id !== id);
    this.instructorsSubject.next(filtered);
  }
}