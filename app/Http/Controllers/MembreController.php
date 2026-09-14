<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des comptes choristes par le maître de chœur.
 *
 * Principe du lien d'invitation : le maître de chœur crée le compte, l'appli
 * renvoie un lien à usage unique valable 7 jours. Il le colle dans WhatsApp,
 * le choriste l'ouvre et choisit LUI-MÊME son mot de passe.
 *
 * Pourquoi pas un mot de passe défini par le maître de chœur : il le
 * connaîtrait, et il finirait écrit sur un cahier. Ici personne d'autre que
 * le choriste ne connaît son mot de passe. Et aucun envoi d'email à
 * configurer — ce qui compte quand le canal réel, c'est WhatsApp.
 */
class MembreController extends Controller
{
    // GET /api/membres
    public function index()
    {
        Gate::authorize('viewAny', User::class);

        $membres = User::deMaChorale()
            ->with('pupitre')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'pupitre_id', 'chorale_id', 'active_le', 'created_at']);

        return response()->json($membres);
    }

    // POST /api/membres — crée le compte et renvoie le lien d'invitation
    public function store(Request $request)
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => ['required', Rule::in(User::ROLES)],
            'pupitre_id' => 'nullable|integer|exists:pupitres,id',
        ]);

        $membre = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            // Mot de passe aléatoire jamais communiqué : le compte n'est
            // utilisable qu'une fois l'invitation acceptée.
            'password' => Str::random(40),
            'role' => $validated['role'],
            'pupitre_id' => $validated['pupitre_id'] ?? null,
            // Jamais depuis la requête : toujours la chorale de l'utilisateur connecté.
            'chorale_id' => $request->user()->chorale_id,
        ]);

        return response()->json([
            'membre' => $membre->load('pupitre'),
            'lien_invitation' => $this->genererLienInvitation($membre),
        ], 201);
    }

    // POST /api/membres/{membre}/invitation — régénère un lien
    public function invitation(User $membre)
    {
        Gate::authorize('inviter', $membre);

        return response()->json(['lien_invitation' => $this->genererLienInvitation($membre)]);
    }

    // PUT /api/membres/{membre} — change le rôle ou le pupitre
    public function update(Request $request, User $membre)
    {
        Gate::authorize('update', $membre);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'role' => ['sometimes', 'required', Rule::in(User::ROLES)],
            'pupitre_id' => 'nullable|integer|exists:pupitres,id',
        ]);

        // On teste "n'est plus maître de chœur", et non "devient choriste" :
        // depuis l'arrivée du rôle instrumentiste, rétrograder le dernier
        // maître de chœur en instrumentiste passait à travers la protection
        // et laissait la chorale sans personne pour administrer quoi que ce soit.
        if (array_key_exists('role', $validated) && $validated['role'] !== 'maitre_choeur') {
            $this->refuserSiDernierMaitreDeChoeur($membre, 'rétrograder');
        }

        $membre->update($validated);

        return response()->json($membre->fresh()->load('pupitre'));
    }

    // DELETE /api/membres/{membre}
    public function destroy(User $membre)
    {
        Gate::authorize('delete', $membre);

        $this->refuserSiDernierMaitreDeChoeur($membre, 'supprimer');

        $membre->delete();

        return response()->json(['message' => 'Membre supprimé.']);
    }

    // --- Interne ---

    /**
     * Le lien pointe vers le PWA, pas vers l'API : c'est le front qui affiche
     * le formulaire de choix du mot de passe.
     */
    protected function genererLienInvitation(User $membre): string
    {
        $token = Password::broker('invitations')->createToken($membre);

        return rtrim(config('chorabase.frontend_url'), '/')
            .'/?invitation='.$token
            .'&email='.urlencode($membre->email);
    }

    /**
     * Empêche de se retrouver sans aucun maître de chœur : plus personne
     * ne pourrait alors créer de compte ni modifier le répertoire.
     */
    protected function refuserSiDernierMaitreDeChoeur(User $membre, string $action): void
    {
        if (! $membre->estMaitreDeChoeur()) {
            return;
        }

        $autresMaitres = User::where('chorale_id', $membre->chorale_id)
            ->where('role', 'maitre_choeur')
            ->where('id', '!=', $membre->id)
            ->exists();

        if (! $autresMaitres) {
            throw ValidationException::withMessages([
                'role' => "Impossible de {$action} le dernier maître de chœur de la chorale. "
                    .'Nomme d\'abord un autre maître de chœur.',
            ]);
        }
    }
}
