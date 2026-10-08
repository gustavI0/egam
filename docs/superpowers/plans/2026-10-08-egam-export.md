---
id: PLAN-egam-export
type: plan
status: draft
last-updated: 2026-10-08
---

# Export admin EGAM (CSV / XLSX) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un administrateur télécharge, depuis une page du backoffice, les 5 entités EGAM en CSV (un fichier par entité, dans un zip) ou en XLSX (une feuille par entité, avec des liens internes entre feuilles).

**Architecture:** Nouveau module `egam_export`. `ExportTableBuilder` lit les entités via l'enum `Entities` et produit des `ExportTable` neutres (en-têtes + cellules). `CsvExporter` et `XlsxExporter` transforment ces tables en fichiers temporaires. `ExportController` expose une page et deux téléchargements protégés par une permission dédiée et un jeton CSRF.

**Tech Stack:** Drupal 11, PHP 8.1+ (enums, propriétés `readonly`, arguments nommés), `phpoffice/phpspreadsheet`, `ZipArchive`, PHPUnit via `drupal/core-dev`, DDEV.

**Spec:** `docs/superpowers/specs/2026-10-08-egam-export-design.md`

## Global Constraints

- Permission : `export egam data`. Routes : `/admin/config/egam/export` (page), `/admin/config/egam/export/csv`, `/admin/config/egam/export/xlsx`. Les deux téléchargements exigent la permission **et** `_csrf_token: 'TRUE'`.
- Les 5 entités viennent de `Drupal\egam_global\Entities::cases()`. Les identifiants de type d'entité sont `artwork`, `artist`, `game`, `museum`, `screenshot`. Le bundle porte le même nom que le type.
- Requête d'ids avec `accessCheck(FALSE)` (contenu non publié inclus). Chargement par paquets de **200** avec `resetCache()` entre chaque paquet.
- Révision courante et langue par défaut uniquement.
- Colonnes : `id`, `label`, `status` en tête ; `created`, `changed`, `owner`, `url` en fin ; entre les deux, les autres champs de `getFieldDefinitions()`. En-têtes = noms machine.
- Exclus : champs de type `image`, `file`, `metatag` ; champs calculés ; références dont `target_type` est `media` ou `file` ; champs techniques (`uuid`, `langcode`, `default_langcode`, `revision_default`, `revision_translation_affected`, clé de révision, clés de métadonnées de révision).
- Valeurs : texte brut sans HTML ; booléen `1`/`0` ; dates ISO 8601 (`DATE_ATOM`, UTC) ; valeurs multiples jointes par `"\n"` ; référence CSV = `Label (#id)` (utilisateur : label seul), XLSX = label.
- Anti injection de formules : une cellule texte commençant par `=`, `+`, `-`, `@`, tabulation ou retour chariot est préfixée par `'`. Les nombres, booléens et dates ne sont pas préfixés. En XLSX, toute cellule texte est écrite avec `DataType::TYPE_STRING`.
- CSV : UTF-8 avec BOM, séparateur `,`, un fichier par entité nommé `<getPlural()>.csv` (`artworks`, `artists`, `games`, `musea`, `screenshots`), zip nommé `egam-export-YYYY-MM-DD.zip`.
- XLSX : feuilles nommées `ucfirst(getPlural())` (`Artworks`, `Artists`, `Games`, `Musea`, `Screenshots`), en-têtes en gras, volet figé en `A2`, lien interne `sheet://'Artists'!A12`, fichier nommé `egam-export-YYYY-MM-DD.xlsx`.
- Style : PHP Drupal (indentation 2 espaces), pas de `final` sur les services (les tests les mockent). Les messages d'interface sont en anglais, comme le reste du site.
- **Tests : DDEV doit tourner.** Docker est arrêté au moment de la rédaction de ce plan.

Commandes de test utilisées dans tout le plan :

```bash
# Kernel (SQLite en mémoire)
ddev exec 'SIMPLETEST_DB=sqlite://localhost/:memory: vendor/bin/phpunit -c web/core web/modules/custom/egam_export/tests/src/Kernel'
# Unit
ddev exec 'vendor/bin/phpunit -c web/core web/modules/custom/egam_export/tests/src/Unit'
# Functional (tables préfixées, la base de dev n'est pas touchée)
ddev exec 'SIMPLETEST_BASE_URL=http://localhost SIMPLETEST_DB=mysql://db:db@db/db BROWSERTEST_OUTPUT_DIRECTORY=/tmp vendor/bin/phpunit -c web/core web/modules/custom/egam_export/tests/src/Functional'
```

Pour lancer un seul test, ajouter `--filter NomDuTest` avant le chemin.

## Review Focus

Entrées que la spec implique sans qu'une tâche évidente les teste, les plus probables d'abord. Chacune a son test dans la tâche indiquée.

1. Libellé contenant une virgule, des guillemets, un retour à la ligne, des accents ou un emoji : le CSV doit se relire à l'identique (tâche 5).
2. Aucun contenu en base : 5 fichiers/feuilles avec seulement la ligne d'en-têtes, sans erreur (tâches 4, 5 et 6).
3. Plus de 200 entités : aucune ligne perdue ni dupliquée à la frontière des paquets, ordre croissant par id (tâche 4).
4. Texte long contenant du HTML et des entités (`<p>Fish &amp; chips</p><p>Second</p>`) : texte brut lisible, paragraphes séparés par une ligne (tâche 3).
5. Référence multiple dont la première cible a été supprimée : `#id` pour l'orpheline, lien vers la première cible qui existe encore (tâches 3 et 6).

---

### Task 1: Outillage de test et squelette du module

**Files:**

- Modify: `composer.json`, `composer.lock`
- Create: `web/modules/custom/egam_export/egam_export.info.yml`
- Create: `web/modules/custom/egam_export/egam_export.permissions.yml`
- Create: `web/modules/custom/egam_export/tests/src/Kernel/ExportKernelTestBase.php`
- Create: `web/modules/custom/egam_export/tests/src/Kernel/ModuleSmokeTest.php`

**Interfaces:**

- Produces: `Drupal\Tests\egam_export\Kernel\ExportKernelTestBase` (classe abstraite). Elle active les modules EGAM, installe les schémas des 5 entités, reconstruit le routeur et crée les champs de test ci-dessous. Méthode utilitaire : `protected function addField(string $entityTypeId, string $name, string $type, array $storageSettings = [], int $cardinality = 1): void`.
- Champs créés par la base : `artwork.field_date` (string), `artwork.field_sorting_year` (integer), `artwork.field_more_info` (link), `artwork.field_artist_prefix` (list_string : `from` => `D'après`, `attributed_to` => `Attribué à`), `artwork.field_artist` (référence `artist`), `artwork.field_museum` (référence `museum`), `artwork.field_subject` (référence `taxonomy_term`, illimité), `artwork.field_photo` (image), `artwork.field_attachment` (référence `file`), `screenshot.field_artwork` (référence `artwork`, illimité), `screenshot.field_game` (référence `game`).

- [ ] **Step 1: Démarrer l'environnement**

Run: `ddev start`
Expected: les conteneurs démarrent. Si Docker n'est pas lancé, démarrer OrbStack d'abord.

- [ ] **Step 2: Ajouter les dépendances**

```bash
ddev composer require phpoffice/phpspreadsheet
ddev composer require --dev drupal/core-dev --with-all-dependencies
ddev exec vendor/bin/phpunit --version
```

Expected: PHPUnit affiche sa version. `composer.json` gagne `phpoffice/phpspreadsheet` dans `require` et `drupal/core-dev` dans `require-dev`. Si `drupal/core-dev` est refusé à cause de la version de Drupal installée, utiliser la même contrainte que `drupal/core-recommended`.

- [ ] **Step 3: Écrire la classe de base des tests Kernel**

`web/modules/custom/egam_export/tests/src/Kernel/ExportKernelTestBase.php` :

