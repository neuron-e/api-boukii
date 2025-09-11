import { Component, inject, OnInit, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatChipsModule } from '@angular/material/chips';
import { MatBadgeModule } from '@angular/material/badge';
import { MatTooltipModule } from '@angular/material/tooltip';

import { PageLayoutComponent } from '../../shared/components/layout/page-layout.component';
import { ScheduleEvent, EventType, RecurrencePattern } from './models/schedule.interface';

@Component({
  selector: 'app-schedules-calendar',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    ReactiveFormsModule,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatMenuModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatDatepickerModule,
    MatNativeDateModule,
    MatChipsModule,
    MatBadgeModule,
    MatTooltipModule,
    PageLayoutComponent,
  ],
  template: `
    <app-page-layout>
      <div class="page-header">
        <div class="page-title">
          <h1 class="text-2xl font-bold">Calendario de Horarios</h1>
          <p class="text-gray-600">Gestión de horarios y planificación de clases</p>
        </div>
        
        <div class="page-actions">
          <button mat-stroked-button (click)="toggleView()">
            <mat-icon>{{ calendarView === 'month' ? 'view_week' : 'calendar_month' }}</mat-icon>
            {{ calendarView === 'month' ? 'Vista Semanal' : 'Vista Mensual' }}
          </button>
          <button mat-stroked-button (click)="openFilters()">
            <mat-icon>filter_list</mat-icon>
            Filtros
            <span *ngIf="getActiveFiltersCount() > 0" class="filter-count">({{ getActiveFiltersCount() }})</span>
          </button>
          <button mat-raised-button color="primary" (click)="createEvent()">
            <mat-icon>add</mat-icon>
            Nuevo Evento
          </button>
        </div>
      </div>

      <!-- Calendar Navigation -->
      <div class="calendar-navigation mb-4">
        <button mat-icon-button (click)="navigatePrevious()">
          <mat-icon>chevron_left</mat-icon>
        </button>
        <h2 class="current-period">{{ getCurrentPeriodLabel() }}</h2>
        <button mat-icon-button (click)="navigateNext()">
          <mat-icon>chevron_right</mat-icon>
        </button>
        <button mat-stroked-button (click)="goToToday()">Hoy</button>
      </div>

      <!-- Calendar Grid -->
      <mat-card class="calendar-card">
        <mat-card-content>
          <!-- Month View -->
          <div *ngIf="calendarView === 'month'" class="calendar-month">
            <div class="calendar-header">
              <div *ngFor="let day of weekDays" class="calendar-header-day">
                {{ day }}
              </div>
            </div>
            <div class="calendar-grid">
              <div 
                *ngFor="let day of calendarDays; let i = index" 
                class="calendar-day"
                [class.other-month]="day.otherMonth"
                [class.today]="day.isToday"
                [class.selected]="day.isSelected"
                (click)="selectDay(day)">
                <div class="day-number">{{ day.date.getDate() }}</div>
                <div class="day-events">
                  <div 
                    *ngFor="let event of day.events; let eventIndex = index"
                    class="event-item"
                    [class]="'event-' + event.type"
                    [matTooltip]="getEventTooltip(event)"
                    (click)="openEvent(event, $event)">
                    <div class="event-time">{{ event.start_time | date:'HH:mm' }}</div>
                    <div class="event-title">{{ event.title }}</div>
                    <div *ngIf="event.instructor" class="event-instructor">{{ event.instructor.name }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Week View -->
          <div *ngIf="calendarView === 'week'" class="calendar-week">
            <div class="time-slots">
              <div class="time-header"></div>
              <div *ngFor="let hour of timeSlots" class="time-slot">
                {{ hour }}:00
              </div>
            </div>
            <div class="week-days">
              <div *ngFor="let day of weekDays; let dayIndex = index" class="week-day">
                <div class="week-day-header">
                  <div class="week-day-name">{{ day }}</div>
                  <div class="week-day-date">{{ getWeekDayDate(dayIndex).getDate() }}</div>
                </div>
                <div class="week-day-slots">
                  <div 
                    *ngFor="let hour of timeSlots" 
                    class="week-slot"
                    (click)="createEventAtTime(dayIndex, hour)">
                    <div 
                      *ngFor="let event of getEventsForTimeSlot(dayIndex, hour)"
                      class="week-event"
                      [class]="'event-' + event.type"
                      [style.top.px]="getEventTopPosition(event)"
                      [style.height.px]="getEventHeight(event)"
                      (click)="openEvent(event, $event)">
                      <div class="event-content">
                        <div class="event-title">{{ event.title }}</div>
                        <div class="event-time">{{ event.start_time | date:'HH:mm' }} - {{ event.end_time | date:'HH:mm' }}</div>
                        <div *ngIf="event.instructor" class="event-instructor">{{ event.instructor.name }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </mat-card-content>
      </mat-card>

      <!-- Event Legend -->
      <mat-card class="legend-card mt-4">
        <mat-card-content>
          <h3 class="text-lg font-medium mb-3">Leyenda</h3>
          <div class="legend-items">
            <div *ngFor="let type of eventTypes" class="legend-item">
              <div class="legend-color" [class]="'event-' + type.key"></div>
              <span class="legend-label">{{ type.label }}</span>
              <mat-chip class="legend-count">{{ getEventCountByType(type.key) }}</mat-chip>
            </div>
          </div>
        </mat-card-content>
      </mat-card>
    </app-page-layout>
  `,
  styles: [`
    .page-header {
      @apply flex justify-between items-start mb-6;
    }

    .calendar-navigation {
      @apply flex items-center justify-center gap-4;
    }

    .current-period {
      @apply text-xl font-semibold min-w-[200px] text-center;
    }

    .calendar-card {
      @apply min-h-[600px];
    }

    /* Month View */
    .calendar-month {
      @apply w-full;
    }

    .calendar-header {
      @apply grid grid-cols-7 gap-1 mb-2;
    }

    .calendar-header-day {
      @apply p-3 text-center font-medium text-gray-600 bg-gray-50;
    }

    .calendar-grid {
      @apply grid grid-cols-7 gap-1;
    }

    .calendar-day {
      @apply min-h-[120px] p-2 border border-gray-200 cursor-pointer hover:bg-gray-50 transition-colors;
    }

    .calendar-day.other-month {
      @apply text-gray-400 bg-gray-50;
    }

    .calendar-day.today {
      @apply bg-blue-50 border-blue-300;
    }

    .calendar-day.selected {
      @apply bg-blue-100 border-blue-500;
    }

    .day-number {
      @apply font-medium mb-1;
    }

    .day-events {
      @apply space-y-1;
    }

    .event-item {
      @apply p-1 rounded text-xs cursor-pointer;
    }

    .event-title {
      @apply font-medium truncate;
    }

    .event-instructor {
      @apply text-xs opacity-75;
    }

    /* Week View */
    .calendar-week {
      @apply flex;
    }

    .time-slots {
      @apply w-16 border-r border-gray-200;
    }

    .time-header {
      @apply h-16 border-b border-gray-200;
    }

    .time-slot {
      @apply h-12 p-2 text-xs text-gray-600 border-b border-gray-100;
    }

    .week-days {
      @apply flex-1 flex;
    }

    .week-day {
      @apply flex-1 border-r border-gray-200;
    }

    .week-day-header {
      @apply h-16 p-2 border-b border-gray-200 text-center;
    }

    .week-day-name {
      @apply font-medium;
    }

    .week-day-date {
      @apply text-lg;
    }

    .week-day-slots {
      @apply relative;
    }

    .week-slot {
      @apply h-12 border-b border-gray-100 cursor-pointer hover:bg-gray-50;
    }

    .week-event {
      @apply absolute left-1 right-1 p-1 rounded text-xs cursor-pointer;
    }

    /* Event Types */
    .event-class {
      @apply bg-blue-200 text-blue-800;
    }

    .event-private {
      @apply bg-green-200 text-green-800;
    }

    .event-group {
      @apply bg-purple-200 text-purple-800;
    }

    .event-workshop {
      @apply bg-orange-200 text-orange-800;
    }

    .event-exam {
      @apply bg-red-200 text-red-800;
    }

    .event-meeting {
      @apply bg-gray-200 text-gray-800;
    }

    .event-maintenance {
      @apply bg-yellow-200 text-yellow-800;
    }

    /* Legend */
    .legend-items {
      @apply flex flex-wrap gap-4;
    }

    .legend-item {
      @apply flex items-center gap-2;
    }

    .legend-color {
      @apply w-4 h-4 rounded;
    }

    .legend-count {
      @apply ml-2;
    }
  `]
})
export class SchedulesCalendarPage implements OnInit {
  private fb = inject(FormBuilder);

