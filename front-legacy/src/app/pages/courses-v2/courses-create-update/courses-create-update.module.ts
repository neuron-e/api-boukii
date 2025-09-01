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
import { CoursesCreateUpdateComponent } from './courses-create-update.component';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { WidgetClientsGroupModule } from 'src/@vex/components/widgets/widget-clients-group/widget-clients-group.module';
import { WidgetClientsSportsModule } from 'src/@vex/components/widgets/widget-clients-sports/widget-clients-sports.module';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatNativeDateModule } from '@angular/material/core';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatListModule } from '@angular/material/list';
import { NgxMatDatetimePickerModule, NgxMatTimepickerModule } from '@angular-material-components/datetime-picker';
import { MatStepperModule } from '@angular/material/stepper';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { BreadcrumbsModule } from 'src/@vex/components/breadcrumbs/breadcrumbs.module';
import { SecondaryToolbarModule } from 'src/@vex/components/secondary-toolbar/secondary-toolbar.module';
import { MatTableModule } from '@angular/material/table';
import { MatSortModule } from '@angular/material/sort';
import { MatChipsModule } from '@angular/material/chips';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTabsModule } from '@angular/material/tabs';
import { TranslateModule } from '@ngx-translate/core';
import { FluxToolbarModule } from 'src/@vex/components/flux-component/flux-toolbar/app.module';
import { FluxUploadImgModule } from 'src/@vex/components/form/upload-img/app.module';
import { MatExpansionModule } from '@angular/material/expansion';
import { FluxLayoutModule } from 'src/@vex/components/flux-component/flux-layout/app.module';
import { CoursesDetailCardModule } from '../../../../@vex/components/flux-component/course-card/app.module';
import { FluxModalModule } from 'src/@vex/components/flux-component/flux-modal/app.module';
import { FluxDisponibilidadModule } from 'src/@vex/components/flux-component/flux-disponibilidad/app.module';
import { AngularEditorModule } from '@kolkov/angular-editor';
import { ComponenteInputModule } from "../../../../@vex/components/form/input/app.module";
import { ComponenteEditorModule } from 'src/@vex/components/form/editor/app.module';
import { ComponenteDatePickerModule } from 'src/@vex/components/form/datepicker/app.module';
import { ComponenteSelectModule } from 'src/@vex/components/form/select/app.module';
import { CoursesModule } from '../courses.module';
import { ComponenteButtonModule } from 'src/@vex/components/form/button/app.module';
import { CourseOpcionComponent } from '../components/opcion/opcion.component';

@NgModule({
  imports: [
    FormsModule,
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatInputModule,
    MatButtonModule,
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
    MatCheckboxModule,
    MatProgressSpinnerModule,
    MatTabsModule,
    TranslateModule,
    FluxToolbarModule,
    FluxUploadImgModule,
    MatExpansionModule,
    FluxLayoutModule,
    CoursesDetailCardModule,
    FluxModalModule,
    FluxDisponibilidadModule,
    AngularEditorModule,
    ComponenteInputModule,
    ComponenteEditorModule,
    ComponenteDatePickerModule,
    FormsModule,
    ComponenteSelectModule,
    ComponenteButtonModule,
    CourseOpcionComponent
  ],
  declarations: [CoursesCreateUpdateComponent],
  exports: [CoursesCreateUpdateComponent]
})
export class CoursesCreateUpdateModule { }