```php
<?php

namespace Drupal\Tests\egam_export\Kernel;

use Drupal\egam_global\Entities;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Shared setup: EGAM entities plus a representative set of fields.
 */
abstract class ExportKernelTestBase extends KernelTestBase {

  protected static $modules = [
    'system', 'user', 'field', 'text', 'options', 'link', 'file', 'image',
    'datetime', 'taxonomy', 'filter',
    'egam_global', 'egam_artwork', 'egam_artist', 'egam_game',
    'egam_museum', 'egam_screenshot', 'egam_export',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    foreach (Entities::cases() as $case) {
      $this->installEntitySchema($case->value);
    }
    $this->installSchema('file', ['file_usage']);

    $this->addField('artwork', 'field_date', 'string');
    $this->addField('artwork', 'field_sorting_year', 'integer');
    $this->addField('artwork', 'field_more_info', 'link');
    $this->addField('artwork', 'field_artist_prefix', 'list_string', [
      'allowed_values' => ['from' => "D'après", 'attributed_to' => 'Attribué à'],
    ]);
    $this->addField('artwork', 'field_artist', 'entity_reference', ['target_type' => 'artist']);
    $this->addField('artwork', 'field_museum', 'entity_reference', ['target_type' => 'museum']);
    $this->addField('artwork', 'field_subject', 'entity_reference', ['target_type' => 'taxonomy_term'], -1);
    $this->addField('artwork', 'field_photo', 'image');
    $this->addField('artwork', 'field_attachment', 'entity_reference', ['target_type' => 'file']);
    $this->addField('screenshot', 'field_artwork', 'entity_reference', ['target_type' => 'artwork'], -1);
    $this->addField('screenshot', 'field_game', 'entity_reference', ['target_type' => 'game']);

    $this->container->get('router.builder')->rebuild();
  }

  protected function addField(string $entityTypeId, string $name, string $type, array $storageSettings = [], int $cardinality = 1): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entityTypeId,
      'type' => $type,
      'settings' => $storageSettings,
      'cardinality' => $cardinality,
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entityTypeId,
      'bundle' => $entityTypeId,
      'label' => $name,
    ])->save();
  }

}
```

- [ ] **Step 4: Écrire le test de fumée (il doit échouer)**

`web/modules/custom/egam_export/tests/src/Kernel/ModuleSmokeTest.php` :

```php
<?php

namespace Drupal\Tests\egam_export\Kernel;

/**
 * The module installs next to the five entity modules and defines its permission.
 */
class ModuleSmokeTest extends ExportKernelTestBase {

  public function testPermissionIsDefined(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();
    $this->assertArrayHasKey('export egam data', $permissions);
  }

  public function testPermissionIsRestricted(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();
    $this->assertTrue($permissions['export egam data']['restrict access']);
  }

}
```

- [ ] **Step 5: Lancer le test et constater l'échec**

Run: la commande Kernel avec `--filter ModuleSmokeTest`
Expected: FAIL, `Unable to install modules: module 'egam_export' is missing` (ou équivalent).

- [ ] **Step 6: Créer le module**

`web/modules/custom/egam_export/egam_export.info.yml` :

```yaml
name: 'EGAM Export'
type: module
description: 'Admin export of all EGAM entities to CSV and XLSX.'
package: Custom
core_version_requirement: ^10 || ^11
dependencies:
  - egam_global:egam_global
  - egam_artwork:egam_artwork
  - egam_artist:egam_artist
  - egam_game:egam_game
  - egam_museum:egam_museum
  - egam_screenshot:egam_screenshot
```

`web/modules/custom/egam_export/egam_export.permissions.yml` :

```yaml
export egam data:
  title: 'Export EGAM data'
  description: 'Download all EGAM content, including unpublished content, as CSV or XLSX.'
  restrict access: true
```

- [ ] **Step 7: Lancer le test et constater le succès**

Run: la commande Kernel avec `--filter ModuleSmokeTest`
Expected: PASS (2 tests). Si l'installation d'un module EGAM échoue dans le Kernel (service ou schéma manquant), ajouter le module ou le schéma nécessaire à `$modules` / `setUp()` de la base : c'est le but de ce test de fumée. Si seuls des échecs de dépréciation sans rapport apparaissent, préfixer la commande par `SYMFONY_DEPRECATIONS_HELPER=disabled`.

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock web/modules/custom/egam_export
git commit -m "feat(export): scaffold egam_export module and test tooling

Adds PhpSpreadsheet and core-dev so the export can be built test-first,
and a permission so the feature can be limited to chosen roles.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Objets valeur `ExportCell`, `ExportTable`, `ExportException`

**Files:**

- Create: `web/modules/custom/egam_export/src/Export/ExportCell.php`
- Create: `web/modules/custom/egam_export/src/Export/ExportTable.php`
- Create: `web/modules/custom/egam_export/src/Export/ExportException.php`
- Test: `web/modules/custom/egam_export/tests/src/Unit/ExportTableTest.php`

**Interfaces:**

- Produces:
  - `ExportCell::__construct(string $text, ?string $csvText = NULL, ?Entities $linkEntity = NULL, string|int|null $linkId = NULL, ?string $externalUrl = NULL)`, propriétés publiques `readonly`, méthode `csv(): string` (renvoie `$csvText ?? $text`).
  - `ExportTable::__construct(Entities $entity, array $headers, array $rows)` avec `$headers` = `list<string>` et `$rows` = `list<list<ExportCell>>`. Propriétés publiques `readonly` `$entity`, `$headers`, `$rows`. Méthodes : `sheetName(): string`, `fileName(): string`, `rowNumberFor(string|int $id): ?int`. Le numéro de ligne est celui de la feuille (en-têtes en ligne 1, première donnée en ligne 2). La cellule 0 de chaque ligne est l'`id`.
  - `ExportException extends \RuntimeException`.

- [ ] **Step 1: Écrire le test (il doit échouer)**

`web/modules/custom/egam_export/tests/src/Unit/ExportTableTest.php` :

```php
<?php

namespace Drupal\Tests\egam_export\Unit;

use Drupal\egam_export\Export\ExportCell;
use Drupal\egam_export\Export\ExportTable;
use Drupal\egam_global\Entities;
use Drupal\Tests\UnitTestCase;

class ExportTableTest extends UnitTestCase {

  private function table(Entities $entity, array $ids): ExportTable {
    $rows = array_map(
      static fn (string $id): array => [new ExportCell($id), new ExportCell("Label $id")],
      $ids,
    );
    return new ExportTable($entity, ['id', 'label'], $rows);
  }

  public function testRowNumbersStartAfterTheHeaderRow(): void {
    $table = $this->table(Entities::Artist, ['10', '12']);
    $this->assertSame(2, $table->rowNumberFor('10'));
    $this->assertSame(3, $table->rowNumberFor(12));
  }

  public function testUnknownIdHasNoRowNumber(): void {
    $table = $this->table(Entities::Artist, ['10']);
    $this->assertNull($table->rowNumberFor('999'));
  }

  public function testEmptyTableHasNoRowNumbers(): void {
    $table = $this->table(Entities::Artist, []);
    $this->assertNull($table->rowNumberFor('1'));
  }

  public function testNamesComeFromThePluralForm(): void {
    $table = $this->table(Entities::Museum, []);
    $this->assertSame('Musea', $table->sheetName());
    $this->assertSame('musea.csv', $table->fileName());
  }

  public function testCsvTextFallsBackToText(): void {
    $this->assertSame('Leonardo', (new ExportCell('Leonardo'))->csv());
    $this->assertSame('Leonardo (#1)', (new ExportCell('Leonardo', 'Leonardo (#1)'))->csv());
  }

}
```

- [ ] **Step 2: Lancer le test et constater l'échec**

Run: la commande Unit
Expected: FAIL, `Class "Drupal\egam_export\Export\ExportCell" not found`.

- [ ] **Step 3: Écrire les classes**

`ExportCell.php` :

```php
<?php

namespace Drupal\egam_export\Export;

use Drupal\egam_global\Entities;

/**
 * One exported cell: text, plus an optional link to another sheet or a URL.
 */
final class ExportCell {

  public function __construct(
    public readonly string $text,
    public readonly ?string $csvText = NULL,
    public readonly ?Entities $linkEntity = NULL,
    public readonly string|int|null $linkId = NULL,
    public readonly ?string $externalUrl = NULL,
  ) {}

  /**
   * Text to write in a CSV file.
   */
  public function csv(): string {
    return $this->csvText ?? $this->text;
  }

}
```

`ExportTable.php` :

```php
<?php

namespace Drupal\egam_export\Export;

use Drupal\egam_global\Entities;

/**
 * The exported content of one entity type: headers and rows of cells.
 *
 * The first cell of every row is the entity id.
 */
final class ExportTable {

  /**
   * Sheet row number by entity id.
   *
   * @var array<string, int>
   */
  private array $rowNumbers = [];

  /**
   * @param list<string> $headers
   * @param list<list<ExportCell>> $rows
   */
  public function __construct(
    public readonly Entities $entity,
    public readonly array $headers,
    public readonly array $rows,
  ) {
    foreach ($rows as $index => $row) {
      // Row 1 of a sheet holds the headers.
      $this->rowNumbers[$row[0]->text] = $index + 2;
    }
  }

  public function sheetName(): string {
    return ucfirst((string) $this->entity->getPlural());
  }

  public function fileName(): string {
    return $this->entity->getPlural() . '.csv';
  }

  public function rowNumberFor(string|int $id): ?int {
    return $this->rowNumbers[(string) $id] ?? NULL;
  }

}
```

`ExportException.php` :

```php
<?php

namespace Drupal\egam_export\Export;

/**
 * Thrown when an export file cannot be produced.
 */
class ExportException extends \RuntimeException {}
```

- [ ] **Step 4: Lancer le test et constater le succès**

