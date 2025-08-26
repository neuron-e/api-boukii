// Authentication V5 Models
// Complete TypeScript interfaces for V5 API authentication

export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
}

export interface ResetPasswordRequest {
  email: string;
}

export interface Role {
  id: number;
  name: string;
  slug: string;
  description?: string;
  permissions: Permission[];
}

export interface Permission {
  id: number;
  name: string;
  slug: string;
  description?: string;
  resource: string;
  action: string;
}

export interface UserRole {
  user_id: number;
  role_id: number;
  school_id: number;
  granted_at: string;
  granted_by: number;
  role: Role;
}

export interface User {
  id: number;
  name: string;
  email: string;
  type?: string;
  role?: string;
  email_verified_at?: string;
  is_active: boolean;
  last_login_at?: string;
  created_at: string;
  updated_at: string;
  roles?: UserRole[];
}

export interface School {
  id: number;
  name: string;
  slug: string;
  description?: string;
  logo?: string;
  active: boolean;
  user_role?: string;
  can_administer?: boolean;
  created_at: string;
  updated_at: string;
  seasons: Season[];
}

export interface Season {
  id: number;
  school_id: number;
  name: string;
  slug: string;
  start_date: string;
  end_date: string;
  status: 'active' | 'inactive' | 'upcoming' | 'completed';
  is_current: boolean;
  created_at: string;
  updated_at: string;
}

export interface LoginResponse {
  success: boolean;
  message: string;
  data?: {
    token: string;
    user: User;
    schools: School[];
  };
}

export interface RegisterResponse {
  success: boolean;
  message: string;
  data?: {
    user: User;
    token?: string; // Optional, some APIs auto-login after register
  };
}

export interface MeResponse {
  success: boolean;
  message: string;
  data?: {
    user: User;
    schools: School[];
    permissions: string[];
  };
}

export interface SchoolPermission {
  school_id: number;
  user_roles: UserRole[];
  effective_permissions: string[];
  can_manage: boolean;
  can_administrate: boolean;
}

export interface AuthContext {
  school_id: number;
  season_id: number;
  permissions: string[];
  user_school_permissions: SchoolPermission;
  effective_roles: Role[];
}

export interface ApiResponse<T = unknown> {
  success: boolean;
  message: string;
  data?: T;
  meta?: {
    pagination?: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
    [key: string]: unknown;
  };
}

// RFC 7807 Problem Details for HTTP APIs
export interface ProblemDetails {
  type?: string;
  title: string;
  status: number;
  detail?: string;
  instance?: string;
  code?: string;
  errors?: { [key: string]: string[] };
  userMessage?: string;
  timestamp?: string;
  request_id?: string;
}