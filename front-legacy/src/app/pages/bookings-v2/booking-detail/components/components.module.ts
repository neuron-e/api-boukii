import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import {BookingDescriptionCard} from './booking-description-card/booking-description-card.component';
import {BookingReservationDetailComponent} from './booking-reservation-detail/booking-reservation-detail.component';
import {TranslateModule} from '@ngx-translate/core';
import {MatIconModule} from '@angular/material/icon';
import {MatCheckboxModule} from '@angular/material/checkbox';
import {FormsModule, ReactiveFormsModule} from '@angular/forms';
import {BreadcrumbsModule} from '../../../../../@vex/components/breadcrumbs/breadcrumbs.module';
import {MatButtonModule} from '@angular/material/button';
import {FormDetailsPrivateComponent} from './form-details-private/form-details-private.component';
import {MatCardModule} from '@angular/material/card';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {MatInputModule} from '@angular/material/input';
import {MatSelectModule} from '@angular/material/select';
import {FormDetailsColectiveFlexComponent} from './form-details-colective-flex/form-details-colective-flex.component';
import {FormDetailsColectiveFixComponent} from './form-details-colective-fix/form-details-colective-fix.component';
import {StepObservationsComponent} from './step-observations/step-observations.component';
import {FluxModalModule} from '../../../../../@vex/components/flux-component/flux-modal/app.module';
import {MatRadioModule} from '@angular/material/radio';
import {MatDialogModule} from '@angular/material/dialog';
import {IconComponent} from '../../../../../@vex/components/icon/app.component';
import {ComponentsCustomModule} from '../../../../components/components-custom.module';
import {BookingDetailDialogComponent} from './booking-dialog/booking-dialog.component';



@NgModule({
  declarations: [
    BookingDescriptionCard,
    BookingReservationDetailComponent,
    FormDetailsPrivateComponent,
    FormDetailsColectiveFlexComponent,
    FormDetailsColectiveFixComponent,
    StepObservationsComponent,
    BookingDetailDialogComponent
  ],
  imports: [
    CommonModule,
    TranslateModule,
    MatIconModule,
    MatCheckboxModule,
    FormsModule,
    BreadcrumbsModule,
    MatButtonModule,
    MatCardModule,
    ReactiveFormsModule,
    MatDatepickerModule,
    MatInputModule,
    MatSelectModule,
    FluxModalModule,
    MatRadioModule,
    MatDialogModule,
    IconComponent,
    ComponentsCustomModule,
  ],
  exports: [
    BookingDescriptionCard,
    BookingReservationDetailComponent,
    FormDetailsPrivateComponent,
    FormDetailsColectiveFlexComponent,
    FormDetailsColectiveFixComponent,
    StepObservationsComponent
  ]
})
export class ComponentsModule { }