Run: la commande Unit
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/egam_export
git commit -m "feat(export): add export cell and table value objects

Gives the builder and both exporters one neutral shape to share, so the
XLSX links can be computed from row numbers without touching entities.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 3: `FieldValueNormalizer`

**Files:**

- Create: `web/modules/custom/egam_export/src/Export/FieldValueNormalizer.php`
- Create: `web/modules/custom/egam_export/egam_export.services.yml`
- Test: `web/modules/custom/egam_export/tests/src/Kernel/FieldValueNormalizerTest.php`

**Interfaces:**

- Consumes: `ExportCell` (tâche 2), `Entities`, `ExportKernelTestBase` et ses champs (tâche 1).
- Produces: service `Drupal\egam_export\Export\FieldValueNormalizer` avec `normalize(FieldItemListInterface $items): ExportCell` et `sanitize(string $value): string` (préfixe `'` si la valeur commence par `=`, `+`, `-`, `@`, tabulation ou retour chariot).

- [ ] **Step 1: Écrire le test (il doit échouer)**

`web/modules/custom/egam_export/tests/src/Kernel/FieldValueNormalizerTest.php` :

```php
<?php

namespace Drupal\Tests\egam_export\Kernel;

use Drupal\egam_artist\Entity\Artist;
use Drupal\egam_artwork\Entity\Artwork;
use Drupal\egam_export\Export\FieldValueNormalizer;
use Drupal\egam_global\Entities;
use Drupal\egam_screenshot\Entity\Screenshot;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;

class FieldValueNormalizerTest extends ExportKernelTestBase {

  private function normalizer(): FieldValueNormalizer {
    return $this->container->get(FieldValueNormalizer::class);
  }

  public function testPlainStringIsKept(): void {
    $artwork = Artwork::create(['label' => 'Mona Lisa']);
    $this->assertSame('Mona Lisa', $this->normalizer()->normalize($artwork->get('label'))->text);
  }

  /**
   * @dataProvider formulaProvider
   */
  public function testFormulaTriggersArePrefixed(string $label): void {
    $artwork = Artwork::create(['label' => $label]);
    $this->assertSame("'" . $label, $this->normalizer()->normalize($artwork->get('label'))->text);
  }

  public static function formulaProvider(): array {
    return [
      'equals' => ['=HYPERLINK("http://evil.example","x")'],
      'plus' => ['+1+1'],
      'minus' => ['-2+3'],
      'at' => ['@SUM(A1)'],
      'tab' => ["\tcmd"],
      'carriage return' => ["\rcmd"],
    ];
  }

  public function testNegativeIntegerIsNotPrefixed(): void {
    $artwork = Artwork::create(['label' => 'Old', 'field_sorting_year' => -500]);
    $this->assertSame('-500', $this->normalizer()->normalize($artwork->get('field_sorting_year'))->text);
  }

  public function testBooleanIsOneOrZero(): void {
    $this->assertSame('1', $this->normalizer()->normalize(Artwork::create(['label' => 'a', 'status' => 1])->get('status'))->text);
    $this->assertSame('0', $this->normalizer()->normalize(Artwork::create(['label' => 'b', 'status' => 0])->get('status'))->text);
  }

  public function testTimestampIsIso8601InUtc(): void {
    $artwork = Artwork::create(['label' => 'a', 'created' => 0]);
    $this->assertSame('1970-01-01T00:00:00+00:00', $this->normalizer()->normalize($artwork->get('created'))->text);
  }

  public function testLongTextBecomesPlainText(): void {
    $artwork = Artwork::create([
      'label' => 'a',
      'description' => ['value' => '<p>Fish &amp; chips</p><p>Second</p>'],
    ]);
    $this->assertSame("Fish & chips\nSecond", $this->normalizer()->normalize($artwork->get('description'))->text);
  }

  public function testListValueShowsItsLabel(): void {
    $artwork = Artwork::create(['label' => 'a', 'field_artist_prefix' => 'attributed_to']);
    $this->assertSame('Attribué à', $this->normalizer()->normalize($artwork->get('field_artist_prefix'))->text);
  }

  public function testLinkShowsItsUri(): void {
    $artwork = Artwork::create(['label' => 'a', 'field_more_info' => ['uri' => 'https://example.com/more', 'title' => 'More']]);
    $this->assertSame('https://example.com/more', $this->normalizer()->normalize($artwork->get('field_more_info'))->text);
  }

  public function testEmptyFieldIsAnEmptyCell(): void {
    $artwork = Artwork::create(['label' => 'a']);
    $this->assertSame('', $this->normalizer()->normalize($artwork->get('field_date'))->text);
  }

  public function testReferenceToEgamEntityCarriesALink(): void {
    $artist = Artist::create(['label' => 'Leonardo']);
    $artist->save();
    $artwork = Artwork::create(['label' => 'Mona Lisa', 'field_artist' => $artist->id()]);

    $cell = $this->normalizer()->normalize($artwork->get('field_artist'));

    $this->assertSame('Leonardo', $cell->text);
    $this->assertSame('Leonardo (#' . $artist->id() . ')', $cell->csv());
    $this->assertSame(Entities::Artist, $cell->linkEntity);
    $this->assertSame($artist->id(), $cell->linkId);
  }

  public function testReferenceToTaxonomyTermHasNoLink(): void {
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    $term = Term::create(['vid' => 'tags', 'name' => 'Oil']);
    $term->save();
    $artwork = Artwork::create(['label' => 'a', 'field_subject' => [$term->id()]]);

    $cell = $this->normalizer()->normalize($artwork->get('field_subject'));

    $this->assertSame('Oil', $cell->text);
    $this->assertSame('Oil (#' . $term->id() . ')', $cell->csv());
    $this->assertNull($cell->linkEntity);
    $this->assertNull($cell->linkId);
  }

  public function testOwnerShowsTheUsernameOnly(): void {
    $user = User::create(['name' => 'alice']);
    $user->save();
    $artwork = Artwork::create(['label' => 'a', 'uid' => $user->id()]);

    $cell = $this->normalizer()->normalize($artwork->get('uid'));

    $this->assertSame('alice', $cell->csv());
    $this->assertNull($cell->linkEntity);
  }

  public function testMultiReferenceWithDeletedFirstTargetLinksToFirstExistingOne(): void {
    $artwork = Artwork::create(['label' => 'Second']);
    $artwork->save();
    $screenshot = Screenshot::create(['label' => 's']);
    $screenshot->set('field_artwork', [['target_id' => 999], ['target_id' => $artwork->id()]]);

    $cell = $this->normalizer()->normalize($screenshot->get('field_artwork'));

    $this->assertSame("#999\nSecond", $cell->text);
    $this->assertSame("#999\nSecond (#" . $artwork->id() . ')', $cell->csv());
    $this->assertSame(Entities::Artwork, $cell->linkEntity);
    $this->assertSame($artwork->id(), $cell->linkId);
  }

  public function testReferenceToDeletedEntityOnlyHasNoLink(): void {
    $screenshot = Screenshot::create(['label' => 's']);
    $screenshot->set('field_artwork', [['target_id' => 999]]);

    $cell = $this->normalizer()->normalize($screenshot->get('field_artwork'));

    $this->assertSame('#999', $cell->text);
    $this->assertNull($cell->linkEntity);
    $this->assertNull($cell->linkId);
  }

}
```

- [ ] **Step 2: Lancer le test et constater l'échec**

Run: la commande Kernel avec `--filter FieldValueNormalizerTest`
Expected: FAIL, service `Drupal\egam_export\Export\FieldValueNormalizer` introuvable.

- [ ] **Step 3: Écrire le service et l'enregistrer**

`web/modules/custom/egam_export/egam_export.services.yml` :

```yaml
services:
  Drupal\egam_export\Export\FieldValueNormalizer:
    class: Drupal\egam_export\Export\FieldValueNormalizer
```

`web/modules/custom/egam_export/src/Export/FieldValueNormalizer.php` :

