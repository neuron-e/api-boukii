export type InstructorStatus = 'active' | 'inactive' | 'on_leave' | 'suspended';
export type CertificationLevel = 'beginner' | 'intermediate' | 'advanced' | 'expert' | 'master';
export type LanguageSkill = 'native' | 'fluent' | 'intermediate' | 'basic';

export interface Instructor {
  id: number;
  name: string;
  email: string;
  phone?: string;
  status: InstructorStatus;
  certification_level: CertificationLevel;
  specialties: string[];
  languages: Array<{
    language: string;
    level: LanguageSkill;
  }>;
  experience_years: number;
  hire_date: Date;
  hourly_rate: number;
  bio?: string;
  photo_url?: string;
  availability?: {
    [key: string]: Array<{
      start_time: string;
      end_time: string;
    }>;
  };
  stats?: {
    total_classes: number;
    average_rating: number;
    total_students: number;
    cancellation_rate: number;
  };
  avg_rating?: number;
  total_reviews?: number;
  is_available_today?: boolean;
  created_at?: Date;
  updated_at?: Date;
}

export interface InstructorFilter {
  search?: string;
  status?: InstructorStatus;
  specialties?: string[];
  certification_level?: CertificationLevel;
  availability?: string; // date string
}

export interface InstructorCreateRequest {
  name: string;
  email: string;
  phone?: string;
  certification_level: CertificationLevel;
  specialties: string[];
  languages: Array<{
    language: string;
    level: LanguageSkill;
  }>;
  experience_years: number;
  hire_date: string;
  hourly_rate: number;
  bio?: string;
  availability?: {
    [key: string]: Array<{
      start_time: string;
      end_time: string;
    }>;
  };
}

export interface InstructorUpdateRequest extends Partial<InstructorCreateRequest> {
  status?: InstructorStatus;
}