import { Component, Input, OnInit, Output, EventEmitter } from "@angular/core";
import { FormBuilder, FormGroup, Validators, FormArray } from "@angular/forms";
import { MatDialog } from "@angular/material/dialog";

import { LangService } from "src/service/langService";
import { UtilsService } from "src/service/utils.service";
import { CreateUserDialogComponent } from "../create-user-dialog/create-user-dialog.component";
import * as moment from 'moment/moment';
import { ApiCrudService } from '../../../../../../service/crud.service';

@Component({
  selector: "booking-step-two",
  templateUrl: "./step-two.component.html",
  styleUrls: ["./step-two.component.scss"],
})
export class StepTwoComponent implements OnInit {
  @Input() initialData: any;
  @Input() client: any;
  @Input() allLevels: any;
  @Output() stepCompleted = new EventEmitter<FormGroup>();
  @Output() prevStep = new EventEmitter();
  stepForm: FormGroup;

  selectedUtilizers;
  utilizers;
  userAvatar = "../../../../assets/img/avatar.png";

  constructor(
    private fb: FormBuilder,
    protected langService: LangService,
    protected utilsService: UtilsService,
    public dialog: MatDialog,
    private crudService: ApiCrudService
  ) { }

  ngOnInit(): void {
    this.utilizers = [this.client, ...this.client.utilizers];
    if(this.initialData && this.initialData.length) {
      this.selectedUtilizers = Array.isArray(this.initialData.utilizers) ? this.initialData.utilizers : [this.initialData.utilizers];
    } else {
      this.selectedUtilizers = [];
    }
    this.stepForm = this.fb.group({
      utilizers: this.fb.array(this.selectedUtilizers, Validators.required),
    });
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

  // Manejar el cambio de los checkboxes
  onCheckboxChange(event: any) {
    const checkArray: FormArray = this.stepForm.get("utilizers") as FormArray;

    if (event.checked) {
      // Si el checkbox se selecciona, añadimos el valor al FormArray
      checkArray.push(this.fb.control(event.source.value));
    } else {
      // Si el checkbox se deselecciona, lo eliminamos del FormArray
      let index = checkArray.controls.findIndex(
        (x) => x.value === event.source.value
      );
      if (index !== -1) {
        checkArray.removeAt(index);
      }
    }
  }

  isChecked(utilizer) {
    const currentUtilizers = this.stepForm.get("utilizers").value;
    return !!currentUtilizers.find((u) => u.id === utilizer.id);
  }

  openBookingDialog() {
    const dialogRef = this.dialog.open(CreateUserDialogComponent, {
      width: "670px",
      panelClass: "",
      data: {
        utilizers: this.utilizers,
      },
    });
    dialogRef.afterClosed().subscribe((data: any) => {
      if (data) {
        if (data.action === 'new') {
          const client = {
            email: this.client.email,
            first_name: data.data.name,
            last_name: data.data.surname,
            birth_date: moment(data.data.birthDate).format('DD.MM.yyyy'),
            phone: this.client.phone,
            telephone: this.client.telephone,
            address: this.client.address,
            cp: this.client.cp,
            city: this.client.city,
            province: this.client.province,
            country: this.client.country,
            image: null,
            language1_id: data.data.lenguages[0]?.id,
            language2_id: data.data.lenguages[1]?.id,
            language3_id: data.data.lenguages[2]?.id,
            language4_id: data.data.lenguages[3]?.id,
            language5_id: data.data.lenguages[4]?.id,
            language6_id: data.data.lenguages[5]?.id,
            station_id: this.client.station_id
          }
          this.crudService.create('/clients', client)
            .subscribe((clientCreated: any) => {
              this.crudService.create('/clients-utilizers', {
                client_id: clientCreated.data.id,
                main_id: this.client.id
              }).subscribe((res) => {
                this.utilizers = [this.client, ...this.client.utilizers, client];
              })
            })
        }
      }
    })
  }
}