```php
<?php

namespace Drupal\egam_export\Export;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\egam_global\Entities;
use Drupal\options\Plugin\Field\FieldType\ListItemBase;

/**
 * Turns the values of a field into one exportable cell.
 */
class FieldValueNormalizer {

  /**
   * First characters that make a spreadsheet read a cell as a formula.
   */
  private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

  public function normalize(FieldItemListInterface $items): ExportCell {
    $type = $items->getFieldDefinition()->getType();
    if ($type === 'entity_reference') {
      return $this->normalizeReference($items);
    }
    $values = [];
    foreach ($items as $item) {
      $values[] = $this->normalizeItem($type, $item);
    }
    return new ExportCell(implode("\n", $values));
  }

  /**
   * Prefixes values that a spreadsheet would execute as a formula.
   */
  public function sanitize(string $value): string {
    if ($value !== '' && in_array($value[0], self::FORMULA_TRIGGERS, TRUE)) {
      return "'" . $value;
    }
    return $value;
  }

  private function normalizeItem(string $type, FieldItemInterface $item): string {
    return match ($type) {
      'boolean' => $item->value ? '1' : '0',
      'integer', 'decimal', 'float' => (string) $item->value,
      'created', 'changed', 'timestamp' => gmdate(\DATE_ATOM, (int) $item->value),
      'datetime' => (string) $item->value,
      'list_string', 'list_integer', 'list_float' => $this->sanitize($this->listLabel($item)),
      'text', 'text_long', 'text_with_summary' => $this->sanitize($this->plainText((string) $item->value)),
      'link' => $this->sanitize((string) $item->uri),
      default => $this->sanitize($item->getString()),
    };
  }

  private function listLabel(FieldItemInterface $item): string {
    $key = (string) $item->value;
    if ($item instanceof ListItemBase) {
      return (string) ($item->getPossibleOptions()[$key] ?? $key);
    }
    return $key;
  }

  private function plainText(string $html): string {
    $withBreaks = preg_replace('#<(br\s*/?|/p)>#i', "\n", $html);
    return trim(Html::decodeEntities(strip_tags($withBreaks)));
  }

  private function normalizeReference(FieldItemListInterface $items): ExportCell {
    $targetType = (string) $items->getFieldDefinition()->getSetting('target_type');
    $linkEntity = Entities::tryFrom($targetType);
    $texts = [];
    $csvTexts = [];
    $linkId = NULL;
    foreach ($items as $item) {
      $id = $item->target_id;
      $entity = $item->entity;
      if ($entity === NULL) {
        // The target was deleted: keep its id, nothing to link to.
        $texts[] = $csvTexts[] = '#' . $id;
        continue;
      }
      $label = $this->sanitize((string) $entity->label());
      $texts[] = $label;
      $csvTexts[] = $targetType === 'user' ? $label : $label . ' (#' . $id . ')';
      if ($linkEntity !== NULL && $linkId === NULL) {
        $linkId = $id;
      }
    }
    return new ExportCell(
      implode("\n", $texts),
      implode("\n", $csvTexts),
      $linkId !== NULL ? $linkEntity : NULL,
      $linkId,
    );
  }

}
```

- [ ] **Step 4: Lancer le test et constater le succès**

Run: la commande Kernel avec `--filter FieldValueNormalizerTest`
Expected: PASS. Si `testLongTextBecomesPlainText` échoue sur les retours à la ligne, corriger `plainText()` : c'est le comportement attendu qui fait foi (`"Fish & chips\nSecond"`).

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/egam_export
git commit -m "feat(export): normalize field values into export cells

Centralizes how each field type reads in a spreadsheet, including the
formula-injection guard, so CSV and XLSX cannot drift apart.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 4: `ExportTableBuilder`

**Files:**

- Create: `web/modules/custom/egam_export/src/Export/ExportTableBuilder.php`
- Modify: `web/modules/custom/egam_export/egam_export.services.yml`
- Test: `web/modules/custom/egam_export/tests/src/Kernel/ExportTableBuilderTest.php`

**Interfaces:**

- Consumes: `FieldValueNormalizer::normalize()` (tâche 3), `ExportCell`, `ExportTable` (tâche 2), `Entities`.
- Produces: service `Drupal\egam_export\Export\ExportTableBuilder` avec `buildAll(): array` (`list<ExportTable>` dans l'ordre de `Entities::cases()`) et `build(Entities $entity): ExportTable`.

- [ ] **Step 1: Écrire le test (il doit échouer)**

`web/modules/custom/egam_export/tests/src/Kernel/ExportTableBuilderTest.php` :

```php
<?php

namespace Drupal\Tests\egam_export\Kernel;

use Drupal\egam_artwork\Entity\Artwork;
use Drupal\egam_export\Export\ExportTable;
use Drupal\egam_export\Export\ExportTableBuilder;
use Drupal\egam_global\Entities;

class ExportTableBuilderTest extends ExportKernelTestBase {

  private function build(Entities $entity): ExportTable {
    return $this->container->get(ExportTableBuilder::class)->build($entity);
  }

  private function column(ExportTable $table, string $header): array {
    $index = array_search($header, $table->headers, TRUE);
    $this->assertNotFalse($index, "Missing column $header");
    return array_map(static fn (array $row): string => $row[$index]->text, $table->rows);
  }

  public function testBuildAllReturnsOneTablePerEntityInEnumOrder(): void {
    $tables = $this->container->get(ExportTableBuilder::class)->buildAll();
    $this->assertSame(
      Entities::cases(),
      array_map(static fn (ExportTable $table): Entities => $table->entity, $tables),
    );
  }

  public function testEmptyDatabaseStillGivesHeaders(): void {
    foreach ($this->container->get(ExportTableBuilder::class)->buildAll() as $table) {
      $this->assertSame([], $table->rows, $table->entity->value);
      $this->assertContains('id', $table->headers, $table->entity->value);
    }
  }

  public function testLeadingAndTrailingColumns(): void {
    $headers = $this->build(Entities::Artwork)->headers;
    $this->assertSame(['id', 'label', 'status'], array_slice($headers, 0, 3));
    $this->assertSame(['created', 'changed', 'owner', 'url'], array_slice($headers, -4));
  }

  public function testBusinessFieldsAreIncluded(): void {
    $headers = $this->build(Entities::Artwork)->headers;
    foreach (['description', 'field_date', 'field_artist', 'field_museum', 'field_subject'] as $name) {
      $this->assertContains($name, $headers);
    }
  }

  public function testUnpublishedContentIsIncluded(): void {
    Artwork::create(['label' => 'Draft', 'status' => 0])->save();
    Artwork::create(['label' => 'Live', 'status' => 1])->save();

    $table = $this->build(Entities::Artwork);

    $this->assertSame(['Draft', 'Live'], $this->column($table, 'label'));
    $this->assertSame(['0', '1'], $this->column($table, 'status'));
  }

  public function testImageAndFileFieldsAreExcluded(): void {
    $headers = $this->build(Entities::Artwork)->headers;
    $this->assertNotContains('field_photo', $headers);
    $this->assertNotContains('field_attachment', $headers);
  }

  public function testTechnicalFieldsAreExcluded(): void {
    $headers = $this->build(Entities::Artwork)->headers;
    foreach (['uuid', 'langcode', 'default_langcode', 'revision_id', 'revision_uid', 'revision_timestamp', 'revision_log', 'revision_default', 'revision_translation_affected'] as $name) {
      $this->assertNotContains($name, $headers);
    }
  }

  public function testUrlColumnIsTheAbsoluteCanonicalUrl(): void {
    $artwork = Artwork::create(['label' => 'Mona Lisa']);
    $artwork->save();

    $url = $this->column($this->build(Entities::Artwork), 'url')[0];

    $this->assertMatchesRegularExpression('#^https?://[^/]+/artwork/' . $artwork->id() . '$#', $url);
    $this->assertSame($url, $this->build(Entities::Artwork)->rows[0][count($this->build(Entities::Artwork)->headers) - 1]->externalUrl);
  }

  public function testFormulaLikeLabelIsPrefixed(): void {
    Artwork::create(['label' => '=1+1'])->save();
    $this->assertSame(["'=1+1"], $this->column($this->build(Entities::Artwork), 'label'));
  }

  public function testRowLookupMatchesTheEntityId(): void {
    $first = Artwork::create(['label' => 'One']);
    $first->save();
    $second = Artwork::create(['label' => 'Two']);
    $second->save();

    $table = $this->build(Entities::Artwork);

    $this->assertSame(2, $table->rowNumberFor($first->id()));
    $this->assertSame(3, $table->rowNumberFor($second->id()));
  }

  public function testNoRowIsLostOrDuplicatedAcrossChunks(): void {
    for ($i = 1; $i <= 201; $i++) {
      Artwork::create(['label' => "Work $i"])->save();
    }

    $ids = array_map('intval', $this->column($this->build(Entities::Artwork), 'id'));

    $this->assertSame(range(1, 201), $ids);
  }

}
```

- [ ] **Step 2: Lancer le test et constater l'échec**

Run: la commande Kernel avec `--filter ExportTableBuilderTest`
Expected: FAIL, service `ExportTableBuilder` introuvable.

- [ ] **Step 3: Écrire le service et l'enregistrer**

Ajouter à `egam_export.services.yml` (sous `services:`) :

```yaml
  Drupal\egam_export\Export\ExportTableBuilder:
    class: Drupal\egam_export\Export\ExportTableBuilder
    arguments: ['@entity_type.manager', '@entity_field.manager', '@Drupal\egam_export\Export\FieldValueNormalizer']
```

`web/modules/custom/egam_export/src/Export/ExportTableBuilder.php` :

```php
<?php

namespace Drupal\egam_export\Export;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\egam_global\Entities;

/**
 * Reads the EGAM entities and builds one ExportTable per entity type.
 */
class ExportTableBuilder {

  private const CHUNK_SIZE = 200;

  /**
   * Columns placed first and last, in this order.
   */
  private const LEADING_FIELDS = ['id', 'label', 'status'];
  private const TRAILING_FIELDS = ['created', 'changed', 'uid'];

  private const TECHNICAL_FIELDS = [
    'uuid',
    'langcode',
    'default_langcode',
    'revision_default',
    'revision_translation_affected',
  ];

  private const EXCLUDED_FIELD_TYPES = ['image', 'file', 'metatag'];

  private const EXCLUDED_TARGET_TYPES = ['media', 'file'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly FieldValueNormalizer $normalizer,
  ) {}

  /**
   * @return list<ExportTable>
   *   One table per entity type, in the order of Entities::cases().
   */
  public function buildAll(): array {
    return array_map($this->build(...), Entities::cases());
  }

  public function build(Entities $entity): ExportTable {
    $columns = $this->columns($entity->value);
    $headers = array_map(
      static fn (string $name): string => $name === 'uid' ? 'owner' : $name,
      $columns,
    );
    $headers[] = 'url';

    $storage = $this->entityTypeManager->getStorage($entity->value);
    $ids = array_values($storage->getQuery()->accessCheck(FALSE)->sort('id')->execute());

    $rows = [];
    foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
      $loaded = $storage->loadMultiple($chunk);
      ksort($loaded);
      foreach ($loaded as $content) {
        $row = [];
        foreach ($columns as $name) {
          $row[] = $this->normalizer->normalize($content->get($name));
        }
        $url = $content->toUrl('canonical', ['absolute' => TRUE])->toString();
        $row[] = new ExportCell($url, externalUrl: $url);
        $rows[] = $row;
      }
      $storage->resetCache($chunk);
    }

    return new ExportTable($entity, $headers, $rows);
  }

  /**
   * Field names to export, in column order (the url column is added apart).
   *
   * @return list<string>
   */
  private function columns(string $entityTypeId): array {
    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    $technical = array_filter([
      ...self::TECHNICAL_FIELDS,
      $entityType->getKey('revision'),
      ...array_values($entityType->getRevisionMetadataKeys()),
    ]);
    $fixed = [...self::LEADING_FIELDS, ...self::TRAILING_FIELDS];

    $middle = [];
    $definitions = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $entityTypeId);
    foreach ($definitions as $name => $definition) {
      if (in_array($name, $fixed, TRUE) || in_array($name, $technical, TRUE)) {
        continue;
      }
      if ($this->isExcluded($definition)) {
        continue;
      }
      $middle[] = $name;
    }

    return [...self::LEADING_FIELDS, ...$middle, ...self::TRAILING_FIELDS];
  }

  private function isExcluded(FieldDefinitionInterface $definition): bool {
    if ($definition->isComputed()) {
      return TRUE;
    }
    if (in_array($definition->getType(), self::EXCLUDED_FIELD_TYPES, TRUE)) {
      return TRUE;
    }
    return $definition->getType() === 'entity_reference'
      && in_array($definition->getSetting('target_type'), self::EXCLUDED_TARGET_TYPES, TRUE);
  }

}
```

- [ ] **Step 4: Lancer le test et constater le succès**

Run: la commande Kernel avec `--filter ExportTableBuilderTest`
Expected: PASS. Si `testTechnicalFieldsAreExcluded` signale un champ technique imprévu (par exemple un champ ajouté par un autre module), l'ajouter à `TECHNICAL_FIELDS` plutôt que d'assouplir le test.

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/egam_export
git commit -m "feat(export): build export tables from the five EGAM entities

Reads fields at runtime so new Field UI fields are exported without code
changes, and loads in chunks so a large catalogue does not exhaust memory.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 5: `CsvExporter`

**Files:**

- Create: `web/modules/custom/egam_export/src/Export/CsvExporter.php`
- Modify: `web/modules/custom/egam_export/egam_export.services.yml`
- Test: `web/modules/custom/egam_export/tests/src/Kernel/CsvExporterTest.php`

**Interfaces:**

- Consumes: `ExportTable`, `ExportCell`, `ExportException` (tâche 2), `Entities`.
- Produces: service `Drupal\egam_export\Export\CsvExporter` avec `export(array $tables): string` : prend `list<ExportTable>`, renvoie le chemin d'un zip temporaire (à supprimer par l'appelant).

