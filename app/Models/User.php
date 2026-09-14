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
     * Les rôles connus, et le seul endroit où ils sont listés.
     *
     * Ils étaient auparavant figés dans un ENUM en base ET répétés dans les
     * règles de validation : ajouter "instrumentiste" demandait de penser aux
     * deux, et en oublier un se voyait seulement à l'usage. Une seule liste,
     * ici, référencée partout.
     */
    public const ROLES = ['maitre_choeur', 'choriste', 'instrumentiste'];

    /**
     * Les attributs qu'on peut remplir en masse (via create()/update()).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',        // voir User::ROLES
        'pupitre_id',  // sa voix principale (Soprano, Alto, Ténor, Basse)
        'chorale_id',  // la chorale à laquelle il appartient
        'active_le',   // null tant que la personne n'a pas choisi son mot de passe
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
            'active_le' => 'datetime',
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

    /**
     * Organiste, percussionniste, guitariste... Il lit le programme comme tout
     * le monde et peut y ajouter SA note (tonalité jouée, intro, tempo), mais
     * il ne crée ni ne supprime rien. Voir CelebrationPolicy.
     */
    public function estInstrumentiste(): bool
    {
        return $this->role === 'instrumentiste';
    }

    /**
     * Le compte est-il utilisable ? Un compte cree a la main par le maitre de
     * choeur a un mot de passe aleatoire : il reste inactif tant que la
     * personne n'a pas ouvert son lien d'invitation.
     */
    public function estActive(): bool
    {
        return $this->active_le !== null;
    }

    /** Deux utilisateurs sont-ils dans la même chorale ? */
    public function memeChoraleQue(?int $choraleId): bool
    {
        return $choraleId !== null && $this->chorale_id === $choraleId;
    }
}
