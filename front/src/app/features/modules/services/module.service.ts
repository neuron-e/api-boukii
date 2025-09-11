import { Injectable, inject } from '@angular/core';
import { Observable, BehaviorSubject, combineLatest } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { map } from 'rxjs/operators';

import {
  ModuleSubscription,
  ModuleCatalog,
  SubscriptionRequest,
  SubscriptionUpdate,
  ModuleUsage,
  ModuleConfiguration,
  ModuleAnalytics,
  BillingInfo,
  ModuleNotification,
  PlanComparison,
  SubscriptionTier
} from '../models/module.interface';

@Injectable({
  providedIn: 'root'
})
export class ModuleService {
  private http = inject(HttpClient);
  private apiUrl = '/api/v5/modules';

  private subscriptionsSubject = new BehaviorSubject<ModuleSubscription[]>([]);
  private catalogSubject = new BehaviorSubject<ModuleCatalog[]>([]);
  private notificationsSubject = new BehaviorSubject<ModuleNotification[]>([]);

  public subscriptions$ = this.subscriptionsSubject.asObservable();
  public catalog$ = this.catalogSubject.asObservable();
  public notifications$ = this.notificationsSubject.asObservable();

  // Subscription Management
  getSubscriptions(): Observable<ModuleSubscription[]> {
    return this.http.get<any>(`${this.apiUrl}/subscriptions`).pipe(
      map(response => response.subscriptions || [])
    );
  }

  getSubscription(subscriptionId: number): Observable<ModuleSubscription> {
    return this.http.get<any>(`${this.apiUrl}/subscriptions/${subscriptionId}`).pipe(
      map(response => response.subscription)
    );
  }

  subscribe(request: SubscriptionRequest): Observable<ModuleSubscription> {
    return this.http.post<any>(`${this.apiUrl}/subscribe`, request).pipe(
      map(response => response.subscription)
    );
  }

  updateSubscription(moduleSlug: string, update: SubscriptionUpdate): Observable<ModuleSubscription> {
    return this.http.put<ModuleSubscription>(`${this.apiUrl}/subscriptions/${moduleSlug}`, update);
  }

  pauseSubscription(moduleSlug: string, reason?: string): Observable<ModuleSubscription> {
    return this.http.patch<ModuleSubscription>(`${this.apiUrl}/subscriptions/${moduleSlug}/pause`, { reason });
  }

  resumeSubscription(moduleSlug: string): Observable<ModuleSubscription> {
    return this.http.patch<ModuleSubscription>(`${this.apiUrl}/subscriptions/${moduleSlug}/resume`, {});
  }

  cancelSubscription(moduleSlug: string, reason?: string, immediate = false): Observable<{ message: string; effective_date: string }> {
    return this.http.delete<any>(`${this.apiUrl}/subscriptions/${moduleSlug}`, {
      body: { reason, immediate }
    });
  }

  startTrial(moduleSlug: string, trialDays: number = 30): Observable<ModuleSubscription> {
    return this.http.post<any>(`${this.apiUrl}/trial`, { 
      module_slug: moduleSlug, 
      trial_days: trialDays 
    }).pipe(
      map(response => response.subscription)
    );
  }

  // Catalog
  getCatalog(category?: string): Observable<ModuleCatalog[]> {
    return this.http.get<any>(`${this.apiUrl}/catalog`).pipe(
      map(response => [...(response.core_modules || []), ...(response.contractable_modules || [])])
    );
  }

  getCatalogModule(slug: string): Observable<ModuleCatalog> {
    return this.http.get<ModuleCatalog>(`${this.apiUrl}/catalog/${slug}`);
  }

  // Usage & Analytics  
  getUsage(moduleSlug: string, period: 'daily' | 'weekly' | 'monthly' = 'monthly'): Observable<ModuleUsage> {
    return this.http.get<any>(`${this.apiUrl}/${moduleSlug}/usage`).pipe(
      map(response => response.usage_stats)
    );
  }

  checkModuleAccess(moduleSlug: string): Observable<{ has_access: boolean; is_core: boolean }> {
    return this.http.get<any>(`${this.apiUrl}/${moduleSlug}/access`);
  }

  getAllUsage(period: 'daily' | 'weekly' | 'monthly' = 'monthly'): Observable<ModuleUsage[]> {
    return this.http.get<ModuleUsage[]>(`${this.apiUrl}/usage`, {
      params: { period }
    });
  }

  getAnalytics(moduleSlug: string, period: 'daily' | 'weekly' | 'monthly' = 'monthly'): Observable<ModuleAnalytics> {
    return this.http.get<ModuleAnalytics>(`${this.apiUrl}/subscriptions/${moduleSlug}/analytics`, {
      params: { period }
    });
  }

  // Configuration
  getConfiguration(moduleSlug: string): Observable<ModuleConfiguration> {
    return this.http.get<ModuleConfiguration>(`${this.apiUrl}/subscriptions/${moduleSlug}/config`);
  }

  updateConfiguration(moduleSlug: string, config: Partial<ModuleConfiguration>): Observable<ModuleConfiguration> {
    return this.http.put<ModuleConfiguration>(`${this.apiUrl}/subscriptions/${moduleSlug}/config`, config);
  }

  // Billing
  getBillingInfo(): Observable<BillingInfo> {
    return this.http.get<BillingInfo>(`${this.apiUrl}/billing`);
  }