- [ ] **Step 1: Écrire le test (il doit échouer)**

`web/modules/custom/egam_export/tests/src/Kernel/CsvExporterTest.php` :

```php
<?php

namespace Drupal\Tests\egam_export\Kernel;

use Drupal\egam_export\Export\CsvExporter;
use Drupal\egam_export\Export\ExportCell;
use Drupal\egam_export\Export\ExportTable;
use Drupal\egam_global\Entities;

class CsvExporterTest extends ExportKernelTestBase {

  private array $paths = [];

  protected function tearDown(): void {
    foreach ($this->paths as $path) {
      @unlink($path);
    }
    parent::tearDown();
  }

  /**
   * @param array<string, list<list<ExportCell>>> $rowsByEntity
   *   Rows keyed by Entities value; missing entities get no rows.
   */
  private function export(array $rowsByEntity = []): \ZipArchive {
    $tables = array_map(
      static fn (Entities $entity): ExportTable => new ExportTable(
        $entity,
        ['id', 'label'],
        $rowsByEntity[$entity->value] ?? [],
      ),
      Entities::cases(),
    );
    $path = $this->container->get(CsvExporter::class)->export($tables);
    $this->paths[] = $path;
    $zip = new \ZipArchive();
    $this->assertTrue($zip->open($path));
    return $zip;
  }

  /**
   * @return list<list<string>>
   */
  private function parse(string $csv): array {
    $handle = fopen('php://temp', 'w+');
    fwrite($handle, $csv);
    rewind($handle);
    $records = [];
    while (($record = fgetcsv($handle, NULL, ',', '"', '')) !== FALSE) {
      $records[] = $record;
    }
    fclose($handle);
    return $records;
  }

  public function testZipContainsExactlyOneCsvPerEntity(): void {
    $zip = $this->export();
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $names[] = $zip->getNameIndex($i);
    }
    sort($names);
    $this->assertSame(['artists.csv', 'artworks.csv', 'games.csv', 'musea.csv', 'screenshots.csv'], $names);
  }

  public function testFilesStartWithAUtf8Bom(): void {
    $zip = $this->export();
    $this->assertStringStartsWith("\xEF\xBB\xBF", $zip->getFromName('artworks.csv'));
  }

  public function testEmptyTableHasOnlyTheHeaderRow(): void {
    $zip = $this->export();
    $records = $this->parse(substr($zip->getFromName('artists.csv'), 3));
    $this->assertSame([['id', 'label']], $records);
  }

  public function testSpecialCharactersSurviveARoundTrip(): void {
    $label = "Hello, \"World\"\nsecond line";
    $zip = $this->export([
      'artwork' => [
        [new ExportCell('1'), new ExportCell($label)],
        [new ExportCell('2'), new ExportCell('Éloïse 🎨 — café')],
      ],
    ]);

    $records = $this->parse(substr($zip->getFromName('artworks.csv'), 3));

    $this->assertSame([
      ['id', 'label'],
      ['1', $label],
      ['2', 'Éloïse 🎨 — café'],
    ], $records);
  }

  public function testReferencesUseTheCsvText(): void {
    $zip = $this->export([
      'artwork' => [[new ExportCell('1'), new ExportCell('Leonardo', 'Leonardo (#7)')]],
    ]);

    $records = $this->parse(substr($zip->getFromName('artworks.csv'), 3));

    $this->assertSame('Leonardo (#7)', $records[1][1]);
  }

}
```

- [ ] **Step 2: Lancer le test et constater l'échec**

Run: la commande Kernel avec `--filter CsvExporterTest`
Expected: FAIL, service `CsvExporter` introuvable.

- [ ] **Step 3: Écrire le service et l'enregistrer**

Ajouter à `egam_export.services.yml` :

```yaml
  Drupal\egam_export\Export\CsvExporter:
    class: Drupal\egam_export\Export\CsvExporter
    arguments: ['@file_system']
```

`web/modules/custom/egam_export/src/Export/CsvExporter.php` :

