import { Component, OnInit, OnDestroy } from '@angular/core';
import { Subscription, firstValueFrom } from 'rxjs';
import { PointageService } from '../../services/pointage.service';
import { UtilisateurPresence, DernierPointage } from '../pointage/pointage.interface';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NavBarComponent } from '../../../app/theme/layouts/admin-layout/nav-bar/nav-bar.component';
import { AuthService } from '../../services/Service1/auth.service';

interface NouveauPointage {
  cardId: string;
  matricule: string;
  nom: string;
  prenom: string;
  photo?: string;
  fonction?: string;
}

@Component({
  selector: 'app-dashboard-vigile',
  standalone: true,
  imports: [CommonModule, FormsModule, NavBarComponent],
  templateUrl: './dashboard-vigile.component.html',
  styleUrls: ['./dashboard-vigile.component.scss']
})
export class DashboardVigileComponent implements OnInit, OnDestroy {
  currentDate = new Date();
  doorStatus: 'open' | 'closed' = 'closed';
  loading = true;
  error: string | null = null;
  showReconnectingMessage = false;
  isSocketConnected = false;
  cardError: { cardId: string, message: string, code: number } | null = null;
  navCollapsed = false;
  navCollapsedMob = false;
  nouveauPointage: NouveauPointage | null = null;
  derniersPointages: DernierPointage[] = [];
  utilisateurs: UtilisateurPresence[] = [];
  searchQuery = '';
  currentPage = 1;
  itemsPerPage = 6;
  doorCommand: 'OPEN' | 'CLOSE' = 'CLOSE';
  private subscriptions: Subscription[] = [];

  constructor(
    private pointageService: PointageService,
    private authService: AuthService
  ) {
    setInterval(() => {
      this.currentDate = new Date();
    }, 1000);

    this.subscriptions.push(
      this.pointageService.cardError$.subscribe(error => {
        if (error) {
          this.cardError = error;
          document.body.style.overflow = 'hidden';
          setTimeout(() => this.closeCardError(), 5000);
        }
      })
    );
  }

  ngOnInit(): void {
    this.initializeComponent();
  }

  private initializeComponent(): void {
    this.subscriptions.push(
      this.pointageService.getSocketConnectionStatus().subscribe(connected => {
        this.isSocketConnected = connected;
        this.showReconnectingMessage = !connected;
      })
    );

    this.isSocketConnected = this.pointageService.isConnected();
    this.subscribeToSocketEvents();
    this.loadData();
  }

  private subscribeToSocketEvents(): void {
    this.subscriptions.push(
      this.pointageService.getCurrentPointage().subscribe(pointage => {
        if (!pointage || !pointage.utilisateur) return;

        this.nouveauPointage = {
          cardId: pointage.cardId,
          matricule: pointage.utilisateur.matricule,
          nom: pointage.utilisateur.nom || '',
          prenom: pointage.utilisateur.prenom || '',
          photo: pointage.utilisateur.photo,
          fonction: pointage.utilisateur.fonction
        };

        this.loadUtilisateurs();
        this.loadDerniersPointages();
      })
    );

    this.subscriptions.push(
      this.pointageService.getCurrentPointagesList().subscribe(pointages => {
        this.utilisateurs = this.utilisateurs.map(user => {
          const pointage = pointages.find(p => p.user_id === user._id);
          return {
            ...user,
            status: pointage ? this.determinerStatus({
              estPresent: pointage.estPresent,
              estRetard: pointage.estRetard,
              estEnAttente: pointage.estEnAttente
            }) : 'Absent',
            entree: pointage?.premierPointage ? new Date(pointage.premierPointage).toLocaleTimeString() : '—',
            sortie: pointage?.dernierPointage ? new Date(pointage.dernierPointage).toLocaleTimeString() : '—'
          };
        });
      })
    );

    this.subscriptions.push(
      this.pointageService.getDoorStatus().subscribe(
        (response) => {
          if (response.status) this.doorCommand = response.command;
        },
        () => this.error = "Erreur lors de la mise à jour du statut de la porte"
      )
    );
  }

  private async loadData(): Promise<void> {
    try {
      this.loading = true;
      this.error = null;
      const vigileInfo = await this.authService.isVigile().toPromise();
      if (!vigileInfo?.isVigile) throw new Error("Accès non autorisé - Rôle vigile requis");

      await Promise.all([this.loadUtilisateurs(), this.loadDerniersPointages()]);
    } catch (err) {
      this.error = err instanceof Error ? err.message : "Erreur lors du chargement des données";
    } finally {
      this.loading = false;
    }
  }


  formatHeure(date: Date | string | null | undefined): string {
  if (!date) {
    console.warn('⚠️ Heure invalide :', date);
    return '—';
  }

  const d = new Date(date);
  return isNaN(d.getTime())
    ? '—'
    : d.toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
      });
}



