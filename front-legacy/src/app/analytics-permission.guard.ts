import { Injectable } from '@angular/core';
import { CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot, Router } from '@angular/router';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AnalyticsPermissionGuard implements CanActivate {

  constructor(private router: Router) {}

  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot
  ): Observable<boolean> | Promise<boolean> | boolean {

    // Verificar permisos del usuario
    const user = this.getCurrentUser();

    if (!user) {
      this.router.navigate(['/login']);
      return false;
    }

    // Verificar permisos específicos para analytics
    if (!this.hasAnalyticsPermissions(user)) {
      this.router.navigate(['/unauthorized']);
      return false;
    }

    return true;
  }

  private getCurrentUser(): any {
    const userStr = localStorage.getItem('boukiiUser');
    return userStr ? JSON.parse(userStr) : null;
  }

  private hasAnalyticsPermissions(user: any): boolean {
    // Implementar lógica de permisos según tu sistema
    return user.role === 'admin' ||
      user.permissions?.includes('analytics.view') ||
      user.permissions?.includes('statistics.view');
  }
}
