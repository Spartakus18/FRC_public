<?php

namespace App\Models\Huile;

use App\Models\Gasoil\MaterielFusion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfert extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
		'bon',
		'transfertDe' ,
		'subdivision1',
		'transfererA',
		'subdivision2',
		'article_id',
		'quantite',
    ];

    public function materiel1() {
        return $this->belongsTo(MaterielFusion::class, 'transfertDe');
    }
    public function materiel2() {
        return $this->belongsTo(MaterielFusion::class, 'transfererA');
    }
    public function subdivision1() {
        return $this->belongsTo(Subdivision::class, 'subdivision1');
    }
    public function subdivision2() {
        return $this->belongsTo(Subdivision::class, 'subdivision2');
    }
    public function article() {
        return $this->belongsTo(ArticleVersement::class, 'article_id');
    }
}
