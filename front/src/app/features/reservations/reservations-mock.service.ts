import { Injectable } from '@angular/core';

export type ReservationStatus = 'active' | 'completed' | 'cancelled';
export type ReservationType = 'individual' | 'multiple';

export interface Reservation {
  id: number;
  client: string;
  course: string;
  date: string; // ISO string
  status: ReservationStatus;
  paid: boolean;
  type: ReservationType;
}

@Injectable({ providedIn: 'root' })
export class ReservationsMockService {
  private readonly reservations: Reservation[] = [
    { id: 1, client: 'Alice Johnson', course: 'Yoga Basics', date: '2025-08-20', status: 'active', paid: true, type: 'individual' },
    { id: 2, client: 'Bob Smith', course: 'Pilates', date: '2025-08-18', status: 'completed', paid: true, type: 'multiple' },
    { id: 3, client: 'Carlos Ruiz', course: 'Crossfit', date: '2025-08-19', status: 'cancelled', paid: false, type: 'individual' },
    { id: 4, client: 'Diana Lee', course: 'Swimming', date: '2025-08-21', status: 'active', paid: false, type: 'multiple' },
    { id: 5, client: 'Ethan Brown', course: 'Tennis', date: '2025-08-22', status: 'completed', paid: true, type: 'individual' },
    { id: 6, client: 'Fiona Green', course: 'Boxing', date: '2025-08-18', status: 'active', paid: true, type: 'individual' },
    { id: 7, client: 'George Hall', course: 'Karate', date: '2025-08-17', status: 'cancelled', paid: false, type: 'multiple' },
    { id: 8, client: 'Hannah Ivers', course: 'Zumba', date: '2025-08-25', status: 'active', paid: false, type: 'individual' },
    { id: 9, client: 'Ian Jacobs', course: 'Yoga Basics', date: '2025-08-26', status: 'completed', paid: true, type: 'multiple' },
    { id: 10, client: 'Julia King', course: 'Pilates', date: '2025-08-27', status: 'active', paid: false, type: 'individual' }
  ];

  getReservations(): Reservation[] {
    return this.reservations;
  }
}

