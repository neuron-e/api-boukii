export interface TableColumn<T> {
  label: string;
  property: string;
  type: 'text' | 'id' | 'image' | 'badge' | 'progress' | 'warning' | 'checkbox' | 'button' | 'active' | 'dates' | 'client' | 'register_date'| 'duration'| 'datesCourse'| 'count'| 'light_school'| 'sports_monitor'| 'booking_users_image'| 'clients_schools'
    | 'light' | 'price' | 'payment_status' | 'cancelation_status' | 'bookings' | 'level' | 'status' | 'birth' | 'date' | 'sport' | 'flexible' | 'sports' | 'booking_users' | 'client_image' | 'booking_dates' | 'trads'
    | 'course_type_data' | 'light_data'  | 'payment_status_data' | 'course_image' | 'payment_method_id' | 'payment_method' | 'coronita' | 'qr' | 'light_monitors_schools' | 'monitor' | 'client_2' | 'booking_users_image_monitors' | 'monitor_sports_degrees';
  visible?: boolean;
  cssClasses?: string[];
}
