# SyncOps

SyncOps est une démonstration publique de traitement fiable de webhooks. Le projet rend visibles les sujets souvent cachés derrière une intégration robuste : signature HMAC, idempotence, file d'attente, reprises, mise en quarantaine et observabilité.

> État : MVP fonctionnel et configuration de déploiement DigitalOcean prête. Aucune ressource cloud n'est créée automatiquement par le dépôt.

## MVP prévu

- recevoir des événements provenant de sources de démonstration ;
- vérifier leur signature sans exposer de secret ;
- reconnaître un doublon grâce à une clé d'idempotence ;
- traiter les événements en arrière-plan ;
- appliquer des reprises avec temporisation ;
- placer les échecs définitifs en quarantaine ;
- afficher un tableau de bord et un journal d’événements filtrable ;
- afficher l'historique complet, les tentatives et une charge utile expurgée ;
- permettre une relance manuelle simulée depuis la quarantaine ;
- présenter les connecteurs et le pipeline technique dans des vues dédiées.

Les connecteurs et destinations du MVP sont entièrement simulés dans l'application. SyncOps n'acceptera pas d'URL arbitraire et n'enverra aucune donnée vers un service tiers.

## Technologies

- PHP 8.3 et Laravel 13 ;
- PostgreSQL 18 pour la cible de production ;
- file Laravel avec pilote base de données ;
- Blade, Tailwind CSS 4 et Vite 8 ;
- PHPUnit 12, Laravel Pint et GitHub Actions.

## Démarrage local

Pré-requis : PHP 8.3, Composer 2, Node.js 24 et npm.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm ci
npm run build
php artisan serve
```

Le tableau de bord est disponible sur `GET /`, le journal filtrable sur `GET /events`, la quarantaine sur `GET /quarantine`, les connecteurs sur `GET /connectors`, l’explication du pipeline sur `GET /architecture` et le point de santé répond sans contenu sur `GET /health`.

## Vérifications

```bash
php artisan test --compact
vendor/bin/pint --format agent
npm run build
```

La CI exécute la suite sur SQLite et PostgreSQL 18, valide le formatage et les métadonnées Composer, audite les dépendances PHP et JavaScript, construit les ressources Vite et l'image de production.

## Déploiement DigitalOcean

Le fichier [`.do/app.yaml`](.do/app.yaml) décrit une application App Platform à Londres avec :

- un service web à 5 USD/mois ;
- un worker dédié à la file `webhooks` à 5 USD/mois ;
- une base PostgreSQL 18 de développement à 7 USD/mois ;
- un job de préparation avant déploiement et un reset quotidien à 04:00 Europe/Paris, facturés uniquement pendant leur exécution.

Le socle coûte donc 17 USD/mois, hors temps d'exécution très court des jobs. L'auto-déploiement est désactivé. Avant la création, il faut sélectionner manuellement le VPC `default-lon1`. Après l'import du spec, ajouter uniquement au composant `web`, avec portée `RUN_TIME` et type chiffré `SECRET` : `APP_KEY`, `SYNCOPS_BILLING_DEMO_SECRET`, `SYNCOPS_COMMERCE_DEMO_SECRET` et `SYNCOPS_CRM_DEMO_SECRET`.

Le domaine prévu est `syncops.mkirchel.com`. Le CNAME Cloudflare doit rester en mode **DNS only**. La redirection du domaine de démarrage DigitalOcean ne doit être ajoutée qu'après création, lorsque son nom exact est connu, afin de conserver le chemin et la chaîne de requête d'origine.

## Documentation

- [Périmètre du MVP](docs/MVP.md)
- [Architecture cible](docs/ARCHITECTURE.md)

## Sécurité de la démonstration

- données entièrement fictives et réinitialisables ;
- aucune création publique de compte ;
- aucun secret ou jeton dans le dépôt ;
- charge utile limitée et informations sensibles masquées ;
- comparaison de signature en temps constant ;
- protection contre les doublons au niveau de la base ;
- aucune destination réseau configurable par le public.
- configuration de production contrôlée avant le démarrage du web et du worker ;
- en-têtes CSP, HSTS, anti-framing, anti-MIME-sniffing et politique de permissions ;
- reset transactionnel refusé si un compte ou un connecteur inconnu existe.

## Licence

Le choix de licence sera finalisé avant la première version publique stable.
