# SyncOps — périmètre du MVP

## Objectif

Montrer, dans une démonstration publique compréhensible, comment une application reçoit et traite des événements externes de manière fiable, traçable et sûre.

SyncOps complète le portfolio par un troisième angle : DeskOps démontre les workflows métier, LeadOps la transformation de données, et SyncOps les intégrations asynchrones.

## Sources de démonstration

- `billing-demo`
- `commerce-demo`
- `crm-demo`

Ces sources sont internes au projet. Elles produisent uniquement des données fictives et ne représentent aucun fournisseur réel.

## Scénarios obligatoires

1. événement valide traité avec succès ;
2. doublon reconnu et ignoré sans second effet ;
3. signature invalide rejetée ;
4. erreur temporaire, puis réussite après reprise ;
5. erreur permanente placée en quarantaine ;
6. relance manuelle simulée depuis le détail d'un événement.

## États visibles

`received` → `verified` → `queued` → `processing` → `succeeded`

Branches complémentaires : `rejected`, `duplicate`, `retry_scheduled`, `dead_lettered` et `replayed`.

Chaque changement d'état doit produire une entrée horodatée dans la chronologie de l'événement.

## Écrans

- tableau de bord synthétique ;
- liste filtrable des événements ;
- détail d'un événement et chronologie ;
- quarantaine des échecs définitifs ;
- liste des connecteurs simulés ;
- page d'architecture et de garanties techniques.

## Contraintes de sécurité

- validation HMAC et comparaison en temps constant ;
- clé d'idempotence unique au niveau de la base ;
- taille maximale des charges utiles ;
- masquage des champs sensibles dans l'interface et les journaux ;
- limitation de débit et protection CSRF selon le type de route ;
- aucune URL de destination arbitraire afin d'écarter le risque SSRF ;
- aucun secret, accès généré ou donnée personnelle réelle ;
- aucune création publique de compte.

## Hors périmètre

- connecteurs réels vers Stripe, Shopify, HubSpot, Slack ou un service de messagerie ;
- facturation et abonnements ;
- WebSockets ;
- Redis, Kafka ou une infrastructure distribuée ;
- transformations arbitraires de charges utiles ;
- destinations réseau fournies par l'utilisateur ;
- fonctionnalités d'intelligence artificielle.

## Critères d'acceptation

- les six scénarios sont reproductibles depuis l'interface ;
- le traitement métier s'exécute dans une file séparée ;
- un doublon ne produit jamais deux traitements ;
- les reprises ont une limite explicite et observable ;
- chaque événement dispose d'une chronologie complète ;
- les tests couvrent le succès et les échecs importants ;
- les audits de dépendances, le formatage, les tests et le build passent en CI ;
- la démonstration ne peut déclencher aucune requête vers une destination choisie par un visiteur.

## Phases de réalisation

1. socle, qualité et documentation ;
2. modèle de données et données fictives ;
3. réception, signature et idempotence ;
4. file, traitement et reprises ;
5. quarantaine et relance ;
6. interface d'observabilité ;
7. durcissement sécurité et accessibilité ;
8. configuration de déploiement et intégration au portfolio.
