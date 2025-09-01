import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { AbstractControl, FormGroup, Validators } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';

@Component({
  selector: 'app-form-input',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class ComponenteInputComponent implements OnInit {
  @Input() control!: string
  @Input() value!: string
  @Input() name!: string
  @Input() type: "number" | "text" | "tel" | "email" = "text"
  @Input() form!: FormGroup
  @Input() required: boolean = false
  @Input() Suffix: string = ""
  @Input() Prefix: string = ""
  @Input() max: number = Infinity
  @Input() min: number = 0
  @Input() margin: number = 10
  @Input() readonly: boolean = false

  @Input() errors: string = ""
  @Output() do = new EventEmitter()

  get c(): { [key: string]: AbstractControl } { return this.form.controls; }

  ngOnInit(): void {
    if (this.form && this.control) {
      this.required = this.form.get(this.control)?.hasValidator(Validators.required) || false
    }
  }

  constructor(private TranslateService: TranslateService) { }
  getErrorMessage(controlName: string): string {
    const control = this.c[controlName];
    if (control.errors) {
      for (const errorKey in control.errors) {
        if (control.errors.hasOwnProperty(errorKey)) {
          const params = control.errors[errorKey];
          return this.TranslateService.instant(`errors.${errorKey}`, params);
        }
      }
    }
    return '';
  }
}
