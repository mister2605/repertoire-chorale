<?php

namespace App\Http\Controllers;

use App\Models\Pupitre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

/**
 * Les pupitres ne sont plus figés dans le seeder : chaque chorale gère
 * les siens. Une chorale à trois voix n'a pas à traîner un pupitre "Ténor"
 * vide, et une chorale qui ajoute "Soliste" n'a plus à toucher au code.
 */
class PupitreController extends Controller
{
    // GET /api/pupitres
    public function index()
    {
        Gate::authorize('viewAny', Pupitre::class);

        return response()->json(
            Pupitre::orderBy('nom')
                ->withCount(['chants', 'choristes'])
                ->get()
        );
    }

    // POST /api/pupitres
    public function store(Request $request)
    {
        Gate::authorize('create', Pupitre::class);

        $valide = $request->validate([
            'nom' => ['required', 'string', 'max:100', $this->nomUnique($request)],
        ]);

        // chorale_id est rempli automatiquement par le trait AppartientAChorale
        $pupitre = Pupitre::create($valide);

        return response()->json($pupitre->loadCount(['chants', 'choristes']), 201);
    }

    // PUT /api/pupitres/{pupitre}
    public function update(Request $request, Pupitre $pupitre)
    {
        Gate::authorize('update', $pupitre);

        $valide = $request->validate([
            'nom' => ['required', 'string', 'max:100', $this->nomUnique($request, $pupitre->id)],
        ]);

        $pupitre->update($valide);

        return response()->json($pupitre->fresh()->loadCount(['chants', 'choristes']));
    }

    // DELETE /api/pupitres/{pupitre}
    public function destroy(Pupitre $pupitre)
    {
        Gate::authorize('delete', $pupitre);

        // Supprimer un pupitre utilisé par des chants effacerait aussi, en
        // cascade, le lien vers l'enregistrement audio de cette voix pour
        // chacun de ces chants. C'est irréversible et invisible : on refuse.
        $nbChants = $pupitre->chants()->count();

        if ($nbChants > 0) {
            throw ValidationException::withMessages([
                'nom' => "Ce pupitre est utilisé par {$nbChants} chant(s). "
                    .'Retire-le de ces chants avant de le supprimer, ou renomme-le.',
            ]);
        }

        // Les choristes rattachés perdent simplement leur pupitre (mise à null
        // par la contrainte SQL) : rien d'irréversible, on laisse passer.
        $pupitre->delete();

        return response()->json(['message' => 'Pupitre supprimé.']);
    }

    /** Deux pupitres ne peuvent pas porter le même nom DANS UNE MÊME chorale. */
    protected function nomUnique(Request $request, ?int $ignorer = null): Unique
    {
        $regle = Rule::unique('pupitres', 'nom')
            ->where('chorale_id', $request->user()->chorale_id);

        return $ignorer ? $regle->ignore($ignorer) : $regle;
    }
}
