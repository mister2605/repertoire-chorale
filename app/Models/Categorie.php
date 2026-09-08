<?php

namespace App\Models;

use App\Models\Concerns\AppartientAChorale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Categorie extends Model
{
    use AppartientAChorale;

    protected $fillable = ['nom'];

    public function chants(): BelongsToMany
    {
        return $this->belongsToMany(Chant::class, 'chant_categorie');
    }
}
