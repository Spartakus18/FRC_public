<?php

namespace App\Models\Parametre;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role_user extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_roles',
    ];

    public function user() {
        return $this->hasOne(User::class);
    }
}
