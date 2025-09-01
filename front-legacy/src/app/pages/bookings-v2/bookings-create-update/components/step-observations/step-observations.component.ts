import {Component, OnInit, Output, EventEmitter, Input, ElementRef, ViewChild, AfterViewInit} from '@angular/core';
import { FormBuilder, FormGroup, Validators } from "@angular/forms";

@Component({
  selector: "booking-step-observations",
  templateUrl: "./step-observations.component.html",
  styleUrls: ["./step-observations.component.scss"],
})
export class StepObservationsComponent implements OnInit, AfterViewInit {
  @Input() initialData: any;
  @Output() stepCompleted = new EventEmitter<FormGroup>();
  @Output() saveAndCreateNew = new EventEmitter<FormGroup>();
  @Output() prevStep = new EventEmitter();
  stepForm: FormGroup;
  @ViewChild('clientObsField') clientObsField: ElementRef;

  constructor(private fb: FormBuilder) {}

  ngOnInit(): void {
    this.stepForm = this.fb.group({
      clientObs: [this.initialData ? this.initialData.clientObs : ''],
      schoolObs: [this.initialData ? this.initialData.schoolObs : ''],
    });
    this.setFocusOnClientObs();
  }

  ngAfterViewInit(): void {
    this.setFocusOnClientObs();
  }

  isFormValid() {
    return this.stepForm.valid;
  }

  handlePrevStep() {
    this.prevStep.emit();
  }

  completeStep() {
    if (this.isFormValid()) {
      this.stepCompleted.emit(this.stepForm);
    }
  }

  completeAndReset() {
    if (this.isFormValid()) {
      // Emitir el evento específico para guardar y crear nuevo
      this.saveAndCreateNew.emit(this.stepForm);
    }
  }


  private setFocusOnClientObs(): void {
    if (this.clientObsField) {
      this.clientObsField.nativeElement.focus();
    }
  }
}
