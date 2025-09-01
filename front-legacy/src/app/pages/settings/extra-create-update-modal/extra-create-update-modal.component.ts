import { Component, Inject, OnInit } from '@angular/core';
import { UntypedFormBuilder, UntypedFormGroup, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ApiCrudService } from 'src/service/crud.service';

@Component({
  selector: 'vex-extra-create-update-modal',
  templateUrl: './extra-create-update-modal.component.html',
  styleUrls: ['./extra-create-update-modal.component.scss']
})
export class ExtraCreateUpdateModalComponent implements OnInit {

  loading: boolean = true;
  form: UntypedFormGroup;
  mode: 'create' | 'update' = 'create';

  constructor(@Inject(MAT_DIALOG_DATA) public defaults: any, private dialogRef: MatDialogRef<any>, private fb: UntypedFormBuilder,
    private crudService: ApiCrudService, private snackbar: MatSnackBar) {

  }

  ngOnInit(): void {

    if(this.defaults !== null && this.defaults.id) {
      this.mode = 'update';
    } else {

      this.mode = 'create';
    }

    this.form = this.fb.group({
      product: [this.defaults.product, Validators.required],
      name: [this.defaults?.name, Validators.required],
      price: [this.defaults?.price, Validators.required],
      tva: [this.defaults?.tva, Validators.required],
      status: [this.defaults?.status, Validators.required]
    })

    this.loading = false;
  }

  save() {
    this.dialogRef.close(this.defaults);

  }

  isCreateMode() {
    return this.mode === 'create';
  }

  isUpdateMode() {
    return this.mode === 'update';
  }

}
