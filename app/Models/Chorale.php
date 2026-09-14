<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Une chorale = un "locataire" (tenant) de la plateforme.
 *
 * Tout le reste (chants, pupitres, catégories, membres) appartient à
 * exactement une chorale. C'est la table qui permet à Chorabase de servir
 * plusieurs chorales sans qu'aucune ne voie les données d'une autre.
 */
class Chorale extends Model
{
    protected $fillable = ['nom', 'slug', 'ville', 'actif', 'code_adhesion', 'adhesion_ouverte'];

    // Le code d'adhésion est un secret : il ne sort jamais par accident
    // dans une réponse JSON. Le contrôleur le renvoie explicitement,
    // et seulement au maître de chœur.
    protected $hidden = ['code_adhesion'];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'adhesion_ouverte' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Chorale $chorale) {
            $chorale->code_adhesion ??= Str::random(24);
        });
    }

    /** Invalide l'ancien lien d'adhésion et en crée un nouveau. */
    public function regenererCodeAdhesion(): string
    {
        $this->update(['code_adhesion' => Str::random(24)]);

        return $this->code_adhesion;
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
