<?php

declare(strict_types=1);

namespace Jobs;

use Appocular\Assessor\Jobs\SnapshotBaselining;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;

class SnapshotBaseliningTest extends \TestCase
{
    use DatabaseMigrations;

    public function testFindingBaseline(): void
    {
        $baseline = \factory(Snapshot::class)->create();
        $snapshot = \factory(Snapshot::class)->create();

        $snapshot->history()->create(['history' => "banana\n" . $baseline->id . "\napple\n"]);

        $job = new SnapshotBaselining($snapshot);
        $job->handle();

        $this->seeInDatabase('snapshots', ['id' => $snapshot->id, 'baseline' => $baseline->id]);
        // The history should be deleted when done.
        $this->missingFromDatabase('history', ['snapshot_id' => $snapshot->id]);
    }

    public function testNotFindingBaseline(): void
    {
        \factory(Snapshot::class)->create();
        $snapshot = \factory(Snapshot::class)->create();

        $snapshot->history()->create(['history' => "banana\npineapple\napple\n"]);

        $job = new SnapshotBaselining($snapshot);
        $job->handle();

        $this->seeInDatabase('snapshots', ['id' => $snapshot->id, 'baseline' => '']);
        // The history should be deleted when done.
        $this->missingFromDatabase('history', ['snapshot_id' => $snapshot->id]);
    }

    public function testIgnoringItself(): void
    {
        $baseline = \factory(Snapshot::class)->create();
        $snapshot = \factory(Snapshot::class)->create();

        $snapshot->history()->create(['history' => $snapshot->id . "\nbanana\n" . $baseline->id . "\napple\n"]);

        $job = new SnapshotBaselining($snapshot);
        $job->handle();

        $this->seeInDatabase('snapshots', ['id' => $snapshot->id, 'baseline' => $baseline->id]);
    }
}
