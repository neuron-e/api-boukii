import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { LayoutModule } from '../../../@vex/layout/layout.module';
import { PageLayoutModule } from 'src/@vex/components/page-layout/page-layout.module';
import { BreadcrumbsModule } from 'src/@vex/components/breadcrumbs/breadcrumbs.module';
import { RouterModule } from '@angular/router';
import { SecondaryToolbarModule } from 'src/@vex/components/secondary-toolbar/secondary-toolbar.module';
import { MatIconModule } from '@angular/material/icon';
import { WidgetLargeGoalChartModule } from 'src/@vex/components/widgets/widget-large-goal-chart/widget-large-goal-chart.module';
import { WidgetQuickValueStartModule } from 'src/@vex/components/widgets/widget-quick-value-start/widget-quick-value-start.module';
import { WidgetQuickValueCenterModule } from 'src/@vex/components/widgets/widget-quick-value-center/widget-quick-value-center.module';
import { WidgetQuickLineChartModule } from 'src/@vex/components/widgets/widget-quick-line-chart/widget-quick-line-chart.module';
import { ChartModule } from 'src/@vex/components/chart/chart.module';
import { WidgetAssistantModule } from 'src/@vex/components/widgets/widget-assistant/widget-assistant.module';
import { WidgetLargeChartModule } from 'src/@vex/components/widgets/widget-large-chart/widget-large-chart.module';
import { WidgetTableModule } from 'src/@vex/components/widgets/widget-table/widget-table.module';
import { ComponentsModule } from 'src/@vex/components/components.module';
import { MatDialogModule } from '@angular/material/dialog';
import { CoursesComponent } from './courses.component';
import { CoursesRoutingModule } from './courses-routing.module';
import { CoursesCreateUpdateModule } from './courses-create-update/courses-create-update.module';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatDividerModule } from '@angular/material/divider';
import { MatCardModule } from '@angular/material/card';
import { CoursesDetailModalModule } from './pendiente/course-detail-modal/course-detail-modal.module';
import { TranslateModule } from '@ngx-translate/core';
import { CoursesCreateUpdateModalModule } from './pendiente/courses-create-update-modal/courses-create-update-modal.module';
import { CourseDetailModule } from './course-detail/course-detail.module';
import { CoursesDetailCardModule } from 'src/@vex/components/flux-component/course-card/app.module';
import { ComponenteInputModule } from 'src/@vex/components/form/input/app.module';
import { ComponenteEditorModule } from 'src/@vex/components/form/editor/app.module';
import { ComponenteSelectModule } from 'src/@vex/components/form/select/app.module';
import { CourseOpcionComponent } from './components/opcion/opcion.component';
import { CourseStatisticsComponent } from './course-statistics/course-statistics.component';
import {MatTooltipModule} from '@angular/material/tooltip';
import {MatProgressSpinnerModule} from '@angular/material/progress-spinner';

@NgModule({
  declarations: [CoursesComponent, CourseStatisticsComponent],
  exports: [
    CourseStatisticsComponent
  ],
  imports: [
    CommonModule,
    LayoutModule,
    PageLayoutModule,
    BreadcrumbsModule,
    RouterModule,
    CoursesRoutingModule,
    SecondaryToolbarModule,
    MatIconModule,
    ChartModule,
    WidgetQuickLineChartModule,
    WidgetQuickValueCenterModule,
    WidgetQuickValueStartModule,
    WidgetLargeGoalChartModule,
    WidgetAssistantModule,
    WidgetLargeChartModule,
    WidgetTableModule,
    CoursesCreateUpdateModule,
    CourseDetailModule,
    CoursesDetailModalModule,
    CoursesCreateUpdateModalModule,
    MatDialogModule,
    ComponentsModule,
    MatSlideToggleModule,
    MatDividerModule,
    MatCardModule,
    TranslateModule,
    CoursesDetailCardModule,
    ComponenteInputModule,
    ComponenteEditorModule,
    ComponenteSelectModule,
    CourseOpcionComponent,
    MatTooltipModule,
    MatProgressSpinnerModule,
  ]
})
export class CoursesModule { }
