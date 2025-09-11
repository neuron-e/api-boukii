import { Injectable, inject } from '@angular/core';
import { Observable, BehaviorSubject } from 'rxjs';
import { HttpClient } from '@angular/common/http';

export interface Course {
  id: number;
  name: string;
  description?: string;
  level: 'beginner' | 'intermediate' | 'advanced' | 'expert';
  price: number;
  duration_minutes: number;
  max_participants: number;
  created_at?: Date;
  updated_at?: Date;
}

export interface CourseFilter {
  search?: string;
  level?: string;
  status?: string;
}

@Injectable({
  providedIn: 'root'
})
export class CoursesService {
  private http = inject(HttpClient);
  private apiUrl = '/courses';
  
  private coursesSubject = new BehaviorSubject<Course[]>([]);
  public courses$ = this.coursesSubject.asObservable();

  getCourses(filter?: CourseFilter): Observable<Course[]> {
    const params: any = {};
    
    if (filter) {
      if (filter.search) params.search = filter.search;
      if (filter.level) params.level = filter.level;
      if (filter.status) params.status = filter.status;
    }

    return this.http.get<Course[]>(this.apiUrl, { params });
  }

  getCourse(id: number): Observable<Course> {
    return this.http.get<Course>(`${this.apiUrl}/${id}`);
  }

  createCourse(courseData: Partial<Course>): Observable<Course> {
    return this.http.post<Course>(this.apiUrl, courseData);
  }

  updateCourse(id: number, courseData: Partial<Course>): Observable<Course> {
    return this.http.put<Course>(`${this.apiUrl}/${id}`, courseData);
  }

  deleteCourse(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }
}