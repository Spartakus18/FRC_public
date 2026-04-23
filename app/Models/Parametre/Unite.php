<?php

namespace App\Models\Parametre;

use App\Models\AjustementStock\ArticleDepot;
use App\Models\BC\Bon_commande;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unite extends Model
{
    use HasFactory;

    protected $table = 'unites';

    protected $fillable = [
        'nom_unite',
    ];

    public function bonCommande() {
        return $this->hasMany(Bon_commande::class);
    }
}
