import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { LoginRoutingModule } from './login-routing.module';
import { LoginComponent } from './login.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';
import {MAT_SNACK_BAR_DEFAULT_OPTIONS, MatSnackBarModule} from '@angular/material/snack-bar';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { TranslateModule } from '@ngx-translate/core';
import {MatMenuModule} from '@angular/material/menu';
import {ToolbarUserModule} from '../../../@vex/layout/toolbar/toolbar-user/toolbar-user.module';


@NgModule({
  declarations: [LoginComponent],
  providers: [
    {
      provide: MAT_SNACK_BAR_DEFAULT_OPTIONS,
      useValue: { verticalPosition: 'top', horizontalPosition: 'center', duration: 3000 }
    }
  ],
    imports: [
        CommonModule,
        LoginRoutingModule,
        ReactiveFormsModule,
        MatInputModule,
        MatIconModule,
        MatSnackBarModule,
        FormsModule,
        MatTooltipModule,
        MatButtonModule,
        MatCheckboxModule,
        TranslateModule,
        MatMenuModule,
        ToolbarUserModule
    ]
})
export class LoginModule {
}
