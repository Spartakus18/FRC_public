<?php

namespace App\Models\Location;

use App\Models\Consommable\Gasoil;
use App\Models\Gasoil\Transfert;
use App\Models\Parametre\Pneu;
use App\Models\Produit\Produit;
use App\Models\Produit\Vente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicule extends Model
{
    use HasFactory;

    protected $fillable = ['nom_vehicule', 'status', 'nbr_pneu'];

    public function pneu()
    {
        return $this->hasMany(Pneu::class);
    }

    public function location()
    {
        return $this->hasMany(Location::class);
    }

    public function nouvel()
    {
        return $this->hasMany(Produit::class);
    }

    public function vente()
    {
        return $this->hasMany(Vente::class);
    }

    public function transfert()
    {
        return $this->hasOne(Transfert::class);
    }

    /* Gestion des gasoils */

    // Gasoil reçu
    public function gasoilsCible()
    {
        return $this->hasMany(Gasoil::class, 'vehicule_id_cible');
    }

    // Gasoil donné (transfert)
    public function gasoilsSelect()
    {
        return $this->hasMany(Gasoil::class, 'vehicule_id_select');
    }
}
