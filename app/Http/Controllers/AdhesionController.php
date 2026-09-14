<?php

namespace App\Http\Controllers;

use App\Models\Chorale;
use App\Models\Pupitre;
use App\Models\User;
use App\Support\ChoraleCourante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password as ReglesMotDePasse;

/**
 * Adhésion en libre-service.
 *
 * Le maître de chœur diffuse UN lien dans le groupe WhatsApp de la chorale.
 * Chaque choriste ouvre le lien, saisit son nom, son email, son pupitre et
 * son mot de passe. Il n'a rien à demander à personne.
 *
 * UNE règle non négociable : le rôle créé ici est TOUJOURS "choriste".
 * Le rôle ne vient jamais du formulaire. Un lien WhatsApp se transfère,
 * se copie, sort du groupe — si quelqu'un pouvait cocher "maître de chœur"
 * lui-même, n'importe quel destinataire du lien pourrait effacer tout le
 * répertoire. Le maître de chœur promeut ensuite qui il veut depuis
 * l'écran Membres, en deux clics.
 *
 * Ce que risque un lien qui fuite reste borné : un inconnu peut lire les
 * chants. Le maître de chœur le voit apparaître dans la liste, le supprime,
 * et régénère le lien — l'ancien devient mort.
 */
class AdhesionController extends Controller
{
    /** GET /adhesion/{code} — de quelle chorale s'agit-il ? */
    public function infos(string $code)
    {
        $chorale = $this->choraleOuvertePourCode($code);

        // Les pupitres appartiennent à la chorale : on force le contexte,
        // sinon le filtre automatique ne renvoie rien (visiteur non connecté).
        $pupitres = app(ChoraleCourante::class)->pour(
            $chorale->id,
            fn () => Pupitre::orderBy('nom')->get(['id', 'nom'])
        );

        return response()->json([
            'chorale' => ['nom' => $chorale->nom, 'ville' => $chorale->ville],
            'pupitres' => $pupitres,
        ]);
    }

    /** POST /adhesion/{code} — le choriste crée son compte lui-même. */
    public function rejoindre(Request $request, string $code)
    {
        $chorale = $this->choraleOuvertePourCode($code);

        $valide = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', ReglesMotDePasse::min(8)],
            'pupitre_id' => 'nullable|integer',
        ], [
            'email.unique' => 'Un compte existe déjà avec cet email. Connecte-toi plutôt que de créer un nouveau compte.',
        ]);

        // Le pupitre doit appartenir à CETTE chorale, pas à une autre.
        $pupitreId = null;
        if (! empty($valide['pupitre_id'])) {
            $pupitreId = app(ChoraleCourante::class)->pour(
                $chorale->id,
                fn () => Pupitre::whereKey($valide['pupitre_id'])->value('id')
            );
        }

        $membre = User::create([
            'name' => $valide['name'],
            'email' => $valide['email'],
            'password' => $valide['password'],
            'role' => 'choriste', // JAMAIS depuis la requête. Voir le commentaire de classe.
            'pupitre_id' => $pupitreId,
            'chorale_id' => $chorale->id,
            // La personne vient de choisir son mot de passe : compte utilisable.
            'active_le' => now(),
        ]);

        // On connecte directement : le choriste vient de choisir son mot de
        // passe, lui redemander de se reconnecter n'apporte rien.
        Auth::login($membre);
        $request->session()->regenerate();

        return response()->json($membre->load('pupitre'), 201);
    }

    protected function choraleOuvertePourCode(string $code): Chorale
    {
        $chorale = Chorale::where('code_adhesion', $code)
            ->where('adhesion_ouverte', true)
            ->where('actif', true)
            ->first();

        // Même réponse que le code soit inconnu, périmé ou l'adhésion fermée :
        // on ne donne aucune indication à qui essaierait des codes au hasard.
        abort_if($chorale === null, 404, "Ce lien d'adhésion n'est plus valable.");

        return $chorale;
    }
}
