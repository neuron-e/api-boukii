import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { TranslatePipe } from '@shared/pipes/translate.pipe';

interface Course {
  name: string;
  sport: string;
  type: 'collective' | 'private';
  level: string;
  status: 'active' | 'finished' | 'ongoing';
  description: string;
}

@Component({
  selector: 'app-courses-list-page',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, TranslatePipe],
  templateUrl: './courses-list.page.html',
  styleUrls: ['./courses-list.page.scss'],
})
export class CoursesListPageComponent {
  private readonly fb = inject(FormBuilder);

  courses: Course[] = [
    {
      name: 'Ski Basics',
      sport: 'Ski',
      type: 'collective',
      level: 'Beginner',
      status: 'active',
      description: 'Introductory group lessons for skiing',
    },
    {
      name: 'Advanced Ski',
      sport: 'Ski',
      type: 'private',
      level: 'Advanced',
      status: 'ongoing',
      description: 'One-on-one coaching for advanced skiers',
    },
    {
      name: 'Snowboard Fun',
      sport: 'Snowboard',
      type: 'collective',
      level: 'Intermediate',
      status: 'finished',
      description: 'Group snowboarding sessions for intermediate riders',
    },
    {
      name: 'Kids Ski Camp',
      sport: 'Ski',
      type: 'collective',
      level: 'Beginner',
      status: 'ongoing',
      description: 'Ski camp tailored for kids',
    },
    {
      name: 'Freestyle Snowboard',
      sport: 'Snowboard',
      type: 'private',
      level: 'Advanced',
      status: 'active',
      description: 'Private freestyle training sessions',
    },
    {
      name: 'Racing Ski',
      sport: 'Ski',
      type: 'collective',
      level: 'Expert',
      status: 'finished',
      description: 'High-performance racing course',
    },
  ];

  filteredCourses: Course[] = [...this.courses];

  statusTabs: Array<'active' | 'finished' | 'ongoing' | 'all'> = [
    'active',
    'finished',
    'ongoing',
    'all',
  ];
  selectedTab: 'active' | 'finished' | 'ongoing' | 'all' = 'active';

  filtersForm = this.fb.group({
    type: [''],
    sport: [''],
    search: [''],
  });

  selectedCourse: Course | null = null;

  constructor() {
    this.filtersForm.valueChanges.subscribe(() => this.applyFilters());
    this.applyFilters();
  }

  selectTab(tab: 'active' | 'finished' | 'ongoing' | 'all'): void {
    this.selectedTab = tab;
    this.applyFilters();
  }

  openCourse(course: Course): void {
    this.selectedCourse = course;
  }

  closeCourse(): void {
    this.selectedCourse = null;
  }

  private applyFilters(): void {
    const { type, sport, search } = this.filtersForm.value;
    this.filteredCourses = this.courses.filter((course) => {
      const matchesTab = this.selectedTab === 'all' || course.status === this.selectedTab;
      const matchesType = !type || course.type === type;
      const matchesSport =
        !sport || course.sport.toLowerCase().includes((sport as string).toLowerCase());
      const matchesSearch =
        !search || course.name.toLowerCase().includes((search as string).toLowerCase());
      return matchesTab && matchesType && matchesSport && matchesSearch;
    });
  }
}
