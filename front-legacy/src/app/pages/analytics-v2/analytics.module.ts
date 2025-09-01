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
import { AnalyticsComponent } from './analytics.component';
import { AnalyticsRoutingModule } from './analytics-routing.module';
import { MatTabsModule } from '@angular/material/tabs';
import { TranslateModule } from '@ngx-translate/core';
import { MatCardModule } from '@angular/material/card';
import { MatDividerModule } from '@angular/material/divider';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatOptionModule } from '@angular/material/core';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import {MatCheckboxModule} from '@angular/material/checkbox';
import {MatProgressSpinnerModule} from '@angular/material/progress-spinner';
import {MatTableModule} from '@angular/material/table';
import {MatProgressBarModule} from '@angular/material/progress-bar';
import {MatMenuModule} from '@angular/material/menu';
import { KpisCardComponent } from './kpis-card/kpis-card.component';
import { FiltersComponent } from './filters/filters.component';
import {MatChipsModule} from '@angular/material/chips';
import {MatTooltipModule} from '@angular/material/tooltip';
import { MonitorsLegacyComponent } from './monitors-legacy/monitors-legacy.component';
import { BookingListModalComponent } from './booking-list-modal/booking-list-modal.component';
import {MatPaginatorModule} from '@angular/material/paginator';
import { CourseStatisticsModalComponent } from './course-statistics-modal/course-statistics-modal.component';
import {CoursesModule} from '../courses-v2/courses.module';

@NgModule({
  declarations: [AnalyticsComponent, KpisCardComponent, FiltersComponent, MonitorsLegacyComponent, BookingListModalComponent, CourseStatisticsModalComponent],
  imports: [
    CommonModule,
    LayoutModule,
    PageLayoutModule,
    BreadcrumbsModule,
    RouterModule,
    AnalyticsRoutingModule,
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
    //LanguageCreateUpdateModule,
    MatDialogModule,
    ComponentsModule,
    MatTabsModule,
    TranslateModule,
    MatCardModule,
    MatDividerModule,
    MatDatepickerModule,
    MatFormFieldModule,
    MatInputModule,
    ReactiveFormsModule,
    FormsModule,
    MatOptionModule,
    MatSelectModule,
    MatButtonModule,
    MatCheckboxModule,
    MatProgressSpinnerModule,
    MatTableModule,
    MatProgressBarModule,
    MatMenuModule,
    MatChipsModule,
    MatTooltipModule,
    MatPaginatorModule,
    CoursesModule
  ]
})
export class AnalyticsModule {
}