  updatePaymentMethod(paymentMethodId: string): Observable<{ success: boolean; message: string }> {
    return this.http.put<any>(`${this.apiUrl}/billing/payment-method`, { payment_method_id: paymentMethodId });
  }

  downloadInvoice(invoiceId: string): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/billing/invoices/${invoiceId}/download`, {
      responseType: 'blob'
    });
  }

  // Plan Comparison
  getPlanComparison(): Observable<PlanComparison> {
    return this.http.get<PlanComparison>(`${this.apiUrl}/plans/comparison`);
  }

  upgradePlan(tier: SubscriptionTier, billingCycle: 'monthly' | 'annual' = 'monthly'): Observable<{ success: boolean; message: string; subscription_id: string }> {
    return this.http.post<any>(`${this.apiUrl}/plans/upgrade`, { tier, billing_cycle: billingCycle });
  }

  // Notifications
  getNotifications(): Observable<ModuleNotification[]> {
    return this.http.get<ModuleNotification[]>(`${this.apiUrl}/notifications`);
  }

  markNotificationAsRead(notificationId: number): Observable<void> {
    return this.http.patch<void>(`${this.apiUrl}/notifications/${notificationId}/read`, {});
  }

  markAllNotificationsAsRead(): Observable<{ marked_count: number }> {
    return this.http.patch<any>(`${this.apiUrl}/notifications/read-all`, {});
  }

  // Dependencies & Compatibility
  checkDependencies(moduleSlug: string): Observable<{
    module_slug: string;
    dependencies: {
      slug: string;
      name: string;
      required: boolean;
      satisfied: boolean;
      min_tier?: SubscriptionTier;
    }[];
    conflicts: {
      slug: string;
      name: string;
      reason: string;
    }[];
  }> {
    return this.http.get<any>(`${this.apiUrl}/catalog/${moduleSlug}/dependencies`);
  }

  // Module Access Control
  hasModuleAccess(moduleSlug: string): Observable<boolean> {
    return this.getSubscriptions().pipe(
      map(subscriptions => 
        subscriptions.some(sub => 
          sub.module_slug === moduleSlug && 
          ['active', 'trial'].includes(sub.status)
        )
      )
    );
  }

  getModulePermissions(moduleSlug: string): Observable<{
    module_slug: string;
    permissions: {
      action: string;
      allowed: boolean;
      reason?: string;
    }[];
  }> {
    return this.http.get<any>(`${this.apiUrl}/subscriptions/${moduleSlug}/permissions`);
  }

  // Integration helpers
  getActiveModules(): Observable<ModuleSubscription[]> {
    return this.subscriptions$.pipe(
      map(subscriptions => subscriptions.filter(sub => ['active', 'trial'].includes(sub.status)))
    );
  }

  getModulesByCategory(category: string): Observable<ModuleCatalog[]> {
    return this.catalog$.pipe(
      map(catalog => catalog.filter(module => module.category === category))
    );
  }

  getUnreadNotificationsCount(): Observable<number> {
    return this.notifications$.pipe(
      map(notifications => notifications.filter(n => !n.read).length)
    );
  }

  // Combined operations
  getModuleOverview(): Observable<{
    total_subscriptions: number;
    active_subscriptions: number;
    trial_subscriptions: number;
    total_monthly_cost: number;
    available_modules: number;
    unread_notifications: number;
  }> {
    return combineLatest([
      this.subscriptions$,
      this.catalog$,
      this.notifications$
    ]).pipe(
      map(([subscriptions, catalog, notifications]) => ({
        total_subscriptions: subscriptions.length,
        active_subscriptions: subscriptions.filter(s => s.status === 'active').length,
        trial_subscriptions: subscriptions.filter(s => s.status === 'trial').length,
        total_monthly_cost: subscriptions
          .filter(s => ['active', 'trial'].includes(s.status))
          .reduce((sum, s) => sum + (s.monthly_price || 0), 0),
        available_modules: catalog.length,
        unread_notifications: notifications.filter(n => !n.read).length
      }))
    );
  }

  // Local state management
  refreshSubscriptions(): void {
    this.getSubscriptions().subscribe(subscriptions => {
      this.subscriptionsSubject.next(subscriptions);
    });
  }

  refreshCatalog(): void {
    this.getCatalog().subscribe(catalog => {
      this.catalogSubject.next(catalog);
    });
  }

  refreshNotifications(): void {
    this.getNotifications().subscribe(notifications => {
      this.notificationsSubject.next(notifications);
    });
  }

  updateSubscriptionInCache(updatedSubscription: ModuleSubscription): void {
    const currentSubscriptions = this.subscriptionsSubject.value;
    const index = currentSubscriptions.findIndex(sub => sub.id === updatedSubscription.id);
    
    if (index > -1) {
      const newSubscriptions = [...currentSubscriptions];
      newSubscriptions[index] = updatedSubscription;
      this.subscriptionsSubject.next(newSubscriptions);
    } else {
      this.subscriptionsSubject.next([...currentSubscriptions, updatedSubscription]);
    }
  }

  removeSubscriptionFromCache(moduleSlug: string): void {
    const currentSubscriptions = this.subscriptionsSubject.value;
    const filteredSubscriptions = currentSubscriptions.filter(sub => sub.module_slug !== moduleSlug);
    this.subscriptionsSubject.next(filteredSubscriptions);
  }
}