  calendarView: 'month' | 'week' = 'month';
  currentDate = new Date();
  selectedDate: Date | null = null;
  
  weekDays = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sab', 'Dom'];
  timeSlots = Array.from({ length: 12 }, (_, i) => 8 + i); // 8:00 - 19:00
  
  eventTypes = [
    { key: 'class' as EventType, label: 'Clases Regulares' },
    { key: 'private' as EventType, label: 'Clases Privadas' },
    { key: 'group' as EventType, label: 'Clases Grupales' },
    { key: 'workshop' as EventType, label: 'Talleres' },
    { key: 'exam' as EventType, label: 'Exámenes' },
    { key: 'meeting' as EventType, label: 'Reuniones' },
    { key: 'maintenance' as EventType, label: 'Mantenimiento' }
  ];

  calendarDays: CalendarDay[] = [];
  events: ScheduleEvent[] = [
    {
      id: 1,
      title: 'Clase de Esquí Principiantes',
      type: 'class',
      start_time: new Date('2025-01-15T09:00:00'),
      end_time: new Date('2025-01-15T11:00:00'),
      instructor: { id: 1, name: 'Carlos Martín' },
      level: 'beginner',
      max_participants: 8,
      enrolled_count: 6,
      location: 'Pista Verde 1',
      recurrence: 'weekly',
      status: 'confirmed'
    },
    {
      id: 2,
      title: 'Clase Privada - Ana García',
      type: 'private',
      start_time: new Date('2025-01-15T14:00:00'),
      end_time: new Date('2025-01-15T15:00:00'),
      instructor: { id: 2, name: 'María López' },
      level: 'intermediate',
      max_participants: 1,
      enrolled_count: 1,
      location: 'Pista Azul 2',
      status: 'confirmed'
    },
    {
      id: 3,
      title: 'Taller de Técnica Avanzada',
      type: 'workshop',
      start_time: new Date('2025-01-16T10:00:00'),
      end_time: new Date('2025-01-16T12:00:00'),
      instructor: { id: 3, name: 'Pedro Ruiz' },
      level: 'advanced',
      max_participants: 12,
      enrolled_count: 8,
      location: 'Pista Roja 1',
      status: 'confirmed'
    }
  ];

