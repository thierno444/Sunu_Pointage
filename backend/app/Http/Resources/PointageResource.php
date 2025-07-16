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

    $premierPointage = $this->premierPointage ?? $this->premierPointage_temp;
    $dernierPointage = $this->dernierPointage ?? $this->dernierPointage_temp;

    return [
        'id' => $this->_id,
        'date' => optional($this->date)->format('Y-m-d'),
        'premierPointage' => $premierPointage ? date('H:i', strtotime($premierPointage)) : null,
        'dernierPointage' => $dernierPointage ? date('H:i', strtotime($dernierPointage)) : null,
        'statut' => $this->estRejete ? 'Rejeté' : (
            $this->estEnAttente ? 'En attente' : (
                $this->estRetard || $this->estRetard_temp ? 'Retard' : (
                    $this->estPresent ? 'Présent' : 'Absent'
                )
            )
        ),
        'utilisateurs' => [
            'id' => $this->utilisateur->_id ?? null,
            'matricule' => $this->utilisateur?->matricule ?? 'N/A',
            'nom' => $this->utilisateur?->nom ?? 'N/A',
            'prenom' => $this->utilisateur?->prenom ?? 'N/A',
            'email' => $this->utilisateur?->email ?? 'N/A',
        ]
    ];
}


}
