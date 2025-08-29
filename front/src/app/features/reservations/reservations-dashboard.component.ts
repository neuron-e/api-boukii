import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { ReservationsMockService, Reservation } from './reservations-mock.service';
import { ReservationsListComponent } from './reservations-list/reservations-list.component';
import { ReservationsCalendarComponent } from './reservations-calendar/reservations-calendar.component';

@Component({
  selector: 'app-reservations-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    ReactiveFormsModule,
    ReservationsListComponent,
    ReservationsCalendarComponent
  ],
  templateUrl: './reservations-dashboard.component.html',
  styleUrl: './reservations-dashboard.component.scss'
})
export class ReservationsDashboardComponent implements OnInit {
  view: 'list' | 'calendar' = 'list';
  reservations: Reservation[];
  stats: { today: number; pending: number; cancelled: number; income: number };
  
  // Form Controls
  searchControl = new FormControl('');
  dateFromControl = new FormControl('');
  dateToControl = new FormControl('');
  statusControl = new FormControl('');
  typeControl = new FormControl('');

  constructor(private service: ReservationsMockService) {
    this.reservations = this.service.getReservations();
    this.stats = this.service.getStats();
  }
  
  ngOnInit(): void {
    // Subscribe to filter changes
    this.searchControl.valueChanges.subscribe(() => this.applyFilters());
    this.dateFromControl.valueChanges.subscribe(() => this.applyFilters());
    this.dateToControl.valueChanges.subscribe(() => this.applyFilters());
    this.statusControl.valueChanges.subscribe(() => this.applyFilters());
    this.typeControl.valueChanges.subscribe(() => this.applyFilters());
  }

  filteredReservations: Reservation[] = [];
  
  private applyFilters(): void {
    const searchTerm = this.searchControl.value?.toLowerCase() || '';
    const dateFrom = this.dateFromControl.value;
    const dateTo = this.dateToControl.value;
    const status = this.statusControl.value;
    const type = this.typeControl.value;
    
    this.filteredReservations = this.reservations.filter(r => {
      // Date filtering
      const reservationDate = new Date(r.date);
      const matchesDateFrom = !dateFrom || reservationDate >= new Date(dateFrom);
      const matchesDateTo = !dateTo || reservationDate <= new Date(dateTo);
      
      // Search filtering
      const matchesSearch = !searchTerm || 
        r.client.toLowerCase().includes(searchTerm) ||
        r.monitor.toLowerCase().includes(searchTerm) ||
        r.course.toLowerCase().includes(searchTerm);
      
      // Status and type filtering
      const matchesStatus = !status || r.status === status;
      const matchesType = !type || r.type === type;
      
      return matchesDateFrom && matchesDateTo && matchesSearch && matchesStatus && matchesType;
    });
  }

  toggleView(): void {
    this.view = this.view === 'list' ? 'calendar' : 'list';
  }
  
  setView(view: 'list' | 'calendar'): void {
    this.view = view;
  }
  
  createReservation(): void {
    // Navigate to create reservation form
    console.log('Navigate to create reservation');
  }
  
  exportReservations(): void {
    const csvContent = this.generateCSVContent();
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `reservas-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  }
  
  private generateCSVContent(): string {
    const headers = ['ID', 'Cliente', 'Tipo', 'Curso', 'Fecha', 'Estado', 'Monitor', 'Precio'];
    const rows = this.filteredReservations.map(r => [
      r.id.toString(),
      r.client,
      r.type === 'course' ? 'Curso' : 'Equipamiento',
      r.course,
      new Date(r.date).toLocaleDateString('es-ES'),
      this.getStatusLabel(r.status),
      r.monitor,
      r.price
    ]);
    
    const csvRows = [headers, ...rows].map(row => 
      row.map(field => `"${field}"`).join(',')
    );
    
    return csvRows.join('\n');
  }
  
  private getStatusLabel(status: string): string {
    const statusLabels = {
      'confirmed': 'Confirmada',
      'pending': 'Pendiente',
      'cancelled': 'Cancelada',
      'completed': 'Completada'
    };
    return statusLabels[status as keyof typeof statusLabels] || status;
  }
}

