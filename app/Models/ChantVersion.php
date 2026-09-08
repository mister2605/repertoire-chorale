<?php

namespace App\Models;

use App\Models\Concerns\AppartientAChorale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChantVersion extends Model
{
    use AppartientAChorale;

    protected $fillable = ['chant_id', 'chorale_id', 'paroles', 'modifie_par'];

    public function chant(): BelongsTo
    {
        return $this->belongsTo(Chant::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }
}
