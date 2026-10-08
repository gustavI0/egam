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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
class FieldValueNormalizerTest extends ExportKernelTestBase {

  private function normalizer(): FieldValueNormalizer {
    return $this->container->get(FieldValueNormalizer::class);
  }

  public function testPlainStringIsKept(): void {
    $artwork = Artwork::create(['label' => 'Mona Lisa']);
    $this->assertSame('Mona Lisa', $this->normalizer()->normalize($artwork->get('label'))->text);
  }

  #[DataProvider('formulaProvider')]
  public function testFormulaTriggersArePrefixedInCsvOnly(string $label): void {
    $artwork = Artwork::create(['label' => $label]);

    $cell = $this->normalizer()->normalize($artwork->get('label'));

    $this->assertSame("'" . $label, $cell->csv());
    // The XLSX writer stores text as a string, so it needs no prefix.
    $this->assertSame($label, $cell->text);
  }

  public function testReferenceLabelStartingWithAFormulaTriggerIsPrefixedInCsvOnly(): void {
    $artist = Artist::create(['label' => '=cmd']);
    $artist->save();
    $artwork = Artwork::create(['label' => 'a', 'field_artist' => $artist->id()]);

    $cell = $this->normalizer()->normalize($artwork->get('field_artist'));

    $this->assertSame('=cmd', $cell->text);
    $this->assertSame("'=cmd (#" . $artist->id() . ')', $cell->csv());
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
    $this->assertEquals($artist->id(), $cell->linkId);
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
    $this->assertEquals($artwork->id(), $cell->linkId);
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
