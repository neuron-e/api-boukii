import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CourseDetailComponent } from './course-detail.component';
import { BreadcrumbsModule } from '../../../../@vex/components/breadcrumbs/breadcrumbs.module';
import { SecondaryToolbarModule } from '../../../../@vex/components/secondary-toolbar/secondary-toolbar.module';
import { MatTabsModule } from '@angular/material/tabs';
import { TranslateModule } from '@ngx-translate/core';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { CoursesDetailCardModule } from 'src/@vex/components/flux-component/course-card/app.module';
import { MatIconModule } from '@angular/material/icon';
import { FluxModalModule } from 'src/@vex/components/flux-component/flux-modal/app.module';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { AngularEditorModule } from '@kolkov/angular-editor';
import { MatButtonModule } from '@angular/material/button';
import { CoursesDetailCardNivelModule } from 'src/@vex/components/flux-component/course-nivel/app.module';
import { ComponenteEditorModule } from "../../../../@vex/components/form/editor/app.module";
import { PageLayoutModule } from 'src/@vex/components/page-layout/page-layout.module';
import { ComponentsModule } from 'src/@vex/components/components.module';
import { ComponenteButtonModule } from 'src/@vex/components/form/button/app.module';
import { ComponenteInputModule } from "../../../../@vex/components/form/input/app.module";
import { CourseOpcionComponent } from '../components/opcion/opcion.component';
import { BookingsModule } from '../../bookings-v2/bookings.module';
import {MatOptionModule} from '@angular/material/core';
import {MatSelectModule} from '@angular/material/select';

@NgModule({
  declarations: [CourseDetailComponent],
    imports: [
        CommonModule,
        BreadcrumbsModule, ReactiveFormsModule,
        SecondaryToolbarModule,
        MatTabsModule,
        TranslateModule,
        MatProgressSpinnerModule,
        FormsModule,
        MatCardModule,
        MatFormFieldModule,
        MatInputModule,
        MatSlideToggleModule,
        CoursesDetailCardModule, ComponentsModule,
        PageLayoutModule,
        MatIconModule, FluxModalModule, MatCheckboxModule, MatDatepickerModule, AngularEditorModule, MatButtonModule, CoursesDetailCardNivelModule,
        ComponenteEditorModule,
        ComponenteButtonModule,
        ComponenteInputModule,
        CourseOpcionComponent,
        BookingsModule, MatOptionModule, MatSelectModule
    ]
})
export class CourseDetailModule { }
