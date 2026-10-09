<?php

namespace Drupal\Tests\egam_dashboard\Kernel;

use Drupal\egam_dashboard\Dashboard\DashboardStats;
use Drupal\egam_global\Entities;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The figures and data gaps shown on the dashboard.
 */
#[RunTestsInSeparateProcesses]
class DashboardStatsTest extends DashboardKernelTestBase {

  private function stats(): DashboardStats {
    return $this->container->get(DashboardStats::class);
  }

  private function gap(string $id): ?object {
    foreach ($this->stats()->gaps() as $gap) {
      if ($gap->id === $id) {
        return $gap;
      }
    }
    return NULL;
  }

  public function testCountsSplitPublishedFromUnpublished(): void {
    $this->make(Entities::Artwork, ['status' => 1]);
    $this->make(Entities::Artwork, ['status' => 1]);
    $this->make(Entities::Artwork, ['status' => 0]);
    $this->make(Entities::Museum, ['status' => 0]);

    $counts = [];
    foreach ($this->stats()->counts() as $count) {
      $counts[$count->entity->value] = $count;
    }

    $this->assertCount(5, $counts);
    $this->assertSame(2, $counts['artwork']->published);
    $this->assertSame(1, $counts['artwork']->unpublished);
    $this->assertSame(3, $counts['artwork']->total());
    $this->assertSame(0, $counts['museum']->published);
    $this->assertSame(1, $counts['museum']->unpublished);
    $this->assertSame(0, $counts['artist']->total());
  }

  public function testUnpublishedContentIsCountedAsWellAsPublished(): void {
    $this->make(Entities::Game, ['status' => 0]);

    // Entities::count() only counts what visitors can see: not the dashboard.
    $this->assertSame(0, Entities::Game->count());
    $counts = $this->stats()->counts();
    $game = array_values(array_filter($counts, fn ($c) => $c->entity === Entities::Game))[0];
    $this->assertSame(1, $game->total());
  }

  public function testArtworkGapsCountOnlyArtworksThatMissTheValue(): void {
    Vocabulary::create(['vid' => 'artwork_type', 'name' => 'Type'])->save();
    $type = Term::create(['vid' => 'artwork_type', 'name' => 'Peinture']);
    $type->save();
    $artist = $this->make(Entities::Artist);
    $museum = $this->make(Entities::Museum);

    // Complete artwork: nothing missing except the cover.
    $this->make(Entities::Artwork, [
      'field_artwork_type' => $type->id(),
      'field_artist' => $artist->id(),
      'field_museum' => $museum->id(),
    ]);
    // Bare artwork: everything missing.
    $this->make(Entities::Artwork);

    $this->assertSame(1, $this->gap('artwork_without_type')->count);
    $this->assertSame(1, $this->gap('artwork_without_artist')->count);
    $this->assertSame(1, $this->gap('artwork_without_museum')->count);
    $this->assertSame(2, $this->gap('artwork_without_cover')->count);
  }

  public function testAGapWhoseFieldWasDeletedIsLeftOut(): void {
    FieldStorageConfig::loadByName('artwork', 'field_museum')->delete();
    FieldStorageConfig::loadByName('screenshot', 'field_game')->delete();
    $this->make(Entities::Artwork);

    $ids = array_map(fn ($gap) => $gap->id, $this->stats()->gaps());

    $this->assertNotContains('artwork_without_museum', $ids);
    $this->assertNotContains('game_without_artwork', $ids);
    $this->assertContains('artwork_without_artist', $ids);
  }

  public function testGapCountsIncludeUnpublishedArtworks(): void {
    $this->make(Entities::Artwork, ['status' => 0]);

    $this->assertSame(1, $this->gap('artwork_without_artist')->count);
  }

  public function testGameWithoutAnyScreenshotHasNoArtwork(): void {
    $withShot = $this->make(Entities::Game);
    $alone = $this->make(Entities::Game);
    $artwork = $this->make(Entities::Artwork);
    $this->make(Entities::Screenshot, [
      'field_game' => $withShot->id(),
      'field_artwork' => $artwork->id(),
    ]);

    $gap = $this->gap('game_without_artwork');

    $this->assertSame(1, $gap->count);
    $this->assertSame([$alone->id()], array_map(fn ($e) => $e->id(), $gap->samples));
  }

  public function testSamplesAreLimitedToFive(): void {
    for ($i = 0; $i < 7; $i++) {
      $this->make(Entities::Artwork);
    }

    $gap = $this->gap('artwork_without_artist');

    $this->assertSame(7, $gap->count);
    $this->assertCount(5, $gap->samples);
  }

  public function testNothingMissingGivesAnEmptyGap(): void {
    $gap = $this->gap('artwork_without_type');

    $this->assertSame(0, $gap->count);
    $this->assertSame([], $gap->samples);
  }

  public function testRecentChangesAreSortedAcrossEntitiesAndLimited(): void {
    $this->make(Entities::Museum);
    $this->make(Entities::Artist);
    $this->make(Entities::Game);
    // Saving stamps "changed" with the current time: set known values after.
    foreach (['game' => 3000, 'artist' => 2000, 'museum' => 1000] as $type => $time) {
      $this->setChanged($type, $time);
    }

    $recent = $this->stats()->recent(2);

    $this->assertSame(['game', 'artist'], array_map(fn ($e) => $e->getEntityTypeId(), $recent));
  }

  private function setChanged(string $entityTypeId, int $time): void {
    $mapping = $this->container->get('entity_type.manager')->getStorage($entityTypeId)->getTableMapping();
    $table = $mapping->getDataTable() ?: $mapping->getBaseTable();
    $this->container->get('database')->update($table)->fields(['changed' => $time])->execute();
  }

}
