import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';

interface SportProgressItem {
  sport: string;
  level: string;
}

interface EvaluationItem {
  date: string;
  result: string;
}

interface BookingHistoryItem {
  id: number;
  date: string;
  service: string;
}

@Component({
  selector: 'app-client-profile',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './client-profile.component.html',
  styleUrls: ['./client-profile.component.scss'],
})
export class ClientProfileComponent {
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);

  viewMode = signal<'client' | 'utilizer'>('client');
  activeTab = signal<'sport' | 'evaluations'>('sport');

  currentClient = {
    id: 1,
    first_name: 'Alice',
    last_name: 'Johnson',
    email: 'alice@example.com',
  };

  personalForm: FormGroup = this.fb.group({
    first_name: [this.currentClient.first_name],
    last_name: [this.currentClient.last_name],
    email: [this.currentClient.email],
  });

  sportProgress: SportProgressItem[] = [
    { sport: 'Ski', level: 'Intermediate' },
    { sport: 'Snowboard', level: 'Beginner' },
  ];

  evaluations: EvaluationItem[] = [
    { date: '2024-01-01', result: 'Good' },
    { date: '2024-02-15', result: 'Excellent' },
  ];

  bookingHistory: BookingHistoryItem[] = [
    { id: 1, date: '2024-03-01', service: 'Ski lesson' },
    { id: 2, date: '2024-04-10', service: 'Snowboard session' },
  ];

  switchMode(mode: 'client' | 'utilizer'): void {
    this.viewMode.set(mode);
  }

  switchTab(tab: 'sport' | 'evaluations'): void {
    this.activeTab.set(tab);
  }

  newBooking(): void {
    this.router.navigate(['/reservations/new'], {
      queryParams: { clientId: this.currentClient.id },
    });
  }
}

