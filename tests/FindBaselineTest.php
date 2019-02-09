<?php

use Appocular\Assessor\Events\NewBatch;
use Appocular\Assessor\Listeners\FindBaseline;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class FindBaselineTest extends TestCase
{
    use DatabaseMigrations;

    public function testFindingBaseline()
    {
        $baseline = factory(Appocular\Assessor\Snapshot::class)->create();
        $snapshot = factory(Appocular\Assessor\Snapshot::class)->create();

        $snapshot->history()->create(['history' => "banana\n" . $baseline->id . "\napple\n"]);

        $listener = new FindBaseline();
        $listener->handle(new NewBatch($snapshot->id));

        $this->seeInDatabase('snapshots', ['id' => $snapshot->id, 'baseline' => $baseline->id]);
        // The history should be deleted when done.
        $this->missingFromDatabase('history', ['snapshot_id' => $snapshot->id]);
    }

    public function testNotFindingBaseline()
    {
        $baseline = factory(Appocular\Assessor\Snapshot::class)->create();
        $snapshot = factory(Appocular\Assessor\Snapshot::class)->create();

        $snapshot->history()->create(['history' => "banana\npineapple\napple\n"]);

        $listener = new FindBaseline();
        $listener->handle(new NewBatch($snapshot->id));

        $this->seeInDatabase('snapshots', ['id' => $snapshot->id, 'baseline' => '']);
        // The history should be deleted when done.
        $this->missingFromDatabase('history', ['snapshot_id' => $snapshot->id]);
    }
}
