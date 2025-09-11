export type SubscriptionTier = 'free' | 'basic' | 'premium' | 'enterprise';
export type ModuleStatus = 'active' | 'inactive' | 'trial' | 'expired' | 'suspended' | 'cancelled';
export type ModuleCategory = 'core' | 'management' | 'communication' | 'analytics' | 'finance' | 'marketing';

export interface ModuleSubscription {
  id: number;
  module_slug: string;
  name: string;
  description: string;
  icon: string;
  subscription_tier: SubscriptionTier;
  status: ModuleStatus;
  activated_at: Date;
  expires_at?: Date;
  trial_ends_at?: Date;
  activated_by?: {
    id: number;
    name: string;
    email: string;
  };
  monthly_price?: number;
  annual_price?: number;
  features: string[];
  settings?: Record<string, any>;
  usage_stats?: {
    monthly_usage: number;
    api_calls?: number;
    storage_used?: number;
    active_users?: number;
  };
  limits?: {
    api_calls?: number;
    storage_gb?: number;
    users?: number;
    records?: number;
  };
  created_at?: Date;
  updated_at?: Date;
}

export interface ModuleCatalog {
  slug: string;
  name: string;
  description: string;
  long_description?: string;
  icon: string;
  category: ModuleCategory;
  is_new: boolean;
  is_popular?: boolean;
  requires_approval?: boolean;
  pricing_tiers: ModulePricingTier[];
  features_comparison?: Record<SubscriptionTier, string[]>;
  dependencies: string[];
  screenshots?: string[];
  demo_url?: string;
  documentation_url?: string;
  support_level?: 'basic' | 'priority' | 'dedicated';
  min_plan_required?: SubscriptionTier;
  created_at?: Date;
  updated_at?: Date;
}

export interface ModulePricingTier {
  tier: SubscriptionTier;
  price: number;
  annual_price?: number;
  features: string[];
  limits?: {
    api_calls?: number;
    storage_gb?: number;
    users?: number;
    records?: number;
  };
  trial_days?: number;
}

export interface SubscriptionRequest {
  module_slug: string;
  subscription_tier: SubscriptionTier;
  billing_cycle?: 'monthly' | 'annual';
  auto_renew?: boolean;
  trial_requested?: boolean;
}

export interface SubscriptionUpdate {
  subscription_tier?: SubscriptionTier;
  billing_cycle?: 'monthly' | 'annual';
  auto_renew?: boolean;
  settings?: Record<string, any>;
}

export interface ModuleUsage {
  module_slug: string;
  period_start: string;
  period_end: string;
  api_calls: number;
  storage_used: number;
  active_users: number;
  records_created: number;
  features_used: string[];
  peak_usage_date?: string;
  cost: number;
}

export interface ModulePermission {
  module_slug: string;
  permission: string;
  granted: boolean;
  granted_by?: {
    id: number;
    name: string;
    email: string;
  };
  granted_at?: Date;
}

export interface ModuleConfiguration {
  module_slug: string;
  settings: Record<string, any>;
  integrations?: {
    name: string;
    enabled: boolean;
    config?: Record<string, any>;
  }[];
  webhooks?: {
    url: string;
    events: string[];
    active: boolean;
  }[];
  api_keys?: {
    name: string;
    key: string;
    permissions: string[];
    expires_at?: Date;
  }[];
}

export interface ModuleAnalytics {
  module_slug: string;
  period: 'daily' | 'weekly' | 'monthly';
  metrics: {
    date: string;
    usage_count: number;
    unique_users: number;
    api_calls: number;
    errors: number;
    response_time_avg: number;
  }[];
  top_features: {
    feature: string;
    usage_count: number;
    percentage: number;
  }[];
  user_engagement: {
    daily_active: number;
    weekly_active: number;
    monthly_active: number;
    retention_rate: number;
  };
}

export interface BillingInfo {
  subscription_id: string;
  next_billing_date: Date;
  amount: number;
  currency: string;
  billing_cycle: 'monthly' | 'annual';
  payment_method?: {
    type: 'card' | 'bank' | 'paypal';
    last_four?: string;
    expires?: string;
  };
  billing_history: {
    date: Date;
    amount: number;
    status: 'paid' | 'pending' | 'failed' | 'refunded';
    invoice_url?: string;
  }[];
}

export interface ModuleNotification {
  id: number;
  module_slug: string;
  type: 'info' | 'warning' | 'error' | 'success';
  title: string;
  message: string;
  action_required?: boolean;
  action_url?: string;
  read: boolean;
  created_at: Date;
}

export interface PlanComparison {
  plans: {
    name: string;
    tier: SubscriptionTier;
    price_monthly: number;
    price_annual: number;
    features: {
      category: string;
      items: {
        name: string;
        included: boolean | string;
        tooltip?: string;
      }[];
    }[];
    limits: {
      modules: number | 'unlimited';
      users: number | 'unlimited';
      storage: number | 'unlimited';
      api_calls: number | 'unlimited';
    };
    popular?: boolean;
    recommended?: boolean;
  }[];
}