<?php

use Appocular\Assessor\Events\SnapshotCreated;
use Appocular\Assessor\Listeners\SnapshotCreatedListener;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class SnapshotCreatedListenerTest extends TestCase
{
    use DatabaseMigrations;

    public function testFindingBaseline()
    {
        $baseline = factory(Appocular\Assessor\Snapshot::class)->create();
        $snapshot = factory(Appocular\Assessor\Snapshot::class)->create();

        $snapshot->history()->create(['history' => "banana\n" . $baseline->id . "\napple\n"]);

        $listener = new SnapshotCreatedListener();
        $listener->handle(new SnapshotCreated($snapshot));

        $this->seeInDatabase('snapshots', ['id' => $snapshot->id, 'baseline' => $baseline->id]);
        // The history should be deleted when done.
        $this->missingFromDatabase('history', ['snapshot_id' => $snapshot->id]);
    }

    public function testNotFindingBaseline()
    {
        $baseline = factory(Appocular\Assessor\Snapshot::class)->create();
        $snapshot = factory(Appocular\Assessor\Snapshot::class)->create();

        $snapshot->history()->create(['history' => "banana\npineapple\napple\n"]);

        $listener = new SnapshotCreatedListener();
        $listener->handle(new SnapshotCreated($snapshot));

        $this->seeInDatabase('snapshots', ['id' => $snapshot->id, 'baseline' => '']);
        // The history should be deleted when done.
        $this->missingFromDatabase('history', ['snapshot_id' => $snapshot->id]);
    }
}
