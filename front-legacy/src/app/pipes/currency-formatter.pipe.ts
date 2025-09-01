import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'currencyFormatter'
})
export class CurrencyFormatterPipe implements PipeTransform {

  transform(value: number | string, currency: string = 'CHF', locale: string = 'es-ES'): string {
    if (value === null || value === undefined || value === '') {
      return '0';
    }

    const numValue = typeof value === 'string' ? parseFloat(value) : value;

    if (isNaN(numValue)) {
      return '0';
    }

    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: numValue % 1 === 0 ? 0 : 2
    }).format(numValue);
  }

}
