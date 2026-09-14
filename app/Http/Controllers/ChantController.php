<?php

namespace App\Http\Controllers;

use App\Models\Chant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ChantController extends Controller
{
    // GET /api/chants — liste, avec recherche et filtre par pupitre
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Chant::class);

        $chants = Chant::with(['categories', 'pupitres'])
            ->when($request->query('q'), fn ($query, $q) => $query->where('titre', 'like', "%{$q}%"))
            ->when($request->query('pupitre_id'), function ($query, $pupitreId) {
                $query->whereHas('pupitres', fn ($p) => $p->where('pupitres.id', $pupitreId));
            })
            ->latest('updated_at')
            ->get();

        return response()->json($chants);
    }

    // GET /api/chants/{chant} — détail avec historique
    public function show(Chant $chant)
    {
        Gate::authorize('view', $chant);

        $chant->load(['categories', 'pupitres', 'versions.auteur', 'auteur']);

        return response()->json($chant);
    }

    // POST /api/chants
    public function store(Request $request)
    {
        Gate::authorize('create', Chant::class);

        $validated = $this->valider($request, creation: true);

        $chant = Chant::create([
            'titre' => $validated['titre'],
            'paroles' => $validated['paroles'],
            'tonalite' => $validated['tonalite'] ?? null,
            'audio_path' => $this->stockerAudio($request, 'audio', 'chants/audio'),
            'created_by' => $request->user()->id,
        ]);

        $chant->categories()->sync($validated['categorie_ids'] ?? []);
        $chant->pupitres()->sync($validated['pupitre_ids'] ?? []);
        $this->stockerAudiosPupitres($request, $chant);

        return response()->json($chant->load(['categories', 'pupitres']), 201);
    }

    // PUT /api/chants/{chant} — archive l'ancienne version des paroles
    public function update(Request $request, Chant $chant)
    {
        Gate::authorize('update', $chant);

        $validated = $this->valider($request, creation: false);

        // L'archivage des paroles est géré par le modèle (une seule règle, un seul endroit)
        if (array_key_exists('paroles', $validated)) {
            $chant->mettreAJourParoles($validated['paroles'], $request->user());
        }

        $chant->fill(array_filter([
            'titre' => $validated['titre'] ?? null,
            'tonalite' => $validated['tonalite'] ?? null,
        ], fn ($v) => $v !== null));

        if ($nouvelAudio = $this->stockerAudio($request, 'audio', 'chants/audio')) {
            $this->supprimerFichier($chant->audio_path);
            $chant->audio_path = $nouvelAudio;
        }

        $chant->save();

        if (array_key_exists('categorie_ids', $validated)) {
            $chant->categories()->sync($validated['categorie_ids']);
        }

        if (array_key_exists('pupitre_ids', $validated)) {
            $chant->pupitres()->syncWithoutDetaching($validated['pupitre_ids']);
        }

        $this->stockerAudiosPupitres($request, $chant);

        return response()->json($chant->fresh()->load(['categories', 'pupitres']));
    }

    // DELETE /api/chants/{chant}
    public function destroy(Chant $chant)
    {
        Gate::authorize('delete', $chant);

        $this->supprimerFichier($chant->audio_path);

        foreach ($chant->pupitres as $pupitre) {
            $this->supprimerFichier($pupitre->pivot->audio_path);
        }

        $chant->delete(); // les pivots et versions partent en cascade (contraintes SQL)

        return response()->json(['message' => 'Chant supprimé.']);
    }

    // --- Interne ---

    protected function valider(Request $request, bool $creation): array
    {
        $requis = $creation ? 'required' : 'sometimes|required';

        // La regle "mimes" de Laravel ne se fie PAS a l'extension du nom de
        // fichier : elle devine le type reel a partir du contenu, puis verifie
        // qu'il correspond a l'une des extensions listees. Elle est donc aussi
        // sure que "mimetypes", tout en couvrant les variantes de type qu'un
        // telephone Android peut produire (audio/x-m4a, application/ogg...)
        // et qu'une liste "mimetypes" ecrite a la main finit toujours par rater.
        $formatsAudio = 'mimes:mp3,wav,m4a,ogg,oga,aac,mp4,webm,3gp,amr';
        $tailleMax = 'max:'.(int) config('chorabase.taille_max_audio_ko');

        return $request->validate([
            'titre' => "{$requis}|string|max:255",
            'paroles' => "{$requis}|string|max:50000",
            'tonalite' => 'nullable|string|max:100',
            'categorie_ids' => 'sometimes|array',
            'categorie_ids.*' => 'integer|exists:categories,id',
            'pupitre_ids' => 'sometimes|array',
            'pupitre_ids.*' => 'integer|exists:pupitres,id',
            'audio' => "nullable|file|{$formatsAudio}|{$tailleMax}",
            'audio_pupitre' => 'nullable|array',
            'audio_pupitre.*' => "file|{$formatsAudio}|{$tailleMax}",
        ], [
            'audio.uploaded' => $this->messageEchecUpload(),
            'audio_pupitre.*.uploaded' => $this->messageEchecUpload(),
            'audio.mimes' => 'Format audio non reconnu. Formats acceptes : MP3, WAV, M4A, OGG, AAC.',
            'audio_pupitre.*.mimes' => 'Format audio non reconnu. Formats acceptes : MP3, WAV, M4A, OGG, AAC.',
        ]);
    }

    /**
     * "uploaded" est l'erreur que Laravel renvoie quand PHP lui-meme a refuse
     * le fichier — presque toujours parce que upload_max_filesize ou
     * post_max_size du php.ini est plus petit que le fichier. Le message par
     * defaut ("Le fichier n'a pas pu etre televerse") n'aide personne, alors
     * qu'ici on peut nommer la vraie cause et la vraie limite.
     */
    protected function messageEchecUpload(): string
    {
        return sprintf(
            'Le fichier depasse la limite de PHP (upload_max_filesize = %s, post_max_size = %s). '
            .'Augmente ces deux valeurs dans le php.ini, ou envoie un fichier plus leger.',
            ini_get('upload_max_filesize'),
            ini_get('post_max_size')
        );
    }

    protected function stockerAudio(Request $request, string $champ, string $dossier): ?string
    {
        return $request->hasFile($champ)
            ? $request->file($champ)->store($dossier, 'public')
            : null;
    }

    protected function stockerAudiosPupitres(Request $request, Chant $chant): void
    {
        // ex: audio_pupitre[3] => fichier pour le pupitre id=3
        foreach ($request->file('audio_pupitre', []) as $pupitreId => $fichier) {
            // On ne fait confiance qu'aux pupitres réellement rattachés à ce chant
            if (! $chant->pupitres()->whereKey($pupitreId)->exists()) {
                continue;
            }

            $chemin = $fichier->store('chants/audio/pupitres', 'public');
            $chant->pupitres()->updateExistingPivot($pupitreId, ['audio_path' => $chemin]);
        }
    }

    protected function supprimerFichier(?string $chemin): void
    {
        if ($chemin) {
            Storage::disk('public')->delete($chemin);
        }
    }
}
