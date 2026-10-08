---
id: SPEC-egam-export
type: spec
status: draft
last-updated: 2026-10-08
---

# Export admin des données EGAM — design

Date : 2026-10-08
Statut : en attente de relecture

## Objectif

Permettre à un administrateur d'exporter les données des 5 entités EGAM (`artwork`, `artist`, `game`, `museum`, `screenshot`) depuis le backoffice, pour les lire et les analyser dans Excel.

Usage retenu : **lecture et analyse**. Les colonnes sont lisibles (labels plutôt que ids seuls), les ids sont conservés pour croiser les données, les champs techniques sont omis. Ce n'est ni une sauvegarde ni un format de ré-import.

## Périmètre

### Inclus

- Une page d'administration dédiée, réservée aux utilisateurs ayant la permission `export egam data`.
- Export **CSV** : un fichier par entité, regroupés dans un zip.
- Export **XLSX** : un classeur, une feuille par entité, avec des liens internes entre feuilles pour les références.
- Tout le contenu est exporté, publié ou non, avec une colonne `status`.

### Exclu

- Les images et fichiers : champs `image`, `file` et références vers des médias ou fichiers. Leurs fichiers ne sont pas inclus non plus.
- Les révisions anciennes : seule la révision courante est exportée.
- Les traductions : seule la langue par défaut est exportée.
- Les filtres des listes publiques : l'export est toujours complet.
- Le ré-import des données.
- La génération en tâche de fond (Batch API ou queue). Voir « Évolutions possibles ».

## Hypothèses validées

1. Le contenu non publié est inclus.
2. Les champs à valeurs multiples sont joints par un retour à la ligne dans une cellule. Pour une référence multiple en XLSX, le lien interne pointe vers la première cible et le texte liste toutes les cibles.
3. Seule la révision courante, dans la langue par défaut, est exportée.
4. Les champs texte longs sont exportés en texte brut, sans HTML.

## Contexte technique

- Les 5 entités étendent `RevisionableContentEntityBase`. Leurs champs de base sont `status`, `label`, `description`, `uid`, `created` et `changed`.
- Les champs métier (`field_artist`, `field_museum`, `field_date`, `field_thumbnail`…) sont des champs configurables créés via Field UI. L'export les découvre à l'exécution avec `getFieldDefinitions()`, sans liste codée en dur.
- L'enum `Drupal\egam_global\Entities` est le point d'entrée pour itérer sur les 5 entités (`Entities::cases()`, `getPlural()`).
- Une route admin existe déjà sous `/admin/config/egam` (module `egam_global`).

## Architecture

Nouveau module `web/modules/custom/egam_export`, dépendant de `egam_global` et des 5 modules d'entités. Nouvelle dépendance Composer : `phpoffice/phpspreadsheet`.

```
egam_export/
├── egam_export.info.yml
├── egam_export.permissions.yml      # « export egam data »
├── egam_export.routing.yml
├── egam_export.links.menu.yml       # entrée sous Configuration > EGAM
├── egam_export.services.yml
└── src/
    ├── Controller/ExportController.php
    ├── Export/ExportTable.php
    ├── Export/ExportTableBuilder.php
    ├── Export/FieldValueNormalizer.php
    ├── Export/CsvExporter.php
    └── Export/XlsxExporter.php
```

| Unité | Rôle | Dépend de |
| --- | --- | --- |
| `ExportTable` | Objet valeur : entité, colonnes, lignes, index `id` → numéro de ligne | rien |
| `FieldValueNormalizer` | Convertit une valeur de champ en cellule | définitions de champs |
| `ExportTableBuilder` | Construit les 5 `ExportTable` | `Entities`, `FieldValueNormalizer`, entity type manager |
| `CsvExporter` | Écrit les tables en CSV puis en zip | `ExportTable` |
| `XlsxExporter` | Écrit les tables en classeur XLSX | `ExportTable`, PhpSpreadsheet |
| `ExportController` | Page admin et réponses de téléchargement | builder, exporters |

## Accès et routes

| Route | Chemin | Protection |
| --- | --- | --- |
| page | `/admin/config/egam/export` | permission `export egam data` |
| csv | `/admin/config/egam/export/csv` | permission + `_csrf_token: 'TRUE'` |
| xlsx | `/admin/config/egam/export/xlsx` | permission + `_csrf_token: 'TRUE'` |