```php
<?php

namespace Drupal\egam_export\Export;

use Drupal\Core\File\FileSystemInterface;

/**
 * Writes export tables as CSV files grouped in a zip archive.
 */
class CsvExporter {

  /**
   * Lets Excel detect UTF-8 so accents display correctly.
   */
  private const BOM = "\xEF\xBB\xBF";

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * @param list<ExportTable> $tables
   *
   * @return string
   *   Path of a temporary zip file. The caller deletes it.
   *
   * @throws \Drupal\egam_export\Export\ExportException
   */
  public function export(array $tables): string {
    if (!class_exists(\ZipArchive::class)) {
      throw new ExportException('The PHP zip extension is required for the CSV export.');
    }
    $path = tempnam($this->fileSystem->getTempDirectory(), 'egam-csv-');
    if ($path === FALSE) {
      throw new ExportException('Could not create a temporary file for the CSV export.');
    }

    $zip = new \ZipArchive();
    if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
      @unlink($path);
      throw new ExportException('Could not open the CSV archive for writing.');
    }
    foreach ($tables as $table) {
      $zip->addFromString($table->fileName(), self::BOM . $this->toCsv($table));
    }
    if (!$zip->close()) {
      @unlink($path);
      throw new ExportException('Could not write the CSV archive.');
    }

    return $path;
  }

  private function toCsv(ExportTable $table): string {
    $handle = fopen('php://temp', 'w+');
    fputcsv($handle, $table->headers, ',', '"', '', "\n");
    foreach ($table->rows as $row) {
      $values = array_map(static fn (ExportCell $cell): string => $cell->csv(), $row);
      fputcsv($handle, $values, ',', '"', '', "\n");
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);
    return $csv;
  }

}
```

- [ ] **Step 4: Lancer le test et constater le succès**

Run: la commande Kernel avec `--filter CsvExporterTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/egam_export
git commit -m "feat(export): export tables as zipped CSV files

One file per entity keeps each CSV flat and importable, and the BOM makes
Excel show accented titles correctly.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 6: `XlsxExporter`

**Files:**

- Create: `web/modules/custom/egam_export/src/Export/XlsxExporter.php`
- Modify: `web/modules/custom/egam_export/egam_export.services.yml`
- Test: `web/modules/custom/egam_export/tests/src/Kernel/XlsxExporterTest.php`

**Interfaces:**

- Consumes: `ExportTable` (`entity`, `headers`, `rows`, `sheetName()`, `rowNumberFor()`), `ExportCell` (`text`, `linkEntity`, `linkId`, `externalUrl`), `ExportException`.
- Produces: service `Drupal\egam_export\Export\XlsxExporter` avec `export(array $tables): string` : prend `list<ExportTable>`, renvoie le chemin d'un `.xlsx` temporaire (à supprimer par l'appelant).

- [ ] **Step 1: Écrire le test (il doit échouer)**

`web/modules/custom/egam_export/tests/src/Kernel/XlsxExporterTest.php` :

```php
<?php

namespace Drupal\Tests\egam_export\Kernel;

use Drupal\egam_export\Export\ExportCell;
use Drupal\egam_export\Export\ExportTable;
use Drupal\egam_export\Export\XlsxExporter;
use Drupal\egam_global\Entities;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class XlsxExporterTest extends ExportKernelTestBase {

  private array $paths = [];

  protected function tearDown(): void {
    foreach ($this->paths as $path) {
      @unlink($path);
    }
    parent::tearDown();
  }

  /**
   * @param array<string, list<list<ExportCell>>> $rowsByEntity
   *   Rows keyed by Entities value; missing entities get no rows.
   */
  private function export(array $rowsByEntity = []): Spreadsheet {
    $tables = array_map(
      static fn (Entities $entity): ExportTable => new ExportTable(
        $entity,
        ['id', 'label'],
        $rowsByEntity[$entity->value] ?? [],
      ),
      Entities::cases(),
    );
    $path = $this->container->get(XlsxExporter::class)->export($tables);
    $this->paths[] = $path;
    return IOFactory::load($path);
  }

  public function testOneSheetPerEntityInEnumOrder(): void {
    $spreadsheet = $this->export();
    $this->assertSame(
      ['Artworks', 'Artists', 'Games', 'Musea', 'Screenshots'],
      $spreadsheet->getSheetNames(),
    );
  }

  public function testHeaderRowIsBoldAndFrozen(): void {
    $sheet = $this->export()->getSheetByName('Artworks');
    $this->assertSame('id', $sheet->getCell('A1')->getValue());
    $this->assertSame('label', $sheet->getCell('B1')->getValue());
    $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
    $this->assertSame('A2', $sheet->getFreezePane());
  }

  public function testEmptyEntityHasOnlyTheHeaderRow(): void {
    $sheet = $this->export()->getSheetByName('Games');
    $this->assertSame(1, $sheet->getHighestRow());
  }

  public function testIdColumnIsNumeric(): void {
    $sheet = $this->export([
      'artist' => [[new ExportCell('12'), new ExportCell('Leonardo')]],
    ])->getSheetByName('Artists');
    $this->assertSame(12, $sheet->getCell('A2')->getValue());
  }

  public function testReferenceLinksToTheRowOfItsTarget(): void {
    $sheet = $this->export([
      'artist' => [
        [new ExportCell('11'), new ExportCell('Raphael')],
        [new ExportCell('12'), new ExportCell('Leonardo')],
      ],
      'artwork' => [
        [new ExportCell('1'), new ExportCell('Leonardo', 'Leonardo (#12)', Entities::Artist, '12')],
      ],
    ])->getSheetByName('Artworks');

    $cell = $sheet->getCell('B2');

    $this->assertSame('Leonardo', $cell->getValue());
    $this->assertTrue($cell->hasHyperlink());
    $this->assertStringContainsString("'Artists'!A3", $cell->getHyperlink()->getUrl());
  }

  public function testReferenceToAMissingRowHasNoLink(): void {
    $sheet = $this->export([
      'artwork' => [
        [new ExportCell('1'), new ExportCell('Ghost', 'Ghost (#99)', Entities::Artist, '99')],
      ],
    ])->getSheetByName('Artworks');

    $this->assertFalse($sheet->getCell('B2')->hasHyperlink());
  }

  public function testCellWithoutLinkInformationHasNoLink(): void {
    $sheet = $this->export([
      'artwork' => [[new ExportCell('1'), new ExportCell('#999')]],
    ])->getSheetByName('Artworks');

    $this->assertFalse($sheet->getCell('B2')->hasHyperlink());
  }

  public function testExternalUrlBecomesAHyperlink(): void {
    $sheet = $this->export([
      'artwork' => [[new ExportCell('1'), new ExportCell('https://example.com/artwork/1', externalUrl: 'https://example.com/artwork/1')]],
    ])->getSheetByName('Artworks');

    $this->assertSame('https://example.com/artwork/1', $sheet->getCell('B2')->getHyperlink()->getUrl());
  }

  public function testTextStartingWithEqualsIsNeverAFormula(): void {
    $sheet = $this->export([
      'artwork' => [[new ExportCell('1'), new ExportCell('=1+1')]],
    ])->getSheetByName('Artworks');

    $cell = $sheet->getCell('B2');

    $this->assertSame('s', $cell->getDataType());
    $this->assertSame('=1+1', $cell->getValue());
  }

  public function testSpecialCharactersSurviveARoundTrip(): void {
    $label = "Éloïse 🎨 — \"café\"\nline 2";
    $sheet = $this->export([
      'artwork' => [[new ExportCell('1'), new ExportCell($label)]],
    ])->getSheetByName('Artworks');

    $this->assertSame($label, $sheet->getCell('B2')->getValue());
  }

}
```

- [ ] **Step 2: Lancer le test et constater l'échec**

Run: la commande Kernel avec `--filter XlsxExporterTest`
Expected: FAIL, service `XlsxExporter` introuvable.

- [ ] **Step 3: Écrire le service et l'enregistrer**

Ajouter à `egam_export.services.yml` :

```yaml
  Drupal\egam_export\Export\XlsxExporter:
    class: Drupal\egam_export\Export\XlsxExporter
    arguments: ['@file_system']
```

`web/modules/custom/egam_export/src/Export/XlsxExporter.php` :

```php
<?php

namespace Drupal\egam_export\Export;

use Drupal\Core\File\FileSystemInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Writes export tables as one XLSX workbook, one sheet per entity type.
 */
class XlsxExporter {

  private const COLUMN_WIDTH = 28;
  private const ID_COLUMN_WIDTH = 8;

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * @param list<ExportTable> $tables
   *
   * @return string
   *   Path of a temporary XLSX file. The caller deletes it.
   *
   * @throws \Drupal\egam_export\Export\ExportException
   */
  public function export(array $tables): string {
    $byEntity = [];
    foreach ($tables as $table) {
      $byEntity[$table->entity->value] = $table;
    }

    $spreadsheet = new Spreadsheet();
    foreach (array_values($tables) as $index => $table) {
      $sheet = $index === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
      $this->writeSheet($sheet, $table, $byEntity);
    }
    $spreadsheet->setActiveSheetIndex(0);

    $path = tempnam($this->fileSystem->getTempDirectory(), 'egam-xlsx-');
    if ($path === FALSE) {
      throw new ExportException('Could not create a temporary file for the XLSX export.');
    }
    try {
      (new Xlsx($spreadsheet))->save($path);
    }
    catch (\Exception $e) {
      @unlink($path);
      throw new ExportException('Could not write the XLSX file: ' . $e->getMessage(), 0, $e);
    }
    finally {
      $spreadsheet->disconnectWorksheets();
    }

    return $path;
  }

