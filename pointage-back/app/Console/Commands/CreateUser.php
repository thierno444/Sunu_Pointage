<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Hash;

class CreateUser extends Command
{
    protected $signature = 'create:user 
        {nom} 
        {prenom} 
        {email} 
        {password} 
        {role}
        {--telephone=} 
        {--matricule=} 
        {--fonction=} 
        {--cardId=}';

    protected $description = 'Créer un utilisateur dans la base de données';

    public function handle()
    {
        $utilisateur = Utilisateur::create([
            'nom' => $this->argument('nom'),
            'prenom' => $this->argument('prenom'),
            'email' => $this->argument('email'),
            'password' => Hash::make($this->argument('password')),
            'role' => $this->argument('role'),
            'telephone' => $this->option('telephone'),
            'matricule' => $this->option('matricule'),
            'fonction' => $this->option('fonction'),
            'cardId' => $this->option('cardId'),
            'type' => 'employe',
            'statut' => 'actif',
        ]);

        $this->info('Utilisateur créé avec succès : ' . $utilisateur->email);
    }
}
