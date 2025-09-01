// analytics-export.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { AnalyticsNotificationService } from './analytics-notification.service';

export interface ExportOptions {
  format: 'csv' | 'excel' | 'pdf';
  sections?: string[];
  includeCharts?: boolean;
  includeRawData?: boolean;
  dateRange?: {
    start: string;
    end: string;
  };
}

@Injectable({
  providedIn: 'root'
})
export class AnalyticsExportService {

  private readonly baseUrl = '/api/admin/analytics';

  constructor(
    private http: HttpClient,
    private notificationService: AnalyticsNotificationService
  ) {}

  exportDashboard(filters: any, options: ExportOptions): Observable<any> {
    this.notificationService.showInfo('Iniciando exportación...', 'Exportación');

    return this.http.post<any>(`${this.baseUrl}/export/dashboard`, {
      filters,
      options
    });
  }

  exportExecutiveSummary(filters: any, format: 'pdf' | 'excel'): Observable<any> {
    return this.http.post<any>(`${this.baseUrl}/export/executive-summary`, {
      filters,
      format
    });
  }

  exportDetailedAnalysis(filters: any, sections: string[], format: 'excel' | 'pdf'): Observable<any> {
    return this.http.post<any>(`${this.baseUrl}/export/detailed-analysis`, {
      filters,
      sections,
      format
    });
  }

  downloadFile(filename: string): void {
    const link = document.createElement('a');
    link.href = `/api/admin/finance/download-export/${filename}`;
    link.download = filename;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    this.notificationService.showSuccess('Descarga iniciada', 'Exportación');
  }
}
