<?php

namespace Listeners;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\SnapshotUpdated;
use Appocular\Assessor\Listeners\ResetCheckpointBaselines;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class ResetCheckpointBaselinesTest extends \TestCase
{
    use DatabaseMigrations;

    public function testBaselineGetsReset()
    {
        $baseline = factory(Snapshot::class)->create();
        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create(['snapshot_id' => $snapshot->id, 'baseline_sha' => 'deadbeef']);
        factory(Checkpoint::class)->create(['snapshot_id' => $snapshot->id, 'baseline_sha' => 'deadbeef']);
        factory(Checkpoint::class)->create(['snapshot_id' => $snapshot->id, 'image_sha' => null, 'baseline_sha' => 'deadbeef']);

        $listener = new ResetCheckpointBaselines();
        $listener->handle(new SnapshotUpdated($snapshot));

        // Shouldn't change when snapshot baseline didn't change.
        $this->assertEquals('deadbeef', $snapshot->checkpoints()->first()->baseline_sha);
        // And imageless checkpoints should be left alone.
        $this->assertCount(3, $snapshot->checkpoints()->get());

        // Set new baseline.
        $baseline = factory(Snapshot::class)->create();
        $snapshot->baseline = $baseline->id;

        // Sync changes so listener can see what changed.
        $snapshot->syncChanges();

        $listener->handle(new SnapshotUpdated($snapshot));

        $snapshot->refresh();
        $this->assertEquals(null, $snapshot->checkpoints()->first()->baseline_sha);
        $this->assertCount(2, $snapshot->checkpoints()->get());
    }
}
