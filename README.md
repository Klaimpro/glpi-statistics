# DSI Stats pour GLPI 11

`dsistats` est un plugin GLPI 11 permettant d’afficher des statistiques opérationnelles sur les tickets de groupes techniques sélectionnés.

Il fournit des tableaux mensuels, des graphiques ECharts et des exports PDF à partir des champs statistiques natifs de GLPI.

Ce dépôt est anonymisé. Les noms de groupes présents par défaut sont des exemples génériques et doivent être adaptés avant une utilisation en production.

## Fonctionnalités

- Statistiques mensuelles par groupe technique.
- Distinction entre incidents et demandes.
- Temps moyen de prise en compte.
- Temps moyen de résolution.
- Affichage en heures et en jours métier avec la convention `7 heures = 1 jour métier`.
- Indicateurs visuels de conformité aux objectifs.
- Graphiques en courbes avec ECharts via les assets fournis par GLPI.
- Export PDF des tableaux et des graphiques.
- Respect des entités GLPI et des droits de visibilité des tickets.
- Aucune modification du cœur GLPI.
- Aucun CDN externe.

## Compatibilité

- GLPI `11.0.x`
- Version cible testée : GLPI `11.0.5`
- Version PHP : mêmes prérequis que GLPI 11

Le plugin déclare la compatibilité suivante :

```php
min: 11.0.0
max: 12.0.0
```

## Installation

Copier le dossier du plugin dans le répertoire des plugins GLPI :

```bash
cp -a dsistats /var/www/html/glpi/plugins/dsistats
```

Vérifier ensuite les droits et permissions selon votre installation GLPI :

```bash
chown -R www-data:www-data /var/www/html/glpi/plugins/dsistats
find /var/www/html/glpi/plugins/dsistats -type d -exec chmod 755 {} \;
find /var/www/html/glpi/plugins/dsistats -type f -exec chmod 644 {} \;
```

Installer puis activer le plugin depuis GLPI :

```text
Configuration > Plugins > DSI Stats > Installer > Activer
```

## Droits d’accès

Les pages du plugin sont destinées aux utilisateurs de l’interface centrale GLPI capables de gérer des tickets.

L’accès est contrôlé avec les droits natifs GLPI liés aux tickets dans `Dashboard::canView()`.

Le plugin conserve également le droit `plugin_dsistats` pour l’accès à sa configuration.

Comportement actuel :

- les utilisateurs disposant de droits de gestion des tickets peuvent accéder aux tableaux, graphiques, filtres et exports PDF ;
- les utilisateurs Self-Service ne doivent pas voir ni ouvrir les pages du plugin ;
- les accès directs par URL sont contrôlés côté serveur ;
- les ACL d’entités et de tickets restent appliquées par `StatsService`.

## Sources de données

Le plugin utilise les données de tickets et d’affectation de GLPI :

- `glpi_tickets.date` pour le mois de création du ticket ;
- `glpi_tickets.type` pour distinguer incident et demande ;
- `glpi_tickets.takeintoaccount_delay_stat` pour le délai de prise en compte ;
- `glpi_tickets.solve_delay_stat` pour le délai de résolution ;
- `glpi_groups_tickets` pour les groupes techniques affectés ;
- `glpi_groups_tickets.type = CommonITILActor::ASSIGN`;
- `glpi_tickets.is_deleted = 0`.

Les statistiques sont regroupées selon le mois de création du ticket.

Les valeurs de délai absentes sont exclues des moyennes. Une valeur de délai égale à `0` est considérée comme valide.

## Configuration des groupes

Cette version publique utilise des groupes anonymisés dans `src/StatsService.php` :

```php
GROUP_A
GROUP_B
GROUP_PARENT > GROUP_C
```

Avant d’utiliser le plugin sur une instance GLPI réelle, adapter `TARGET_GROUPS` avec vos propres noms et noms complets de groupes GLPI, ou remplacer ce mécanisme temporaire par une configuration basée sur les IDs de groupes.

Exemple :

```php
private const TARGET_GROUPS = [
    'group_a' => [
        'label' => 'Groupe A',
        'name' => 'GROUP_A',
        'completename' => 'GROUP_A',
    ],
];
```

Le plugin recherche volontairement les groupes exacts et n’inclut pas automatiquement les sous-groupes.

## Objectifs

Les objectifs anonymisés actuels sont définis dans `StatsService::TARGETS` :

- `Groupe A` : résolution des incidents en moins de `14h` ;
- `Groupe B` : prise en compte des demandes en moins de `70h` ;
- `Groupe C` : résolution des incidents en moins de `70h`.

Les seuils sont évalués strictement :

```text
moyenne < seuil = conforme
moyenne >= seuil = non conforme
```

## PDF Export

Le plugin propose des exports PDF depuis :

- la page des tableaux ;
- la page des graphiques.

La génération PDF utilise TCPDF fourni par l’environnement GLPI.

L’export du graphique fonctionne en convertissant le graphique ECharts visible en image PNG temporaire côté navigateur, puis en l’envoyant à la route d’export. Le serveur recalcule toujours les données métier et n’intègre que l’image du graphique dans le PDF. Aucune image de graphique n’est stockée durablement.

## Vérifications de développement

Après modification de fichiers PHP, lancer les vérifications de syntaxe :

```bash
php -l setup.php
php -l hook.php
php -l front/dashboard.php
php -l front/graphs.php
php -l front/export.php
php -l src/Config.php
php -l src/Dashboard.php
php -l src/PdfExporter.php
php -l src/Profile.php
php -l src/StatsService.php
```

## Notes de sécurité

- Ne pas publier de noms de groupes de production dans un dépôt public.
- Ne pas publier d’archives ZIP générées depuis un environnement interne.
- Ne pas publier de fichiers `.env`, journaux, sauvegardes ou exports de base de données.
- Relire `src/StatsService.php` avant publication si des groupes ou seuils ont été personnalisés.
- Garder ce plugin en dehors des fichiers du cœur GLPI.

## Hygiène du dépôt

Les fichiers recommandés à exclure sont déjà listés dans `.gitignore`, notamment :

```gitignore
*.zip
*.log
.env
.env.*
vendor/
node_modules/
```

## License

GPL-3.0-or-later.
