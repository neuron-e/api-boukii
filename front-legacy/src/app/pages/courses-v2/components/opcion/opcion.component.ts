import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { FormGroup, FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { TranslateModule } from '@ngx-translate/core';
import { FluxModalModule } from 'src/@vex/components/flux-component/flux-modal/app.module';
import { ComponenteButtonModule } from 'src/@vex/components/form/button/app.module';
import { ComponenteEditorModule } from 'src/@vex/components/form/editor/app.module';
import { ComponenteInputModule } from 'src/@vex/components/form/input/app.module';

@Component({
  selector: 'vex-course-detail-opcion',
  templateUrl: './opcion.component.html',
  styleUrls: ['./opcion.component.scss'],
  standalone: true,
  imports: [
    ReactiveFormsModule,
    CommonModule,
    TranslateModule,
    MatIconModule,
    MatSlideToggleModule,
    ComponenteInputModule,
    ComponenteButtonModule,
    FluxModalModule, MatCheckboxModule,
    MatFormFieldModule, MatDatepickerModule, ComponenteEditorModule, FormsModule,
    MatInputModule, MatButtonModule,


  ],
})
export class CourseOpcionComponent {
  @Input() Form: FormGroup
  @Output() update = new EventEmitter()
  toggleClaimText: boolean = false
  @Input() isCreate: boolean = false;
  sendEmailModal: boolean = false
  minDate = new Date(2000, 1, 1);
  nowDate = new Date()
  maxDate = new Date(2099, 12, 31);

  ngOnInit() {
    // Si el campo claim_text tiene algún valor, activa el toggle
    this.toggleClaimText = !!this.Form?.get('claim_text')?.value;
  }
}
