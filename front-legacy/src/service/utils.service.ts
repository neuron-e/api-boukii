import { Injectable } from "@angular/core";
import moment from "moment";
import { MOCK_COUNTRIES } from "src/app/static-data/countries-data";
import {Observable, of, tap} from 'rxjs';
import {ApiCrudService} from './crud.service';
import {MatCalendarCellCssClasses} from '@angular/material/datepicker';
import {TranslateService} from '@ngx-translate/core';

@Injectable({
  providedIn: "root",
})
export class UtilsService {
  private countries = MOCK_COUNTRIES;
  season: any = [];
  holidays: any = [];

  constructor(private crudService: ApiCrudService, private translateService: TranslateService) {}

  calculateYears(birthDateString: string) {
    const fechaActual = moment();
    const age = fechaActual.diff(
      moment(birthDateString, "YYYY-MM-DD"),
      "years"
    );
    return age;
  }

  getSportLevels(id: any, allLevels:any) {
    return allLevels.filter((a) => a.sport_id === id)
  }

  getHighestAuthorizedDegree(monitor, sport_id: number, school_id: number): any | null {
    // Encuentra los deportes asociados al monitor
    const degrees = monitor.monitor_sports_degrees
      .filter(degree =>
        degree.sport_id === sport_id &&
        degree.school_id === school_id
      )
      .map(degree => degree.monitor_sport_authorized_degrees)
      .flat(); // Aplanamos el array para obtener todos los grados autorizados

    if (degrees.length === 0) {
      return null; // Si no hay grados autorizados, retornamos null
    }

    // Buscamos el degree autorizado con el degree_order más alto
    const highestDegree = degrees.reduce((prev, current) => {
      return current.degree.degree_order > prev.degree.degree_order ? current : prev;
    });


    return highestDegree;
  }

  getCountry(id: any) {
    const country = this.countries.find((c) => c.id == id);
    return country ? country.name : "NDF";
  }

  formatDate(date: string, format = "YYYY-MM-DD") {
    return moment(date).format(format);
  }

  getSchoolData() {
    const user = JSON.parse(localStorage.getItem("boukiiUser"));
    return user.schools[0]
  }

  getSeason(schoolId: number): Observable<any> {
    // Si ya tenemos la temporada, devolverla directamente
    if (this.season) {
      return of(this.season); // Devuelve un Observable con la temporada almacenada
    }

    // Si no está almacenada, hacemos la llamada a la API
    return this.crudService
      .list('/seasons', 1, 10000, 'asc', 'id', '&school_id=' + schoolId + '&is_active=1')
      .pipe(
        tap((response) => {
          this.season = response.data[0]; // Guardamos la temporada en caché
          this.holidays = this.season.vacation_days ? JSON.parse(this.season.vacation_days) : [];
        })
      );
  }

  // Método adicional para obtener las vacaciones
  getHolidays(): any[] {
    return this.holidays;
  }

  getAvailableWeekDays(settings: any): string | null {
    if (settings !== null) {
      const data = typeof settings === 'string' ? JSON.parse(settings) : settings;

      const dayTranslations = {
        monday: this.translateService.instant('Lundi'), // Traducción para Monday
        tuesday: this.translateService.instant('Mardi'), // Traducción para Tuesday
        wednesday: this.translateService.instant('Mercredi'), // Traducción para Wednesday
        thursday: this.translateService.instant('Jeudi'), // Traducción para Thursday
        friday: this.translateService.instant('Vendredi'), // Traducción para Friday
        saturday: this.translateService.instant('Samedi'), // Traducción para Saturday
        sunday: this.translateService.instant('Diamanche'), // Traducción para Sunday
      };

      let ret = null;

      if (data !== null) {
        Object.keys(data.weekDays).forEach((day) => {
          if (data.weekDays[day] && dayTranslations[day]) {
            ret = ret === null ? dayTranslations[day] : `${ret} - ${dayTranslations[day]}`;
          }
        });
      }

      return ret;
    }

    return null;
  }

  isFutureDate(courseDate: any): boolean {
    const today = new Date();
    const courseDay = new Date(courseDate.date);

    // Ajustar hora de inicio
    const [startHour, startMinute] = courseDate.hour_start.split(':').map(Number);
    courseDay.setHours(startHour, startMinute, 0, 0);

    // Verificar si la fecha es futura o si es hoy pero con hora futura
    return courseDay > today;
  }

