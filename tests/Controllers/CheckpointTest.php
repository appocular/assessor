<?php

namespace Controllers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\SlugGenerator;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\WithoutMiddleware;
use Prophecy\Argument;

class CheckpointTest extends ControllerTestBase
{
    use DatabaseMigrations;

    public function setUp() : void
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

    /**
     * Test that access control works.
     */
    public function testAccessControl()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];

        $this->get('checkpoint/' . $checkpoints[0]->id);
        $this->assertResponseStatus(401);
    }

    public function testGettingCheckpoint()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];

        $this->get('checkpoint/' . $checkpoints[0]->id, $this->headers());
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'self' => route('checkpoint.show', ['id' => $checkpoints[0]->id]),
            'name' => $checkpoints[0]->name,
            'image_url' => $checkpoints[0]->image_url,
            'baseline_url' => $checkpoints[0]->baseline_url,
            'diff_url' => $checkpoints[0]->diff_url,
            'status' => 'unknown',
            'diff_status' => 'unknown',
            'actions' => [
                'approve' => route('checkpoint.approve', ['id' => $checkpoints[0]->id]),
                'reject' => route('checkpoint.reject', ['id' => $checkpoints[0]->id]),
                'ignore' => route('checkpoint.ignore', ['id' => $checkpoints[0]->id]),
            ],
            'slug' => SlugGenerator::toSlug($checkpoints[0]->name),
        ]);

        $this->get('checkpoint/' . $checkpoints[1]->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'self' => route('checkpoint.show', ['id' => $checkpoints[1]->id]),
            'name' => $checkpoints[1]->name,
            'image_url' => $checkpoints[1]->image_url,
            'baseline_url' => $checkpoints[1]->baseline_url,
            'diff_url' => $checkpoints[1]->diff_url,
            'status' => 'unknown',
            'diff_status' => 'unknown',
            'actions' => [
                'approve' => route('checkpoint.approve', ['id' => $checkpoints[1]->id]),
                'reject' => route('checkpoint.reject', ['id' => $checkpoints[1]->id]),
                'ignore' => route('checkpoint.ignore', ['id' => $checkpoints[1]->id]),
            ],
            'slug' => SlugGenerator::toSlug($checkpoints[1]->name),
        ]);
        $this->get('checkpoint/random');
        $this->assertResponseStatus(404);
    }

    public function testApprovingCheckpoint()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];

        $this->get('checkpoint/' . $checkpoints[0]->id, $this->headers());
        // Verify that it's not approved.
        $this->seeJson(['status' => 'unknown']);

        $this->put('checkpoint/' . $checkpoints[0]->id . '/approve');

        $this->get('checkpoint/' . $checkpoints[0]->id);
        $this->seeJson(['status' => 'approved']);
    }

    public function testRejectingCheckpoint()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];

        $this->get('checkpoint/' . $checkpoints[0]->id, $this->headers());
        // Verify that it's not rejected.
        $this->seeJson(['status' => 'unknown']);

        $this->put('checkpoint/' . $checkpoints[0]->id . '/reject');

        $this->get('checkpoint/' . $checkpoints[0]->id);
        $this->seeJson(['status' => 'rejected']);
    }

    public function testIgnoringCheckpoint()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];

        $this->get('checkpoint/' . $checkpoints[0]->id, $this->headers());
        // Verify that it's not ignored.
        $this->seeJson(['status' => 'unknown']);

        $this->put('checkpoint/' . $checkpoints[0]->id . '/ignore');

        $this->get('checkpoint/' . $checkpoints[0]->id);
        $this->seeJson(['status' => 'ignored']);
    }
}
