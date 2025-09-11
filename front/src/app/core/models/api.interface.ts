export interface ApiResponse<T = any> {
  data: T;
  message?: string;
  status: 'success' | 'error';
  code?: number;
}

export interface PaginatedResponse<T = any> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
  };
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
}

export interface ErrorResponse {
  message: string;
  errors?: { [key: string]: string[] };
  status: 'error';
  code: number;
}