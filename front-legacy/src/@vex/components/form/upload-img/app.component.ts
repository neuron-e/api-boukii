import { Component, ElementRef, EventEmitter, Input, Output, ViewChild, } from '@angular/core';

@Component({
  selector: 'vex-flux-upload-img',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss']
})
export class FluxUploadImgComponent {
  @Input() imagePreviewUrl: any = ""
  @Input() width: number = 400
  @Input() height: number = 290
  @Input() edit: boolean = false

  @Input() size: number = 1
  @Input() format: string = "PNG, JPG"
  @Input() roundCropper: boolean = false

  @Output() upload = new EventEmitter();

  onFileChanged(event: Event) {
    this.imageChangedEvent = event
    const file = (event.target as HTMLInputElement).files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = () => {
        if (!this.edit) this.imagePreviewUrl = reader.result;
        this.upload.emit(reader.result)
        reader.onload = null;
      };
      reader.readAsDataURL(file);
    }
  }
  @ViewChild('fileInput') fileInput: ElementRef<HTMLInputElement>;

  triggerFileInput() {
    this.fileInput.nativeElement.click();
  }

  imageChangedEvent: any = null;
  archivo!: File;
  imageCropped(event: any) {
    this.blobToBase64(event.blob)
      .then((base64String) => {
        this.upload.emit(base64String)
      })
      .catch((error) => {
        console.error('Error converting Blob to Base64:', error);
      });
  }

  blobToBase64(blob: Blob): Promise<string> {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onloadend = function () { resolve(reader.result as string); };
      reader.onerror = function (error) { reject(error); };
      reader.readAsDataURL(blob);
    });
  }

}
