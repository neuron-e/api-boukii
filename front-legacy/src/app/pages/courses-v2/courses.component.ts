import { Component } from '@angular/core';
import { TableColumn } from 'src/@vex/interfaces/table-column.interface';
import { CoursesCreateUpdateComponent } from './courses-create-update/courses-create-update.component';
import { ApiCrudService } from 'src/service/crud.service';
import moment from 'moment';
import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import jsPDF from 'jspdf';
import * as QRCode from 'qrcode';
import { CoursesService } from 'src/service/courses.service';


@Component({
  selector: 'vex-courses',
  templateUrl: './courses.component.html',
  styleUrls: ['./courses.component.scss']
})

export class CoursesComponent {

  showDetail: boolean = false;
  detailData: any;
  selectedLevel: any;
  imagePath = 'https://api.boukii.com/storage/icons/collectif_ski2x.png';
  selectedGroup: any = [];
  monitors: any = [];
  groupedByColor = {};
  user: any;
  imageAvatar = '../../../assets/img/avatar.png';
  colorKeys: string[] = [];

  createComponent = CoursesCreateUpdateComponent;
  entity = '/admin/courses';
  deleteEntity = '/courses';
  icon = '../../../assets/img/icons/cursos.svg';

  columns: TableColumn<any>[] = [
    { label: 'Id', property: 'id', type: 'text', visible: true },
    { label: 'type', property: 'course_type', type: 'course_image', visible: true },
    { label: 'course', property: 'translations', type: 'trads', visible: true },
    { label: 'sport', property: 'sport', type: 'sport', visible: true },
    { label: 'FX-FI', property: 'is_flexible', type: 'flexible', visible: true },
    { label: 'dates', property: 'course_dates', type: 'datesCourse', visible: true },
    { label: 'duration', property: 'duration', type: 'duration', visible: true },
    { label: 'price', property: 'price', type: 'price', visible: true },
    { label: 'register', property: 'created_at', type: 'date', visible: true },
    { label: 'bookings', property: 'max_participants', type: 'bookings', visible: true },
    { label: 'status', property: 'active', type: 'light', visible: true },
    { label: 'options', property: 'options', type: 'light', visible: true },
    { label: 'Online', property: 'online', type: 'light', visible: true },
    { label: 'Actions', property: 'actions', type: 'button', visible: true }
  ];


  constructor(private crudService: ApiCrudService, private router: Router, private translateService: TranslateService, private snackbar: MatSnackBar, public courses: CoursesService) {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.getMonitors();
    this.courses.resetcourseFormGroup()
  }

  showDetailEvent(event: any) {
    if (event.showDetail || (!event.showDetail && this.detailData !== null && this.detailData.id !== event.item.id)) {
      this.detailData = event.item;
      this.groupedByColor = {};
      this.colorKeys = [];
      if(this.detailData.course_type ==1) {
        this.crudService.list('/degrees', 1, 10000, 'asc', 'degree_order', '&school_id=' + this.detailData.school_id + '&sport_id=' + this.detailData.sport_id)
          .subscribe((data) => {
            this.detailData.degrees = [];
            data.data.forEach((element: any) => {
              if (element.active) this.detailData.degrees.push({ ...element, Subgrupo: this.getSubGroups(element.id) });
            });
            this.detailData.degrees.forEach((level: any) => {
              if (!this.groupedByColor[level.color]) this.groupedByColor[level.color] = [];
              level.active = false;
              this.detailData.course_dates.forEach((cs: any) => {
                if(cs.course_groups) {
                  cs.course_groups.forEach((group: any) => {
                    if (group.degree_id === level.id) {
                      level.active = true;
                      level.old = true;
                    }
                    level.visible = false;
                  });
                }
              });
              this.groupedByColor[level.color].push(level);
            });
            this.colorKeys = Object.keys(this.groupedByColor);
            //this.crudService.list('/stations', 1, 10000, 'desc', 'id', '&school_id=' + this.detailData.school_id)
            //  .subscribe((st: any) => {
            //    st.data.forEach((element: any) => {
            //      if (element.id === this.detailData.station_id) this.detailData.station = element
            //    });
            //  })
            //this.crudService.list('/booking-users', 1, 10000, 'desc', 'id', '&school_id=' + this.detailData.school_id + '&course_id=' + this.//detailData.id)
            //  .subscribe((bookingUser: any) => {
            //    this.detailData.users = bookingUser.data;
            //  })
            this.courses.settcourseFormGroup(this.detailData)
            this.showDetail = true;
          });
      } else  {
        this.showDetail = true;
        this.courses.settcourseFormGroup(this.detailData, true)
      }

    } else this.showDetail = event.showDetail;
  }

