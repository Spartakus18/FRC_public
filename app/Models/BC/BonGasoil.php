<?php

namespace App\Models\BC;

use App\Models\AjustementStock\Lieu_stockage;
use App\Models\Consommable\Gasoil;
use App\Models\Parametre\Materiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonGasoil extends Model
{
    use HasFactory;
    protected $table = "bon_gasoils";
    protected $fillable = [
        'num_bon',
        'ajouter_par',
        'modifier_par',
        'quantite',
        'source_lieu_stockage_id',
    ];


    public function gasoil()
    {
        return $this->hasMany(Gasoil::class, "bon_id");
    }

    public function sourceStockage()
    {
        return $this->belongsTo(Lieu_stockage::class, 'source_lieu_stockage_id');
    }

    /**
     * Génère un numéro de bon selon le format: 001/01/2016-G
     *
     * @param string $type Le type de bon (approStock, achat, transfert)
     * @param int|null $sequenceNumber Numéro séquentiel spécifique (optionnel)
     * @return string Le numéro de bon généré
     */
    public static function generateBonNumber(string $type, ?int $sequenceNumber = null): string
    {
        // Mapping des types vers leurs codes
        $typeCodes = [
            'approStock' => '01',
            'achat' => '02',
            'transfert' => '03'
        ];

        // Vérification du type
        if (!isset($typeCodes[$type])) {
            throw new \InvalidArgumentException("Type de bon invalide. Types acceptés: approStock, achat, transfert");
        }

        $typeCode = $typeCodes[$type];
        $currentYear = date('Y');

        // Si un numéro séquentiel est spécifié
        if ($sequenceNumber !== null) {
            // Vérifier que le numéro est positif
            if ($sequenceNumber <= 0) {
                throw new \InvalidArgumentException("Le numéro séquentiel doit être supérieur à 0");
            }

            // Formater le numéro avec 3 chiffres (001, 012, etc.)
            $sequentialNumber = str_pad($sequenceNumber, 3, '0', STR_PAD_LEFT);

            return "{$sequentialNumber}/{$typeCode}/{$currentYear}-G";
        }

        // AUTO-INCRÉMENTATION : si aucun numéro spécifié
        // Chercher le dernier numéro pour ce type et cette année avec suffixe -G
        $lastBon = self::where('num_bon', 'LIKE', "%/{$typeCode}/{$currentYear}-G")
            ->orderBy('num_bon', 'desc')
            ->first();

        if ($lastBon) {
            // Extraire le numéro séquentiel du dernier bon
            // Format: 001/01/2010-G
            $parts = explode('/', $lastBon->num_bon);
            $lastNumber = intval($parts[0]);
            $nextNumber = $lastNumber + 1;
        } else {
            // Premier bon de ce type pour cette année
            $nextNumber = 1;
        }

        // Formater le numéro avec 3 chiffres (001, 002, etc.)
        $sequentialNumber = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        return "{$sequentialNumber}/{$typeCode}/{$currentYear}-G";
    }

    /**
     * Récupère le prochain numéro disponible (auto-incrémenté) pour un type donné
     *
     * @param string $type Le type de bon (approStock, achat, transfert)
     * @return int Le prochain numéro disponible
     */
    public static function getNextAvailableNumber(string $type): int
    {
        $typeCodes = [
            'approStock' => '01',
            'achat' => '02',
            'transfert' => '03'
        ];

        if (!isset($typeCodes[$type])) {
            throw new \InvalidArgumentException("Type de bon invalide");
        }

        $typeCode = $typeCodes[$type];
        $currentYear = date('Y');

        $lastBon = self::where('num_bon', 'LIKE', "%/{$typeCode}/{$currentYear}-G")
            ->orderBy('num_bon', 'desc')
            ->first();

        if ($lastBon) {
            $parts = explode('/', $lastBon->num_bon);
            $lastNumber = intval($parts[0]);
            return $lastNumber + 1;
        }

        return 1;
    }
}
