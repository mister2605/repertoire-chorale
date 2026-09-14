<?php

namespace App\Http\Controllers;

use App\Models\Celebration;
use App\Models\CelebrationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CelebrationController extends Controller
{
    // GET /api/celebrations?passees=1
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Celebration::class);

        $celebrations = Celebration::query()
            ->visiblesPar($request->user())
            ->when(
                $request->boolean('passees'),
                fn ($q) => $q->passees(),
                fn ($q) => $q->aVenir(),
            )
            ->with(['items.chant:id,titre,tonalite'])
            ->get();

        return response()->json($celebrations);
    }

    // GET /api/celebrations/{celebration}
    public function show(Celebration $celebration)
    {
        Gate::authorize('view', $celebration);

        $celebration->load(['items.chant:id,titre,tonalite', 'auteur:id,name']);

        return response()->json($celebration);
    }

    // POST /api/celebrations
    public function store(Request $request)
    {
        Gate::authorize('create', Celebration::class);

        $valide = $this->valider($request);

        $celebration = DB::transaction(function () use ($valide, $request) {
            $celebration = Celebration::create([
                'type' => $valide['type'],
                'titre' => $valide['titre'] ?? null,
                'debut_le' => $valide['debut_le'],
                'lieu' => $valide['lieu'] ?? null,
                'notes' => $valide['notes'] ?? null,
                'publiee_le' => ($valide['publiee'] ?? false) ? now() : null,
                'created_by' => $request->user()->id,
            ]);

            $this->remplacerItems($celebration, $valide['items'] ?? []);

            return $celebration;
        });

        return response()->json($celebration->load('items.chant:id,titre,tonalite'), 201);
    }

    // PUT /api/celebrations/{celebration}
    public function update(Request $request, Celebration $celebration)
    {
        Gate::authorize('update', $celebration);

        $valide = $this->valider($request);

        DB::transaction(function () use ($celebration, $valide) {
            $celebration->update([
                'type' => $valide['type'],
                'titre' => $valide['titre'] ?? null,
                'debut_le' => $valide['debut_le'],
                'lieu' => $valide['lieu'] ?? null,
                'notes' => $valide['notes'] ?? null,
                // On ne dépublie pas par omission : publier est une décision,
                // dépublier aussi. Le champ n'est touché que s'il est envoyé.
                'publiee_le' => array_key_exists('publiee', $valide)
                    ? ($valide['publiee'] ? ($celebration->publiee_le ?? now()) : null)
                    : $celebration->publiee_le,
            ]);

            $this->remplacerItems($celebration, $valide['items'] ?? []);
        });

        return response()->json($celebration->fresh()->load('items.chant:id,titre,tonalite'));
    }

    // DELETE /api/celebrations/{celebration}
    public function destroy(Celebration $celebration)
    {
        Gate::authorize('delete', $celebration);

        $celebration->delete();

        return response()->json(['message' => 'Célébration supprimée.']);
    }

    /**
     * PATCH /api/celebrations/{celebration}/items/{item}/note-instrument
     *
     * La seule écriture ouverte aux instrumentistes. Volontairement étroite :
     * un endpoint qui ne sait modifier qu'un champ ne peut pas, par accident
     * ou par requête forgée, servir à réécrire le programme.
     */
    public function annoter(Request $request, Celebration $celebration, CelebrationItem $item)
    {
        Gate::authorize('annoter', $celebration);

        abort_unless($item->celebration_id === $celebration->id, 404);

        $valide = $request->validate([
            'note_instrument' => 'nullable|string|max:2000',
        ]);

        $item->update(['note_instrument' => $valide['note_instrument'] ?? null]);

        return response()->json($item->fresh()->load('chant:id,titre,tonalite'));
    }

    // --- Interne ---

    protected function valider(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(Celebration::TYPES)],
            'titre' => 'nullable|string|max:255',
            'debut_le' => 'required|date',
            'lieu' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'publiee' => 'sometimes|boolean',

            'items' => 'array|max:60',
            'items.*.moment' => 'required|string|max:80',
            // Le chant doit appartenir à la chorale. Le "where" n'est pas
            // décoratif : exists() interroge la table directement et ne passe
            // PAS par le filtre automatique de chorale. Sans lui, un
            // identifiant envoyé à la main ferait apparaître dans le programme
            // le titre d'un chant d'une autre chorale.
            'items.*.chant_id' => [
                'nullable', 'integer',
                Rule::exists('chants', 'id')->where('chorale_id', $request->user()->chorale_id),
            ],
            'items.*.titre_libre' => 'nullable|string|max:255',
            'items.*.notes' => 'nullable|string|max:2000',
            'items.*.note_instrument' => 'nullable|string|max:2000',
        ], [], [
            'debut_le' => 'date',
            'items.*.moment' => 'moment',
        ]);
    }

    /**
     * Remplace la liste des lignes. On réécrit tout plutôt que de calculer un
     * différentiel : un programme fait une douzaine de lignes, et le code qui
     * calcule des différentiels est le premier endroit où se cachent les bugs
     * d'ordre et de doublons.
     *
     * Les notes d'instrumentiste, elles, sont conservées : elles n'appartiennent
     * pas au maître de chœur, et les perdre à chaque enregistrement serait
     * une mauvaise surprise pour l'organiste.
     */
    protected function remplacerItems(Celebration $celebration, array $items): void
    {
        $notesConservees = $celebration->items()
            ->get(['moment', 'note_instrument'])
            ->filter(fn ($i) => filled($i->note_instrument))
            ->pluck('note_instrument', 'moment');

        $celebration->items()->delete();

        foreach (array_values($items) as $position => $ligne) {
            // Une ligne sans chant ni titre écrit à la main n'a rien à dire :
            // on ne la garde pas plutôt que d'afficher une ligne vide.
            if (blank($ligne['chant_id'] ?? null) && blank($ligne['titre_libre'] ?? null)) {
                continue;
            }

            $celebration->items()->create([
                'chorale_id' => $celebration->chorale_id,
                'position' => $position,
                'moment' => $ligne['moment'],
                'chant_id' => $ligne['chant_id'] ?? null,
                'titre_libre' => blank($ligne['chant_id'] ?? null) ? ($ligne['titre_libre'] ?? null) : null,
                'notes' => $ligne['notes'] ?? null,
                'note_instrument' => $ligne['note_instrument'] ?? $notesConservees[$ligne['moment']] ?? null,
            ]);
        }
    }
}
