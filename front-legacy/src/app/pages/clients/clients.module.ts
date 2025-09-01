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
import { ClientsRoutingModule } from './clients-routing.module';
import { ClientsComponent } from './clients.component';
import { ClientsCreateUpdateModule } from './client-create-update/client-create-update.module';
import { ClientDetailModule } from './client-detail/client-detail.module';
import { MatDividerModule } from '@angular/material/divider';
import { MatCardModule } from '@angular/material/card';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { AddClientUserModalModule } from './add-client-user/add-client-user.module';
import { ClientsCreateUpdateModalModule } from './client-create-update-modal/client-create-update-modal.module';
import { TranslateModule } from '@ngx-translate/core';
import { ComponentsCustomModule } from 'src/app/components/components-custom.module';
import { IconComponent } from 'src/@vex/components/icon/app.component';

@NgModule({
  declarations: [ClientsComponent],
  imports: [
    CommonModule,
    LayoutModule,
    PageLayoutModule,
    BreadcrumbsModule,
    RouterModule,
    ClientsRoutingModule,
    SecondaryToolbarModule,
    MatIconModule,
    ChartModule,
    WidgetQuickLineChartModule,
    WidgetQuickValueCenterModule,
    WidgetQuickValueStartModule,
    WidgetLargeGoalChartModule, IconComponent,
    WidgetAssistantModule,
    WidgetLargeChartModule,
    WidgetTableModule,
    ClientsCreateUpdateModule,
    MatDialogModule,
    ComponentsModule,
    MatDividerModule,
    MatCardModule,
    MatSlideToggleModule,
    AddClientUserModalModule,
    ClientsCreateUpdateModalModule,
    TranslateModule,
    ComponentsCustomModule,
    ClientDetailModule,
    ClientsCreateUpdateModule,
    ClientsCreateUpdateModalModule
  ]
})
export class ClientsModule {
}
