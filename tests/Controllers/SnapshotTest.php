<?php

namespace Controllers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Snapshot;
use Appocular\Assessor\User;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\WithoutMiddleware;
use Prophecy\Argument;

class SnapshotTest extends ControllerTestBase
{
    use DatabaseMigrations;

    public function setUp()
    {
        parent::setUp();
        // Set up a frontend token.
        \putenv('FRONTEND_TOKEN=FrontendToken');
    }

    /**
     * Return authorization headers for request.
     *
     * Note that the Illuminate\Auth\TokenGuard is only constructed on the
     * first request in a test, and the Authorization headert thus "sticks
     * around" for the subsequent requests, rendering passing the header to
     * them pointless.
     */
    public function headers()
    {
        return ["Authorization" => 'Bearer FrontendToken'];
    }

    public function testGettingSnapshot()
    {
        $snapshot = factory(Snapshot::class)->create();

        $this->get('snapshot/' . $snapshot->id, $this->headers());
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'self' => route('snapshot.show', ['id' => $snapshot->id]),
            'checkpoints' => [],
            'status' => 'unknown',
            'run_status' => 'pending',
        ]);

        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];
        $checkpointsJson = array_map(function ($checkpoint) {
            return [
                'self' => route('checkpoint.show', ['id' => $checkpoint->id]),
                'name' => $checkpoint->name,
                'image_url' => $checkpoint->image_url,
                'baseline_url' => $checkpoint->baseline_url,
                'diff_url' => $checkpoint->diff_url,
                'status' => 'unknown',
                'diff_status' => 'unknown',
                'actions' => [
                    'approve' => route('checkpoint.approve', ['id' => $checkpoint->id]),
                    'reject' => route('checkpoint.reject', ['id' => $checkpoint->id]),
                    'ignore' => route('checkpoint.ignore', ['id' => $checkpoint->id]),
                ],
            ];
        }, $checkpoints);

        $this->get('snapshot/' . $snapshot->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'self' => route('snapshot.show', ['id' => $snapshot->id]),
            'checkpoints' => $checkpointsJson,
            'status' => 'unknown',
            'run_status' => 'pending',
        ]);
    }
}
