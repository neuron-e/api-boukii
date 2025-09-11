import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'join',
  standalone: true
})
export class JoinPipe implements PipeTransform {
  transform(value: any[], separator: string = ','): string {
    if (!value || !Array.isArray(value)) {
      return '';
    }
    return value.join(separator);
  }
}