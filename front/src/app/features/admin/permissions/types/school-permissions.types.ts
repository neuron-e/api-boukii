export interface School {
  id: number;
  name: string;
  code: string;
  active: boolean;
  logo?: string;
  address?: string;
  adminEmail?: string;
}

export interface UserSchoolRole {
  id?: number;
  userId: number;
  schoolId: number;
  roles: string[];
  permissions?: string[]; // permisos específicos adicionales
  startDate?: string;
  endDate?: string;
  isActive: boolean;
  createdAt?: string;
  updatedAt?: string;
}

export interface PermissionScope {
  resource: string; // 'bookings', 'clients', 'reports', etc.
  actions: string[]; // 'create', 'read', 'update', 'delete', 'manage'
}

export interface UserPermissionMatrix {
  userId: number;
  userName: string;
  userEmail: string;
  schoolPermissions: SchoolPermission[];
}

export interface SchoolPermission {
  school: School;
  roles: string[];
  permissions: PermissionScope[];
  isActive: boolean;
  effectivePermissions: string[]; // computed permissions from roles + specific permissions
}

export interface BulkPermissionAssignment {
  userIds: number[];
  schoolId: number;
  roles: string[];
  permissions?: string[];
  startDate?: string;
  endDate?: string;
}

export interface PermissionAssignmentFilters {
  schoolId?: number;
  userId?: number;
  role?: string;
  status?: 'active' | 'inactive' | 'expired';
  search?: string;
  startDate?: string;
  endDate?: string;
  page?: number;
  perPage?: number;
}

export interface PermissionMatrixResponse {
  data: UserPermissionMatrix[];
  meta: {
    total: number;
    page: number;
    perPage: number;
    lastPage: number;
    totalUsers: number;
    totalSchools: number;
  };
}

export interface SchoolRoleAssignment {
  schoolId: number;
  schoolName: string;
  assignments: UserRoleAssignment[];
}

export interface UserRoleAssignment {
  userId: number;
  userName: string;
  userEmail: string;
  roles: string[];
  permissions: string[];
  isActive: boolean;
  startDate?: string;
  endDate?: string;
}