# SyncOps

SyncOps est une démonstration publique de traitement fiable de webhooks. Le projet rend visibles les sujets souvent cachés derrière une intégration robuste : signature HMAC, idempotence, file d'attente, reprises, mise en quarantaine et observabilité.

> État : réception signée, idempotence, traitement asynchrone, reprises bornées, quarantaine, relance manuelle et interface d’observabilité détaillée disponibles.

## MVP prévu

- recevoir des événements provenant de sources de démonstration ;
- vérifier leur signature sans exposer de secret ;
- reconnaître un doublon grâce à une clé d'idempotence ;
- traiter les événements en arrière-plan ;
- appliquer des reprises avec temporisation ;
- placer les échecs définitifs en quarantaine ;
- afficher un tableau de bord et un journal d’événements filtrable ;
- afficher l'historique complet, les tentatives et une charge utile expurgée ;
- permettre une relance manuelle simulée depuis la quarantaine.

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

Le tableau de bord est disponible sur `GET /`, le journal filtrable sur `GET /events` et le point de santé répond sans contenu sur `GET /health`.

## Vérifications

```bash
php artisan test --compact
vendor/bin/pint --format agent
npm run build
```

La CI valide aussi les métadonnées Composer et audite les dépendances PHP et JavaScript.

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

## Licence

Le choix de licence sera finalisé avant la première version publique stable.
