import { Pipe, PipeTransform } from '@angular/core';
import moment from 'moment';

@Pipe({
  name: 'dateRangeFormatter'
})
export class DateRangeFormatterPipe implements PipeTransform {

  transform(startDate: string | Date, endDate: string | Date, format: string = 'DD/MM/YYYY'): string {
    if (!startDate || !endDate) {
      return 'Fechas no válidas';
    }

    const start = moment(startDate);
    const end = moment(endDate);

    if (!start.isValid() || !end.isValid()) {
      return 'Fechas no válidas';
    }

    if (start.isSame(end, 'day')) {
      return start.format(format);
    }

    return `${start.format(format)} - ${end.format(format)}`;
  }

}