  generateCourseHours(
    startTime: string,
    endTime: string,
    mainDuration: string,
    interval: string,
    activitiesBooked: any[] = [],
    selectedDate: any = null,
    utilizers: any[] = []
  ): string[] {
    const [startHours, startMinutes] = startTime.split(':').map(Number);
    const [endHours, endMinutes] = endTime.split(':').map(Number);
    const intervalParts = interval.split(' ');
    const mainDurationParts = mainDuration.split(' ');

    let intervalHours = 0;
    let intervalMinutes = 0;
    let mainDurationHours = 0;
    let mainDurationMinutes = 0;

    intervalParts.forEach(part => {
      if (part.includes('h')) {
        intervalHours = parseInt(part, 10);
      } else if (part.includes('min')) {
        intervalMinutes = parseInt(part, 10);
      }
    });

    mainDurationParts.forEach(part => {
      if (part.includes('h')) {
        mainDurationHours = parseInt(part, 10);
      } else if (part.includes('min')) {
        mainDurationMinutes = parseInt(part, 10);
      }
    });

    let currentHours = startHours;
    let currentMinutes = startMinutes;
    const result = [];

    // 📌 Filtrar actividades ocupadas según `selectedDate` y `utilizers`
    const conflictingActivities = activitiesBooked.flatMap(activity =>
      activity.dates.filter(dateObj =>
        moment(dateObj.date).format('YYYY-MM-DD') === selectedDate &&
        activity.utilizers.some(activityUtilizer => utilizers.some(u => u.id === activityUtilizer.id))
      )
    );

    // 📌 Extraer intervalos ocupados
    const occupiedIntervals = conflictingActivities.map(dateObj => ({
      start: this.timeToMinutes(dateObj.startHour),
      end: this.timeToMinutes(dateObj.endHour)
    }));

    while (true) {
      let nextIntervalEndHours = currentHours + mainDurationHours;
      let nextIntervalEndMinutes = currentMinutes + mainDurationMinutes;

      nextIntervalEndHours += Math.floor(nextIntervalEndMinutes / 60);
      nextIntervalEndMinutes %= 60;

      const currentMinutesTime = this.timeToMinutes(`${currentHours}:${currentMinutes}`);
      const nextMinutesTime = this.timeToMinutes(`${nextIntervalEndHours}:${nextIntervalEndMinutes}`);

      // 📌 Si la siguiente hora finaliza después del endTime, salimos del bucle
      if (nextMinutesTime > this.timeToMinutes(endTime)) {
        break;
      }

      // 📌 Evitar agregar horarios ocupados
      if (!this.isTimeOccupied(currentMinutesTime, nextMinutesTime, occupiedIntervals)) {
        result.push(`${currentHours.toString().padStart(2, '0')}:${currentMinutes.toString().padStart(2, '0')}`);
      }

      currentMinutes += intervalMinutes;
      currentHours += intervalHours + Math.floor(currentMinutes / 60);
      currentMinutes %= 60;

      if (currentHours > endHours || (currentHours === endHours && currentMinutes >= endMinutes)) {
        break;
      }
    }

    return result;
  }

  timeToMinutes(time: string): number {
    const [hours, minutes] = time.split(':').map(Number);
    return hours * 60 + minutes;
  }

