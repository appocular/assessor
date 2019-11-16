<?php

declare(strict_types=1);

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Jobs\SubmitImage;
use Appocular\Assessor\Models\Repo;
use Appocular\Assessor\TestCase;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;

class BatchControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function setUp(): void
    {
        parent::setUp();
        // Set up a repo.
        $repo = new Repo(['uri' => 'http://example.org/']);
        $repo->api_token = 'RepoToken';
        $repo->save();
        $this->repoId = $repo->uri;
    }

    /**
     * Return authorization headers for request.
     *
     * Note that the Illuminate\Auth\TokenGuard is only constructed on the
     * first request in a test, and the Authorization headert thus "sticks
     * around" for the subsequent requests, rendering passing the header to
     * them pointless.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return ["Authorization" => 'Bearer RepoToken'];
    }

    /**
     * Test that a batch can be created and deleted.
     */
    public function testCreateAndDelete(): void
    {
        $id = \str_repeat('0', 40);
        $batch_id = $this->startBatch($id);

        // Assert that we can see the batch and snapshot in the database.
        $this->seeInDatabase('batches', ['id' => $batch_id, 'snapshot_id' => $id]);
        $this->seeInDatabase('snapshots', ['id' => $id]);

        $this->delete('/batch/' . $batch_id);
        $this->assertResponseStatus(200);
        // Assert that the batch was deleted but the snapshot still exists.
        $this->missingFromDatabase('batches', ['id' => $batch_id, 'id' => $id]);
        $this->seeInDatabase('snapshots', ['id' => $id]);
    }

    /**
     * Test that the snapshot is associated with the repo that owns the token.
     */
    public function testRepoAssociation(): void
    {
        $id = 'banano';
        $batch_id = $this->startBatch($id);

        // Assert that we can see the batch and snapshot in the database.
        $this->seeInDatabase('batches', ['id' => $batch_id, 'snapshot_id' => $id]);
        $this->seeInDatabase('snapshots', ['id' => $id, 'repo_id' => $this->repoId]);
    }

    public function testUnknownBatch(): void
    {
        $this->delete('/batch/random', [], $this->headers());
        $this->assertResponseStatus(404);
    }

    public function testBatchValidation(): void
    {
        $this->json('POST', '/batch', [], $this->headers());
        $this->assertResponseStatus(422);
        $this->seeJsonEquals([
            'id' => [0 => 'The id field is required.'],
        ]);
    }

    public function testHistoryHandling(): void
    {
        // Suppress jobs so the history isn't processed before we have chance to inpect it.
        Queue::fake();
        $id = 'first';
        $batch_id = $this->startBatch($id, "one\ntwo");

        // Assert that the history is saved.
        $this->seeInDatabase('history', ['snapshot_id' => $id, 'history' => "one\ntwo"]);

        $this->delete('/batch/' . $batch_id);
        $this->assertResponseStatus(200);
        $this->missingFromDatabase('batches', ['id' => $batch_id]);

        // Assert that the history is still there.
        $this->seeInDatabase('history', ['snapshot_id' => $id, 'history' => "one\ntwo"]);

        $this->startBatch($id, "three\nfour");

        // Assert that the history is still the same.
        $this->seeInDatabase('history', ['snapshot_id' => $id, 'history' => "one\ntwo"]);
        $this->missingFromDatabase('history', ['snapshot_id' => $id, 'history' => "three\nfour"]);
    }

    public function testCheckpointValidation(): void
    {
        $id = \str_repeat('1', 40);
        $batch_id = $this->startBatch($id);

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', []);
        $this->assertResponseStatus(422);
        $this->seeJsonEquals([
            'name' => [0 => 'The name field is required.'],
            'image' => [0 => 'The image field is required.'],
        ]);
    }

    public function testBadCheckpoint(): void
    {
        $id = \str_repeat('1', 40);
        $batch_id = $this->startBatch($id);

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => 'random data']);
        $this->assertResponseStatus(400);
        $this->assertEquals("Bad image data\n", $this->response->getContent());
    }

    public function testAddingCheckpoint(): void
    {
        Queue::fake();

        $id = \str_repeat('1', 40);
        $batch_id = $this->startBatch($id);

        // Test image taken from
        // http://www.schaik.com/pngsuite/pngsuite_bas_png.html As
        // BatchController checks for a PNG header, we need something that
        // looks like an image, and an actual image is the superior likeness.
        $image = \base64_encode(\file_get_contents(__DIR__ . '/../../../fixtures/images/basn6a16.png'));

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => $image]);
        $this->assertResponseStatus(201);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'image_url' => null,
        ]);
        Queue::assertPushed(SubmitImage::class);

        // Submitting a checkpoint with the same name should just trigger
        // another SubmitImage job which will overwrite the former.
        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => $image]);
        $this->assertResponseStatus(201);
        Queue::assertPushed(SubmitImage::class, 2);

        // Posting a second image should work.
        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image2', 'image' => $image]);
        $this->assertResponseStatus(201);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image2',
            'image_url' => null,
        ]);
        Queue::assertPushed(SubmitImage::class, 3);

        // The first image should still be there.
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'image_url' => null,
        ]);

        $this->delete('/batch/' . $batch_id);
        $this->assertResponseStatus(200);

        // A new batch on another snapshot should be able to add the same image.
        $id2 = \str_repeat('2', 40);
        $batch_id = $this->startBatch($id2);

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => $image]);
        $this->assertResponseStatus(201);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id2,
            'name' => 'test image',
            'image_url' => null,
        ]);
        Queue::assertPushed(SubmitImage::class, 4);

        // The image from the other run should still be there.
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'image_url' => null,
        ]);
    }

    public function testAddingCheckpointWithMetadata(): void
    {
        Queue::fake();

        $id = \str_repeat('1', 40);
        $batch_id = $this->startBatch($id);

        // Test image taken from http://www.schaik.com/pngsuite/pngsuite_bas_png.html
        $image = \base64_encode(\file_get_contents(__DIR__ . '/../../../fixtures/images/basn6a16.png'));

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => $image]);
        $this->assertResponseStatus(201);

        $this->json(
            'POST',
            '/batch/' . $batch_id . '/checkpoint',
            ['name' => 'test image', 'image' => $image, 'meta' => ['test' => 'value']],
        );
        $this->assertResponseStatus(201);

        $this->json(
            'POST',
            '/batch/' . $batch_id . '/checkpoint',
            ['name' => 'test image', 'image' => $image, 'meta' => ['test' => 'value', 'key2' => 'more data']],
        );
        $this->assertResponseStatus(201);

        // This one should overwrite the previous as the name and meta is the same.
        $this->json(
            'POST',
            '/batch/' . $batch_id . '/checkpoint',
            ['name' => 'test image', 'image' => $image, 'meta' => ['key2' => 'more data', 'test' => 'value']],
        );
        $this->assertResponseStatus(201);

        // Check that there's exactly 3 checkpoints in the database.
        $this->assertEquals(3, $this->app->make('db')->table('checkpoints')->count());

        // Now check that all checkpoints is in the database.
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'meta' => null,
        ]);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'meta' => \json_encode(['test' => 'value']),
        ]);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'meta' => \json_encode(['key2' => 'more data', 'test' => 'value']),
        ]);
    }

    public function testMetadataValidation(): void
    {
        Queue::fake();

        $id = \str_repeat('7', 40);
        $batch_id = $this->startBatch($id);

        // Test image taken from http://www.schaik.com/pngsuite/pngsuite_bas_png.html
        $image = \base64_encode(\file_get_contents(__DIR__ . '/../../../fixtures/images/basn6a16.png'));

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => '1', 'image' => $image]);
        $this->assertResponseStatus(201);

        $this->json(
            'POST',
            '/batch/' . $batch_id . '/checkpoint',
            ['name' => '2', 'image' => $image, 'meta' => ['test' => 'test']],
        );
        $this->assertResponseStatus(201);

        $this->json(
            'POST',
            '/batch/' . $batch_id . '/checkpoint',
            ['name' => '3', 'image' => $image, 'meta' => ['test' => 'test', 'test more' => 'value']],
        );
        $this->assertResponseStatus(201);

        $this->json(
            'POST',
            '/batch/' . $batch_id . '/checkpoint',
            ['name' => '4', 'image' => $image, 'meta' => ['test' => 'test', 'test more' => ['value']]],
        );
        $this->assertResponseStatus(422);
        $this->json(
            'POST',
            '/batch/' . $batch_id . '/checkpoint',
            ['name' => '4', 'image' => $image, 'meta' => ['test' => []]],
        );
        $this->assertResponseStatus(422);

        $this->json(
            'POST',
            '/batch/' . $batch_id . '/checkpoint',
            ['name' => '4', 'image' => $image, 'meta' => ['test' => null]],
        );
        $this->assertResponseStatus(422);
    }

    /**
     * Start a batch and return the id.
     */
    public function startBatch(string $id, ?string $history = null): string
    {

        $data = ['id' => $id];

        if ($history) {
            $data['history'] = $history;
        }

        $this->json('POST', '/batch', $data, $this->headers());

        $this->assertResponseStatus(201);
        $this->assertTrue($this->response->headers->has('Location'));
        $location = $this->response->headers->get('Location');
        $parts = \explode('/', $location);

        return \array_pop($parts);
    }
}
