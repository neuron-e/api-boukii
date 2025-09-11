import { Injectable, inject } from '@angular/core';
import { Observable, BehaviorSubject } from 'rxjs';
import { HttpClient } from '@angular/common/http';

import { 
  ScheduleEvent, 
  EventCreateRequest, 
  EventUpdateRequest,
  EventFilter,
  ConflictResult,
  AvailabilityCheck
} from '../models/schedule.interface';

@Injectable({
  providedIn: 'root'
})
export class ScheduleService {
  private http = inject(HttpClient);
  private apiUrl = '/schedules';
  
  private eventsSubject = new BehaviorSubject<ScheduleEvent[]>([]);
  public events$ = this.eventsSubject.asObservable();

  getEvents(filter?: EventFilter): Observable<ScheduleEvent[]> {
    const params: any = {};
    
    if (filter) {
      if (filter.instructor_id) params.instructor_id = filter.instructor_id;
      if (filter.type) params.type = filter.type;
      if (filter.level) params.level = filter.level;
      if (filter.location) params.location = filter.location;
      if (filter.date_from) params.date_from = filter.date_from;
      if (filter.date_to) params.date_to = filter.date_to;
      if (filter.status) params.status = filter.status;
    }

    return this.http.get<ScheduleEvent[]>(this.apiUrl, { params });
  }

  getEvent(id: number): Observable<ScheduleEvent> {
    return this.http.get<ScheduleEvent>(`${this.apiUrl}/${id}`);
  }

  createEvent(eventData: EventCreateRequest): Observable<ScheduleEvent> {
    return this.http.post<ScheduleEvent>(this.apiUrl, eventData);
  }

  updateEvent(eventData: EventUpdateRequest): Observable<ScheduleEvent> {
    return this.http.put<ScheduleEvent>(`${this.apiUrl}/${eventData.id}`, eventData);
  }

  deleteEvent(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }

  checkAvailability(availability: AvailabilityCheck): Observable<ConflictResult> {
    return this.http.post<ConflictResult>(`${this.apiUrl}/check-availability`, availability);
  }

  getInstructorAvailability(instructorId: number, date: string): Observable<{
    instructor_id: number;
    date: string;
    available_slots: Array<{ start_time: string; end_time: string; }>;
    booked_slots: Array<{ start_time: string; end_time: string; event_id: number; }>;
  }> {
    return this.http.get<any>(`${this.apiUrl}/instructor/${instructorId}/availability`, {
      params: { date }
    });
  }

  getLocationAvailability(location: string, date: string): Observable<{
    location: string;
    date: string;
    available_slots: Array<{ start_time: string; end_time: string; }>;
    booked_slots: Array<{ start_time: string; end_time: string; event_id: number; }>;
  }> {
    return this.http.get<any>(`${this.apiUrl}/location/availability`, {
      params: { location, date }
    });
  }

  duplicateEvent(id: number, newDate?: string): Observable<ScheduleEvent> {
    const body: any = {};
    if (newDate) body.new_date = newDate;
    
    return this.http.post<ScheduleEvent>(`${this.apiUrl}/${id}/duplicate`, body);
  }

  cancelEvent(id: number, reason?: string): Observable<ScheduleEvent> {
    const body: any = {};
    if (reason) body.cancellation_reason = reason;
    
    return this.http.patch<ScheduleEvent>(`${this.apiUrl}/${id}/cancel`, body);
  }

  confirmEvent(id: number): Observable<ScheduleEvent> {
    return this.http.patch<ScheduleEvent>(`${this.apiUrl}/${id}/confirm`, {});
  }

  getRecurringEvents(parentId: number): Observable<ScheduleEvent[]> {
    return this.http.get<ScheduleEvent[]>(`${this.apiUrl}/${parentId}/recurring`);
  }

  updateRecurringSeries(
    parentId: number, 
    eventData: EventUpdateRequest,
    updateType: 'this' | 'future' | 'all' = 'all'
  ): Observable<{ updated_count: number; events: ScheduleEvent[] }> {
    return this.http.put<any>(`${this.apiUrl}/${parentId}/recurring`, {
      ...eventData,
      update_type: updateType
    });
  }

  deleteRecurringSeries(
    parentId: number,
    deleteType: 'this' | 'future' | 'all' = 'all'
  ): Observable<{ deleted_count: number }> {
    return this.http.delete<any>(`${this.apiUrl}/${parentId}/recurring`, {
      body: { delete_type: deleteType }
    });
  }

  // Local state management methods
  refreshEvents(filter?: EventFilter): void {
    this.getEvents(filter).subscribe(events => {
      this.eventsSubject.next(events);
    });
  }

  addEventToCache(event: ScheduleEvent): void {
    const currentEvents = this.eventsSubject.value;
    this.eventsSubject.next([...currentEvents, event]);
  }

  updateEventInCache(updatedEvent: ScheduleEvent): void {
    const currentEvents = this.eventsSubject.value;
    const index = currentEvents.findIndex(event => event.id === updatedEvent.id);
    
    if (index > -1) {
      const newEvents = [...currentEvents];
      newEvents[index] = updatedEvent;
      this.eventsSubject.next(newEvents);
    }
  }

  removeEventFromCache(eventId: number): void {
    const currentEvents = this.eventsSubject.value;
    const filteredEvents = currentEvents.filter(event => event.id !== eventId);
    this.eventsSubject.next(filteredEvents);
  }
}