export interface Booking {
  id: number;
  client_id: number;
  course_id: number;
  status: BookingStatus;
  
  // Pricing
  base_price: number;
  extras_price: number;
  discount_amount: number;
  total_price: number;
  original_price?: number;
  
  // Dates
  created_at: string;
  updated_at: string;
  confirmed_at?: string;
  paid_at?: string;
  cancelled_at?: string;
  
  // Notes and details
  notes?: string;
  cancellation_reason?: string;
  special_requirements?: string;
  
  // Relations
  client: BookingClient;
  course: BookingCourse;
  participants: BookingParticipant[];
  payments: BookingPayment[];
  equipment: BookingEquipment[];
  extras: BookingExtra[];
  
  // Metadata
  metadata?: Record<string, any>;
}

export interface BookingClient {
  id: number;
  full_name: string;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  date_of_birth?: string;
  emergency_contact?: string;
  emergency_phone?: string;
  total_bookings?: number;
  created_at: string;
}

export interface BookingCourse {
  id: number;
  title: string;
  description: string;
  category: 'course' | 'activity' | 'private';
  dates: string[];
  schedule: string;
  price: number;
  location: string;
  available_spots: number;
  max_participants: number;
  min_age?: number;
  max_age?: number;
  instructor?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface BookingParticipant {
  id?: number;
  booking_id?: number;
  name: string;
  age: number;
  date_of_birth?: string;
  level: 'beginner' | 'intermediate' | 'advanced';
  medical_notes?: string;
  emergency_contact?: string;
  emergency_phone?: string;
  dietary_restrictions?: string;
  equipment_size?: {
    ski_boots?: string;
    helmet?: string;
    jacket?: string;
  };
}

export interface BookingPayment {
  id: number;
  booking_id: number;
  amount: number;
  method: PaymentMethod;
  status: PaymentStatus;
  transaction_id?: string;
  gateway_response?: any;
  created_at: string;
  processed_at?: string;
}

export interface BookingEquipment {
  id?: number;
  booking_id?: number;
  item_id: number;
  quantity: number;
  size?: string;
  price: number;
  item: {
    id: number;
    name: string;
    category: string;
    daily_price: number;
    available_sizes: string[];
  };
}

export interface BookingExtra {
  id?: number;
  booking_id?: number;
  extra_id: number;
  quantity: number;
  price: number;
  extra: {
    id: number;
    name: string;
    description: string;
    price: number;
    type: 'service' | 'product' | 'insurance';
  };
}

export type BookingStatus = 
  | 'pending'      // Pendiente de confirmación
  | 'confirmed'    // Confirmada pero no pagada
  | 'paid'         // Pagada completamente
  | 'partial_paid' // Parcialmente pagada
  | 'cancelled'    // Cancelada
  | 'completed'    // Completada (curso realizado)
  | 'no_show';     // No se presentó

export type PaymentMethod = 
  | 'cash' 
  | 'card' 
  | 'transfer' 
  | 'paypal' 
  | 'stripe' 
  | 'boukii_pay';

export type PaymentStatus = 
  | 'pending' 
  | 'processing' 
  | 'completed' 
  | 'failed' 
  | 'cancelled' 
  | 'refunded';

export interface CreateBookingRequest {
  client_id: number;
  course_id: number;
  participants: Omit<BookingParticipant, 'id' | 'booking_id'>[];
  equipment?: Omit<BookingEquipment, 'id' | 'booking_id'>[];
  extras?: Omit<BookingExtra, 'id' | 'booking_id'>[];
  promo_code?: string;
  notes?: string;
  special_requirements?: string;
  payment_method?: PaymentMethod;
  send_confirmation?: boolean;
}

export interface UpdateBookingRequest {
  participants?: Omit<BookingParticipant, 'id' | 'booking_id'>[];
  equipment?: Omit<BookingEquipment, 'id' | 'booking_id'>[];
  extras?: Omit<BookingExtra, 'id' | 'booking_id'>[];
  notes?: string;
  special_requirements?: string;
  status?: BookingStatus;
}

export interface BookingFilters {
  search?: string;
  status?: BookingStatus[];
  dateFrom?: string;
  dateTo?: string;
  clientId?: number;
  courseId?: number;
  instructorId?: number;
  paymentStatus?: PaymentStatus[];
  category?: string[];
  priceMin?: number;
  priceMax?: number;
}

export interface BookingStats {
  total: number;
  pending: number;
  confirmed: number;
  paid: number;
  cancelled: number;
  completed: number;
  no_show: number;
  revenue: {
    total: number;
    current_month: number;
    previous_month: number;
    growth_percentage: number;
  };
  popular_courses: {
    course_id: number;
    course_title: string;
    bookings_count: number;
    revenue: number;
  }[];
  avg_booking_value: number;
  occupancy_rate: number;
  cancellation_rate: number;
}

export interface BookingCalendarEvent {
  id: number;
  title: string;
  start: string;
  end: string;
  course_id: number;
  booking_id: number;
  status: BookingStatus;
  participants_count: number;
  instructor: string;
  location: string;
}

export interface PromoCodeValidation {
  valid: boolean;
  discount_type: 'percentage' | 'fixed' | 'free_equipment';
  discount_value: number;
  discount_amount: number;
  code: string;
  name: string;
  expires_at?: string;
  usage_limit?: number;
  usage_count: number;
  applicable_courses?: number[];
  conditions?: {
    min_amount?: number;
    max_discount?: number;
    first_time_only?: boolean;
    valid_until?: string;
  };
}

export interface AvailableTimeSlot {
  date: string;
  start_time: string;
  end_time: string;
  available_spots: number;
  price: number;
  instructor?: {
    id: number;
    name: string;
  };
}

// Utility types for forms
export interface BookingFormData extends CreateBookingRequest {
  client?: BookingClient;
  course?: BookingCourse;
}

export interface BookingSearchResult {
  bookings: Booking[];
  total: number;
  page: number;
  per_page: number;
  filters_applied: BookingFilters;
}

// Equipment and extras available for selection
export interface AvailableEquipment {
  id: number;
  name: string;
  category: string;
  description: string;
  daily_price: number;
  available_sizes: string[];
  available_quantity: number;
  image?: string;
}

export interface AvailableExtra {
  id: number;
  name: string;
  description: string;
  price: number;
  type: 'service' | 'product' | 'insurance';
  required: boolean;
  max_quantity?: number;
}

// Notification preferences
export interface BookingNotificationSettings {
  send_confirmation: boolean;
  send_reminder: boolean;
  reminder_days_before: number;
  send_payment_confirmation: boolean;
  send_cancellation_notice: boolean;
  notification_methods: ('email' | 'sms' | 'push')[];
}