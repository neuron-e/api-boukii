import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';
import {MAT_SNACK_BAR_DEFAULT_OPTIONS, MatSnackBarModule} from '@angular/material/snack-bar';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { RecoverPasswordComponent } from './recover-password.component';
import { RecoverPasswordRoutingModule } from './recover-password-routing.module';
import { TranslateModule } from '@ngx-translate/core';
import {MatMenuModule} from '@angular/material/menu';


@NgModule({
  declarations: [RecoverPasswordComponent],
  providers: [
    {
      provide: MAT_SNACK_BAR_DEFAULT_OPTIONS,
      useValue: { verticalPosition: 'top', horizontalPosition: 'center', duration: 3000 }
    }
  ],
    imports: [
        CommonModule,
        ReactiveFormsModule,
        MatInputModule,
        MatIconModule,
        MatSnackBarModule,
        FormsModule,
        MatTooltipModule,
        MatButtonModule,
        MatCheckboxModule,
        RecoverPasswordRoutingModule,
        TranslateModule,
        MatMenuModule
    ]
})
export class RecoverPasswordModule {
}
