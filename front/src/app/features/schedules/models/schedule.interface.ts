export type EventType = 'class' | 'private' | 'group' | 'workshop' | 'exam' | 'meeting' | 'maintenance';
export type EventStatus = 'confirmed' | 'tentative' | 'cancelled' | 'completed';
export type RecurrencePattern = 'none' | 'daily' | 'weekly' | 'monthly';
export type SkillLevel = 'beginner' | 'intermediate' | 'advanced' | 'expert';

export interface Instructor {
  id: number;
  name: string;
  email?: string;
  phone?: string;
  specialties?: string[];
}

export interface ScheduleEvent {
  id: number;
  title: string;
  description?: string;
  type: EventType;
  start_time: Date;
  end_time: Date;
  instructor?: Instructor;
  level?: SkillLevel;
  max_participants?: number;
  enrolled_count?: number;
  location?: string;
  equipment_required?: string[];
  recurrence?: RecurrencePattern;
  recurrence_until?: Date;
  status: EventStatus;
  notes?: string;
  created_at?: Date;
  updated_at?: Date;
}

export interface EventCreateRequest {
  title: string;
  description?: string;
  type: EventType;
  start_time: string;
  end_time: string;
  instructor_id?: number;
  level?: SkillLevel;
  max_participants?: number;
  location?: string;
  equipment_required?: string[];
  recurrence?: RecurrencePattern;
  recurrence_until?: string;
  notes?: string;
}

export interface EventUpdateRequest extends Partial<EventCreateRequest> {
  id: number;
}

export interface EventFilter {
  instructor_id?: number;
  type?: EventType;
  level?: SkillLevel;
  location?: string;
  date_from?: string;
  date_to?: string;
  status?: EventStatus;
}

export interface CalendarView {
  type: 'month' | 'week' | 'day';
  date: Date;
}

export interface TimeSlot {
  hour: number;
  minute: number;
  events: ScheduleEvent[];
  available: boolean;
}

export interface AvailabilityCheck {
  instructor_id?: number;
  location?: string;
  start_time: string;
  end_time: string;
  exclude_event_id?: number;
}

export interface ConflictResult {
  has_conflicts: boolean;
  conflicts: {
    type: 'instructor' | 'location' | 'equipment';
    message: string;
    conflicting_event?: ScheduleEvent;
  }[];
}