  calculateFormattedDuration(hourStart: string, hourEnd: string): string {
    let start = moment(hourStart.replace(': 00', ''), "HH:mm");
    let end = moment(hourEnd.replace(': 00', ''), "HH:mm");
    let duration = moment.duration(end.diff(start));
    let formattedDuration = "";
    if (duration.hours() > 0) formattedDuration += duration.hours() + "h ";
    if (duration.minutes() > 0) formattedDuration += duration.minutes() + "m";
    return formattedDuration.trim();
  }

  getMonitors() {
    this.crudService.list('/monitors', 1, 10000, 'desc', 'id', '&school_id=' + this.user.schools[0].id).subscribe((monitor) => this.monitors = monitor.data)
  }

  getMonitor(id: number) {
    if (id && id !== null) return this.monitors.find((m) => m.id === id);
  }

  encontrarPrimeraClaveConValor(obj: any): string | null {
    if (obj !== null) {
      for (const clave of Object.keys(obj)) {
        if (obj[clave] !== null && clave !== 'intervalo') return obj[clave];
      } return null;
    }
  }

  encontrarPrimeraCombinacionConValores(data: any, course: any) {
    if (data !== null) {
      for (const intervalo of data) {
        if (Object.keys(intervalo).some(key => key !== 'intervalo' && intervalo[key] !== null)) return intervalo;
      } return null;
    }
  }

  getStudents(levelId: any) {
    let ret = 0;
    this.detailData.booking_users.forEach(element => {
      if (element.degree_id === levelId) ret = ret + 1;
    }); return ret;
  }

  getMaxStudents(levelId: any) {
    let ret = 0;
    this.detailData.course_dates[0].course_groups.forEach(group => {
      if (group.degree_id === levelId) group.course_subgroups.forEach(sb => ret = ret + sb.max_participants);
    });
    return ret;
  }

  getGroups(levelId: any) {
    let ret = 0;
    this.detailData.course_dates.forEach(courseDate => {
      courseDate.course_groups.forEach(group => {
        if (group.degree_id === levelId) ret = group.course_subgroups.length
      });
    }); return ret;
  }

  getSubGroups(levelId: any) {
    let ret = 0;
    this.detailData.course_dates.forEach(courseDate => {
      let find = false;
      if(courseDate.course_groups) {
        courseDate.course_groups.forEach(group => {
          if (group.degree_id === levelId && !find) {
            ret = group.course_subgroups[0]?.max_participants;
            find = true;
          }
        });
      }
    });
    return ret;
  }

  goTo(route: string) {
    this.router.navigate([route]);
  }


  selectGroup(level: any) {
    this.selectedGroup = [];
    this.selectedLevel = level;
    const item = this.detailData.course_dates[0].course_groups.find((g) => g.degree_id === level.id);
    if (item) this.selectedGroup = item.course_subgroups;
    this.selectedGroup.forEach(element => this.crudService.list('/booking-users', 1, 10000, 'asc', 'id', '&course_subgroup_id=' + element.id).subscribe((data) => element.totalUsers = data.data.length));
  }

  hexToRgb(hex: string) {
    const rgb = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return rgb ? {
      r: parseInt(rgb[1], 16),
      g: parseInt(rgb[2], 16),
      b: parseInt(rgb[3], 16)
    } : null;
  }

