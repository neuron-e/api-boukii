import { BrowserModule } from '@angular/platform-browser';
import { NgModule } from '@angular/core';
import { AppRoutingModule } from './app-routing.module';
import { AppComponent } from './app.component';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { VexModule } from '../@vex/vex.module';
import { HttpClientModule, HttpClient } from '@angular/common/http';
import { CustomLayoutModule } from './custom-layout/custom-layout.module';
import { DashboardComponent } from './pages/dashboard/dashboard.component';
import { ComponentsModule } from 'src/@vex/components/components.module';
import { AuthService } from 'src/service/auth.service';
import { TranslateLoader, TranslateModule } from '@ngx-translate/core';
import { TranslateHttpLoader } from '@ngx-translate/http-loader';
import { MAT_DATE_LOCALE } from '@angular/material/core';
import { PreviewModalComponent } from './components/preview-modal/preview-modal.component';
import { MatDialogModule } from '@angular/material/dialog';
import { ComponentsCustomModule } from './components/components-custom.module';
import { CurrencyFormatterPipe } from './pipes/currency-formatter.pipe';
import { PercentageFormatterPipe } from './pipes/percentage-formatter.pipe';
import { NumberFormatterPipe } from './pipes/number-formatter.pipe';
import { DateRangeFormatterPipe } from './pipes/date-range-formatter.pipe';

// AoT requires an exported function for factories
export function HttpLoaderFactory(http: HttpClient) {
    return new TranslateHttpLoader(http, './assets/i18n/', '.json');
}

@NgModule({
    declarations: [AppComponent, DashboardComponent, PreviewModalComponent, CurrencyFormatterPipe, PercentageFormatterPipe, NumberFormatterPipe, DateRangeFormatterPipe],
    imports: [
        BrowserModule,
        AppRoutingModule,
        BrowserAnimationsModule,
        HttpClientModule,
        TranslateModule.forRoot({
            loader: {
                provide: TranslateLoader,
                useFactory: HttpLoaderFactory,
                deps: [HttpClient]
            }
        }),
        // Vex
        VexModule,
        CustomLayoutModule,
        ComponentsModule,
        MatDialogModule,
        ComponentsCustomModule
    ],
    providers: [AuthService, { provide: MAT_DATE_LOCALE, useValue: 'en-GB' }],
    bootstrap: [AppComponent]
})
export class AppModule { }
