<?php

namespace App\Models\Huile;

use App\Models\Gasoil\MaterielFusion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vidange extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
		'bon',
		'materiel_id',
		'compteur',
		'subdivision_id',
		'article_id',
		'quantite',
		'heure_vidange',
		'compteur_vidange',
    ];

    public function materiel() {
        return $this->belongsTo(MaterielFusion::class, 'materiel_id');
    }

    public function subdivision() {
        return $this->belongsTo(Subdivision::class, 'subdivision_id');
    }

    public function article() {
        return $this->belongsTo(ArticleVersement::class, 'article_id');
    }
}
