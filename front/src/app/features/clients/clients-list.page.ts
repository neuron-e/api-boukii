import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormControl } from '@angular/forms';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

export interface Client {
  id: number;
  name: string;
  email: string;
  phone: string;
  totalBookings: number;
  completedCourses?: number;
  isActive: boolean;
  registrationDate?: Date;
  lastActivity?: Date;
}

@Component({
  selector: 'app-clients-list-page',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormsModule],
  templateUrl: './clients-list.page.html',
  styleUrls: ['./clients-list.page.scss'],
})
export class ClientsListPageComponent implements OnInit {
  private readonly router = inject(Router);

  searchControl = new FormControl('');
  viewMode: 'cards' | 'table' = 'cards';
  sortBy: 'name' | 'bookings' | 'recent' = 'name';
  sortOrder: 'asc' | 'desc' = 'asc';

  clients: Client[] = [
    { id: 1, name: 'Alice Johnson', email: 'alice@example.com', phone: '+34 612 345 678', totalBookings: 3, completedCourses: 2, isActive: true, registrationDate: new Date('2024-01-15'), lastActivity: new Date('2025-01-20') },
    { id: 2, name: 'Bob Smith', email: 'bob.smith@example.com', phone: '+34 698 765 432', totalBookings: 5, completedCourses: 3, isActive: true, registrationDate: new Date('2024-02-10'), lastActivity: new Date('2025-01-18') },
    { id: 3, name: 'Charlie Brown', email: 'charlie.brown@example.com', phone: '+34 655 123 789', totalBookings: 2, completedCourses: 1, isActive: false, registrationDate: new Date('2024-03-05'), lastActivity: new Date('2024-12-15') },
    { id: 4, name: 'Diana Prince', email: 'diana.prince@example.com', phone: '+34 677 890 123', totalBookings: 7, completedCourses: 5, isActive: true, registrationDate: new Date('2023-11-20'), lastActivity: new Date('2025-01-22') },
    { id: 5, name: 'Eve Adams', email: 'eve.adams@example.com', phone: '+34 644 567 890', totalBookings: 1, completedCourses: 0, isActive: true, registrationDate: new Date('2025-01-10'), lastActivity: new Date('2025-01-10') },
    { id: 6, name: 'Frank Wright', email: 'frank.wright@example.com', phone: '+34 633 456 789', totalBookings: 4, completedCourses: 2, isActive: true, registrationDate: new Date('2024-06-12'), lastActivity: new Date('2025-01-19') },
    { id: 7, name: 'Grace Lee', email: 'grace.lee@example.com', phone: '+34 611 234 567', totalBookings: 6, completedCourses: 4, isActive: true, registrationDate: new Date('2024-04-08'), lastActivity: new Date('2025-01-21') },
    { id: 8, name: 'Henry Ford', email: 'henry.ford@example.com', phone: '+34 622 345 678', totalBookings: 8, completedCourses: 6, isActive: true, registrationDate: new Date('2023-09-15'), lastActivity: new Date('2025-01-23') },
    { id: 9, name: 'Ivy Nguyen', email: 'ivy.nguyen@example.com', phone: '+34 688 901 234', totalBookings: 2, completedCourses: 1, isActive: false, registrationDate: new Date('2024-07-25'), lastActivity: new Date('2024-11-30') },
    { id: 10, name: 'Jack Black', email: 'jack.black@example.com', phone: '+34 699 012 345', totalBookings: 5, completedCourses: 3, isActive: true, registrationDate: new Date('2024-05-18'), lastActivity: new Date('2025-01-17') },
  ];

  filteredClients = [...this.clients];

  ngOnInit(): void {
    this.searchControl.valueChanges.subscribe((term) => {
      this.filterAndSortClients(term);
    });
    this.filterAndSortClients();
  }

  private filterAndSortClients(searchTerm?: string | null): void {
    const value = searchTerm?.toLowerCase() ?? '';
    
    // Filter clients
    let filtered = this.clients.filter(
      (c) =>
        c.name.toLowerCase().includes(value) ||
        c.email.toLowerCase().includes(value) ||
        c.phone.toLowerCase().includes(value)
    );

    // Sort clients
    filtered.sort((a, b) => {
      let comparison = 0;
      
      switch (this.sortBy) {
        case 'name':
          comparison = a.name.localeCompare(b.name);
          break;
        case 'bookings':
          comparison = a.totalBookings - b.totalBookings;
          break;
        case 'recent':
          const aDate = a.lastActivity || a.registrationDate || new Date(0);
          const bDate = b.lastActivity || b.registrationDate || new Date(0);
          comparison = bDate.getTime() - aDate.getTime();
          break;
      }
      
      return this.sortOrder === 'desc' ? -comparison : comparison;
    });

    this.filteredClients = filtered;
  }

  onSortChange(): void {
    this.filterAndSortClients(this.searchControl.value);
  }

  sort(field: 'name' | 'bookings'): void {
    if (this.sortBy === field) {
      this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortBy = field;
      this.sortOrder = 'asc';
    }
    this.filterAndSortClients(this.searchControl.value);
  }

  getClientInitials(name: string): string {
    return name
      .split(' ')
      .map(word => word.charAt(0))
      .join('')
      .substring(0, 2)
      .toUpperCase();
  }

  trackByClientId(index: number, client: Client): number {
    return client.id;
  }

  navigateToProfile(client: Client): void {
    this.router.navigate(['/clients', client.id, 'profile']);
  }

  editClient(client: Client): void {
    this.router.navigate(['/clients', client.id, 'edit']);
  }

  createClient(): void {
    this.router.navigate(['/clients/new']);
  }

  exportClients(): void {
    const csvContent = this.generateCSVContent();
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `clientes-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  }

  private generateCSVContent(): string {
    const headers = ['ID', 'Nombre', 'Email', 'Teléfono', 'Reservas Totales', 'Cursos Completados', 'Estado', 'Fecha Registro', 'Última Actividad'];
    const rows = this.filteredClients.map(client => [
      client.id,
      client.name,
      client.email,
      client.phone,
      client.totalBookings,
      client.completedCourses || 0,
      client.isActive ? 'Activo' : 'Inactivo',
      client.registrationDate?.toLocaleDateString('es-ES') || '',
      client.lastActivity?.toLocaleDateString('es-ES') || ''
    ]);

    const csvRows = [headers, ...rows].map(row => 
      row.map(field => `"${field}"`).join(',')
    );

    return csvRows.join('\n');
  }
}