  calculateAvailableHours(
    date: any,
    hour: string,
    course: any,
    activitiesBooked: any[] = [],
    utilizers: any[] = [],
    duration: string | null = null
  ): boolean {

    let formatDate = moment(date).format('YYYY-MM-DD');
    const courseDate = course.course_dates.find((d: any) =>
      moment(d.date).format('YYYY-MM-DD') === formatDate
    );

    if (!courseDate) {
      return true;
    }

    const selectedDate = moment(date).format('YYYY-MM-DD');
    const selectedHour = moment(`${selectedDate} ${hour}`, 'YYYY-MM-DD HH:mm');
    const start = moment(`${selectedDate} ${courseDate.hour_start}`, 'YYYY-MM-DD HH:mm');
    const end = moment(`${selectedDate} ${courseDate.hour_end}`, 'YYYY-MM-DD HH:mm');

    // Si es hoy, deshabilitar horas pasadas
    if (moment(selectedDate).isSame(moment(), 'day')) {
      if (selectedHour.isBefore(moment(), 'minute')) {
        return true;
      }
    }

    // Verificar si la hora está dentro del rango permitido
    if (selectedHour.isBefore(start) || selectedHour.isAfter(end)) {
      return true;
    }

    // Si hay duración, calcular la hora de finalización
    if (duration) {
      const durationMinutes = this.parseDurationToMinutes(duration);
      const selectedEndHour = selectedHour.clone().add(durationMinutes, 'minutes');

      // Verificar que la hora de finalización no exceda el rango permitido
      if (selectedEndHour.isAfter(end)) {
        return true;
      }
    }

    // Filtrar actividades con fechas que coincidan y con utilizadores en común
    const conflictingDates = activitiesBooked.flatMap(activity =>
      activity.dates.filter(dateObj =>
        moment(dateObj.date).format('YYYY-MM-DD') === selectedDate &&
        activity.utilizers.some(activityUtilizer => utilizers.some(u => u.id === activityUtilizer.id))
      )
    );

    // Obtener intervalos ocupados
    const occupiedIntervals = conflictingDates.map(dateObj => ({
      start: moment(`${selectedDate} ${dateObj.startHour}`, 'YYYY-MM-DD HH:mm'),
      end: moment(`${selectedDate} ${dateObj.endHour}`, 'YYYY-MM-DD HH:mm')
    }));

    // Verificar si la hora está ocupada
    return occupiedIntervals.some(interval => selectedHour.isBetween(interval.start, interval.end, 'minute', '[)'));
  }

// Método auxiliar para convertir duración en minutos
  parseDurationToMinutes(duration: string): number {
    const regex = /(\d+)h(?:\s*(\d+)m)?/; // Captura '1h', '1h 30m', '2h', etc.
    const matches = duration.match(regex);

    if (!matches) return 0;

    const hours = parseInt(matches[1], 10) || 0;
    const minutes = parseInt(matches[2] || '0', 10);

    return (hours * 60) + minutes;
  }


  dateClass(color: string, course: any, minDate:any) {
    return (date: Date): MatCalendarCellCssClasses => {
      const currentDate = moment(date, "YYYY-MM-DD").format("YYYY-MM-DD");
      if (
        course.course_dates.find(s => moment(s.date).format('YYYY-MM-DD') === currentDate) &&
        moment(minDate, "YYYY-MM-DD")
          .startOf("day")
          .isSameOrBefore(moment(date, "YYYY-MM-DD").startOf("day"))
      ) {
        return `with-course ${color}`;
      } else {
        return;
      }
    };
  }

  inUseDatesFilter = (d: Date, myHolidayDates:any, course:any, allowPast = false): boolean => {
    if (!d) return false; // Si la fecha es nula o indefinida, no debería ser seleccionable.

    const formattedDate = moment(d).format('YYYY-MM-DD');
    const time = moment(d).startOf('day').valueOf(); // .getTime() es igual a .valueOf()
    const today = moment().startOf('day'); // Fecha actual (sin hora, solo día)

    // Verifica si la fecha es anterior a hoy.
    const isPastDate = allowPast ? !allowPast : moment(d).isBefore(today);

    // Encuentra si la fecha actual está en myHolidayDates.
    const isHoliday = myHolidayDates.some(x => x.getTime() === time);

    // Encuentra si la fecha actual está en selectedItem.course_dates y si es activa.
    const courseDate = course.course_dates.find(s => moment(s.date).format('YYYY-MM-DD') === formattedDate);
    const isActive = courseDate ? (courseDate.active || courseDate.active === 1) : false;

    // La fecha debería ser seleccionable si no es un día festivo y está activa (o sea, active no es falso ni 0).
    return !isHoliday && isActive && !isPastDate;
  }

