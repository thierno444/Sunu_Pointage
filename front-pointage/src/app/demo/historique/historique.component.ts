import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SharedModule } from 'src/app/theme/shared/shared.module';
import { IconService } from '@ant-design/icons-angular';
import { FallOutline, RiseOutline, SearchOutline } from '@ant-design/icons-angular/icons';
import { ApiService, Pointage } from 'src/app/services/api.service';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';


@Component({
  selector: 'app-historique',
  standalone: true,
  imports: [CommonModule, SharedModule],
  templateUrl: './historique.component.html',
  styleUrl: './historique.component.scss'
})
export class HistoriqueComponent implements OnInit {
  pointages: Pointage[] = [];
  filteredPointages: Pointage[] = [];
  searchQuery: string = '';
  currentPage: number = 1;
  itemsPerPage: number = 5;
  selectedDate: string = '';


  AnalyticEcommerce = [
  {
    title: 'Total Absence',
    background: 'bg-danger-subtle',
    border: 'border-danger',
    percentage: '0',
    color: 'text-danger'
  },
  {
    title: 'Total Présence',
    background: 'bg-success-subtle',
    border: 'border-success',
    percentage: '0',
    color: 'text-success'
  }
];


  constructor(private iconService: IconService, private apiService: ApiService) {
    this.iconService.addIcon(SearchOutline, RiseOutline, FallOutline);
  }

  ngOnInit(): void {
    this.loadPointages();
  }

  loadPointages() {
  this.apiService.getPointages().subscribe({
    next: data => {
      console.log('Pointages reçus:', data); // Pour vérifier

      this.pointages = data;
      this.filteredPointages = [...this.pointages];
      this.updateAnalytics();
    },
    error: error => {
      console.error('Erreur de chargement:', error);
    }
  });
}



  updateAnalytics() {
  const absents = this.pointages.filter(p => p.estPresent === false).length;
  const presents = this.pointages.filter(p => p.estPresent === true).length;

  this.AnalyticEcommerce[0].percentage = absents.toString();
  this.AnalyticEcommerce[1].percentage = presents.toString();
}

onDateChange(): void {
  if (!this.selectedDate) {
    this.filteredPointages = [...this.pointages];
  } else {
    this.filteredPointages = this.pointages.filter(p =>
      new Date(p.date).toISOString().split('T')[0] === this.selectedDate
    );
  }
  this.currentPage = 1;
}

exportToPDF(): void {
  const doc = new jsPDF();
  doc.text('Historique des pointages', 10, 10);

  const rows = this.filteredPointages.map(p => [
    p.user?.matricule || 'N/A',
    p.user?.nom || 'N/A',
    p.user?.prenom || 'N/A',
    p.user?.email || 'N/A',
    new Date(p.date).toLocaleDateString(),
    p.estPresent ? 'Présent' : 'Absent',
    p.estRetard ? 'Oui' : 'Non'
  ]);

  autoTable(doc, {
    head: [['Matricule', 'Nom', 'Prénom', 'Email', 'Date', 'Présence', 'Retard']],
    body: rows
  });

  doc.save('historique_pointages.pdf');
}


exportToExcel(): void {
  const worksheet = XLSX.utils.json_to_sheet(this.filteredPointages.map(p => ({
    Matricule: p.user?.matricule || 'N/A',
    Nom: p.user?.nom || 'N/A',
    Prénom: p.user?.prenom || 'N/A',
    Email: p.user?.email || 'N/A',
    Date: new Date(p.date).toLocaleDateString(),
    Présence: p.estPresent ? 'Présent' : 'Absent',
    Retard: p.estRetard ? 'Oui' : 'Non'
  })));

  const workbook = { Sheets: { data: worksheet }, SheetNames: ['data'] };
  const excelBuffer: any = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });

  const blob = new Blob([excelBuffer], { type: 'application/octet-stream' });
  saveAs(blob, 'historique_pointages.xlsx');
}



  onSearch(): void {
  const query = this.searchQuery.toLowerCase();
  this.filteredPointages = this.pointages.filter(p =>
    p.user?.nom?.toLowerCase().includes(query) ||
    p.user?.prenom?.toLowerCase().includes(query) ||
    p.user?.email?.toLowerCase().includes(query) ||
    p.user?.matricule?.toLowerCase().includes(query)
  );
  this.currentPage = 1;
}


  paginatedPointages(): Pointage[] {
    const startIndex = (this.currentPage - 1) * this.itemsPerPage;
    return this.filteredPointages.slice(startIndex, startIndex + this.itemsPerPage);
  }

  get totalPages(): number {
    return Math.ceil(this.filteredPointages.length / this.itemsPerPage);
  }

  nextPage() {
    if (this.currentPage < this.totalPages) this.currentPage++;
  }

  previousPage() {
    if (this.currentPage > 1) this.currentPage--;
  }

  loadPointagesForUser(userId: string) {
    this.apiService.getPointagesByUser(userId).subscribe({
      next: (data) => {
        this.pointages = data;
        this.filteredPointages = [...data];
        this.updateAnalytics();
      },
      error: (error) => {
        console.error('Erreur:', error);
      }
    });
  }
}

