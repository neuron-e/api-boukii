import { Component } from '@angular/core';
import {switchMap} from 'rxjs/operators';
import {catchError, of, retry} from 'rxjs';
import {ActivatedRoute} from '@angular/router';

@Component({
  selector: 'vex-client-detail-v2',
  templateUrl: './client-detail-v2.component.html',
  styleUrls: ['./client-detail-v2.component.scss']
})
export class ClientDetailV2Component {

  user: any;
  id: any;
  mainId: any;

  constructor(private activatedRoute: ActivatedRoute) {
  }
  ngOnInit(): void {
    this.user = JSON.parse(localStorage.getItem('boukiiUser'));
    this.id = this.activatedRoute.snapshot.params.id;
    this.mainId = this.activatedRoute.snapshot.params.id;
/*    this.getInitialData().pipe(
      switchMap(() => this.getData())
    ).subscribe(() => {
      this.crudService.get('/evaluation-fulfilled-goals').subscribe((data) => this.evaluationFullfiled = data.data)
      // Aquí puedes realizar cualquier lógica adicional después de obtener los datos iniciales y los datos principales.
    });*/
  }

  getInitialData() {

/*    const requestsInitial = {
      languages: this.getLanguages().pipe(retry(3), catchError(error => {
        console.error('Error fetching languages:', error);
        return of([]); // Devuelve un array vacío en caso de error
      })),
      stations: this.getStations().pipe(retry(3), catchError(error => {
        console.error('Error fetching stations:', error);
        return of([]); // Devuelve un array vacío en caso de error
      })),*/
      /*      clients: this.getClients().pipe(retry(3), catchError(error => {
              console.error('Error fetching clients:', error);
              return of([]); // Devuelve un array vacío en caso de error
            })),*/
    };

  }