  generateCourseDurations(
    startTime: string,
    endTime: string,
    course: any,
    activitiesBooked: any[] = [],
    selectedDate: any = null,
    utilizers: any[] = [],
    interval: string = '15min',
  ) {
    const timeToMinutes = (time) => {
      const [hours, minutes] = time.split(':').map(Number);
      return hours * 60 + minutes;
    };

    const formatMinutes = (totalMinutes) => {
      const hours = Math.floor(totalMinutes / 60);
      const minutes = totalMinutes % 60;
      return `${hours > 0 ? hours + 'h' : ''} ${minutes > 0 ? minutes + 'm' : ''}`.trim();
    };

    const startMinutes = timeToMinutes(startTime);
    const endMinutes = timeToMinutes(endTime);

    // 📌 Convertir intervalos a minutos
    const intervalMatch = interval.match(/(\d+)(h|min)/g);
    let intervalTotalMinutes = 0;

    if (intervalMatch) {
      intervalMatch.forEach(part => {
        if (part.includes('h')) {
          intervalTotalMinutes += parseInt(part, 10) * 60;
        } else if (part.includes('min')) {
          intervalTotalMinutes += parseInt(part, 10);
        }
      });
    } else {
      console.error("Interval format is not correct.");
      return [];
    }

    // 📌 Filtrar actividades que coincidan con la fecha y `utilizers`
    const conflictingActivities = activitiesBooked.flatMap(activity =>
      activity.dates.filter(dateObj =>
        moment(dateObj.date).format('YYYY-MM-DD') === selectedDate &&
        activity.utilizers.some(activityUtilizer => utilizers.some(u => u.id === activityUtilizer.id))
      )
    );

    // 📌 Extraer intervalos ocupados
    const occupiedIntervals = conflictingActivities.map(dateObj => ({
      start: timeToMinutes(dateObj.startHour),
      end: timeToMinutes(dateObj.endHour)
    }));

    // 📌 Calcular intervalos de duración excluyendo los ocupados
    const durations = [];
    for (let minutes = startMinutes + intervalTotalMinutes; minutes <= endMinutes; minutes += 5) {
      if (!this.isTimeOccupied(minutes, minutes + intervalTotalMinutes, occupiedIntervals)) {
        durations.push(formatMinutes(minutes - startMinutes));
      }
    }

    // 📌 Filtrar duraciones con `price_range`
    const tableDurations = [];
    const tablePaxes = [];

    const priceRangeCourse = typeof course.price_range === 'string' ? JSON.parse(course.price_range) : course.price_range;
    durations.forEach(element => {
      const priceRange = priceRangeCourse ? priceRangeCourse.find((p) => p.intervalo === element) : null;
      if (priceRange && priceRange.intervalo === element) {
        if (this.extractValues(priceRange)[0] && (+this.extractValues(priceRange)[0].key) <= course.max_participants) {
          tableDurations.push(this.extractValues(priceRange)[0].interval);

          this.extractValues(priceRange).forEach(element => {
            const pax = element.key;
            if (pax && tablePaxes.length === 0 || pax && tablePaxes.length > 0 && !tablePaxes.includes(pax)) {
              tablePaxes.push(element.key);
            }
          });
        }
      }
    });

    return tableDurations;
  }

   isTimeOccupied(startTime: number, endTime: number, occupiedIntervals: { start: number, end: number }[]) {
    return occupiedIntervals.some(interval =>
      (startTime >= interval.start && startTime < interval.end) ||  // Inicio dentro del rango ocupado
      (endTime > interval.start && endTime <= interval.end) ||      // Fin dentro del rango ocupado
      (startTime <= interval.start && endTime >= interval.end)      // Intervalo completamente cubierto
    );
  }


  calculateEndHour(hour: any, duration: any) {
    if(duration.includes('h') && (duration.includes('min') || duration.includes('m'))) {
      const hours = duration.split(' ')[0].replace('h', '');
      const minutes = duration.split(' ')[1].replace('min', '').replace('m', '');

      return moment(hour, 'HH:mm').add(hours, 'h').add(minutes, 'm').format('HH:mm');
    } else if(duration.includes('h')) {
      const hours = duration.split(' ')[0].replace('h', '');

      return moment(hour, 'HH:mm').add(hours, 'h').format('HH:mm');
    } else {
      const minutes = duration.split(' ')[0].replace('min', '').replace('m', '');

      return moment(hour, 'HH:mm').add(minutes, 'm').format('HH:mm');
    }
  }

  extractValues(data: any): { key: string, value: string, interval: string }[] {
    let results = [];

    for (const key in data) {
      if (data.hasOwnProperty(key) && data[key] != null && key !== "intervalo") {
        results.push({ key: key, value: data[key], interval: data["intervalo"] });
      }
    }

    return results;
  }

  getClientDegreeByClient(client: any, sport_id: number) {
    if (client && client !== null && sport_id && sport_id !== null) {
      const sportObject = client?.client_sports.find(
        (obj) => obj.sport_id === sport_id && obj.school_id == this.getSchoolData().id
      );

      return sportObject?.degree;
    }
  }

}
