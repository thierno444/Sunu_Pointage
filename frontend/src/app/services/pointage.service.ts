import { Injectable, OnDestroy } from '@angular/core';
import { HttpClient, HttpHeaders, HttpErrorResponse } from '@angular/common/http';
import { Observable, Subject, BehaviorSubject, of, forkJoin } from 'rxjs';
import { map, catchError, tap } from 'rxjs/operators';
import { io, Socket } from 'socket.io-client';
import { environment } from 'src/environments/environment';

import {
  Pointage,
  Utilisateur,
  PointageResponse,
  ApiResponse
} from '../demo/pointage/pointage.interface';

@Injectable({
  providedIn: 'root'
})
export class PointageService implements OnDestroy {
  private socket: Socket;
  private apiUrl = `${environment.apiUrl}`;
  private nodeServerUrl = 'http://localhost:3000';
  private currentPointage = new BehaviorSubject<Pointage | null>(null);
  private pointagesEnAttente = new BehaviorSubject<Pointage[]>([]);
  private errorSubject = new Subject<{ message: string; cardId?: string }>();
  private currentPointagesList = new BehaviorSubject<any[]>([]);
  private doorStatus = new BehaviorSubject<'open' | 'closed'>('closed');
  private socketConnected = new BehaviorSubject<boolean>(false);
  private cardErrorSubject = new Subject<{cardId: string, message: string, code: number} | null>();
  cardError$ = this.cardErrorSubject.asObservable();

  constructor(private http: HttpClient) {
    this.socket = io(this.nodeServerUrl, {
      transports: ['websocket'],
      autoConnect: true,
      reconnection: true,
      reconnectionDelay: 1000,
      reconnectionDelayMax: 5000,
      reconnectionAttempts: 5
    });
    this.initializeSocketListeners();
  }

  private getAuthHeaders(): HttpHeaders {
    const token = localStorage.getItem('access_token');
    return new HttpHeaders({
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    });
  }

  handleCardError(error: any) {
    if (error) {
      this.cardErrorSubject.next({
        cardId: error.cardId,
        message: error.message,
        code: error.code
      });
    }
  }

  getSocketConnectionStatus(): Observable<boolean> {
    return this.socketConnected.asObservable();
  }

  private handleError(operation = 'opération') {
    return (error: HttpErrorResponse): Observable<any> => {
      console.error(`Erreur pendant ${operation}:`, error);
      if (error.error instanceof ErrorEvent) {
        console.error('Erreur client:', error.error.message);
      } else {
        console.error(
          `Erreur backend (code ${error.status}):`,
          error.error?.message || error.statusText
        );
      }
      return of({
        status: false,
        message: `Erreur lors de la ${operation}`,
        data: null,
        error: error.error?.message || error.statusText
      });
    };
  }

