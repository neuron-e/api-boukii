import { CommonModule } from '@angular/common';
import { NgModule } from '@angular/core';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialogModule } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatRadioModule } from '@angular/material/radio';
import { MatSelectModule } from '@angular/material/select';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { WidgetClientsGroupModule } from 'src/@vex/components/widgets/widget-clients-group/widget-clients-group.module';
import { WidgetClientsSportsModule } from 'src/@vex/components/widgets/widget-clients-sports/widget-clients-sports.module';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatNativeDateModule } from '@angular/material/core';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatListModule } from '@angular/material/list';
import { NgxMatDatetimePickerModule, NgxMatTimepickerModule } from '@angular-material-components/datetime-picker';
import { SecondaryToolbarModule } from 'src/@vex/components/secondary-toolbar/secondary-toolbar.module';
import { BreadcrumbsModule } from 'src/@vex/components/breadcrumbs/breadcrumbs.module';
import { MatStepperModule } from '@angular/material/stepper';
import { MatChipsModule } from '@angular/material/chips';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatTableModule } from '@angular/material/table';
import { MatSortModule } from '@angular/material/sort';
import { ClientDetailComponent } from './client-detail.component';
import { MatTabsModule } from '@angular/material/tabs';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { ComponentsCustomModule } from '../../../components/components-custom.module';
import { PageLayoutModule } from 'src/@vex/components/page-layout/page-layout.module';
import { ComponentsModule } from 'src/@vex/components/components.module';
import { TranslateModule } from '@ngx-translate/core';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { IconComponent } from 'src/@vex/components/icon/app.component';
import { FluxUploadImgModule } from 'src/@vex/components/form/upload-img/app.module';
import {SportCardComponent} from './sport-card/app.component';

@NgModule({
  imports: [
    FormsModule,
    CommonModule,
    ReactiveFormsModule,
    MatInputModule,
    MatButtonModule, IconComponent,
    MatIconModule,
    MatRadioModule,
    MatSelectModule,
    MatMenuModule,
    MatDividerModule,
    MatAutocompleteModule,
    WidgetClientsGroupModule,
    WidgetClientsSportsModule,
    MatCardModule,
    MatFormFieldModule,
    MatNativeDateModule,
    MatDatepickerModule,
    MatListModule,
    NgxMatDatetimePickerModule,
    NgxMatTimepickerModule,
    MatStepperModule,
    MatSlideToggleModule,
    BreadcrumbsModule,
    SecondaryToolbarModule,
    MatDialogModule,
    MatTableModule,
    MatSortModule,
    MatChipsModule,
    MatTabsModule,
    MatProgressBarModule,
    ComponentsCustomModule,
    PageLayoutModule,
    ComponentsModule,
    TranslateModule,
    MatProgressSpinnerModule,
    FluxUploadImgModule
  ],
  declarations: [ClientDetailComponent, SportCardComponent],
  exports: [ClientDetailComponent]
})
export class ClientDetailModule {
}