  activeFilters = {
    instructor: null,
    type: null,
    level: null,
    location: null
  };

  ngOnInit() {
    this.generateCalendarDays();
  }

  toggleView() {
    this.calendarView = this.calendarView === 'month' ? 'week' : 'month';
    this.generateCalendarDays();
  }

  navigatePrevious() {
    if (this.calendarView === 'month') {
      this.currentDate.setMonth(this.currentDate.getMonth() - 1);
    } else {
      this.currentDate.setDate(this.currentDate.getDate() - 7);
    }
    this.generateCalendarDays();
  }

  navigateNext() {
    if (this.calendarView === 'month') {
      this.currentDate.setMonth(this.currentDate.getMonth() + 1);
    } else {
      this.currentDate.setDate(this.currentDate.getDate() + 7);
    }
    this.generateCalendarDays();
  }

  goToToday() {
    this.currentDate = new Date();
    this.generateCalendarDays();
  }

  getCurrentPeriodLabel(): string {
    if (this.calendarView === 'month') {
      return this.currentDate.toLocaleDateString('es-ES', { 
        month: 'long', 
        year: 'numeric' 
      });
    } else {
      const weekStart = new Date(this.currentDate);
      weekStart.setDate(this.currentDate.getDate() - this.currentDate.getDay() + 1);
      const weekEnd = new Date(weekStart);
      weekEnd.setDate(weekStart.getDate() + 6);
      
      return `${weekStart.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })} - ${weekEnd.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })}`;
    }
  }

