import {Component, OnInit} from '@angular/core';
import {UntypedFormBuilder, Validators} from '@angular/forms';
import {ApiCrudService} from '../../../../service/crud.service';
import {ActivatedRoute, Router} from '@angular/router';
import {MatDialog} from '@angular/material/dialog';
import {MatSnackBar} from '@angular/material/snack-bar';
import {TranslateService} from '@ngx-translate/core';
import moment from 'moment/moment';
import {CoursesService} from '../../../../service/courses.service';

@Component({
  selector: 'vex-course-detail-new',
  templateUrl: './course-detail-new.component.html',
  styleUrls: ['./course-detail-new.component.scss']
})
export class CourseDetailNewComponent implements OnInit{

  user: any;
  settings: any;
  id: number;
  course: any;
  loading: boolean = true;
  shortDescription: string = '';
  courseName: string = '';
  firstCombinationValue: number | null = null;
  description: string = '';
  ageRange: { age_min: number, age_max: number } = { age_min: 0, age_max: 0 };
  shortestDuration: string | null = null;

  constructor(private fb: UntypedFormBuilder, private crudService: ApiCrudService,
              private activatedRoute: ActivatedRoute, private router: Router,
              private dialog: MatDialog, private courseService: CoursesService,
              private snackbar: MatSnackBar, private translateService: TranslateService) {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.settings = typeof this.user.schools[0].settings === 'string' ?
      JSON.parse(this.user.schools[0].settings) : this.user.schools[0].settings;
    this.id = this.activatedRoute.snapshot.params.id;

  }

  ngOnInit(): void {
    this.getCourseData();
  }

  getCourseData() {
    this.crudService.get('/admin/courses/'+this.id,
      ['courseGroups.degree', 'courseGroups.courseDates.courseSubgroups.bookingUsers.client'])
      .subscribe((data) => {
        this.course = data.data;
        this.shortDescription = this.courseService.getShortDescription(this.course);
        this.courseName = this.courseService.getCourseName(this.course);
        this.firstCombinationValue = this.courseService.findFirstCombinationWithValues(this.course.price_range);
        this.description = this.courseService.getDescription(this.course);
        this.ageRange = this.courseService.getAgeRange(this.course.course_dates[0].course_groups);
        this.shortestDuration = this.courseService.getShortestDuration(this.course.course_dates);
        this.loading = false;
      })
  }

  goTo(route: string) {
    this.router.navigate([route]);
  }

}
