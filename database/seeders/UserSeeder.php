<?php

namespace Database\Seeders;

use App\Models\Parametre\Depot;
use App\Models\Parametre\Role_user;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Exécute le seeding.
     */
    public function run(): void
    {
        // Vérifie si les tables roles et depots contiennent des données
        $adminRole = Role_user::firstOrCreate(['nom_role' => 'Administrateur']);
        $defaultDepot = Depot::firstOrCreate(['nom_depot' => 'Analamalotra']);

        // Crée l'utilisateur admin par défaut
        User::firstOrCreate(
            ['identifiant' => 'admin'],
            [
                'nom' => 'Administrateur',
                'role_id' => $adminRole->id,
                'depot_id' => $defaultDepot->id,
                'password' => Hash::make('administrateur'),
            ]
        );
    }
}
