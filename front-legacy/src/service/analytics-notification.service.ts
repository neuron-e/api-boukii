import { Injectable } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { BehaviorSubject, Observable } from 'rxjs';

export interface AnalyticsNotification {
  id: string;
  type: 'success' | 'error' | 'warning' | 'info';
  title: string;
  message: string;
  timestamp: Date;
  duration?: number;
  action?: string;
}

@Injectable({
  providedIn: 'root'
})
export class AnalyticsNotificationService {

  private notificationsSubject = new BehaviorSubject<AnalyticsNotification[]>([]);
  public notifications$ = this.notificationsSubject.asObservable();

  constructor(private snackBar: MatSnackBar) {}

  showSuccess(message: string, title: string = 'Éxito', duration: number = 3000): void {
    this.showNotification('success', title, message, duration);
  }

  showError(message: string, title: string = 'Error', duration: number = 5000): void {
    this.showNotification('error', title, message, duration);
  }

  showWarning(message: string, title: string = 'Advertencia', duration: number = 4000): void {
    this.showNotification('warning', title, message, duration);
  }

  showInfo(message: string, title: string = 'Información', duration: number = 3000): void {
    this.showNotification('info', title, message, duration);
  }

  private showNotification(type: AnalyticsNotification['type'], title: string, message: string, duration: number): void {
    const notification: AnalyticsNotification = {
      id: this.generateId(),
      type,
      title,
      message,
      timestamp: new Date(),
      duration
    };

    // Añadir a la lista de notificaciones
    const currentNotifications = this.notificationsSubject.value;
    this.notificationsSubject.next([...currentNotifications, notification]);

    // Mostrar snackbar
    this.snackBar.open(message, 'Cerrar', {
      duration,
      panelClass: [`snackbar-${type}`],
      horizontalPosition: 'end',
      verticalPosition: 'top'
    });

    // Remover después del tiempo especificado
    setTimeout(() => {
      this.removeNotification(notification.id);
    }, duration);
  }

  removeNotification(id: string): void {
    const currentNotifications = this.notificationsSubject.value;
    const filtered = currentNotifications.filter(n => n.id !== id);
    this.notificationsSubject.next(filtered);
  }

  clearAll(): void {
    this.notificationsSubject.next([]);
  }

  private generateId(): string {
    return Math.random().toString(36).substr(2, 9);
  }
}
