import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FluxUploadImgComponent } from './app.component';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { ReactiveFormsModule } from '@angular/forms';
import { SecondaryToolbarModule } from '../../secondary-toolbar/secondary-toolbar.module';
import { TranslateModule } from '@ngx-translate/core';
import { ImageCropperComponent } from 'ngx-image-cropper';
import { ComponenteButtonModule } from "../button/app.module";


@NgModule({
  declarations: [FluxUploadImgComponent],
  imports: [
    CommonModule,
    MatButtonModule,
    MatIconModule,
    ReactiveFormsModule,
    SecondaryToolbarModule,
    TranslateModule,
    ImageCropperComponent,
    ComponenteButtonModule
],
  exports: [FluxUploadImgComponent]
})
export class FluxUploadImgModule { }
