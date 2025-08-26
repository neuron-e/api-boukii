import { inject } from '@angular/core';
import { Router, CanActivateFn, ActivatedRouteSnapshot } from '@angular/router';
import { map, first } from 'rxjs/operators';
import { FeatureFlagService, FeatureFlags } from '../services/feature-flag.service';

/**
 * Guard que redirige a legacy si la feature flag no está habilitada
 */
export const featureFlagGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  const featureFlagService = inject(FeatureFlagService);
  const router = inject(Router);
  
  // Mapeo de rutas a feature flags
  const routeToFlagMapping: Record<string, keyof FeatureFlags> = {
    'dashboard': 'useV5Dashboard',
    'planificador': 'useV5Planificador',
    'reservas': 'useV5Reservas',
    'cursos': 'useV5Cursos',
    'monitores': 'useV5Monitores',
    'clientes': 'useV5Clientes',
    'analytics': 'useV5Analytics',
    'estadisticas': 'useV5Analytics',
    'settings': 'useV5Settings',
    'ajustes': 'useV5Settings',
    'communications': 'useV5Communications',
    'comunicaciones': 'useV5Communications',
    'chat': 'useV5Chat',
    'renting': 'useV5Renting',
    'material': 'useV5Renting'
  };
  
  // Determinar que feature flag verificar
  const routePath = route.routeConfig?.path || '';
  const flagToCheck = routeToFlagMapping[routePath];
  
  if (!flagToCheck) {
    // Si no hay mapeo específico, permitir acceso (rutas como auth, etc.)
    return true;
  }
  
  return featureFlagService.refreshFlags().pipe(
    first(),
    map(flags => {
      const isEnabled = flags[flagToCheck];
      
      if (!isEnabled) {
        console.log(`[FeatureFlagGuard] Feature ${flagToCheck} disabled, redirecting to legacy`);
        
        // Construir URL de legacy
        const currentUrl = route.url.map(segment => segment.path).join('/');
        const queryParams = route.queryParams;
        const fragment = route.fragment;
        
        // Redirigir a legacy manteniendo parámetros
        let legacyUrl = `/legacy/${currentUrl}`;
        
        const queryString = new URLSearchParams(queryParams).toString();
        if (queryString) {
          legacyUrl += `?${queryString}`;
        }
        
        if (fragment) {
          legacyUrl += `#${fragment}`;
        }
        
        window.location.href = legacyUrl;
        return false;
      }
      
      console.log(`[FeatureFlagGuard] Feature ${flagToCheck} enabled, allowing V5 access`);
      return true;
    })
  );
};

/**
 * Guard para verificar modo mantenimiento
 */
export const maintenanceGuard: CanActivateFn = () => {
  const featureFlagService = inject(FeatureFlagService);
  const router = inject(Router);
  
  return featureFlagService.flags().pipe(
    first(),
    map(flags => {
      if (flags.maintenanceMode) {
        console.log('[MaintenanceGuard] Maintenance mode active, redirecting');
        router.navigate(['/maintenance']);
        return false;
      }
      return true;
    })
  );
};

/**
 * Guard para features beta (requiere flag beta habilitado)
 */
export const betaFeatureGuard: CanActivateFn = () => {
  const featureFlagService = inject(FeatureFlagService);
  const router = inject(Router);
  
  return featureFlagService.flags().pipe(
    first(),
    map(flags => {
      if (!flags.enableBetaFeatures) {
        console.log('[BetaFeatureGuard] Beta features disabled, access denied');
        router.navigate(['/dashboard']);
        return false;
      }
      return true;
    })
  );
};

/**
 * Helper function para crear guards personalizados
 */
export function createFeatureFlagGuard(
  requiredFlag: keyof FeatureFlags,
  redirectPath: string = '/dashboard'
): CanActivateFn {
  return () => {
    const featureFlagService = inject(FeatureFlagService);
    const router = inject(Router);
    
    return featureFlagService.flags().pipe(
      first(),
      map(flags => {
        if (!flags[requiredFlag]) {
          console.log(`[CustomFeatureFlagGuard] ${requiredFlag} disabled, redirecting to ${redirectPath}`);
          router.navigate([redirectPath]);
          return false;
        }
        return true;
      })
    );
  };
}