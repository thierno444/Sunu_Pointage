<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PointageResource extends JsonResource
{
    public function toArray($request)
    {
        \Log::info('Utilisateur lié au pointage :', [
                'utilisateur' => $this->utilisateur
            ]);

        return [
            'id' => $this->_id,
            'date' => $this->date?->format('Y-m-d'),
            'statut' => $this->estRejete ? 'Rejeté' : (
                $this->estEnAttente ? 'En attente' : (
                    $this->estRetard ? 'Retard' : (
                        $this->estPresent ? 'Présent' : 'Absent'
                    )
                )
            ),
            'utilisateurs' => [
                'matricule' => $this->utilisateur?->matricule ?? 'N/A',
                'nom' => $this->utilisateur?->nom ?? 'N/A',
                'prenom' => $this->utilisateur?->prenom ?? 'N/A',
                'email' => $this->utilisateur?->email ?? 'N/A',
            ]
        ];
    }
}
