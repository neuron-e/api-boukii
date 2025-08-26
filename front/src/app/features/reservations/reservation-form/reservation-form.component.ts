import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatStepperModule } from '@angular/material/stepper';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatCheckboxModule } from '@angular/material/checkbox';

interface Client { id: number; name: string; }
interface Course { id: number; sport: string; name: string; price: number; }
interface Extra { id: number; name: string; price: number; }

@Component({
  selector: 'app-reservation-form',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatStepperModule,
    MatInputModule,
    MatSelectModule,
    MatDatepickerModule,
    MatNativeDateModule,
    MatButtonModule,
    MatIconModule,
    MatListModule,
    MatCheckboxModule
  ],
  templateUrl: './reservation-form.component.html',
  styleUrl: './reservation-form.component.scss'
})
export class ReservationFormComponent {
  clients: Client[] = [
    { id: 1, name: 'Alice Johnson' },
    { id: 2, name: 'Bob Smith' },
    { id: 3, name: 'Carlos Ruiz' }
  ];

  courses: Course[] = [
    { id: 1, sport: 'Tennis', name: 'Beginner', price: 50 },
    { id: 2, sport: 'Tennis', name: 'Advanced', price: 70 },
    { id: 3, sport: 'Yoga', name: 'Morning Flow', price: 40 },
    { id: 4, sport: 'Yoga', name: 'Power Yoga', price: 60 }
  ];

  extras: Extra[] = [
    { id: 1, name: 'Equipment rental', price: 10 },
    { id: 2, name: 'Locker', price: 5 },
    { id: 3, name: 'Refreshments', price: 8 }
  ];

  clientGroup = this.fb.group({
    client: ['', Validators.required]
  });

  participantsGroup = this.fb.group({
    participants: this.fb.array([])
  });

  courseGroup = this.fb.group({
    sport: ['', Validators.required],
    course: ['', Validators.required]
  });

  dateExtrasGroup = this.fb.group({
    date: ['', Validators.required],
    extras: [[] as number[]]
  });

  priceGroup = this.fb.group({});

  confirmGroup = this.fb.group({});

  finalReservation?: {
    client: string | undefined;
    participants: string[];
    sport: string | undefined;
    course: string | undefined;
    date: Date | string;
    extras: string[];
    total: number;
  };

  constructor(private fb: FormBuilder) {}

  get participants(): FormArray {
    return this.participantsGroup.get('participants') as FormArray;
  }

  addParticipant(): void {
    this.participants.push(this.fb.control('', Validators.required));
  }

  removeParticipant(index: number): void {
    this.participants.removeAt(index);
  }

  get sports(): string[] {
    return Array.from(new Set(this.courses.map(c => c.sport)));
  }

  get filteredCourses(): Course[] {
    const sport = this.courseGroup.value.sport;
    return this.courses.filter(c => !sport || c.sport === sport);
  }

  get selectedCourse(): Course | undefined {
    const courseId = this.courseGroup.value.course;
    if (courseId === null || courseId === undefined) return undefined;
    if (typeof courseId === 'string') {
      return this.courses.find(c => c.id === parseInt(courseId, 10));
    }
    return this.courses.find(c => c.id === courseId);
  }

  get selectedExtras(): Extra[] {
    const ids: number[] = this.dateExtrasGroup.value.extras || [];
    return this.extras.filter(e => ids.includes(e.id));
  }

  get totalPrice(): number {
    const coursePrice = this.selectedCourse?.price || 0;
    const extrasPrice = this.selectedExtras.reduce((sum, e) => sum + e.price, 0);
    return coursePrice + extrasPrice;
  }

  confirm(): void {
    const clientId = this.clientGroup.value.client;
    if (clientId === null || clientId === undefined) {
      console.error('Client is required');
      return;
    }
    
    const selectedClient = typeof clientId === 'string' 
      ? this.clients.find(c => c.id === parseInt(clientId, 10))
      : this.clients.find(c => c.id === clientId);

    const selectedDate = this.dateExtrasGroup.value.date;
    if (!selectedDate) {
      console.error('Date is required');
      return;
    }

    this.finalReservation = {
      client: selectedClient?.name || 'Unknown',
      participants: this.participants.value || [],
      sport: this.selectedCourse?.sport || 'Unknown',
      course: this.selectedCourse?.name || 'Unknown', 
      date: selectedDate,
      extras: this.selectedExtras.map(e => e.name),
      total: this.totalPrice
    };
  }
}

