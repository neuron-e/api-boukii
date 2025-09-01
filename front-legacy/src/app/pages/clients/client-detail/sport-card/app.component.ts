import { Component, Input } from '@angular/core';
import {ClientDetailComponent} from '../client-detail.component';

@Component({
  selector: 'app-user-detail-sport-card',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss'],
})
export class SportCardComponent {
  constructor(public user: ClientDetailComponent) { }
  @Input() selectedSport: any
  @Input() level: any
  @Input() goals: any
  @Input() evaluations: any
  @Input() border: boolean = true
  @Input() center: boolean = false

  calculateGoalsScore(): number {
    let ret = 0;
    if (this.selectedSport?.level) {
      const goalsx = this.goals.filter((g: any) => g.degree_id === this.level.id);
      const maxPoints = goalsx.length * 10;

      for (const goal of goalsx) {
        this.user.evaluationFullfiled.forEach((element: any) => {
          if (element.degrees_school_sport_goals_id === goal.id) {
            ret += element.score;
          }
        });
      }

      ret = ret > maxPoints ? maxPoints : ret;
      return maxPoints > 0 ? Math.round((ret / maxPoints) * 100) : 0;
    }

    return 0;
  }

  getDegreeScore(goal: any) {
    const d = this.user.evaluationFullfiled.find((element: any) => element.degrees_school_sport_goals_id === goal)
    if (d) return d.score
    return 0
  }
}
