<?php

namespace App\Models\Huile;

use App\Models\Gasoil\MaterielFusion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Versement extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
		'bon',
		'materiel_id',
		'subdivision_id',
		'article_id',
		'quantite',
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
