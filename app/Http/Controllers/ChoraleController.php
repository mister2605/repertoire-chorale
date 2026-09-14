<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Réglages de la chorale, réservés au maître de chœur.
 * Pour l'instant : le lien d'adhésion (l'ouvrir, le fermer, le régénérer).
 */
class ChoraleController extends Controller
{
    // GET /api/chorale
    public function show(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        return response()->json($this->charge($request));
    }

    // PUT /api/chorale — renomme la chorale
    public function update(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $valide = $request->validate([
            'nom' => 'required|string|max:255',
            'ville' => 'nullable|string|max:255',
        ]);

        // Le slug ne bouge pas : il peut servir d'identifiant stable ailleurs.
        // Renommer l'affichage ne doit pas casser une référence existante.
        $request->user()->chorale->update($valide);

        return response()->json($this->charge($request));
    }

    // POST /api/chorale/adhesion — ouvre ou ferme l'adhésion
    public function basculerAdhesion(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $valide = $request->validate(['ouverte' => 'required|boolean']);

        $chorale = $request->user()->chorale;
        $chorale->update(['adhesion_ouverte' => $valide['ouverte']]);

        return response()->json($this->charge($request));
    }

    // POST /api/chorale/adhesion/regenerer — invalide l'ancien lien
    public function regenererAdhesion(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $request->user()->chorale->regenererCodeAdhesion();

        return response()->json($this->charge($request));
    }

    protected function charge(Request $request): array
    {
        $chorale = $request->user()->chorale->fresh();

        return [
            'nom' => $chorale->nom,
            'ville' => $chorale->ville,
            'adhesion_ouverte' => $chorale->adhesion_ouverte,
            // Le code n'est jamais sérialisé automatiquement (il est dans $hidden) :
            // il ne sort qu'ici, pour un maître de chœur authentifié.
            'lien_adhesion' => rtrim(config('chorabase.frontend_url'), '/')
                .'/?adhesion='.$chorale->code_adhesion,
        ];
    }
}
