# CEG ADJENGRE II — Application de gestion scolaire

Application web de gestion d'un collège d'enseignement général : classes, élèves, notes, bulletins, appel par heure, retards et permissions, écolage et comptes des utilisateurs. Elle s'utilise depuis un navigateur et s'installe comme une application sur téléphone (PWA).

**En ligne :** https://ceg-adjengre-ii.onrender.com

---

## Fonctions par rôle

| Rôle | Ce qu'il peut faire |
|---|---|
| **Directeur** (`admin`) | Années scolaires, trimestres, classes (suppression si la classe est vide), matières et groupes, affectations des professeurs avec coefficients, comptes des utilisateurs (création, modification, désactivation, transfert d'un professeur à un autre), registre des élèves, écolage, retards et permissions, résultats, conseils de classe, bulletins de toute l'école |
| **Professeur** | Appel à chaque heure de cours avec comportement, saisie des notes de ses matières |
| **Professeur titulaire** | En plus : résultats de sa classe, conseil de classe, bulletins, absences de la classe (justification) |
| **Surveillant** | Retards avec billet d'entrée imprimable, permissions de sortie et d'absence avec suivi du retour |
| **Comptable** | Écolage de chaque classe par année, encaissements (surplus refusés), dépenses, registre des élèves |

Chaque utilisateur peut changer son nom, son e-mail et son mot de passe depuis **Mon compte**. Un compte créé par le directeur reçoit un mot de passe provisoire, à changer à la première connexion.

## Règles de gestion

- **Moyenne d'une matière** = (moyenne des notes de classe + composition) ÷ 2. Une note manquante compte 0 ; une matière où personne n'a encore de note n'est pas comptée.
- **Note définitive** = moyenne de la matière × coefficient (le coefficient dépend de la classe).
- **Moyenne du trimestre** = total des notes définitives ÷ total des coefficients, avec rangs et ex æquo.
- **Moyenne annuelle** = moyenne des trois trimestres. La décision de passage (proposée à partir de 10/20) se prend au 3ème trimestre d'après cette moyenne.
- **Absences** : comptées en heures à l'appel de chaque heure de cours ; **7 heures = 1 jour**. Une permission d'absence rend les heures de sa période justifiées.
- **Écolage** : montant défini par classe et par année scolaire. Un versement qui dépasse le reste à payer est refusé ; un élève soldé ne peut plus rien verser.
- **Comptes** : un compte désactivé ne peut plus se connecter mais garde son historique (aucune suppression).

## Technologies

- **Laravel 13** (PHP 8.3), Blade, MySQL 8
- **React** (registre des élèves) compilé avec **Vite**, **Bootstrap 5**
- **PWA** : `manifest.json` et service worker (`public/sw.js`)
- Bulletins, billets et permissions : pages HTML imprimables en PDF depuis le navigateur

## Installation en local (Windows avec Laragon)

Prérequis : PHP 8.3, Composer, Node.js 22, MySQL.

```bash
git clone https://github.com/VIDE8/ceg-adjengre-ii.git
cd ceg-adjengre-ii

composer install
npm install

copy .env.example .env
php artisan key:generate
```

Crée une base MySQL vide (par exemple `gestion_savoir`), renseigne les lignes `DB_*` du fichier `.env`, puis :

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

En local, le seeder crée des comptes de test (directeur, comptable, professeurs) et les matières : voir `database/seeders/DatabaseSeeder.php`. **Ces comptes n'existent jamais en production.**

Après modification de fichiers dans `resources/js` ou `resources/sass`, relance `npm run build`.

## Configuration de l'école

Les textes du bulletin et les réglages de l'appel sont dans `config/ecole.php` : nom, devise, ministère, direction régionale, inspection, ville, nom du directeur, logo (`public/logo.png`), nombre d'heures par jour (7) et nombre d'heures de cours par journée.

## Déploiement (Render + Aiven)

| Élément | Choix |
|---|---|
| Application | Render, service web **Docker** (`Dockerfile`, `render.yaml`) |
| Base de données | MySQL **Aiven** (connexion chiffrée, certificat `certs/aiven-ca.pem`) |
| Démarrage | `docker/start.sh` : `php artisan migrate --force` puis `php artisan db:seed --force` puis Apache |

**Variables d'environnement à renseigner sur Render** (jamais dans le dépôt) :

| Variable | Rôle |
|---|---|
| `APP_KEY` | clé de l'application (`php artisan key:generate --show`) |
| `APP_URL` | adresse HTTPS du service |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | connexion Aiven |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | compte du directeur créé au premier démarrage |

Les autres variables (`APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DRIVER=database`, etc.) sont déjà dans `render.yaml`.

En production, le seeder ne crée que le compte du directeur et les matières, et seulement si la base est vide.

**Mettre à jour l'application :**

```bash
git add .
git commit -m "Description de la modification"
git push
```

Render reconstruit et redéploie automatiquement (quelques minutes). Les tables sont mises à jour au démarrage. Évite de publier en pleine journée de cours.

**Limites de l'offre gratuite :** le service Render s'endort après 15 minutes sans visite (premier chargement lent) ; la base Aiven gratuite peut s'éteindre après une longue inactivité (la rallumer depuis la console Aiven). Pour un usage quotidien, passer `plan: free` à `plan: starter` dans `render.yaml`.

## Sécurité et sauvegardes

- Le fichier `.env`, les mots de passe et les exports de base (`*.sql`, `*.zip`) ne doivent jamais être publiés.
- Un compte désactivé est déconnecté immédiatement ; les mots de passe sont chiffrés.
- Faire une copie de la base chaque mois et avant la fin de chaque trimestre (export SQL depuis HeidiSQL, avec SSL et le certificat Aiven).

## Structure du projet

```
app/Http/Controllers/   contrôleurs (notes, appel, bulletins, paiements, utilisateurs, ...)
app/Http/Middleware/    CheckRole (rôles), VerifierCompte (compte actif, mot de passe provisoire)
app/Models/             modèles Eloquent
app/Services/           BulletinService (moyennes, rangs, absences), EcolageService (écolage)
config/ecole.php        informations de l'école
database/migrations/    structure de la base
resources/views/        pages Blade (layouts/app.blade.php = menu commun)
resources/js/react/     registre des élèves (React)
routes/web.php          toutes les routes, regroupées par rôle
```

## Auteur

Projet réalisé pour le CEG ADJENGRE II.