  private initializeSocketListeners(): void {
    this.socket.on('connect', () => {
      console.log('Connecté au serveur Socket.IO');
      this.socketConnected.next(true);
      this.refreshAllData();
    });

    this.socket.on('disconnect', () => {
      console.log('Déconnecté du serveur Socket.IO');
      this.socketConnected.next(false);
    });

    this.socket.on('reconnect_attempt', () => {
      console.log('Tentative de reconnexion...');
    });

    this.socket.on('reconnect', (attemptNumber: number) => {
      console.log(`Reconnecté après ${attemptNumber} tentatives`);
      this.refreshAllData();
    });

    this.socket.on('connect_error', (error) => {
      console.error('Erreur de connexion Socket.IO:', error);
      this.errorSubject.next({ message: 'Erreur de connexion au serveur' });
      this.socketConnected.next(false);
    });

    this.socket.on('card-scanned', (data: { cardId: string; utilisateur: Utilisateur; pointage: Pointage; }) => {
      console.log('Carte scannée:', data);
      if (data.pointage) {
        const pointageComplet = {
          ...data.pointage,
          cardId: data.cardId,
          utilisateur: data.utilisateur
        };
        this.currentPointage.next(pointageComplet);
        this.refreshAllData();
      }
    });

    this.socket.on('door-status', (status: 'open' | 'closed') => {
      this.doorStatus.next(status);
    });

    this.socket.on('card-error', (data: { cardId: string; message: string; code?: number }) => {
      console.error('Erreur de carte:', data);
      this.errorSubject.next(data);
    });

    this.socket.on('pointage-en-attente', (data: { cardId: string; pointage: Pointage; }) => {
      console.log('Pointage en attente reçu:', data);
      const currentPointages = this.pointagesEnAttente.value;
      if (!currentPointages.find(p => p.cardId === data.cardId)) {
        const pointageAvecCard = {
          ...data.pointage,
          cardId: data.cardId
        };
        this.pointagesEnAttente.next([...currentPointages, pointageAvecCard]);
        this.refreshAllData();
      }
    });

    this.socket.on('pointage-validated', (response: PointageResponse) => {
      console.log('Pointage validé:', response);
      if (response.status && response.data?.pointage) {
        const pointageValidated = response.data.pointage;
        if (this.currentPointage.value?.cardId === pointageValidated.cardId) {
          this.currentPointage.next(pointageValidated);
        }
        const currentPointages = this.pointagesEnAttente.value;
        const updatedPointages = currentPointages.filter(p => p.cardId !== pointageValidated.cardId);
        this.pointagesEnAttente.next(updatedPointages);
        this.refreshAllData();
      }
    });

    this.socket.on('card-error', (data: { cardId: string; message: string; code: number }) => {
      console.log('Erreur de carte reçue:', data);
      this.handleCardError(data);
    });
  }

  private refreshAllData() {
    console.log('Rafraîchissement de toutes les données');
    this.getPointagesJour().subscribe(response => {
      if (response.status) {
        console.log('Pointages jour mis à jour:', response.data);
        this.currentPointagesList.next(response.data);
      }
    });
    this.getAllUtilisateursActifs().subscribe(response => {
      if (response.status) {
        console.log('Liste des utilisateurs mise à jour:', response.data);
      }
    });
  }

  // ✅ Utilitaire pour reconstruire la Date
  private reconstruireDateHeure(date: string, heure: string): Date | null {
    if (!date || !heure) return null;
    const fullDateTime = `${date}T${heure}`;
    const d = new Date(fullDateTime);
    return isNaN(d.getTime()) ? null : d;
  }



