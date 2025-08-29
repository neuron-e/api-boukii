import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormControl } from '@angular/forms';
import { FormsModule } from '@angular/forms';
import { UIButtonComponent } from '@shared/components/ui/button/button.component';
import { UIBadgeComponent } from '@shared/components/ui/badge/badge.component';
import { UISearchInputComponent } from '@shared/components/ui/search-input/search-input.component';
import { UITableComponent } from '@shared/components/ui/table/ui-table.component';
import { UIAvatarComponent } from '@shared/components/ui/avatar/ui-avatar.component';
import { UISelectComponent, type SelectOption } from '@shared/components/ui/select/ui-select.component';
import { SegmentedToggleComponent } from '@shared/components/ui/segmented-toggle/segmented-toggle.component';
import { UIKpiCardComponent } from '@shared/components/ui/kpi-card/ui-kpi-card.component';
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
  hasIncompleteData?: boolean;
  status?: 'active' | 'inactive' | 'blocked';
  profilesCount?: number;
  linkedProfiles?: number;
  avatarUrl?: string;
}

@Component({
  selector: 'app-clients-list-page',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormsModule, UIButtonComponent, UIBadgeComponent, UISearchInputComponent, UITableComponent, UIAvatarComponent, UISelectComponent, UIKpiCardComponent, SegmentedToggleComponent],
  templateUrl: './clients-list.page.html',
  styleUrls: ['./clients-list.page.scss'],
})
export class ClientsListPageComponent implements OnInit {
  private readonly router = inject(Router);

  searchControl = new FormControl('');
  viewMode: 'cards' | 'table' = 'table';
  sortBy: 'name' | 'bookings' | 'recent' = 'name';
  sortOrder: 'asc' | 'desc' = 'asc';

  onViewModeChange(mode: string): void {
    this.viewMode = (mode === 'cards' ? 'cards' : 'table');
  }

  // Filters
  typeFilter: string | null = '';
  statusFilter: string | null = '';
  typeOptions: SelectOption[] = [
    { value: '', label: 'Todos los tipos' },
    { value: 'vip', label: 'VIP' },
    { value: 'habitual', label: 'Habitual' },
    { value: 'nuevo', label: 'Nuevo' },
  ];
  statusOptions: SelectOption[] = [
    { value: '', label: 'Todos los estados' },
    { value: 'active', label: 'Activo' },
    { value: 'inactive', label: 'Inactivo' },
    { value: 'blocked', label: 'Bloqueado' },
  ];