  /**
   * @param array<string, ExportTable> $byEntity
   *   Tables keyed by Entities value, used to resolve links between sheets.
   */
  private function writeSheet(Worksheet $sheet, ExportTable $table, array $byEntity): void {
    $sheet->setTitle($table->sheetName());

    foreach ($table->headers as $index => $header) {
      $column = $index + 1;
      $sheet->setCellValueExplicit([$column, 1], $header, DataType::TYPE_STRING);
      $sheet->getColumnDimensionByColumn($column)->setWidth(
        $index === 0 ? self::ID_COLUMN_WIDTH : self::COLUMN_WIDTH,
      );
    }
    $sheet->getStyle([1, 1, count($table->headers), 1])->getFont()->setBold(TRUE);
    $sheet->freezePane('A2');

    foreach ($table->rows as $rowIndex => $row) {
      foreach ($row as $columnIndex => $cell) {
        $this->writeCell($sheet, $columnIndex + 1, $rowIndex + 2, $cell, $columnIndex === 0, $byEntity);
      }
    }
  }

  /**
   * @param array<string, ExportTable> $byEntity
   */
  private function writeCell(Worksheet $sheet, int $column, int $row, ExportCell $cell, bool $isId, array $byEntity): void {
    if ($isId && ctype_digit($cell->text)) {
      // A real number sorts correctly in Excel.
      $sheet->setCellValue([$column, $row], (int) $cell->text);
      return;
    }

    // Always a string: nothing the content says can run as a formula.
    $sheet->setCellValueExplicit([$column, $row], $cell->text, DataType::TYPE_STRING);

    $url = $this->hyperlinkFor($cell, $byEntity);
    if ($url === NULL) {
      return;
    }
    $sheet->getCell([$column, $row])->getHyperlink()->setUrl($url);
    $sheet->getStyle([$column, $row])->getFont()
      ->setUnderline(Font::UNDERLINE_SINGLE)
      ->getColor()->setARGB(Color::COLOR_BLUE);
  }

  /**
   * @param array<string, ExportTable> $byEntity
   */
  private function hyperlinkFor(ExportCell $cell, array $byEntity): ?string {
    if ($cell->externalUrl !== NULL) {
      return $cell->externalUrl;
    }
    if ($cell->linkEntity === NULL || $cell->linkId === NULL) {
      return NULL;
    }
    $target = $byEntity[$cell->linkEntity->value] ?? NULL;
    $targetRow = $target?->rowNumberFor($cell->linkId);
    if ($targetRow === NULL) {
      return NULL;
    }
    return sprintf("sheet://'%s'!A%d", $target->sheetName(), $targetRow);
  }

}
```

- [ ] **Step 4: Lancer le test et constater le succès**

Run: la commande Kernel avec `--filter XlsxExporterTest`
Expected: PASS (10 tests). Si `testReferenceLinksToTheRowOfItsTarget` échoue parce que le lecteur restitue l'URL autrement, la chaîne `'Artists'!A3` doit rester présente dans l'URL relue : c'est ce que vérifie l'assertion `assertStringContainsString`. Ne pas affaiblir davantage ce test.

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/egam_export
git commit -m "feat(export): export tables as an XLSX workbook with sheet links

References jump to the row of their target sheet, so the workbook can be
browsed like the site without a lookup by id.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Contrôleur, routes, menu, accès

**Files:**

- Create: `web/modules/custom/egam_export/src/Controller/ExportController.php`
- Create: `web/modules/custom/egam_export/egam_export.routing.yml`
- Create: `web/modules/custom/egam_export/egam_export.links.menu.yml`
- Test: `web/modules/custom/egam_export/tests/src/Kernel/ExportControllerTest.php`
- Test: `web/modules/custom/egam_export/tests/src/Functional/ExportAccessTest.php`

**Interfaces:**

- Consumes: `ExportTableBuilder::buildAll()`, `CsvExporter::export()`, `XlsxExporter::export()`, `ExportException`.
- Produces: routes `egam_export.page`, `egam_export.csv`, `egam_export.xlsx`. `ExportController::page(): array`, `downloadCsv(): Response`, `downloadXlsx(): Response`. Constructeur : `(ExportTableBuilder, CsvExporter, XlsxExporter, TimeInterface)`.

- [ ] **Step 1: Écrire le test Kernel du contrôleur (il doit échouer)**

`web/modules/custom/egam_export/tests/src/Kernel/ExportControllerTest.php` :

```php
<?php

namespace Drupal\Tests\egam_export\Kernel;

use Drupal\egam_export\Controller\ExportController;
use Drupal\egam_export\Export\CsvExporter;
use Drupal\egam_export\Export\ExportException;
use Drupal\egam_export\Export\ExportTableBuilder;
use Drupal\egam_export\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ExportControllerTest extends ExportKernelTestBase {

  private function controller(?CsvExporter $csv = NULL, ?XlsxExporter $xlsx = NULL): ExportController {
    return new ExportController(
      $this->container->get(ExportTableBuilder::class),
      $csv ?? $this->container->get(CsvExporter::class),
      $xlsx ?? $this->container->get(XlsxExporter::class),
      $this->container->get('datetime.time'),
    );
  }

  public function testCsvDownloadIsAnAttachedZip(): void {
    $response = $this->controller()->downloadCsv();

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame('application/zip', $response->headers->get('Content-Type'));
    $this->assertMatchesRegularExpression(
      '/attachment; filename=egam-export-\d{4}-\d{2}-\d{2}\.zip/',
      $response->headers->get('Content-Disposition'),
    );
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $this->assertFileExists($response->getFile()->getPathname());
    @unlink($response->getFile()->getPathname());
  }

  public function testXlsxDownloadIsAnAttachedWorkbook(): void {
    $response = $this->controller()->downloadXlsx();

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
    $this->assertMatchesRegularExpression(
      '/attachment; filename=egam-export-\d{4}-\d{2}-\d{2}\.xlsx/',
      $response->headers->get('Content-Disposition'),
    );
    @unlink($response->getFile()->getPathname());
  }

  public function testFailedExportRedirectsWithAnErrorMessage(): void {
    $csv = $this->createMock(CsvExporter::class);
    $csv->method('export')->willThrowException(new ExportException('boom'));

    $response = $this->controller($csv)->downloadCsv();

    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertStringEndsWith('/admin/config/egam/export', $response->getTargetUrl());
    $this->assertCount(1, $this->container->get('messenger')->messagesByType('error'));
  }

  public function testPageOffersBothDownloads(): void {
    $page = $this->controller()->page();
    $this->assertArrayHasKey('csv', $page);
    $this->assertArrayHasKey('xlsx', $page);
  }

}
```

- [ ] **Step 2: Lancer le test et constater l'échec**

Run: la commande Kernel avec `--filter ExportControllerTest`
Expected: FAIL, classe `ExportController` introuvable.

- [ ] **Step 3: Écrire les routes, le menu et le contrôleur**

`web/modules/custom/egam_export/egam_export.routing.yml` :

```yaml
egam_export.page:
  path: '/admin/config/egam/export'
  defaults:
    _controller: '\Drupal\egam_export\Controller\ExportController::page'
    _title: 'Export data'
  requirements:
    _permission: 'export egam data'

egam_export.csv:
  path: '/admin/config/egam/export/csv'
  defaults:
    _controller: '\Drupal\egam_export\Controller\ExportController::downloadCsv'
    _title: 'Export data as CSV'
  requirements:
    _permission: 'export egam data'
    _csrf_token: 'TRUE'

egam_export.xlsx:
  path: '/admin/config/egam/export/xlsx'
  defaults:
    _controller: '\Drupal\egam_export\Controller\ExportController::downloadXlsx'
    _title: 'Export data as XLSX'
  requirements:
    _permission: 'export egam data'
    _csrf_token: 'TRUE'
```

`web/modules/custom/egam_export/egam_export.links.menu.yml` :

```yaml
egam_export.page:
  title: 'Export data'
  description: 'Download all EGAM content as CSV or XLSX.'
  route_name: egam_export.page
  parent: system.admin_config_system
```

`web/modules/custom/egam_export/src/Controller/ExportController.php` :

```php
<?php

namespace Drupal\egam_export\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\egam_export\Export\CsvExporter;
use Drupal\egam_export\Export\ExportException;
use Drupal\egam_export\Export\ExportTableBuilder;
use Drupal\egam_export\Export\XlsxExporter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Admin page and downloads for the EGAM data export.
 */
class ExportController extends ControllerBase {

  private const XLSX_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

