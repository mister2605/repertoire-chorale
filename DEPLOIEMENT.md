# Mise en production de Chorabase

Pour le pilote avec la chorale : **un seul domaine, un seul hébergement**.
Laravel sert l'API *et* l'application. Pas de Docker, pas de second service.

---

## 1. À vérifier AVANT d'acheter l'hébergement

Ces quatre points ne se négocient pas après coup. Pose-les par écrit au
support de l'hébergeur avant de payer — la plupart répondent en quelques
heures, et une réponse floue est une réponse « non ».

| # | Ce qu'il faut | Pourquoi |
|---|---|---|
| 1 | **PHP 8.3 ou plus**, sélectionnable | Laravel 13 l'exige. Beaucoup d'offres proposent encore 8.1 par défaut. C'est le blocage le plus fréquent. |
| 2 | **Accès SSH** | Sans lui, impossible de lancer les migrations ou d'installer les dépendances. On peut contourner, mais c'est laborieux et fragile. |
| 3 | **Racine du site modifiable** (pointer vers un sous-dossier) | Le dossier `public/` doit être la racine web. Sinon tout le code source est téléchargeable par n'importe qui. |
| 4 | **HTTPS inclus** (Let's Encrypt / AutoSSL) | Sans HTTPS, une PWA ne s'installe pas sur un téléphone. C'est bloquant pour l'usage réel. |

Vérifie aussi, moins critique : MySQL 8 ou MariaDB 10.6+, et au moins
**2 Go d'espace disque** (les audios s'accumulent vite : 4 pupitres × ~5 Mo
par chant).

**Question à poser telle quelle :**

> Bonjour, je souhaite héberger une application Laravel 13. Puis-je avoir
> PHP 8.3, un accès SSH, et définir un sous-dossier comme racine du site ?
> Quelles sont les valeurs de `upload_max_filesize` et `post_max_size`, et
> puis-je les modifier ?

---

## 2. Le domaine

Il en faut un : HTTPS est obligatoire, et un certificat ne s'obtient pas
sur une adresse IP.

- Un `.com` coûte 10 à 15 € par an chez n'importe quel registrar.
- Un `.bf` s'obtient auprès de l'ARCEP Burkina — plus long, mais local.
- Le plus rapide : la plupart des hébergeurs mutualisés offrent le domaine
  la première année.

Un seul domaine suffit. Pas besoin de `api.` et `app.` séparés : c'est tout
l'intérêt de servir les deux depuis la même adresse.

---

## 3. Préparer le paquet, chez toi

Dans `chorabase-pwa` :

```
npm run deployer
```

Cette commande construit l'application et copie le résultat dans
`repertoire-chorale/public/`, sans toucher aux fichiers de Laravel.

Teste immédiatement en local avant d'envoyer quoi que ce soit :

```
cd C:\wamp64\www\repertoire-chorale
php artisan serve
```

Ouvre `http://localhost:8000` — **sans le port 5173**. Tu dois voir
l'application complète. Si ça marche ici, ça marchera en ligne : c'est
exactement la même configuration.

> **Piège à connaître.** Sanctum compare le domaine **avec son port**.
> Si `SANCTUM_STATEFUL_DOMAINS` contient `localhost` mais pas
> `localhost:8000`, tu te connectes… et tous les appels `/api` répondent
> `Unauthenticated`. Pour tester en local, la ligne doit contenir les
> quatre entrées :
>
> ```
> SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173,localhost:8000,127.0.0.1:8000
> ```
>
> En production, le site répond sur le port 443 (https) et le port
> n'apparaît pas : le nom de domaine seul suffit, sans `https://`.

---

## 4. Envoyer sur le serveur

Envoie tout le dossier `repertoire-chorale` **sauf** :

- `node_modules/` (n'existe pas côté Laravel)
- `.env` (celui de ton poste, avec les réglages locaux)
- `storage/logs/*` et `storage/framework/cache/*`
- `.git/`

Tu peux envoyer `vendor/` par FTP pour éviter un `composer install` sur le
serveur, ou le générer là-bas en SSH (plus propre) :

```
composer install --no-dev --optimize-autoloader
```

**Racine du site** : pointe-la vers `.../repertoire-chorale/public`.
Si l'hébergeur ne le permet pas, préviens-moi — il existe un contournement,
mais il vaut mieux changer d'offre.

---

## 5. Configurer, en SSH

Copie `.env.production.example` en `.env`, remplis les valeurs `A_REMPLIR`,
puis :

```
php artisan key:generate && php artisan migrate --force && php artisan db:seed --class=RepertoireSeeder && php artisan storage:link && php artisan config:cache && php artisan route:cache
```

Vérifie les permissions (sinon Laravel ne peut ni écrire ses logs ni
recevoir les audios) :

```
chmod -R 775 storage bootstrap/cache
```

---

## 6. Créer le premier compte

Il n'existe encore aucun maître de chœur. Une seule fois, en SSH :

```
php artisan tinker --execute="App\Models\User::create(['name'=>'A_REMPLIR','email'=>'A_REMPLIR','password'=>'A_REMPLIR','role'=>'maitre_choeur','chorale_id'=>1,'active_le'=>now()]);"
```

Connecte-toi avec ce compte, ouvre **Réglages** pour ajuster le nom de la
chorale, ses pupitres et ses catégories, puis **Membres** pour ouvrir
l'adhésion et diffuser le lien dans le groupe WhatsApp de la chorale.

---

## 7. Avant d'annoncer aux choristes

- [ ] `APP_DEBUG=false` dans le `.env` du serveur
- [ ] L'adresse s'ouvre bien en `https://`
- [ ] Sur Android, Chrome propose « Ajouter à l'écran d'accueil »
- [ ] Un chant avec audio se crée et se lit
- [ ] Le lien d'adhésion crée bien un compte choriste, testé sur un vrai téléphone
- [ ] Une sauvegarde de la base est programmée (voir ci-dessous)

---

## 8. Les sauvegardes

À faire **avant** que la chorale commence à saisir son répertoire, pas après.
Le répertoire d'une chorale, ce sont des années de travail.

Dans cPanel, planifie une tâche quotidienne :

```
cd ~/repertoire-chorale && mysqldump -u UTILISATEUR -pMOTDEPASSE BASE | gzip > ~/sauvegardes/chorabase-$(date +\%F).sql.gz
```

Et télécharge périodiquement le dossier `storage/app/public` : c'est là que
vivent les enregistrements audio, et aucun `mysqldump` ne les contient.

---

## En cas de problème

Le fichier `storage/logs/laravel.log` sur le serveur contient l'erreur réelle.
C'est toujours la première chose à regarder — et la première chose à me
transmettre si tu me demandes de l'aide.
