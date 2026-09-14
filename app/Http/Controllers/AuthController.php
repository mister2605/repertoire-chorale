<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // POST /login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials, true)) {
            // Cas frequent et deroutant : le compte existe, mais il a ete cree
            // a la main par le maitre de choeur et son mot de passe aleatoire
            // n'a jamais ete remplace. "Identifiants incorrects" envoie alors
            // la personne chercher un probleme qui n'existe pas.
            //
            // Ce message revele qu'un compte non active existe pour cet email.
            // On l'assume : ce compte est de toute facon inutilisable, et pour
            // une chorale de quarante personnes le gain de clarte vaut
            // largement cette information.
            $compte = User::where('email', $credentials['email'])->first();

            if ($compte && ! $compte->estActive()) {
                return response()->json([
                    'message' => "Ce compte n'est pas encore activé. Ouvre le lien d'invitation "
                        .'que le maître de chœur t\'a envoyé pour choisir ton mot de passe.',
                ], 422);
            }

            return response()->json(['message' => 'Identifiants incorrects.'], 422);
        }

        $request->session()->regenerate();

        return response()->json(Auth::user()->load('pupitre', 'chorale'));
    }

    // POST /logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Déconnecté.']);
    }

    // GET /api/user — utilisateur actuellement connecté (ou 401)
    public function me(Request $request)
    {
        return response()->json($request->user()->load('pupitre', 'chorale'));
    }
}
