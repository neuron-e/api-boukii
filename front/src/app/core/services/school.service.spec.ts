import { TestBed } from '@angular/core/testing';
import { describe, it, expect, beforeEach } from '@jest/globals';
import { SchoolService, SchoolsResponse, GetSchoolsParams } from './school.service';
import { ApiService } from './api.service';
import { School } from './context.service';

describe('SchoolService', () => {
  let service: SchoolService;
  let mockApiHttp: jest.Mocked<ApiService>;

  const mockSchool: School = {
    id: 1,
    name: 'Test School',
    slug: 'test-school',
    active: true,
    createdAt: '2024-01-01T00:00:00Z',
    updatedAt: '2024-03-01T00:00:00Z'
  };

  const mockSchoolsResponse: SchoolsResponse = {
    data: [mockSchool],
    meta: {
      total: 1,
      page: 1,
      perPage: 20,
      lastPage: 1,
      from: 1,
      to: 1
    }
  };

  beforeEach(() => {
    const apiHttpSpy: jest.Mocked<ApiService> = {
      get: jest.fn()
    } as any;

    TestBed.configureTestingModule({
      providers: [
        SchoolService,
        { provide: ApiService, useValue: apiHttpSpy }
      ]
    });

    service = TestBed.inject(SchoolService);
    mockApiHttp = TestBed.inject(ApiService) as jest.Mocked<ApiService>;
  });

  describe('Service Creation', () => {
    it('should be created', () => {
      expect(service).toBeTruthy();
    });
  });

  describe('getMySchools', () => {
    beforeEach(() => {
      mockApiHttp.get.mockResolvedValue(mockSchoolsResponse as any);
    });

    it('should call API with default parameters', () => {
      service.getMySchools().subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/me/schools', {
        page: 1,
        perPage: 20,
        active: true,
        orderBy: 'name',
        orderDirection: 'asc'
      });
    });

    it('should call API with custom parameters', () => {
      const params: GetSchoolsParams = {
        page: 2,
        perPage: 10,
        search: 'test',
        active: false,
        orderBy: 'createdAt',
        orderDirection: 'desc'
      };

      service.getMySchools(params).subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/me/schools', {
        page: 2,
        perPage: 10,
        search: 'test',
        active: false,
        orderBy: 'createdAt',
        orderDirection: 'desc'
      });
    });

    it('should omit empty search parameter', () => {
      const params: GetSchoolsParams = {
        search: '',
        page: 1
      };

      service.getMySchools(params).subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/me/schools', {
        page: 1,
        perPage: 20,
        active: true,
        orderBy: 'name',
        orderDirection: 'asc'
      });
    });

    it('should handle partial parameters', () => {
      const params: GetSchoolsParams = {
        search: 'swimming',
        perPage: 5
      };

      service.getMySchools(params).subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/me/schools', {
        page: 1,
        perPage: 5,
        search: 'swimming',
        active: true,
        orderBy: 'name',
        orderDirection: 'asc'
      });
    });

    it('should return schools response', (done) => {
      service.getMySchools().subscribe(response => {
        expect(response).toEqual(mockSchoolsResponse);
        done();
      });
    });

    it('should handle undefined parameters', () => {
      const params: GetSchoolsParams = {
        page: undefined,
        search: undefined
      };

      service.getMySchools(params).subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/me/schools', {
        perPage: 20,
        active: true,
        orderBy: 'name',
        orderDirection: 'asc'
      });
    });
  });

  describe('getSchoolById', () => {
    beforeEach(() => {
      mockApiHttp.get.mockResolvedValue(mockSchool as any);
    });

    it('should fetch a school via /schools/{id}', () => {
      service.getSchoolById(123).subscribe();

      const expectedPath = '/schools/123';
      expect(mockApiHttp.get).toHaveBeenCalledWith(expectedPath);
    });

    it('should return school data', (done) => {
      service.getSchoolById(1).subscribe(school => {
        expect(school).toEqual(mockSchool);
        done();
      });
    });
  });

  describe('getAllMySchools', () => {
    beforeEach(() => {
      mockApiHttp.get.mockResolvedValue({ data: [mockSchool] } as any);
    });

    it('should call API with all flag', () => {
      service.getAllMySchools().subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/me/schools', { all: true });
    });

    it('should return schools array', (done) => {
      service.getAllMySchools().subscribe(schools => {
        expect(schools).toEqual([mockSchool]);
        done();
      });
    });
  });

  describe('listAll', () => {
    beforeEach(() => {
      mockApiHttp.get.mockResolvedValue(mockSchoolsResponse as any);
    });

    it('should call API with high perPage by default', () => {
      service.listAll().subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/schools', {
        page: 1,
        perPage: 1000,
        active: true,
        orderBy: 'name',
        orderDirection: 'asc'
      });
    });

    it('should call API with custom parameters', () => {
      const params: GetSchoolsParams = {
        search: 'test',
        perPage: 50,
        active: false
      };

      service.listAll(params).subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/schools', {
        page: 1,
        perPage: 50,
        search: 'test',
        active: false,
        orderBy: 'name',
        orderDirection: 'asc'
      });
    });

    it('should return schools array', (done) => {
      service.listAll().subscribe(schools => {
        expect(schools).toEqual([mockSchool]);
        done();
      });
    });
  });

  describe('searchSchools', () => {
    beforeEach(() => {
      mockApiHttp.get.mockResolvedValue([mockSchool] as any);
    });

    it('should call API with search query and default limit', () => {
      service.searchSchools('swimming').subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/schools/search', {
        search: 'swimming',
        perPage: 10,
        active: true
      });
    });

    it('should call API with custom limit', () => {
      service.searchSchools('tennis', 5).subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/schools/search', {
        search: 'tennis',
        perPage: 5,
        active: true
      });
    });

    it('should return schools array', (done) => {
      service.searchSchools('test').subscribe(schools => {
        expect(schools).toEqual([mockSchool]);
        done();
      });
    });

    it('should handle empty search query', () => {
      service.searchSchools('').subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/schools/search', {
        search: '',
        perPage: 10,
        active: true
      });
    });
  });

  describe('Error Handling', () => {
    it('should propagate API errors from getMySchools', (done) => {
      const error = new Error('Network error');
      mockApiHttp.get.mockRejectedValue(error);

      service.getMySchools().subscribe({
        error: (err) => {
          expect(err).toEqual(error);
          done();
        }
      });
    });

    it('should propagate API errors from getSchoolById', (done) => {
      const error = new Error('School not found');
      mockApiHttp.get.mockRejectedValue(error);

      service.getSchoolById(999).subscribe({
        error: (err) => {
          expect(err).toEqual(error);
          done();
        }
      });
    });

    it('should propagate API errors from getAllMySchools', (done) => {
      const error = new Error('Unauthorized');
      mockApiHttp.get.mockRejectedValue(error);

      service.getAllMySchools().subscribe({
        error: (err) => {
          expect(err).toEqual(error);
          done();
        }
      });
    });

    it('should propagate API errors from searchSchools', (done) => {
      const error = new Error('Search failed');
      mockApiHttp.get.mockRejectedValue(error);

      service.searchSchools('test').subscribe({
        error: (err) => {
          expect(err).toEqual(error);
          done();
        }
      });
    });
  });

  describe('URL Building', () => {
    beforeEach(() => {
      mockApiHttp.get.mockResolvedValue(mockSchoolsResponse as any);
    });
    it('should build correct URLs with special characters in search', () => {
      const params: GetSchoolsParams = {
        search: 'test & school',
        page: 1
      };

      service.getMySchools(params).subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/me/schools', {
        page: 1,
        perPage: 20,
        search: 'test & school',
        active: true,
        orderBy: 'name',
        orderDirection: 'asc'
      });
    });

    it('should handle boolean parameters correctly', () => {
      const params: GetSchoolsParams = {
        active: false
      };

      service.getMySchools(params).subscribe();

      expect(mockApiHttp.get).toHaveBeenCalledWith('/me/schools', {
        page: 1,
        perPage: 20,
        active: false,
        orderBy: 'name',
        orderDirection: 'asc'
      });
    });
  });
});