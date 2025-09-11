import { ApplicationConfig, ErrorHandler, APP_INITIALIZER } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideAnimations } from '@angular/platform-browser/animations';

import { routes } from './app.routes';
import { baseUrlInterceptor } from '@core/interceptors/base-url.interceptor';
import { authInterceptor } from '@core/interceptors/auth.interceptor';
import { unauthorizedInterceptor } from '@core/interceptors/unauthorized.interceptor';
import { errorInterceptor } from '@core/interceptors/error.interceptor';
import { loadingInterceptor } from '@core/interceptors/loading.interceptor';
import { GlobalErrorHandlerService } from '@core/services/global-error-handler.service';
import { AppInitializerService } from '@core/services/app-initializer.service';

export const appConfig: ApplicationConfig = {
  providers: [
    provideRouter(routes),
    provideHttpClient(
      withInterceptors([
        // Order matters: base URL first, then loading/auth, then error handling
        baseUrlInterceptor,
        loadingInterceptor,
        authInterceptor,
        errorInterceptor,
        unauthorizedInterceptor, // Keep this last for 401 handling
      ])
    ),
    provideAnimations(),

    // Global Error Handler
    {
      provide: ErrorHandler,
      useClass: GlobalErrorHandlerService,
    },

    // App Initializer - runs before app bootstrap
    {
      provide: APP_INITIALIZER,
      useFactory: AppInitializerService.initializerFactory,
      deps: [AppInitializerService],
      multi: true,
    },
  ],
};
