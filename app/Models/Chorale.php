<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une chorale = un "locataire" (tenant) de la plateforme.
 *
 * Tout le reste (chants, pupitres, catégories, membres) appartient à
 * exactement une chorale. C'est la table qui permet à Chorabase de servir
 * plusieurs chorales sans qu'aucune ne voie les données d'une autre.
 */
class Chorale extends Model
{
    protected $fillable = ['nom', 'slug', 'ville', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function membres(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function chants(): HasMany
    {
        return $this->hasMany(Chant::class);
    }

    public function pupitres(): HasMany
    {
        return $this->hasMany(Pupitre::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Categorie::class);
    }
}