private async loadUtilisateurs(): Promise<void> {
  try {
    const response = await firstValueFrom(this.pointageService.getAllUtilisateursActifs());
    if (response?.status) {
      
      // Fonction pour formater une date jj/mm/aaaa
      const formatDate = (date: string | Date | undefined | null): string => {
        if (!date) return '—';
        const d = new Date(date);
        return isNaN(d.getTime())
          ? '—'
          : d.toLocaleDateString('fr-FR');
      };

      this.utilisateurs = response.data.map(user => {
        const pointage = user.pointage || null;

        const entree = this.formatHeure(pointage?.heureEntree);
        const sortie = this.formatHeure(pointage?.heureSortie);

        // ✅ Correction ici : on utilise pointage.date (qui est "2025-07-07")
        const date = formatDate(pointage?.date); 

        const status = this.determinerStatus({
          estPresent: user.estPresent ?? false,
          estRetard: user.estRetard ?? false,
          estEnAttente: user.estEnAttente ?? false,
        });

        return {
          _id: user._id,
          matricule: user.matricule || '',
          nom: user.nom || '',
          prenom: user.prenom || '',
          photo: user.photo || 'assets/images/user/avatar-1.jpg',
          type: user.type || '',
          status,
          entree,
          sortie,
          date, // ✅ Date bien formatée
          cardId: user.cardId || '',
          pointageTrouve: !!pointage,
          pointage: {
            ...pointage,
            heureEntree: entree,
            heureSortie: sortie,
            date,
            retard: user.estRetard ?? false,
          },
        };
      });
    }
  } catch {
    this.error = "Erreur lors du chargement des utilisateurs";
  }
}


  private async loadDerniersPointages(): Promise<void> {
    const response = await this.pointageService.getPointagesJour().toPromise();
    if (response?.status) {
      this.derniersPointages = this.formatDerniersPointages(response.data || []);
    }
  }

  private formatDerniersPointages(data: any[]): DernierPointage[] {
    return data.map(pointage => ({
      matricule: pointage.utilisateur?.matricule || '',
      cardId: pointage.cardId,
      heure: new Date(pointage.date).toLocaleTimeString(),
      nom: pointage.utilisateur?.nom || '',
      prenom: pointage.utilisateur?.prenom || '',
      status: this.determinerStatus({
        estPresent: pointage.estPresent || false,
        estRetard: pointage.estRetard || false,
        estEnAttente: pointage.estEnAttente || false
      })
    }));
  }

  private determinerStatus(opts: { estPresent: boolean; estRetard: boolean; estEnAttente: boolean }): 'Présent' | 'Retard' | 'Absent' {
    if (opts.estRetard) return 'Retard';
    if (opts.estPresent) return 'Présent';
    return 'Absent';
  }

  async validerPointage(cardId: string): Promise<void> {
    if (!this.isSocketConnected) return;
    try {
      const vigileId = localStorage.getItem('vigile_id');
      if (!vigileId) throw new Error("Session vigile invalide");
      const response = await this.pointageService.validerPointage(cardId, vigileId, 'valider');
      if (response.status) {
        this.nouveauPointage = null;
        await this.loadData();
      }
    } catch (err) {
      this.error = err instanceof Error ? err.message : "Erreur lors de la validation";
    }
  }

  async refuserPointage(cardId: string): Promise<void> {
    if (!this.isSocketConnected) return;
    try {
      const vigileId = localStorage.getItem('vigile_id');
      if (!vigileId) throw new Error("Session vigile invalide");
      const response = await this.pointageService.validerPointage(cardId, vigileId, 'rejeter');
      if (response.status) {
        this.nouveauPointage = null;
        await this.loadData();
      }
    } catch (err) {
      this.error = err instanceof Error ? err.message : "Erreur lors du rejet";
    }
  }

  get filteredUtilisateurs(): UtilisateurPresence[] {
    const search = this.searchQuery.toLowerCase().trim();
    if (!search) return this.utilisateurs;
    return this.utilisateurs.filter(user =>
      user.nom.toLowerCase().includes(search) ||
      user.prenom.toLowerCase().includes(search) ||
      user.matricule.toLowerCase().includes(search)
    );
  }

  get paginatedUtilisateurs(): UtilisateurPresence[] {
    const start = (this.currentPage - 1) * this.itemsPerPage;
    return this.filteredUtilisateurs
      .sort((a, b) => {
        if ((a.status === 'Présent') !== (b.status === 'Présent')) return a.status === 'Présent' ? -1 : 1;
        const timeA = a.entree !== '—' ? new Date('1970-01-01T' + a.entree).getTime() : 0;
        const timeB = b.entree !== '—' ? new Date('1970-01-01T' + b.entree).getTime() : 0;
        return timeB - timeA;
      })
      .slice(start, start + this.itemsPerPage);
  }

  get totalPages(): number {
    return Math.ceil(this.filteredUtilisateurs.length / this.itemsPerPage);
  }

  controlDoor(command: 'OPEN' | 'CLOSE'): void {
    if (this.isSocketConnected) {
      this.pointageService.controlDoor(command).subscribe(
        (response) => {
          if (response.status) this.doorCommand = command;
        },
        () => this.error = "Erreur lors du contrôle de la porte"
      );
    } else {
      this.error = "Impossible de contrôler la porte : connexion perdue";
    }
  }

  navMobClick() {
    this.navCollapsedMob = !this.navCollapsedMob;
  }

  closeCardError(): void {
    this.cardError = null;
    document.body.style.overflow = 'auto';
  }

  ngOnDestroy(): void {
    this.subscriptions.forEach(sub => sub.unsubscribe());
    this.pointageService.reset();
    document.body.style.overflow = 'auto';
  }
}
