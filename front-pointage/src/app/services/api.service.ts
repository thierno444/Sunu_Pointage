import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import { Cohorte } from '../demo/cohortes/cohorte.model';
import { Apprenant } from '../demo/liste-apprenants/ apprenant.model';

// Interface pour le pointage
export interface Pointage {
  id?: string;
  date?: string;
  estPresent?: boolean;
  estRetard?: boolean;
  user?: {
    nom?: string;
    prenom?: string;
    email?: string;
    matricule?: string;
  };
  created_at?: string;
  updated_at?: string;
}




export interface Utilisateur {
  id: string;
  nom: string;
  prenom: string;
  fonction: string;
  type: string;
  email: string;
  matricule: string;
  cardId: string;
  telephone: string;
  statut: string;
}

@Injectable({
  providedIn: 'root'
})
export class ApiService {

  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) { }

  /** ======================= COHORTES ======================= */

  getCohortes(): Observable<Cohorte[]> {
    return this.http.get<{ message: string; data: Cohorte[] }>(`${this.apiUrl}/cohortes`)
      .pipe(map(response => response.data));
  }

  getCohorteById(id: string): Observable<Cohorte> {
    return this.http.get<{ message: string; data: Cohorte }>(`${this.apiUrl}/cohortes/${id}`)
      .pipe(map(response => response.data));
  }

  createCohorte(cohorte: Cohorte): Observable<Cohorte> {
    return this.http.post<{ message: string; data: Cohorte }>(`${this.apiUrl}/cohortes`, cohorte)
      .pipe(map(response => response.data));
  }

  updateCohorte(id: string, cohorte: Cohorte): Observable<Cohorte> {
    return this.http.put<{ message: string; data: Cohorte }>(`${this.apiUrl}/cohortes/${id}`, cohorte)
      .pipe(map(response => response.data));
  }

  deleteCohorte(id: string): Observable<void> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/cohortes/${id}`)
      .pipe(map(() => null));
  }

  /** ======================= APPRENANTS ======================= */

  getApprenantsByCohorte(cohorteId: string) {
    return this.http.get(`${this.apiUrl}/cohortes/${cohorteId}/apprenants`);
  }

  createApprenant(formData: FormData): Observable<any> {
    return this.http.post(`${this.apiUrl}/utilisateurs/creerUser`, formData);
  }

  updateApprenant(id: string, apprenantData: any): Observable<any> {
    return this.http.put(`${this.apiUrl}/utilisateurs/${id}`, apprenantData, {
      headers: { 'Content-Type': 'application/json' }
    }).pipe(map((response: any) => response.data));
  }

  updateApprenantWithFormData(id: string, formData: FormData): Observable<any> {
    return this.http.put(`${this.apiUrl}/utilisateurs/${id}`, formData)
      .pipe(map((response: any) => response.data));
  }

  updateApprenantWithPhoto(id: string, formData: FormData): Observable<any> {
    return this.http.post(`${this.apiUrl}/utilisateurs/${id}`, formData)
      .pipe(map((response: any) => response.data));
  }

  deleteApprenant(id: string): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/utilisateurs/${id}`);
  }

  getUserById(id: string): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/utilisateurs/${id}`);
  }

  importApprenants(cohorteId: string, formData: FormData) {
    return this.http.post(`${this.apiUrl}/utilisateurs/import/cohorte/${cohorteId}`, formData);
  }

  getApprenantsCount(): Observable<number> {
    return this.http.get<{ count: number }>(`${this.apiUrl}/count-apprenants`)
      .pipe(map(response => response.count));
  }

  toggleStatus(id: string): Observable<any> {
    return this.http.put(`${this.apiUrl}/utilisateurs/${id}/toggle-status`, {});
  }

  assignCard(id: string, cardId: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/utilisateurs/${id}/assign-card`, { cardId });
  }

  /** ======================= POINTAGES ======================= */

  getPointages(): Observable<Pointage[]> {
  return this.http.get<{ status: boolean; data: Pointage[] }>(`${this.apiUrl}/pointages`)
    .pipe(map(response => response.data));
}


  getPointagesByUser(userId: string): Observable<Pointage[]> {
    return this.http.get<{ message: string; data: Pointage[] }>(`${this.apiUrl}/pointages`, {
      params: { user_id: userId }
    }).pipe(map(response => response.data));
  }

  getPointagesByDate(date: Date): Observable<Pointage[]> {
    return this.http.get<{ message: string; data: Pointage[] }>(`${this.apiUrl}/pointages`, {
      params: { date: date.toISOString() }
    }).pipe(map(response => response.data));
  }

  /** (optionnel) méthode à implémenter plus tard */
  getEmployesByDepartement(departementId: string) {
    throw new Error('Method not implemented.');
  }
}
