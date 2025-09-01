import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'numberFormatter'
})
export class NumberFormatterPipe implements PipeTransform {

  transform(value: number | string, compact: boolean = false, locale: string = 'es-ES'): string {
    if (value === null || value === undefined || value === '') {
      return '0';
    }

    const numValue = typeof value === 'string' ? parseFloat(value) : value;

    if (isNaN(numValue)) {
      return '0';
    }

    if (compact) {
      return this.formatCompact(numValue);
    }

    return new Intl.NumberFormat(locale).format(numValue);
  }

  private formatCompact(value: number): string {
    if (value >= 1000000000) {
      return `${(value / 1000000000).toFixed(1)}B`;
    } else if (value >= 1000000) {
      return `${(value / 1000000).toFixed(1)}M`;
    } else if (value >= 1000) {
      return `${(value / 1000).toFixed(1)}K`;
    }
    return value.toString();
  }

}
