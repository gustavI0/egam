<?php

namespace Drupal\Tests\egam_export\Kernel;

use Drupal\egam_artwork\Entity\Artwork;
use Drupal\egam_export\Export\ExportTable;
use Drupal\egam_export\Export\ExportTableBuilder;
use Drupal\egam_global\Entities;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
class ExportTableBuilderTest extends ExportKernelTestBase {

  private function build(Entities $entity): ExportTable {
    return $this->container->get(ExportTableBuilder::class)->build($entity);
  }

  /**
   * @return list<string>
   */
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

    $table = $this->build(Entities::Artwork);
    $cell = $table->rows[0][count($table->headers) - 1];

    $this->assertMatchesRegularExpression('#^https?://[^/]+/artwork/' . $artwork->id() . '$#', $cell->text);
    $this->assertSame($cell->text, $cell->externalUrl);
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
