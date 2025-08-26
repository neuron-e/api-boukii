import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormControl } from '@angular/forms';
import { Router } from '@angular/router';

export interface Client {
  id: number;
  name: string;
  email: string;
  phone: string;
  totalBookings: number;
}

@Component({
  selector: 'app-clients-list-page',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './clients-list.page.html',
  styleUrls: ['./clients-list.page.scss'],
})
export class ClientsListPageComponent implements OnInit {
  private readonly router = inject(Router);

  searchControl = new FormControl('');

  clients: Client[] = [
    { id: 1, name: 'Alice Johnson', email: 'alice@example.com', phone: '555-0001', totalBookings: 3 },
    { id: 2, name: 'Bob Smith', email: 'bob@example.com', phone: '555-0002', totalBookings: 5 },
    { id: 3, name: 'Charlie Brown', email: 'charlie@example.com', phone: '555-0003', totalBookings: 2 },
    { id: 4, name: 'Diana Prince', email: 'diana@example.com', phone: '555-0004', totalBookings: 7 },
    { id: 5, name: 'Eve Adams', email: 'eve@example.com', phone: '555-0005', totalBookings: 1 },
    { id: 6, name: 'Frank Wright', email: 'frank@example.com', phone: '555-0006', totalBookings: 4 },
    { id: 7, name: 'Grace Lee', email: 'grace@example.com', phone: '555-0007', totalBookings: 6 },
    { id: 8, name: 'Henry Ford', email: 'henry@example.com', phone: '555-0008', totalBookings: 8 },
    { id: 9, name: 'Ivy Nguyen', email: 'ivy@example.com', phone: '555-0009', totalBookings: 2 },
    { id: 10, name: 'Jack Black', email: 'jack@example.com', phone: '555-0010', totalBookings: 5 },
  ];

  filteredClients = [...this.clients];

  ngOnInit(): void {
    this.searchControl.valueChanges.subscribe((term) => {
      const value = term?.toLowerCase() ?? '';
      this.filteredClients = this.clients.filter(
        (c) =>
          c.name.toLowerCase().includes(value) ||
          c.email.toLowerCase().includes(value)
      );
    });
  }

  navigateToProfile(client: Client): void {
    this.router.navigate(['/clients', client.id, 'profile']);
  }
}