  generateCalendarDays() {
    this.calendarDays = [];
    
    if (this.calendarView === 'month') {
      const firstDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
      const lastDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0);
      
      const startDate = new Date(firstDay);
      startDate.setDate(startDate.getDate() - (firstDay.getDay() || 7) + 1);
      
      for (let i = 0; i < 42; i++) {
        const currentDay = new Date(startDate);
        currentDay.setDate(startDate.getDate() + i);
        
        const dayEvents = this.getEventsForDay(currentDay);
        
        this.calendarDays.push({
          date: new Date(currentDay),
          isToday: this.isToday(currentDay),
          isSelected: this.selectedDate ? this.isSameDay(currentDay, this.selectedDate) : false,
          otherMonth: currentDay.getMonth() !== this.currentDate.getMonth(),
          events: dayEvents
        });
      }
    }
  }

  selectDay(day: CalendarDay) {
    this.selectedDate = day.date;
    this.generateCalendarDays();
  }

  getEventsForDay(date: Date): ScheduleEvent[] {
    return this.events.filter(event => 
      this.isSameDay(event.start_time, date)
    );
  }

  getEventsForTimeSlot(dayIndex: number, hour: number): ScheduleEvent[] {
    const targetDate = this.getWeekDayDate(dayIndex);
    return this.events.filter(event => {
      if (!this.isSameDay(event.start_time, targetDate)) return false;
      const eventHour = event.start_time.getHours();
      return eventHour === hour;
    });
  }

  getWeekDayDate(dayIndex: number): Date {
    const weekStart = new Date(this.currentDate);
    weekStart.setDate(this.currentDate.getDate() - this.currentDate.getDay() + 1);
    const targetDate = new Date(weekStart);
    targetDate.setDate(weekStart.getDate() + dayIndex);
    return targetDate;
  }

  getEventTopPosition(event: ScheduleEvent): number {
    const minutes = event.start_time.getMinutes();
    return (minutes / 60) * 48; // 48px per hour slot
  }

  getEventHeight(event: ScheduleEvent): number {
    const duration = event.end_time.getTime() - event.start_time.getTime();
    const hours = duration / (1000 * 60 * 60);
    return Math.max(hours * 48, 24); // Minimum 24px height
  }

  getEventTooltip(event: ScheduleEvent): string {
    const startTime = event.start_time.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    const endTime = event.end_time.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    return `${event.title}\n${startTime} - ${endTime}\n${event.instructor?.name || ''}\n${event.location || ''}`;
  }

  createEvent() {
    // TODO: Open event creation dialog
    console.log('Create new event');
  }

  createEventAtTime(dayIndex: number, hour: number) {
    const targetDate = this.getWeekDayDate(dayIndex);
    targetDate.setHours(hour, 0, 0, 0);
    // TODO: Open event creation dialog with pre-filled date/time
    console.log('Create event at', targetDate);
  }

  openEvent(event: ScheduleEvent, mouseEvent: Event) {
    mouseEvent.stopPropagation();
    // TODO: Open event details/edit dialog
    console.log('Open event', event);
  }

  openFilters() {
    // TODO: Open filters dialog
    console.log('Open filters');
  }

  getActiveFiltersCount(): number {
    return Object.values(this.activeFilters).filter(value => value !== null).length;
  }

  getEventCountByType(type: EventType): number {
    return this.events.filter(event => event.type === type).length;
  }

  private isToday(date: Date): boolean {
    const today = new Date();
    return this.isSameDay(date, today);
  }

  private isSameDay(date1: Date, date2: Date): boolean {
    return date1.getDate() === date2.getDate() &&
           date1.getMonth() === date2.getMonth() &&
           date1.getFullYear() === date2.getFullYear();
  }
}

interface CalendarDay {
  date: Date;
  isToday: boolean;
  isSelected: boolean;
  otherMonth: boolean;
  events: ScheduleEvent[];
}