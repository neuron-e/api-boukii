import {ChangeDetectionStrategy, Component, EventEmitter, Input, Output} from '@angular/core';

export interface KpiData {
  title: string;
  value: number | string;
  previousValue?: number | string;
  unit?: string;
  format?: 'currency' | 'percentage' | 'number' | 'custom';
  currency?: string;
  trend?: 'up' | 'down' | 'stable';
  trendValue?: number;
  trendUnit?: string;
  icon?: string;
  color?: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info';
  clickable?: boolean;
  loading?: boolean;
  subtitle?: string;
  tooltip?: string;
}

// ==================== COMPONENT ====================

@Component({
  selector: 'app-kpis-card',
  templateUrl: './kpis-card.component.html',
  styleUrls: ['./kpis-card.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class KpisCardComponent {
  @Input() kpiData!: KpiData;
  @Input() compact: boolean = false;
  @Input() showTrend: boolean = true;
  @Input() showIcon: boolean = true;
  @Input() animateValue: boolean = true;

  @Output() cardClick = new EventEmitter<KpiData>();
  @Output() trendClick = new EventEmitter<KpiData>();

  // ==================== LIFECYCLE ====================

  constructor() {}

  // ==================== PUBLIC METHODS ====================

  onCardClick(): void {
    if (this.kpiData.clickable) {
      this.cardClick.emit(this.kpiData);
    }
  }

  onTrendClick(event: Event): void {
    event.stopPropagation();
    this.trendClick.emit(this.kpiData);
  }

  // ==================== GETTERS ====================

  get formattedValue(): string {
    if (this.kpiData.loading) {
      return '...';
    }

    const value = this.kpiData.value;

    switch (this.kpiData.format) {
      case 'currency':
        return this.formatCurrency(Number(value));
      case 'percentage':
        return this.formatPercentage(Number(value));
      case 'number':
        return this.formatNumber(Number(value));
      default:
        return String(value);
    }
  }

  get formattedTrend(): string {
    if (!this.kpiData.trendValue) {
      return '';
    }

    const trend = this.kpiData.trendValue;
    const unit = this.kpiData.trendUnit || '';

    switch (this.kpiData.format) {
      case 'currency':
        return `${this.formatCurrency(Math.abs(trend))} ${unit}`;
      case 'percentage':
        return `${this.formatPercentage(Math.abs(trend))} ${unit}`;
      default:
        return `${this.formatNumber(Math.abs(trend))} ${unit}`;
    }
  }

  formatPreviousValue(): string {
    if (this.kpiData.previousValue === undefined) {
      return '';
    }

    const value = this.kpiData.previousValue;

    switch (this.kpiData.format) {
      case 'currency':
        return this.formatCurrency(Number(value));
      case 'percentage':
        return this.formatPercentage(Number(value));
      case 'number':
        return this.formatNumber(Number(value));
      default:
        return String(value);
    }
  }

  get trendIcon(): string {
    switch (this.kpiData.trend) {
      case 'up':
        return 'trending_up';
      case 'down':
        return 'trending_down';
      case 'stable':
        return 'trending_flat';
      default:
        return 'remove';
    }
  }

  get trendClass(): string {
    return `trend-${this.kpiData.trend}`;
  }

  get cardClass(): string {
    const classes = ['kpi-card'];

    if (this.kpiData.color) {
      classes.push(`kpi-card--${this.kpiData.color}`);
    }

    if (this.compact) {
      classes.push('kpi-card--compact');
    }

    if (this.kpiData.clickable) {
      classes.push('kpi-card--clickable');
    }

    if (this.kpiData.loading) {
      classes.push('kpi-card--loading');
    }

    return classes.join(' ');
  }

  get iconColor(): string {
    switch (this.kpiData.color) {
      case 'primary':
        return '#3A57A7';
      case 'secondary':
        return '#FCB859';
      case 'success':
        return '#4CAF50';
      case 'warning':
        return '#FF9800';
      case 'danger':
        return '#F44336';
      case 'info':
        return '#2196F3';
      default:
        return '#757575';
    }
  }

  // ==================== PRIVATE METHODS ====================

  private formatCurrency(value: number): string {
    const currency = this.kpiData.currency || 'CHF';
    return new Intl.NumberFormat('es-ES', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(value);
  }

  private formatPercentage(value: number): string {
    return `${value.toFixed(1)}%`;
  }

  private formatNumber(value: number): string {
    if (value >= 1000000) {
      return `${(value / 1000000).toFixed(1)}M`;
    } else if (value >= 1000) {
      return `${(value / 1000).toFixed(1)}K`;
    }
    return value.toLocaleString('es-ES');
  }
}
