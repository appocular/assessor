<?php

namespace Controllers;

use Appocular\Clients\Contracts\Differ;
use Appocular\Clients\Contracts\Keeper;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\WithoutMiddleware;
use Prophecy\Argument;

class BatchTest extends ControllerTestBase
{
    use DatabaseMigrations;
    use WithoutMiddleware;

    /**
     * Test that a batch can be created and deleted.
     *
     * @return void
     */
    public function testCreateAndDelete()
    {
        $id = str_repeat('0', 40);
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

    public function testUnknownBatch()
    {
        $this->delete('/batch/random');
        $this->assertResponseStatus(404);
    }

    public function testBatchValidation()
    {
        $this->json('POST', '/batch', []);
        $this->assertResponseStatus(422);
        $this->seeJsonEquals([
            'id' => [0 => 'The id field is required.'],
        ]);
    }

    public function testHistoryHandling()
    {
        // Suppress jobs so the history isn't processed before we have chance to inpect it.
        Queue::fake();
        $id = 'first';
        $batch_id = $this->startBatch($id, "one\ntwo");

        // Assert that the history is saved.
        $this->seeInDatabase('history', ['snapshot_id' => $id, 'history' => "one\ntwo"]);

        $this->delete('/batch/' . $batch_id);
        $this->assertResponseStatus(200);

        // Assert that the history is still there.
        $this->seeInDatabase('history', ['snapshot_id' => $id, 'history' => "one\ntwo"]);

        $batch_id = $this->startBatch($id, "three\nfour");

        // Assert that the history is still the same.
        $this->seeInDatabase('history', ['snapshot_id' => $id, 'history' => "one\ntwo"]);
        $this->missingFromDatabase('history', ['snapshot_id' => $id, 'history' => "three\nfour"]);
    }

    public function testCheckpointValidation()
    {
        $id = str_repeat('1', 40);
        $batch_id = $this->startBatch($id);

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', []);
        $this->assertResponseStatus(422);
        $this->seeJsonEquals([
            'name' => [0 => 'The name field is required.'],
            'image' => [0 => 'The image field is required.'],
        ]);
    }

    public function testBadCheckpoint()
    {
        $id = str_repeat('1', 40);
        $batch_id = $this->startBatch($id);

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => 'random data']);
        $this->assertResponseStatus(400);
        $this->assertEquals("Bad image data\n", $this->response->getContent());
    }

    public function testAddingCheckpoint()
    {
        $this->keeperProphecy->store(Argument::any())->willReturn('XXX');
        $id = str_repeat('1', 40);
        $batch_id = $this->startBatch($id);

        // Test image taken from http://www.schaik.com/pngsuite/pngsuite_bas_png.html
        $image = base64_encode(file_get_contents(__DIR__ . '/../../fixtures/images/basn6a16.png'));

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => $image]);
        $this->assertResponseStatus(201);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'image_url' => 'XXX',
        ]);

        // Submitting an image with the same name should replace the data of image.
        $this->keeperProphecy->store(Argument::any())->willReturn('YYY');
        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => $image]);
        $this->assertResponseStatus(201);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'image_url' => 'YYY',
        ]);
        // Check that the old data is missing.
        $this->missingFromDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image2',
            'image_url' => 'XXX',
        ]);

        // Posting a second image should work.
        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image2', 'image' => $image]);
        $this->assertResponseStatus(201);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image2',
            'image_url' => 'YYY',
        ]);

        // The first image should still be there.
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'image_url' => 'YYY',
        ]);

        $this->delete('/batch/' . $batch_id);
        $this->assertResponseStatus(200);

        // A new batch on another snapshot should be able to add the same image.
        $id2 = str_repeat('2', 40);
        $batch_id = $this->startBatch($id2);

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => $image]);
        $this->assertResponseStatus(201);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id2,
            'name' => 'test image',
            'image_url' => 'YYY',
        ]);

        // The image from the other run should still be there.
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'image_url' => 'YYY',
        ]);
    }

    /**
     * Start a batch and return the id.
     */
    public function startBatch($id, $history = null)
    {

        $data = ['id' => $id];
        if ($history) {
            $data['history'] = $history;
        }
        $this->json('POST', '/batch', $data);

        $this->assertResponseStatus(201);
        $this->assertTrue($this->response->headers->has('Location'));
        $location = $this->response->headers->get('Location');
        $parts = explode('/', $location);
        return array_pop($parts);
    }
}
