<?php

namespace App\Models\Parametre;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Depot extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_depot',
    ];

    public function user() {
        return $this->hasOne(User::class);
    }
}