La page affiche une courte explication et deux liens de téléchargement. Les liens portent le jeton CSRF : l'opération est coûteuse et ne doit pas pouvoir être déclenchée par une page tierce. La permission est dédiée pour pouvoir être attribuée à un rôle précis.

## Construction des tables

- Pour chaque case de `Entities::cases()`, les ids sont listés avec `accessCheck(FALSE)` pour inclure le contenu non publié. La permission est le garde-fou.
- Chargement par paquets de 200 avec `resetCache()` entre chaque paquet.
- Colonnes, dans l'ordre :
  1. `id`, `label`, `status`
  2. champs issus de `getFieldDefinitions()`, hors champs techniques (uuid, langcode, `revision_*`, `default_langcode`, `revision_translation_affected`)
  3. `created`, `changed`, `owner` (nom d'utilisateur)
  4. `url` (URL publique absolue)
- Les champs de type `image` et `file`, et les références dont la cible est un média ou un fichier, sont exclus.
- Les 5 tables sont construites avant d'écrire un fichier : le XLSX a besoin de l'index `id` → ligne de chaque feuille pour calculer les liens.

## Normalisation des valeurs

| Type de champ | Cellule |
| --- | --- |
| texte, texte long | texte brut, sans HTML |
| booléen | `1` ou `0` |
| date, timestamp | ISO 8601 |
| référence | CSV : `Label (#id)` ; XLSX : label, avec lien interne |
| liste de choix | le label |
| lien | l'URI |
| valeurs multiples | jointes par un retour à la ligne |
| autre type | `getString()` |

**Protection contre l'injection de formules.** Une cellule texte qui commence par `=`, `+`, `-` ou `@` est préfixée par `'`. Le contenu vient de saisies utilisateur et s'ouvre dans un tableur. En XLSX, les cellules texte sont aussi forcées en type chaîne.

## Export CSV

- Un fichier par entité, nommé d'après `getPlural()` : `artworks.csv`, `artists.csv`, `games.csv`, `musea.csv`, `screenshots.csv`.
- Écriture avec `fputcsv`, UTF-8 avec BOM pour que les accents s'affichent correctement dans Excel.
- Les 5 fichiers sont rassemblés dans un zip temporaire (`ZipArchive`), renvoyé en `BinaryFileResponse` avec `deleteFileAfterSend`.
- Nom du téléchargement : `egam-export-YYYY-MM-DD.zip`.

## Export XLSX

- Une feuille par entité. La première ligne contient les en-têtes, en gras et figée. Les largeurs de colonnes sont raisonnables.
- Une cellule de référence affiche le label de la cible et porte un lien interne `sheet://'Artists'!A12` vers sa ligne.
- La colonne `url` contient un lien externe cliquable.
- Fichier temporaire, même mécanisme de réponse que le CSV.
- Nom du téléchargement : `egam-export-YYYY-MM-DD.xlsx`.

## Erreurs et cas limites

- Référence vers une entité supprimée : `#id` en texte simple, sans lien et sans exception.
- Extension `zip` absente ou échec d'écriture du fichier temporaire : l'erreur est journalisée et la page admin affiche un message clair, sans erreur 500 brute.
- Type de champ inconnu : repli sur `getString()`, donc un nouveau champ ne casse pas l'export.

## Tests

**Kernel**

- `FieldValueNormalizer` : un cas par type de champ.
- `ExportTableBuilder` :
  - le contenu non publié est inclus ;
  - aucun champ image, fichier ou média n'apparaît (négatif) ;
  - aucun champ technique n'apparaît (négatif) ;
  - une valeur commençant par `=` sort préfixée par `'`.
- `XlsxExporter` :
  - le classeur compte 5 feuilles ;
  - le lien d'une référence pointe vers la bonne cellule ;
  - une référence orpheline ne produit ni lien ni exception.

**Fonctionnel**

- Anonyme et utilisateur sans permission : 403 sur les 3 routes (négatif).
- Utilisateur avec permission : 200 et `Content-Type` correct.
- Le zip contient exactement 5 fichiers CSV.
- Jeton CSRF absent sur un téléchargement : 403 (négatif).

**Contrainte pratique :** DDEV doit être démarré (Docker) pour lancer les tests.

## Évolutions possibles

- Passer à Batch API ou à une queue si l'export devient trop lourd (dizaines de milliers de lignes ou timeouts). Le service de construction des tables reste inchangé dans ce cas.
- Ajouter les traductions ou les révisions si le besoin apparaît.
