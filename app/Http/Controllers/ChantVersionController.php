<?php

namespace App\Http\Controllers;

use App\Models\Chant;
use App\Models\ChantVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChantVersionController extends Controller
{
    // GET /api/chants/{chant}/versions — l'historique des paroles
    public function index(Chant $chant)
    {
        Gate::authorize('view', $chant);

        $versions = $chant->versions()->with('auteur:id,name')->get();

        return response()->json($versions);
    }

    // POST /api/chants/{chant}/versions/{version}/restaurer
    public function restaurer(Request $request, Chant $chant, ChantVersion $version)
    {
        Gate::authorize('gererVersions', $chant);

        // Une version appartient à un chant : on refuse de restaurer
        // la version d'un autre chant, même dans la même chorale.
        if ($version->chant_id !== $chant->id) {
            abort(404);
        }

        // On passe par mettreAJourParoles : la version actuelle est archivée
        // avant d'être remplacée. Restaurer n'efface donc jamais rien —
        // on peut annuler une restauration en restaurant la précédente.
        $chant->mettreAJourParoles($version->paroles, $request->user());

        return response()->json($chant->fresh()->load(['versions.auteur', 'categories', 'pupitres', 'auteur']));
    }
}
