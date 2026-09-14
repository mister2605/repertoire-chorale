<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

/**
 * Les catégories (temps liturgiques, thématiques) sortent elles aussi du
 * seeder. Une paroisse n'a pas le même découpage qu'une autre, et un mariage
 * ou une veillée peuvent mériter leur propre entrée.
 */
class CategorieController extends Controller
{
    // GET /api/categories
    public function index()
    {
        Gate::authorize('viewAny', Categorie::class);

        return response()->json(
            Categorie::orderBy('nom')->withCount('chants')->get()
        );
    }

    // POST /api/categories
    public function store(Request $request)
    {
        Gate::authorize('create', Categorie::class);

        $valide = $request->validate([
            'nom' => ['required', 'string', 'max:100', $this->nomUnique($request)],
        ]);

        $categorie = Categorie::create($valide);

        return response()->json($categorie->loadCount('chants'), 201);
    }

    // PUT /api/categories/{categorie}
    public function update(Request $request, Categorie $categorie)
    {
        Gate::authorize('update', $categorie);

        $valide = $request->validate([
            'nom' => ['required', 'string', 'max:100', $this->nomUnique($request, $categorie->id)],
        ]);

        $categorie->update($valide);

        return response()->json($categorie->fresh()->loadCount('chants'));
    }

    // DELETE /api/categories/{categorie}
    public function destroy(Categorie $categorie)
    {
        Gate::authorize('delete', $categorie);

        // Supprimer une catégorie utilisée la retirerait silencieusement de
        // tous les chants concernés. On préfère un refus explicite : dans la
        // plupart des cas, ce que veut le maître de chœur, c'est renommer.
        $nbChants = $categorie->chants()->count();

        if ($nbChants > 0) {
            throw ValidationException::withMessages([
                'nom' => "Cette catégorie est utilisée par {$nbChants} chant(s). "
                    .'Renomme-la, ou retire-la de ces chants avant de la supprimer.',
            ]);
        }

        $categorie->delete();

        return response()->json(['message' => 'Catégorie supprimée.']);
    }

    /** Deux catégories ne peuvent pas porter le même nom DANS UNE MÊME chorale. */
    protected function nomUnique(Request $request, ?int $ignorer = null): Unique
    {
        $regle = Rule::unique('categories', 'nom')
            ->where('chorale_id', $request->user()->chorale_id);

        return $ignorer ? $regle->ignore($ignorer) : $regle;
    }
}
