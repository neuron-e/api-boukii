export type RentalStatus = 'pending' | 'active' | 'overdue' | 'returned' | 'cancelled';
export type ItemCategory = 'skis' | 'snowboard' | 'boots' | 'poles' | 'helmets' | 'protection' | 'accessories';
export type ItemCondition = 'excellent' | 'good' | 'fair' | 'needs_repair' | 'out_of_service';

export interface RentalItem {
  id: number;
  name: string;
  category: ItemCategory;
  size?: string;
  condition: ItemCondition;
  daily_price: number;
  weekly_price?: number;
  deposit_amount: number;
  brand?: string;
  model?: string;
  serial_number?: string;
  description?: string;
  available_quantity: number;
  total_quantity: number;
  created_at?: Date;
  updated_at?: Date;
}

export interface RentalBooking {
  id: number;
  client_id: number;
  client_name: string;
  client_email?: string;
  client_phone?: string;
  status: RentalStatus;
  start_date: Date;
  end_date: Date;
  returned_date?: Date;
  total_price: number;
  deposit_paid: number;
  items: RentalBookingItem[];
  notes?: string;
  created_at?: Date;
  updated_at?: Date;
}

export interface RentalBookingItem {
  id: number;
  rental_booking_id: number;
  item_id: number;
  item: RentalItem;
  quantity: number;
  daily_price: number;
  total_days: number;
  subtotal: number;
  condition_on_rental?: ItemCondition;
  condition_on_return?: ItemCondition;
  damage_notes?: string;
}

export interface InventoryItem extends RentalItem {
  maintenance_history?: MaintenanceRecord[];
  rental_history?: RentalBooking[];
  location?: string;
  barcode?: string;
  purchase_date?: Date;
  purchase_price?: number;
  depreciation_rate?: number;
}

export interface MaintenanceRecord {
  id: number;
  item_id: number;
  type: 'routine' | 'repair' | 'replacement' | 'inspection';
  description: string;
  cost: number;
  performed_by: string;
  performed_at: Date;
  next_maintenance_due?: Date;
  parts_replaced?: string[];
}

export interface RentalFilter {
  status?: RentalStatus;
  client_id?: number;
  date_from?: string;
  date_to?: string;
  item_category?: ItemCategory;
  search?: string;
}

export interface RentalCreateRequest {
  client_id: number;
  start_date: string;
  end_date: string;
  items: Array<{
    item_id: number;
    quantity: number;
  }>;
  notes?: string;
  deposit_payment_method?: 'cash' | 'card' | 'transfer';
}

export interface RentalUpdateRequest {
  start_date?: string;
  end_date?: string;
  items?: Array<{
    item_id: number;
    quantity: number;
  }>;
  notes?: string;
  status?: RentalStatus;
}