  public function __construct(
    private readonly ExportTableBuilder $tableBuilder,
    private readonly CsvExporter $csvExporter,
    private readonly XlsxExporter $xlsxExporter,
    private readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ExportTableBuilder::class),
      $container->get(CsvExporter::class),
      $container->get(XlsxExporter::class),
      $container->get('datetime.time'),
    );
  }

  public function page(): array {
    $attributes = ['class' => ['button', 'button--primary']];
    return [
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Download every artwork, artist, game, museum and screenshot, including unpublished content. Images are not included.'),
      ],
      'csv' => [
        '#type' => 'link',
        '#title' => $this->t('Download CSV (zip)'),
        '#url' => Url::fromRoute('egam_export.csv'),
        '#attributes' => $attributes,
      ],
      'xlsx' => [
        '#type' => 'link',
        '#title' => $this->t('Download XLSX'),
        '#url' => Url::fromRoute('egam_export.xlsx'),
        '#attributes' => $attributes,
      ],
    ];
  }

  public function downloadCsv(): Response {
    return $this->download(
      fn (): string => $this->csvExporter->export($this->tableBuilder->buildAll()),
      'zip',
      'application/zip',
    );
  }

  public function downloadXlsx(): Response {
    return $this->download(
      fn (): string => $this->xlsxExporter->export($this->tableBuilder->buildAll()),
      'xlsx',
      self::XLSX_CONTENT_TYPE,
    );
  }

  /**
   * @param callable(): string $generate
   *   Produces the temporary file and returns its path.
   */
  private function download(callable $generate, string $extension, string $contentType): Response {
    try {
      $path = $generate();
    }
    catch (ExportException $e) {
      $this->getLogger('egam_export')->error('Export failed: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('The export failed. See the "egam_export" log channel for details.'));
      return $this->redirect('egam_export.page');
    }

    $filename = 'egam-export-' . gmdate('Y-m-d', $this->time->getRequestTime()) . '.' . $extension;
    $response = new BinaryFileResponse($path, 200, ['Content-Type' => $contentType], FALSE);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->headers->addCacheControlDirective('no-store');
    $response->deleteFileAfterSend(TRUE);
    return $response;
  }

}
```

- [ ] **Step 4: Lancer le test Kernel et constater le succès**

Run: la commande Kernel avec `--filter ExportControllerTest`
Expected: PASS (4 tests). Si le routeur ne connaît pas encore `egam_export.page` dans `testFailedExportRedirectsWithAnErrorMessage`, vérifier que `setUp()` de la base reconstruit bien le routeur après l'activation des modules.

- [ ] **Step 5: Écrire le test Functional d'accès (il doit passer ou révéler un défaut)**

`web/modules/custom/egam_export/tests/src/Functional/ExportAccessTest.php` :

```php
<?php

namespace Drupal\Tests\egam_export\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Only users with the dedicated permission can reach the export.
 */
class ExportAccessTest extends BrowserTestBase {

  protected static $modules = [
    'egam_global', 'egam_artwork', 'egam_artist', 'egam_game',
    'egam_museum', 'egam_screenshot', 'egam_export',
  ];

  protected $defaultTheme = 'stark';

  private const PATHS = [
    '/admin/config/egam/export',
    '/admin/config/egam/export/csv',
    '/admin/config/egam/export/xlsx',
  ];

  public function testAnonymousUserIsDenied(): void {
    foreach (self::PATHS as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  public function testSiteConfigurationAdminWithoutThePermissionIsDenied(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer site configuration']));
    foreach (self::PATHS as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  public function testPageShowsTokenProtectedLinks(): void {
    $this->drupalLogin($this->drupalCreateUser(['export egam data']));
    $this->drupalGet('/admin/config/egam/export');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Download CSV (zip)');
    $this->assertSession()->linkExists('Download XLSX');
    $this->assertSession()->linkByHrefExists('/admin/config/egam/export/csv?token=');
  }

  public function testDownloadWithoutTokenIsDenied(): void {
    $this->drupalLogin($this->drupalCreateUser(['export egam data']));
    $this->drupalGet('/admin/config/egam/export/csv');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('/admin/config/egam/export/xlsx');
    $this->assertSession()->statusCodeEquals(403);
  }

  public function testCsvDownloadIsAZipOfFiveFiles(): void {
    $this->drupalLogin($this->drupalCreateUser(['export egam data']));
    $this->drupalGet('/admin/config/egam/export');
    $this->clickLink('Download CSV (zip)');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'application/zip');
    $this->assertSession()->responseHeaderContains('Content-Disposition', 'egam-export-');

    $path = tempnam(sys_get_temp_dir(), 'egam-test-');
    file_put_contents($path, $this->getSession()->getDriver()->getContent());
    $zip = new \ZipArchive();
    $this->assertTrue($zip->open($path));
    $this->assertSame(5, $zip->numFiles);
    $zip->close();
    unlink($path);
  }

  public function testXlsxDownloadIsAWorkbook(): void {
    $this->drupalLogin($this->drupalCreateUser(['export egam data']));
    $this->drupalGet('/admin/config/egam/export');
    $this->clickLink('Download XLSX');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    // An XLSX file is a zip archive.
    $this->assertStringStartsWith('PK', $this->getSession()->getDriver()->getContent());
  }

}
```

- [ ] **Step 6: Lancer les tests Functional**

Run: la commande Functional
Expected: PASS (6 tests). Si `SIMPLETEST_BASE_URL=http://localhost` ne répond pas depuis le conteneur, essayer `http://web` ; ce réglage est le seul point à ajuster, pas les tests. Si l'installation du site de test échoue à cause d'un des modules EGAM (config manquante), c'est un défaut à corriger dans ce module ou à signaler, pas à contourner.

- [ ] **Step 7: Lancer toute la suite**

Run: les trois commandes (Unit, Kernel, Functional)
Expected: tout passe.

- [ ] **Step 8: Commit**

```bash
git add web/modules/custom/egam_export
git commit -m "feat(export): add admin page and token-protected downloads

A dedicated permission and CSRF token keep the costly export limited to
chosen roles and impossible to trigger from a third-party page.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Activation, déploiement et vérification manuelle

**Files:**

- Modify: `config/sync/core.extension.yml`
- Modify: `CLAUDE.md`

**Interfaces:**

- Consumes: le module complet des tâches 1 à 7.

- [ ] **Step 1: Vérifier que la config est synchronisée avant de toucher quoi que ce soit**

Run: `ddev drush config:status`
Expected: `No differences between DB and sync directory.` Si des différences sans rapport apparaissent, les noter et ne pas les inclure dans le commit.

- [ ] **Step 2: Activer le module et exporter la config**

```bash
ddev drush en egam_export -y
ddev drush cr
ddev drush config:export -y
git status --short config/sync
```

Expected: seul `config/sync/core.extension.yml` est modifié (ajout de `egam_export: 0`). Le rôle `administrator` a `is_admin: true` : il reçoit la permission automatiquement, aucun fichier de rôle ne change.

- [ ] **Step 3: Vérification manuelle dans le navigateur**

Run: `ddev launch /admin/config/egam/export`, connecté en administrateur.
Expected :

1. La page affiche deux boutons.
2. Le zip contient 5 CSV qui s'ouvrent avec les accents corrects.
3. Le classeur contient 5 feuilles. Dans `Artworks`, un clic sur un nom d'artiste mène à sa ligne dans `Artists`.
4. Aucun champ image ni `metatags` n'apparaît.
5. Une fois déconnecté, `/admin/config/egam/export` répond « Access denied ».

Si un point échoue, corriger par un test qui échoue d'abord, puis le code.

- [ ] **Step 4: Documenter le module dans `CLAUDE.md`**

Ajouter, dans la section « Global Module (`egam_global`) » juste après sa liste, un nouveau bloc :

```markdown
### Export Module (`egam_export`)

Admin-only export of the five entities, at `/admin/config/egam/export` (permission `export egam data`):

- **`ExportTableBuilder`**: reads every entity (published or not) through the `Entities` enum and builds neutral `ExportTable` objects; fields are discovered at runtime
- **`CsvExporter`**: one CSV per entity, zipped
- **`XlsxExporter`**: one sheet per entity, with internal links between sheets (PhpSpreadsheet)
- Tests: `vendor/bin/phpunit -c web/core web/modules/custom/egam_export/tests/src/Kernel` (needs `SIMPLETEST_DB`, run via `ddev exec`)
```

- [ ] **Step 5: Commit**

```bash
git add config/sync/core.extension.yml CLAUDE.md
git commit -m "feat(export): enable egam_export and document it

Enabling the module in config lets the next deploy turn the feature on;
the composer install on the server already pulls PhpSpreadsheet.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 6: Avant de pousser**

Ne pas pousser sans accord : un push sur `main` déclenche le déploiement en production (GitHub Actions). Vérifier d'abord que `git diff origin/main --stat` ne contient que les fichiers de cette feature et les documents de `docs/superpowers/`.