  getAllUtilisateursActifs(): Observable<ApiResponse<any[]>> {
  return forkJoin({
    utilisateurs: this.http.get<ApiResponse<any[]>>(`${this.apiUrl}/utilisateurs`, {
      headers: this.getAuthHeaders()
    }),
    pointages: this.getPointagesJour()
  }).pipe(
    map(({ utilisateurs, pointages }) => {
      console.log('📦 Données brutes utilisateurs:', utilisateurs);
      console.log('📦 Données brutes pointages:', pointages);

      // Vérifie que les deux réponses sont valides
      if (utilisateurs.status && pointages.status) {
        const pointagesMap = new Map(
          pointages.data.map(p => {
            const userId = String(p.utilisateurs?.id || p.utilisateurs?._id || '');
            return [userId, p];
          })
        );

        console.log('📌 pointagesMap:', pointagesMap);

        const utilisateursActifs = utilisateurs.data
          .filter(user => user.statut === 'actif')
          .map(user => {
            const userId = String(user._id || user.id || user.user_id);
            const pointage = pointagesMap.get(userId);

            const fullDate = pointage?.date; // ex: "2025-07-06"
            const heureEntreeStr = pointage?.premierPointage || pointage?.premierPointage_temp || null;
            const heureSortieStr = pointage?.dernierPointage || null;

            const heureEntree = (heureEntreeStr && fullDate) ? new Date(`${fullDate}T${heureEntreeStr}`) : null;
            const heureSortie = (heureSortieStr && fullDate) ? new Date(`${fullDate}T${heureSortieStr}`) : null;


            console.log(`🔄 Mapping pour ${user.nom}:`, {
              user,
              userId,
              pointageTrouvé: !!pointage,
              heureEntree,
              heureSortie
            });

            return {
              ...user,
              pointage: pointage ? {
                ...pointage,
                heureEntree,
                heureSortie
              } : undefined,
              estPresent: pointage?.statut === 'Présent',
              estRetard: pointage?.statut === 'Retard',
              estEnAttente: pointage?.statut === 'En attente'
            };

          });

        return {
          status: true,
          message: 'Utilisateurs actifs avec pointages',
          data: utilisateursActifs
        };
      } else {
        console.warn('⚠️ Échec récupération utilisateurs ou pointages');
        return {
          status: false,
          message: 'Échec récupération utilisateurs ou pointages',
          data: []
        };
      }
    }),
    tap(response => {
      console.log('✅ Réponse finale retournée au composant:', response);
    }),
    catchError(this.handleError('récupération des utilisateurs'))
  );
}

getPointagesJour(): Observable<ApiResponse<any[]>> {
  console.log('📡 Appel de getPointagesJour');
  return this.http.get<ApiResponse<any[]>>(`${this.apiUrl}/pointages/jour`, {
    headers: this.getAuthHeaders()
  }).pipe(
    tap(response => {
      console.log('📥 Réponse brute de getPointagesJour:', response);
      if (response.status) {
        this.currentPointagesList.next(response.data);
        console.log('📌 Liste des pointages mise à jour:', response.data);
      }
    }),
    catchError(error => {
      console.error('❌ Erreur dans getPointagesJour:', error);
      return this.handleError('récupération des pointages du jour')(error);
    })
  );
}


  controlDoor(command: 'OPEN' | 'CLOSE'): Observable<any> {
    return new Observable(observer => {
      this.socket.emit('door-control', command);
      observer.next({ status: true });
      observer.complete();
    });
  }

  getDoorStatus(): Observable<any> {
    return new Observable(observer => {
      this.socket.on('door-status', (response: any) => {
        observer.next(response);
      });
    });
  }

  validerPointage(cardId: string, vigileId: string, action: 'valider' | 'rejeter'): Promise<PointageResponse> {
    return this.http.post<PointageResponse>(
      `${this.apiUrl}/pointages/cartes/${cardId}/valider`,
      {
        vigile_id: vigileId,
        action: action
      },
      { headers: this.getAuthHeaders() }
    ).pipe(
      tap(response => console.log('Réponse validation:', response)),
      catchError(error => {
        console.error('Erreur validation:', error);
        throw error;
      })
    ).toPromise();
  }

  getCurrentPointage(): Observable<Pointage | null> {
    return this.currentPointage.asObservable();
  }

  getPointagesEnAttente(): Observable<Pointage[]> {
    return this.pointagesEnAttente.asObservable();
  }

  getErrors(): Observable<{ message: string; cardId?: string; code?: number }> {
    return this.errorSubject.asObservable();
  }

  getCurrentPointagesList(): Observable<any[]> {
    return this.currentPointagesList.asObservable();
  }

  connect() {
    if (!this.socket.connected) {
      this.socket.connect();
    }
  }

  disconnect() {
    if (this.socket.connected) {
      this.socket.disconnect();
    }
  }

  isConnected(): boolean {
    return this.socket.connected;
  }

  refreshPointages() {
    this.getPointagesJour().subscribe();
  }

  removePointageEnAttente(cardId: string) {
    const currentPointages = this.pointagesEnAttente.value;
    const updatedPointages = currentPointages.filter(p => p.cardId !== cardId);
    this.pointagesEnAttente.next(updatedPointages);
  }

  reset() {
    this.currentPointage.next(null);
    this.pointagesEnAttente.next([]);
    this.currentPointagesList.next([]);
  }

  ngOnDestroy() {
    if (this.socket) {
      this.socket.disconnect();
    }
  }
}
