import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'percentageFormatter'
})
export class PercentageFormatterPipe implements PipeTransform {

  transform(value: number | string, decimals: number = 1): string {
    if (value === null || value === undefined || value === '') {
      return '0%';
    }

    const numValue = typeof value === 'string' ? parseFloat(value) : value;

    if (isNaN(numValue)) {
      return '0%';
    }

    return `${numValue.toFixed(decimals)}%`;
  }

}
