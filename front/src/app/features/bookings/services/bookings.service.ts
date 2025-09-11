import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, BehaviorSubject } from 'rxjs';
import { map, tap } from 'rxjs/operators';

import { Booking, CreateBookingRequest, UpdateBookingRequest, BookingFilters, BookingStats } from '../models/booking.interface';
import { ApiResponse, PaginatedResponse } from '../../../core/models/api.interface';
import { environment } from '../../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class BookingsService {
  private http = inject(HttpClient);
  private baseUrl = '/bookings';

  // State management
  private bookingsSubject = new BehaviorSubject<Booking[]>([]);
  private loadingSubject = new BehaviorSubject<boolean>(false);
  private statsSubject = new BehaviorSubject<BookingStats | null>(null);

  public bookings$ = this.bookingsSubject.asObservable();
  public loading$ = this.loadingSubject.asObservable();
  public stats$ = this.statsSubject.asObservable();

  /**
   * Get paginated list of bookings with filters
   */
  getBookings(filters?: BookingFilters, page = 1, limit = 25): Observable<PaginatedResponse<Booking>> {
    this.loadingSubject.next(true);
    
    let params = new HttpParams()
      .set('page', page.toString())
      .set('limit', limit.toString());

    if (filters) {
      if (filters.search) params = params.set('search', filters.search);
      if (filters.status?.length) params = params.set('status', filters.status.join(','));
      if (filters.dateFrom) params = params.set('date_from', filters.dateFrom);
      if (filters.dateTo) params = params.set('date_to', filters.dateTo);
      if (filters.clientId) params = params.set('client_id', filters.clientId.toString());
      if (filters.courseId) params = params.set('course_id', filters.courseId.toString());
      if (filters.instructorId) params = params.set('instructor_id', filters.instructorId.toString());
    }

    return this.http.get<PaginatedResponse<Booking>>(this.baseUrl, { params }).pipe(
      tap(response => {
        this.bookingsSubject.next(response.data);
        this.loadingSubject.next(false);
      })
    );
  }

  /**
   * Get booking by ID
   */
  getBooking(id: number): Observable<Booking> {
    return this.http.get<ApiResponse<Booking>>(`${this.baseUrl}/${id}`).pipe(
      map(response => response.data)
    );
  }

  /**
   * Create new booking
   */
  createBooking(bookingData: CreateBookingRequest): Observable<Booking> {
    this.loadingSubject.next(true);
    
    return this.http.post<ApiResponse<Booking>>(this.baseUrl, bookingData).pipe(
      map(response => response.data),
      tap(booking => {
        const currentBookings = this.bookingsSubject.value;
        this.bookingsSubject.next([booking, ...currentBookings]);
        this.loadingSubject.next(false);
      })
    );
  }

  /**
   * Update existing booking
   */
  updateBooking(id: number, bookingData: UpdateBookingRequest): Observable<Booking> {
    this.loadingSubject.next(true);
    
    return this.http.put<ApiResponse<Booking>>(`${this.baseUrl}/${id}`, bookingData).pipe(
      map(response => response.data),
      tap(updatedBooking => {
        const currentBookings = this.bookingsSubject.value;
        const index = currentBookings.findIndex(b => b.id === id);
        if (index !== -1) {
          currentBookings[index] = updatedBooking;
          this.bookingsSubject.next([...currentBookings]);
        }
        this.loadingSubject.next(false);
      })
    );
  }

  /**
   * Delete booking
   */
  deleteBooking(id: number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`).pipe(
      tap(() => {
        const currentBookings = this.bookingsSubject.value;
        this.bookingsSubject.next(currentBookings.filter(b => b.id !== id));
      })
    );
  }

  /**
   * Confirm booking
   */
  confirmBooking(id: number): Observable<Booking> {
    return this.http.post<ApiResponse<Booking>>(`${this.baseUrl}/${id}/confirm`, {}).pipe(
      map(response => response.data),
      tap(updatedBooking => this.updateBookingInState(updatedBooking))
    );
  }

  /**
   * Cancel booking
   */
  cancelBooking(id: number, reason?: string): Observable<Booking> {
    const body = reason ? { reason } : {};
    return this.http.post<ApiResponse<Booking>>(`${this.baseUrl}/${id}/cancel`, body).pipe(
      map(response => response.data),
      tap(updatedBooking => this.updateBookingInState(updatedBooking))
    );
  }

  /**
   * Mark booking as paid
   */
  markAsPaid(id: number, paymentData?: any): Observable<Booking> {
    return this.http.post<ApiResponse<Booking>>(`${this.baseUrl}/${id}/mark-paid`, paymentData || {}).pipe(
      map(response => response.data),
      tap(updatedBooking => this.updateBookingInState(updatedBooking))
    );
  }

  /**
   * Get booking statistics
   */
  getBookingStats(dateFrom?: string, dateTo?: string): Observable<BookingStats> {
    let params = new HttpParams();
    if (dateFrom) params = params.set('date_from', dateFrom);
    if (dateTo) params = params.set('date_to', dateTo);

    return this.http.get<ApiResponse<BookingStats>>(`${this.baseUrl}/stats`, { params }).pipe(
      map(response => response.data),
      tap(stats => this.statsSubject.next(stats))
    );
  }

  /**
   * Duplicate booking
   */
  duplicateBooking(id: number): Observable<Booking> {
    return this.http.post<ApiResponse<Booking>>(`${this.baseUrl}/${id}/duplicate`, {}).pipe(
      map(response => response.data)
    );
  }

  /**
   * Send booking confirmation email
   */
  sendConfirmation(id: number): Observable<void> {
    return this.http.post<void>(`${this.baseUrl}/${id}/send-confirmation`, {});
  }

  /**
   * Send booking reminder
   */
  sendReminder(id: number): Observable<void> {
    return this.http.post<void>(`${this.baseUrl}/${id}/send-reminder`, {});
  }

  /**
   * Export bookings to PDF/Excel
   */
  exportBookings(filters?: BookingFilters, format: 'pdf' | 'excel' = 'pdf'): Observable<Blob> {
    let params = new HttpParams().set('format', format);
    
    if (filters) {
      if (filters.search) params = params.set('search', filters.search);
      if (filters.status?.length) params = params.set('status', filters.status.join(','));
      if (filters.dateFrom) params = params.set('date_from', filters.dateFrom);
      if (filters.dateTo) params = params.set('date_to', filters.dateTo);
    }

    return this.http.get(`${this.baseUrl}/export`, { 
      params, 
      responseType: 'blob' 
    });
  }

  /**
   * Export single booking
   */
  exportBooking(id: number, format: 'pdf' | 'excel' = 'pdf'): Observable<Blob> {
    return this.http.get(`${this.baseUrl}/${id}/export`, { 
      params: { format }, 
      responseType: 'blob' 
    });
  }

  /**
   * Get available time slots for a course
   */
  getAvailableSlots(courseId: number, date: string): Observable<any[]> {
    const params = new HttpParams().set('date', date);
    return this.http.get<ApiResponse<any[]>>(`${this.baseUrl}/available-slots/${courseId}`, { params }).pipe(
      map(response => response.data)
    );
  }

  /**
   * Validate promo code
   */
  validatePromoCode(code: string, courseId: number): Observable<any> {
    return this.http.post<ApiResponse<any>>(`${this.baseUrl}/validate-promo`, {
      code,
      course_id: courseId
    }).pipe(
      map(response => response.data)
    );
  }

  /**
   * Get booking calendar events
   */
  getCalendarEvents(dateFrom: string, dateTo: string): Observable<any[]> {
    const params = new HttpParams()
      .set('date_from', dateFrom)
      .set('date_to', dateTo);
      
    return this.http.get<ApiResponse<any[]>>(`${this.baseUrl}/calendar`, { params }).pipe(
      map(response => response.data)
    );
  }

  /**
   * Private helper to update booking in state
   */
  private updateBookingInState(updatedBooking: Booking): void {
    const currentBookings = this.bookingsSubject.value;
    const index = currentBookings.findIndex(b => b.id === updatedBooking.id);
    if (index !== -1) {
      currentBookings[index] = updatedBooking;
      this.bookingsSubject.next([...currentBookings]);
    }
  }

  /**
   * Clear state
   */
  clearState(): void {
    this.bookingsSubject.next([]);
    this.statsSubject.next(null);
    this.loadingSubject.next(false);
  }
}