  exportQR(id: any) {
    this.crudService.get('/admin/clients/course/' + id)
      .subscribe(async (data) => {
        const clientsData = data.data;
        if (clientsData && clientsData.length) {
          const doc = new jsPDF();
          const pageWidth = doc.internal.pageSize.getWidth();
          const colWidth = pageWidth / 2;
          const lineHeight = 6;
          const qrSize = 48;
          let y = 10;
          for (let i = 0; i < clientsData.length; i++) {
            const client = clientsData[i];
            const isLeftColumn = i % 2 === 0;
            const baseX = isLeftColumn ? 10 : colWidth + 6;
            const qrX = baseX + 48;
            let y_text = y;
            const maxWidthText = 48;
            doc.setTextColor(70, 70, 70);
            doc.setFontSize(16);
            doc.setFont('helvetica', 'bold');
            let lines = doc.splitTextToSize(`${client.client?.first_name} ${client.client?.last_name}`, maxWidthText);
            doc.text(lines, baseX, y_text);
            y_text += (lines.length + 0.4) * lineHeight;
            if (client.client?.phone || client.client?.telephone) {
              let clientPhone = '';
              if (client.client?.phone) { clientPhone = client.client.phone; }
              else { clientPhone = client.client.telephone; }
              doc.setFontSize(14);
              doc.setFont('helvetica', 'normal');
              lines = doc.splitTextToSize(`${clientPhone}`, maxWidthText);
              doc.text(lines, baseX, y_text);
              y_text += lines.length * lineHeight;
            }
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            lines = doc.splitTextToSize(`${client.course?.name}`, maxWidthText);
            doc.text(lines, baseX, y_text);
            y_text += (lines.length * lineHeight) - 2;
            if (client.monitor) {
              doc.setFontSize(8);
              lines = doc.splitTextToSize(`Professeur - niveau`, maxWidthText);
              doc.text(lines, baseX, y_text);
              y_text += (lines.length * lineHeight) - 2;
              doc.setFontSize(11);
              doc.setFont('helvetica', 'bold');
              lines = doc.splitTextToSize(`${client.monitor?.first_name} ${client.monitor?.last_name}`, maxWidthText);
              doc.text(lines, baseX, y_text);
              y_text += (lines.length * lineHeight) + 3;
            }
            else y_text += 6;
            if (client.degree) {
              const rgbColor = this.hexToRgb(client.degree.color);
              doc.setFillColor(rgbColor.r, rgbColor.g, rgbColor.b);
              doc.setTextColor(255, 255, 255);
              doc.setFontSize(9);
              doc.setFont('helvetica', 'normal');
              const text = `${client.degree?.annotation} - ${client.degree?.name}`;
              lines = doc.splitTextToSize(text, maxWidthText);
              const textBoxHeight = (lines.length + 0.5) * lineHeight;
              doc.rect(baseX, y_text - lineHeight, maxWidthText, textBoxHeight, 'F');
              doc.text(lines, baseX + 1.5, y_text);
              doc.setTextColor(70, 70, 70);
              y_text += textBoxHeight;
            }
            const qrData = await QRCode.toDataURL(client.client.id.toString());
            doc.addImage(qrData, 'JPEG', qrX, y - 10, qrSize, qrSize);
            if (!isLeftColumn || i === clientsData.length - 1) y += qrSize + lineHeight * 4;
            if (y >= doc.internal.pageSize.getHeight() - 20) {
              doc.addPage();
              y = 10;
            }
          }
          doc.save('clients.pdf');
        }
        else this.snackbar.open(this.translateService.instant('course_without_clients'), 'OK', { duration: 3000 });
      })
  }

  getActiveBookingUsers(bookingUsers: any) {
    let ret = 0;
    bookingUsers.forEach(element => {
      if (element.status === 1) ret = ret + 1;
    }); return ret;
  }
}
