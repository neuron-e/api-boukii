import { NgModule } from "@angular/core";
import { CommonModule } from "@angular/common";
import { BookingsCreateUpdateV2Component } from "./bookings-create-update.component";
import { MatCardModule } from "@angular/material/card";
import { SecondaryToolbarModule } from "src/@vex/components/secondary-toolbar/secondary-toolbar.module";
import { BreadcrumbsModule } from "src/@vex/components/breadcrumbs/breadcrumbs.module";
import { MatIconModule } from "@angular/material/icon";
import { MatButtonModule } from "@angular/material/button";
import { TranslateModule } from "@ngx-translate/core";
import { BookingComponentsModule } from "./components/components.module";
import { FluxModalModule } from "src/@vex/components/flux-component/flux-modal/app.module";
import { MatRadioModule } from '@angular/material/radio';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { FormsModule } from '@angular/forms';
import {MatDialogModule} from '@angular/material/dialog';

@NgModule({
  declarations: [BookingsCreateUpdateV2Component],
    imports: [
        CommonModule,
        TranslateModule,
        MatCardModule,
        BookingComponentsModule,
        SecondaryToolbarModule,
        BreadcrumbsModule,
        MatIconModule,
        MatButtonModule,
        FluxModalModule,
        MatRadioModule,
        MatInputModule,
        MatSelectModule,
        FormsModule,
        MatDialogModule
    ],
  exports: [BookingsCreateUpdateV2Component],
})
export class BookingsCreateUpdateModule { }
