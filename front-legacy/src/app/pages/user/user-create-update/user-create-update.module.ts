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
import { BreadcrumbsModule } from 'src/@vex/components/breadcrumbs/breadcrumbs.module';
import { SecondaryToolbarModule } from 'src/@vex/components/secondary-toolbar/secondary-toolbar.module';
import { MatTabsModule } from '@angular/material/tabs';
import { MatFormFieldModule } from '@angular/material/form-field';
import { NgxDropzoneModule } from 'ngx-dropzone';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import {MAT_SNACK_BAR_DEFAULT_OPTIONS, MatSnackBarModule} from '@angular/material/snack-bar';
import { UserCreateUpdateComponent } from './user-create-update.component';
import { TranslateModule } from '@ngx-translate/core';

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
    SecondaryToolbarModule,
    BreadcrumbsModule,
    MatDividerModule,
    MatTabsModule,
    MatFormFieldModule,
    NgxDropzoneModule,
    MatSlideToggleModule,
    MatSnackBarModule,
    TranslateModule
  ],
  providers: [
    {
      provide: MAT_SNACK_BAR_DEFAULT_OPTIONS,
      useValue: { verticalPosition: 'top', horizontalPosition: 'center', duration: 3000 }
    }
  ],
  declarations: [UserCreateUpdateComponent],
  exports: [UserCreateUpdateComponent]
})
export class UserCreateUpdateModule {
}
