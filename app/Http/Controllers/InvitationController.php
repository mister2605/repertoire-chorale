<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as ReglesMotDePasse;

/**
 * Route PUBLIQUE : le choriste ouvre son lien d'invitation et choisit
 * son mot de passe. C'est le seul endroit de l'appli accessible sans être
 * connecté, en dehors du login.
 *
 * La sécurité repose entièrement sur le jeton :
 *  - stocké haché en base (Laravel s'en charge) ;
 *  - valable 7 jours ;
 *  - détruit dès qu'il est utilisé (usage unique) ;
 *  - la route est limitée en fréquence (throttle) contre le devinage.
 */
class InvitationController extends Controller
{
    // POST /invitation/definir-mot-de-passe
    public function definirMotDePasse(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', ReglesMotDePasse::min(8)],
        ]);

        $statut = Password::broker('invitations')->reset(
            $validated,
            function (User $membre, string $motDePasse) {
                $membre->forceFill([
                    'password' => $motDePasse,
                    'remember_token' => Str::random(60),
                    // Le compte devient utilisable a cet instant precis.
                    'active_le' => now(),
                ])->save();

                event(new PasswordReset($membre));
            }
        );

        if ($statut !== Password::PasswordReset) {
            // Message volontairement identique pour tous les cas d'échec :
            // on ne dit pas si l'email existe, si le lien a expiré ou s'il a
            // déjà servi. Sinon on donne un outil pour tester des emails.
            return response()->json([
                'message' => "Ce lien n'est plus valable. Demande un nouveau lien au maître de chœur.",
            ], 422);
        }

        return response()->json([
            'message' => 'Mot de passe enregistré. Tu peux maintenant te connecter.',
        ]);
    }
}
