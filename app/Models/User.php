<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * ATTENTION — User n'utilise VOLONTAIREMENT pas le trait AppartientAChorale.
 *
 * Au moment du login, personne n'est encore connecté : un filtre automatique
 * sur la chorale renverrait zéro utilisateur et rendrait la connexion
 * impossible. Le rattachement se fait donc par la colonne chorale_id, et
 * le filtrage des listes de membres se fait explicitement avec
 * User::deMaChorale() dans les contrôleurs.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Les attributs qu'on peut remplir en masse (via create()/update()).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',        // 'maitre_choeur' ou 'choriste'
        'pupitre_id',  // sa voix principale (Soprano, Alto, Ténor, Basse)
        'chorale_id',  // la chorale à laquelle il appartient
    ];

    /**
     * Les attributs à cacher lors de la sérialisation (ex: dans une réponse API).
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Les attributs à convertir automatiquement.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // --- Relations ---

    public function pupitre(): BelongsTo
    {
        return $this->belongsTo(Pupitre::class);
    }

    public function chorale(): BelongsTo
    {
        return $this->belongsTo(Chorale::class);
    }

    // --- Filtrage explicite ---

    /** Les membres de la chorale de l'utilisateur connecté. */
    public function scopeDeMaChorale(Builder $query): Builder
    {
        return $query->where('chorale_id', auth()->user()?->chorale_id ?? 0);
    }

    // --- Aides pour les rôles ---

    public function estMaitreDeChoeur(): bool
    {
        return $this->role === 'maitre_choeur';
    }

    public function estChoriste(): bool
    {
        return $this->role === 'choriste';
    }

    /** Deux utilisateurs sont-ils dans la même chorale ? */
    public function memeChoraleQue(?int $choraleId): bool
    {
        return $choraleId !== null && $this->chorale_id === $choraleId;
    }
}
