<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ChantPupitre extends Pivot
{
    protected $appends = ['audio_url'];

    // Lien relatif, pour la même raison que dans Chant : une adresse complète
    // fabriquée depuis APP_URL devient fausse dès que l'application change
    // d'adresse, et l'audio tombe en panne tout seul.
    protected function audioUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->audio_path ? '/storage/'.ltrim($this->audio_path, '/') : null,
        );
    }
}
