import { Injectable } from '@angular/core';

export type ReservationStatus = 'confirmed' | 'pending' | 'cancelled' | 'completed';
export type ReservationType = 'course' | 'equipment';

export interface Reservation {
  id: number;
  client: string;
  course: string;
  date: string; // ISO string
  status: ReservationStatus;
  type: ReservationType;
  price: number;
  monitor: string;
}

@Injectable({ providedIn: 'root' })
export class ReservationsMockService {
  private readonly reservations: Reservation[] = [
    { id: 1, client: 'Alice Johnson', course: 'Yoga Basics', date: '2025-08-20', status: 'confirmed', type: 'course', price: 30, monitor: 'Laura' },
    { id: 2, client: 'Bob Smith', course: 'Pilates', date: '2025-08-18', status: 'completed', type: 'course', price: 25, monitor: 'Miguel' },
    { id: 3, client: 'Carlos Ruiz', course: 'Kayak', date: '2025-08-19', status: 'cancelled', type: 'equipment', price: 15, monitor: 'Sara' },
    { id: 4, client: 'Diana Lee', course: 'Swimming', date: '2025-08-21', status: 'pending', type: 'course', price: 20, monitor: 'Laura' },
    { id: 5, client: 'Ethan Brown', course: 'Tennis', date: '2025-08-22', status: 'completed', type: 'course', price: 18, monitor: 'Carlos' },
    { id: 6, client: 'Fiona Green', course: 'Bike Rental', date: '2025-08-18', status: 'confirmed', type: 'equipment', price: 12, monitor: 'Miguel' },
    { id: 7, client: 'George Hall', course: 'Karate', date: '2025-08-17', status: 'cancelled', type: 'course', price: 22, monitor: 'Ana' },
    { id: 8, client: 'Hannah Ivers', course: 'Zumba', date: '2025-08-25', status: 'pending', type: 'course', price: 16, monitor: 'Sara' },
    { id: 9, client: 'Ian Jacobs', course: 'Yoga Basics', date: '2025-08-26', status: 'completed', type: 'course', price: 30, monitor: 'Laura' },
    { id: 10, client: 'Julia King', course: 'Surfboard', date: '2025-08-27', status: 'confirmed', type: 'equipment', price: 40, monitor: 'Carlos' }
  ];

  getReservations(): Reservation[] {
    return this.reservations;
  }

  getStats() {
    const today = new Date().toISOString().slice(0, 10);
    const todayReservations = this.reservations.filter(r => r.date === today);
    const pending = this.reservations.filter(r => r.status === 'pending');
    const cancelled = this.reservations.filter(r => r.status === 'cancelled');
    const income = todayReservations
      .filter(r => r.status !== 'cancelled')
      .reduce((sum, r) => sum + r.price, 0);
    return {
      today: todayReservations.length,
      pending: pending.length,
      cancelled: cancelled.length,
      income
    };
  }
}

