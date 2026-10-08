<?php

namespace Drupal\Tests\egam_artwork\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\egam_artwork\ArtworkTypes;
use Drupal\egam_artwork\Entity\Artwork;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The artwork type taxonomy and the painting-only fields.
 *
 * The fields come from the real files in config/sync, so a mistake in the
 * exported configuration fails here too.
 */
#[RunTestsInSeparateProcesses]
class ArtworkTypeFieldsTest extends KernelTestBase {

  protected static $modules = [
    'system', 'user', 'field', 'text', 'options', 'taxonomy', 'filter',
    'egam_global', 'egam_artwork', 'egam_artist', 'egam_museum',
  ];

  private const CONFIG = [
    'taxonomy.vocabulary.artwork_type',
    'field.storage.artwork.field_artwork_type',
    'field.storage.artwork.field_school',
    'field.storage.artwork.field_york_project',
    'field.field.artwork.artwork.field_artwork_type',
    'field.field.artwork.artwork.field_school',
    'field.field.artwork.artwork.field_york_project',
  ];

  protected function setUp(): void {
    parent::setUp();
    // Date formats, needed to render the form's date elements.
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('artwork');

    $source = new FileStorage($this->root . '/../config/sync');
    $configManager = $this->container->get('config.manager');
    foreach (self::CONFIG as $name) {
      $this->assertNotFalse($source->read($name), "Missing config file for $name");
      $type = $configManager->getEntityTypeIdByName($name);
      $this->container->get('entity_type.manager')->getStorage($type)
        ->createFromStorageRecord($source->read($name))
        ->save();
    }
    $this->container->get('router.builder')->rebuild();
  }

  private function runDeployHook(): void {
    $this->container->get('module_handler')->loadInclude('egam_artwork', 'php', 'egam_artwork.deploy');
    $sandbox = [];
    egam_artwork_deploy_create_artwork_types($sandbox);
  }

  private function termNames(): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $terms = $storage->loadByProperties(['vid' => ArtworkTypes::VOCABULARY]);
    uasort($terms, static fn ($a, $b): int => $a->getWeight() <=> $b->getWeight());
    return array_values(array_map(static fn ($term): string => $term->label(), $terms));
  }

  private function type(string $name): Term {
    $terms = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => ArtworkTypes::VOCABULARY, 'name' => $name]);
    $this->assertCount(1, $terms, "Term $name");
    return reset($terms);
  }

  private function artwork(?string $type, ?string $school, ?bool $york): Artwork {
    $artwork = Artwork::create([
      'label' => 'Test',
      'field_artwork_type' => $type === NULL ? NULL : $this->type($type)->id(),
      'field_school' => $school,
      'field_york_project' => $york,
    ]);
    $artwork->save();
    return Artwork::load($artwork->id());
  }

  public function testFieldsAreDefinedAsAgreed(): void {
    $definitions = $this->container->get('entity_field.manager')->getFieldDefinitions('artwork', 'artwork');

    $type = $definitions['field_artwork_type'];
    $this->assertSame('entity_reference', $type->getType());
    $this->assertSame('taxonomy_term', $type->getSetting('target_type'));
    $this->assertSame(['artwork_type' => 'artwork_type'], $type->getSetting('handler_settings')['target_bundles']);
    $this->assertSame(1, $type->getFieldStorageDefinition()->getCardinality());
    $this->assertSame('Type d\'œuvre', (string) $type->getLabel());

    $this->assertSame('string', $definitions['field_school']->getType());
    $this->assertSame('École', (string) $definitions['field_school']->getLabel());
    $this->assertSame('boolean', $definitions['field_york_project']->getType());
    $this->assertSame('York Project', (string) $definitions['field_york_project']->getLabel());

    foreach (['field_artwork_type', 'field_school', 'field_york_project'] as $name) {
      $this->assertFalse($definitions[$name]->isRequired(), $name);
    }
  }

  public function testDeployHookCreatesTheFiveTypesInOrder(): void {
    $this->runDeployHook();
    $this->assertSame(['Peinture', 'Sculpture', 'Dessins', 'Gravure', "Objet d'art"], $this->termNames());
  }

  public function testDeployHookCanRunTwiceWithoutDuplicates(): void {
    $this->runDeployHook();
    $this->runDeployHook();
    $this->assertCount(5, $this->termNames());
  }

  public function testDeployHookKeepsAnExistingTypeUntouched(): void {
    Term::create(['vid' => ArtworkTypes::VOCABULARY, 'name' => 'Sculpture', 'weight' => 42])->save();

    $this->runDeployHook();

    $this->assertCount(5, $this->termNames());
    $this->assertSame(42, (int) $this->type('Sculpture')->getWeight());
  }

  public function testPaintingKeepsSchoolAndYorkProject(): void {
    $this->runDeployHook();

    $artwork = $this->artwork('Peinture', 'Flemish', TRUE);

    $this->assertSame('Flemish', $artwork->get('field_school')->value);
    $this->assertSame('1', (string) $artwork->get('field_york_project')->value);
  }

  public function testOtherTypeClearsSchoolAndYorkProject(): void {
    $this->runDeployHook();

    $artwork = $this->artwork('Sculpture', 'Flemish', TRUE);

    $this->assertTrue($artwork->get('field_school')->isEmpty());
    $this->assertTrue($artwork->get('field_york_project')->isEmpty());
  }

  public function testNoTypeClearsSchoolAndYorkProject(): void {
    $this->runDeployHook();

    $artwork = $this->artwork(NULL, 'Flemish', TRUE);

    $this->assertTrue($artwork->get('field_school')->isEmpty());
    $this->assertTrue($artwork->get('field_york_project')->isEmpty());
  }

  public function testChangingAPaintingIntoAnotherTypeClearsTheValues(): void {
    $this->runDeployHook();
    $artwork = $this->artwork('Peinture', 'Flemish', TRUE);

    $artwork->set('field_artwork_type', $this->type('Gravure')->id());
    $artwork->save();

    $reloaded = Artwork::load($artwork->id());
    $this->assertTrue($reloaded->get('field_school')->isEmpty());
    $this->assertTrue($reloaded->get('field_york_project')->isEmpty());
  }

  private function form(): array {
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'artwork',
      'bundle' => 'artwork',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setComponent('field_artwork_type', ['type' => 'options_select', 'weight' => 1]);
    $display->setComponent('field_school', ['type' => 'string_textfield', 'weight' => 2]);
    $display->setComponent('field_york_project', ['type' => 'boolean_checkbox', 'weight' => 3]);
    $display->save();

    return $this->container->get('entity.form_builder')->getForm(Artwork::create(['label' => 'Form']), 'add');
  }

  public function testPaintingFieldsAreOnlyVisibleForPaintings(): void {
    $this->runDeployHook();
    $paintingId = $this->type('Peinture')->id();

    $form = $this->form();

    $expected = [':input[name="field_artwork_type"]' => ['value' => $paintingId]];
    $this->assertSame($expected, $form['field_school']['#states']['visible']);
    $this->assertSame($expected, $form['field_york_project']['#states']['visible']);
    $this->assertArrayNotHasKey('#states', $form['field_artwork_type']);
  }

  public function testPaintingFieldsStayVisibleWhileThePaintingTypeDoesNotExist(): void {
    $form = $this->form();

    $this->assertArrayNotHasKey('#states', $form['field_school']);
    $this->assertArrayNotHasKey('#states', $form['field_york_project']);
  }

}