  clients: Client[] = [
    { id: 1, name: 'Maria González Pérez', email: 'maria.gonzalez@email.com', phone: '+34 666 123 456', totalBookings: 4, completedCourses: 3, isActive: true, registrationDate: new Date('2023-11-01'), lastActivity: new Date('2025-01-20'), status: 'active', profilesCount: 4, linkedProfiles: 3, avatarUrl: 'https://images.unsplash.com/photo-1494790108755-2616b612b47c?w=64&h=64&fit=crop&crop=face' },
    { id: 2, name: 'Carlos Ruiz Martín', email: 'carlos.ruiz@email.com', phone: '+34 666 789 012', totalBookings: 2, completedCourses: 1, isActive: true, registrationDate: new Date('2024-02-01'), lastActivity: new Date('2025-01-18'), status: 'active', profilesCount: 2, linkedProfiles: 1, hasIncompleteData: true },
    { id: 3, name: 'Laura Martín López', email: 'laura.martin@email.com', phone: '+34 666 345 678', totalBookings: 1, completedCourses: 0, isActive: true, registrationDate: new Date('2025-01-01'), lastActivity: new Date('2025-01-22'), status: 'active', profilesCount: 1, linkedProfiles: 0, avatarUrl: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=64&h=64&fit=crop&crop=face' },
    { id: 4, name: 'Diego López García', email: 'diego.lopez@email.com', phone: '+34 666 901 234', totalBookings: 3, completedCourses: 2, isActive: false, registrationDate: new Date('2024-08-01'), lastActivity: new Date('2025-01-10'), status: 'inactive', profilesCount: 3, linkedProfiles: 2 },
    { id: 5, name: 'Ana Fernández Soto', email: 'ana.fernandez@email.com', phone: '+34 666 567 890', totalBookings: 5, completedCourses: 4, isActive: true, registrationDate: new Date('2023-03-01'), lastActivity: new Date('2025-01-21'), status: 'active', profilesCount: 5, linkedProfiles: 4 },
    { id: 6, name: 'Roberto Silva Jiménez', email: 'roberto.silva@email.com', phone: '+34 666 234 567', totalBookings: 2, completedCourses: 1, isActive: true, registrationDate: new Date('2024-09-01'), lastActivity: new Date('2025-01-19'), status: 'active', profilesCount: 2, linkedProfiles: 1 },
    { id: 7, name: 'Carmen López Vega', email: 'carmen.lopez@email.com', phone: '+34 666 456 789', totalBookings: 3, completedCourses: 2, isActive: true, registrationDate: new Date('2025-01-01'), lastActivity: new Date('2025-01-23'), status: 'active', profilesCount: 3, linkedProfiles: 2, hasIncompleteData: true },
    { id: 8, name: 'Miguel Santos Torres', email: 'miguel.santos@email.com', phone: '+34 666 678 901', totalBookings: 1, completedCourses: 0, isActive: false, registrationDate: new Date('2024-06-01'), lastActivity: new Date('2025-01-05'), status: 'inactive', profilesCount: 1, linkedProfiles: 0 },
    { id: 9, name: 'Elena Torres Ruiz', email: 'elena.torres@email.com', phone: '+34 666 890 123', totalBookings: 3, completedCourses: 2, isActive: true, registrationDate: new Date('2022-12-01'), lastActivity: new Date('2025-01-22'), status: 'active', profilesCount: 3, linkedProfiles: 2, avatarUrl: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=64&h=64&fit=crop&crop=face' },
    { id: 10, name: 'Pedro Navarro Díaz', email: 'pedro.navarro@email.com', phone: '+34 666 012 345', totalBookings: 4, completedCourses: 3, isActive: false, registrationDate: new Date('2024-11-01'), lastActivity: new Date('2024-12-28'), status: 'blocked', profilesCount: 4, linkedProfiles: 3, hasIncompleteData: true },
  ];

  filteredClients = [...this.clients];

  ngOnInit(): void {
    // Default to cards view on small screens
    if (typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
      this.viewMode = 'cards';
    }
    this.searchControl.valueChanges.subscribe((term) => {
      this.filterAndSortClients(term);
    });
    this.filterAndSortClients();
  }

  private filterAndSortClients(searchTerm?: string | null): void {
    const value = searchTerm?.toLowerCase() ?? '';
    
    // Filter clients by text
    let filtered = this.clients.filter((c) =>
      c.name.toLowerCase().includes(value) ||
      c.email.toLowerCase().includes(value) ||
      c.phone.toLowerCase().includes(value)
    );

    // Filter by type
    if (this.typeFilter) {
      filtered = filtered.filter((c) => {
        const isVip = c.totalBookings >= 7;
        const isHabitual = c.totalBookings >= 3 && c.totalBookings < 7;
        const isNuevo = c.totalBookings < 3;
        return (
          (this.typeFilter === 'vip' && isVip) ||
          (this.typeFilter === 'habitual' && isHabitual) ||
          (this.typeFilter === 'nuevo' && isNuevo)
        );
      });
    }

    // Filter by status
    if (this.statusFilter) {
      filtered = filtered.filter((c) => c.status === this.statusFilter);
    }

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

  onFiltersChange(): void {
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

  onImageError(event: Event): void {
    const target = event.target as HTMLImageElement;
    if (target && target.nextElementSibling) {
      target.style.display = 'none';
      (target.nextElementSibling as HTMLElement).style.display = 'flex';
    }
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

  getInitials(name: string): string {
    if (!name) return '';
    return name
      .split(' ')
      .map(part => part.charAt(0))
      .join('')
      .toUpperCase()
      .substring(0, 2);
  }
}
