# Tester avec la chorale, sans hébergement

Ton ordinateur fait serveur. Un tunnel Cloudflare lui donne une adresse
publique en `https://`, que les choristes ouvrent depuis leur téléphone.

Gratuit, sans compte, sans carte. Ça dure le temps que ton ordinateur reste
allumé — c'est une répétition grandeur nature, pas une mise en production.

---

## Une fois pour toutes : installer cloudflared

Dans PowerShell :

```
winget install --id Cloudflare.cloudflared
```

Ferme et rouvre PowerShell, puis vérifie :

```
cloudflared --version
```

---

## À chaque séance : quatre étapes

### 1. Construire l'application et lancer le serveur

Dans `chorabase-pwa` :

```
npm run deployer
```

Puis dans `repertoire-chorale`, et **laisse cette fenêtre ouverte** :

```
php artisan serve
```

### 2. Ouvrir le tunnel

Dans une **deuxième** fenêtre PowerShell, et laisse-la ouverte aussi :

```
cloudflared tunnel --url http://localhost:8000
```

Au bout de quelques secondes, une adresse s'affiche dans un encadré :

```
https://quelque-chose-au-hasard.trycloudflare.com
```

Copie-la.

### 3. Dire à Laravel quelle est son adresse publique

Dans une **troisième** fenêtre, depuis `repertoire-chorale` :

```
php artisan chorabase:tunnel https://colle-ici-l-adresse.trycloudflare.com
```

C'est l'étape qu'on ne peut pas sauter. Sans elle, tu te connectes mais
**aucun audio ne se joue** : Laravel fabriquerait des liens vers
`http://localhost:8000`, que seul ton ordinateur peut atteindre.

La commande règle tout et vide le cache de configuration. Rien à relancer.

### 4. Vérifier sur TON téléphone avant de diffuser

Ouvre l'adresse sur ton propre téléphone, en 4G (pas en wifi sur ta box —
tu testerais un chemin que les choristes n'emprunteront pas). Vérifie :

- [ ] La page s'affiche et le cadenas est présent
- [ ] Tu peux te connecter
- [ ] **Un audio se joue** — c'est le point qui casse le plus souvent
- [ ] Chrome propose « Ajouter à l'écran d'accueil »

Si l'audio ne part pas, tu as sauté l'étape 3.

---

## Diffuser aux choristes

Connecte-toi comme maître de chœur, ouvre **Membres**, **Ouvre l'adhésion**,
puis « Copier le message ». Le lien contient déjà l'adresse du tunnel.

Colle-le dans le groupe WhatsApp de la chorale.

Chacun crée son compte, choisit son pupitre, et installe l'application sur
son écran d'accueil.

---

## Après la séance

```
php artisan chorabase:tunnel --stop
```

Ferme les deux fenêtres (Ctrl+C dans chacune). L'adresse cesse aussitôt de
fonctionner — c'est normal, et c'est même une sécurité : elle ne traînera
pas dans un groupe WhatsApp après ton test.

**Referme l'adhésion** depuis l'écran Membres si tu ne comptes pas
enchaîner tout de suite : le lien d'adhésion, lui, reste valable.

---

## Ce à quoi il faut s'attendre

**L'adresse change à chaque fois.** Les tunnels gratuits n'ont pas d'adresse
fixe. À chaque séance, tu refais les étapes 2 et 3 et tu rediffuses le lien.
Les comptes créés, eux, restent : personne n'a à s'inscrire deux fois.

**Le serveur de développement traite une requête à la fois.** `php artisan serve`
n'est pas fait pour la foule. Ça passe très bien à cinq ou dix personnes qui
parcourent l'appli. Si vingt choristes lancent un enregistrement en même
temps, ça va ramer — un fichier audio occupe la ligne pendant toute sa
lecture.

Si ça arrive, dis-le-moi : on bascule sur l'Apache de WAMP, qui gère
plusieurs visiteurs en parallèle. C'est une configuration de dix minutes,
qu'on ne fait que si le besoin s'en fait sentir.

**Ton ordinateur doit rester allumé et connecté.** Veille, coupure de
courant, coupure internet : le lien tombe. Prévois-le si la séance a lieu
loin de chez toi.

---

## Ce qu'il faut observer pendant la séance

Note ce qui bloque, pas ce qui marche. En particulier :

- Combien de choristes arrivent au bout de l'inscription **sans aide** ?
- Combien installent l'appli sur l'écran d'accueil sans qu'on leur montre ?
- Est-ce que quelqu'un cherche une fonction qui n'existe pas ?
- Est-ce que quelqu'un rouvre l'appli **le lendemain**, sans qu'on le lui
  demande ?

La dernière question est la seule qui compte vraiment. Une appli qu'on
installe et qu'on n'ouvre plus a échoué, même si tout a fonctionné.
