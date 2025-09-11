import { Injectable, inject } from '@angular/core';
import { Observable, BehaviorSubject } from 'rxjs';
import { HttpClient } from '@angular/common/http';

import { 
  RentalItem, 
  RentalBooking, 
  RentalFilter, 
  RentalCreateRequest, 
  RentalUpdateRequest,
  InventoryItem 
} from '../models/rental.interface';

@Injectable({
  providedIn: 'root'
})
export class RentingService {
  private http = inject(HttpClient);
  private apiUrl = '/rentals';
  
  private rentalsSubject = new BehaviorSubject<RentalBooking[]>([]);
  private inventorySubject = new BehaviorSubject<InventoryItem[]>([]);
  
  public rentals$ = this.rentalsSubject.asObservable();
  public inventory$ = this.inventorySubject.asObservable();

  // Rental Bookings
  getRentals(filter?: RentalFilter): Observable<RentalBooking[]> {
    const params: any = {};
    
    if (filter) {
      if (filter.status) params.status = filter.status;
      if (filter.client_id) params.client_id = filter.client_id;
      if (filter.date_from) params.date_from = filter.date_from;
      if (filter.date_to) params.date_to = filter.date_to;
      if (filter.item_category) params.item_category = filter.item_category;
    }

    return this.http.get<RentalBooking[]>(this.apiUrl, { params });
  }

  getRental(id: number): Observable<RentalBooking> {
    return this.http.get<RentalBooking>(`${this.apiUrl}/${id}`);
  }

  createRental(rentalData: RentalCreateRequest): Observable<RentalBooking> {
    return this.http.post<RentalBooking>(this.apiUrl, rentalData);
  }

  updateRental(id: number, rentalData: RentalUpdateRequest): Observable<RentalBooking> {
    return this.http.put<RentalBooking>(`${this.apiUrl}/${id}`, rentalData);
  }

  cancelRental(id: number, reason?: string): Observable<RentalBooking> {
    return this.http.patch<RentalBooking>(`${this.apiUrl}/${id}/cancel`, { reason });
  }

  returnRental(id: number, condition?: string, notes?: string): Observable<RentalBooking> {
    return this.http.patch<RentalBooking>(`${this.apiUrl}/${id}/return`, { 
      condition, 
      notes,
      returned_at: new Date().toISOString() 
    });
  }

  extendRental(id: number, new_end_date: string): Observable<RentalBooking> {
    return this.http.patch<RentalBooking>(`${this.apiUrl}/${id}/extend`, { new_end_date });
  }

  // Inventory Management
  getInventory(): Observable<InventoryItem[]> {
    return this.http.get<InventoryItem[]>(`${this.apiUrl}/inventory`);
  }

  getInventoryItem(id: number): Observable<InventoryItem> {
    return this.http.get<InventoryItem>(`${this.apiUrl}/inventory/${id}`);
  }

  updateInventoryItem(id: number, data: Partial<InventoryItem>): Observable<InventoryItem> {
    return this.http.put<InventoryItem>(`${this.apiUrl}/inventory/${id}`, data);
  }

  checkAvailability(
    item_id: number, 
    start_date: string, 
    end_date: string
  ): Observable<{ available: boolean; quantity_available: number; conflicts?: any[] }> {
    return this.http.post<any>(`${this.apiUrl}/inventory/${item_id}/availability`, {
      start_date,
      end_date
    });
  }

  // Analytics
  getRentalStats(period: 'daily' | 'weekly' | 'monthly' = 'monthly'): Observable<{
    total_rentals: number;
    active_rentals: number;
    overdue_rentals: number;
    total_revenue: number;
    popular_items: Array<{ item_name: string; rental_count: number; }>;
    category_breakdown: Array<{ category: string; count: number; revenue: number; }>;
  }> {
    return this.http.get<any>(`${this.apiUrl}/stats`, { params: { period } });
  }

  // Local state management
  refreshRentals(filter?: RentalFilter): void {
    this.getRentals(filter).subscribe(rentals => {
      this.rentalsSubject.next(rentals);
    });
  }

  refreshInventory(): void {
    this.getInventory().subscribe(inventory => {
      this.inventorySubject.next(inventory);
    });
  }

  updateRentalInCache(updatedRental: RentalBooking): void {
    const current = this.rentalsSubject.value;
    const index = current.findIndex(rental => rental.id === updatedRental.id);
    
    if (index > -1) {
      const updated = [...current];
      updated[index] = updatedRental;
      this.rentalsSubject.next(updated);
    } else {
      this.rentalsSubject.next([...current, updatedRental]);
    }
  }

  removeRentalFromCache(id: number): void {
    const current = this.rentalsSubject.value;
    const filtered = current.filter(rental => rental.id !== id);
    this.rentalsSubject.next(filtered);
  }
}