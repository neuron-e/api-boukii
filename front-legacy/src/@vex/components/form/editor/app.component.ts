import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { AbstractControl, FormGroup, Validators } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';
import {AngularEditorConfig} from '@kolkov/angular-editor';

@Component({
  selector: 'app-form-editor',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class ComponenteInputComponent implements OnInit {
  @Input() control!: string
  @Input() name!: string
  @Input() value!: string
  @Input() type: "number" | "text" | "date" | "tel" | "email" = "text"
  @Input() form!: FormGroup
  @Input() required: boolean = false
  @Output() do = new EventEmitter()
  @Output() valueChange = new EventEmitter<string>()


  config: AngularEditorConfig = {
    editable: true,
    spellcheck: true,

    height: '80rem',
    minHeight: '15rem',
    placeholder: 'Enter text here...',
    translate: 'no',
    defaultParagraphSeparator: 'p',
    customClasses: [
      {
        name: "quote",
        class: "quote",
      },
      {
        name: 'redText',
        class: 'redText'
      },
      {
        name: "titleText",
        class: "titleText",
        tag: "h1",
      },
    ]
  };

  get c(): { [key: string]: AbstractControl } { return this.form.controls; }

  constructor(private translateService: TranslateService,) { }

  ngOnInit(): void {
    if (this.form && this.control) {
      this.required = this.form.get(this.control)?.hasValidator(Validators.required) || false
    }
  }

  onEditorChange(event: any) {
    this.valueChange.emit(event);
  }

  getErrorMessage(controlName: string): string {
    const control = this.c[controlName];
    if (control.errors) {
      for (const errorKey in control.errors) {
        if (control.errors.hasOwnProperty(errorKey)) {
          const params = control.errors[errorKey];
          return this.translateService.instant(`errors.${errorKey}`, params);
        }
      }
    }
    return '';
  }

}
