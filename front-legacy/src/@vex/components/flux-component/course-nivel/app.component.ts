import { Component, EventEmitter, Input, Output } from '@angular/core';
import { UntypedFormGroup } from '@angular/forms';

@Component({
  selector: 'vex-course-detail-nivel',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss'],
})
export class CourseDetailCardNivelComponent {

  @Input() courseFormGroup!: UntypedFormGroup
  @Input() checkbox: boolean = false
  @Input() selectedSubgroup: any;
  @Output() changeMonitor = new EventEmitter<any>()

  today: Date = new Date()

  find = (array: any[], key: string, value: string) => array.find((a: any) => a[key] === value)
  DateISO = (value: string) => value ? new Date(value).toLocaleString().split(" ")[0].replace("/", ".").replace("/", ".") : ''
  DateDiff = (value1: string, value2: string): number => Math.round((new Date(value2).getTime() - new Date(value1).getTime()) / 1000 / 60 / 60 / 24)
  Date = (v: string): Date => new Date(v)
  numUsersArray(value: number): number[] {
    return Array.from({ length: value }, (_, i) => i);
  }
  findBookingUsers(bookingUsers: any[], courseDates: any[], degreeId: number): number {
    if (!bookingUsers || !courseDates) return 0;
    if(!this.courseFormGroup.value.is_flexible) {
      return bookingUsers.filter(user => {
       return courseDates[0].course_groups.some(group =>
          group.degree_id === degreeId && group.id === user.course_group_id)}).length;
    } else {
      return bookingUsers.filter(user => {
        return courseDates.some(date =>
          date.course_groups.some(group => group.degree_id === degreeId && group.id === user.course_group_id)
        );
      }).length;
    }
  }

  countGroups(courseDates: any[], degreeId: number): number {
    return Math.round(courseDates
      .flatMap(date => date.course_groups || []) // Obtener todos los grupos
      .filter(group => group.degree_id === degreeId).length / courseDates.length); // Filtrar por degree_id
  }

  countSubgroups(courseDates: any[], degreeId: number): number {
    return Math.round(
      courseDates
        .flatMap(date => date.course_groups || []) // Obtener todos los grupos
        .filter(group => group.degree_id === degreeId) // Filtrar los grupos por degree_id
        .flatMap(group => group.course_subgroups || []) // Extraer todos los subgrupos dentro de los grupos filtrados
        .filter(subgroup => subgroup.degree_id === degreeId).length / courseDates.length // Filtrar por degree_id y dividir por la cantidad de courseDates
    );
  }



}
