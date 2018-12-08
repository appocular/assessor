<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Appocular\Assessor\ImageStore;
use Prophecy\Argument;

class BatchTest extends TestCase
{
    use DatabaseMigrations;

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
            'message' => 'The given data was invalid.',
            'validation_errors' => [
                'id' => [0 => 'The id field is required.'],
            ],
        ]);
    }

    public function testCheckpointValidation()
    {
        $id = str_repeat('1', 40);
        $batch_id = $this->startBatch($id);

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', []);
        $this->assertResponseStatus(422);
        $this->seeJsonEquals([
            'message' => 'The given data was invalid.',
            'validation_errors' => [
                'name' => [0 => 'The name field is required.'],
                'image' => [0 => 'The image field is required.'],
            ],
        ]);
    }

    public function testBadCheckpoint()
    {
        $id = str_repeat('1', 40);
        $batch_id = $this->startBatch($id);

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => 'random data']);
        $this->assertResponseStatus(400);
        $this->seeJson(['message' => 'Bad image data']);
    }

    public function testAddingCheckpoint()
    {
        $imageStore = $this->prophesize(ImageStore::class);
        $imageStore->store(Argument::any())->willReturn('XXX');
        $this->app->instance(ImageStore::class, $imageStore->reveal());
        $id = str_repeat('1', 40);
        $batch_id = $this->startBatch($id);

        // Test image taken from http://www.schaik.com/pngsuite/pngsuite_bas_png.html
        $image = file_get_contents(__DIR__ . '/../fixtures/images/basn6a16.png');

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => base64_encode($image)]);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'image_sha' => 'XXX',
        ]);
        $this->assertResponseStatus(200);

        // Submitting an image with the same name should replace the data of image.
        $imageStore->store(Argument::any())->willReturn('YYY');
        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => base64_encode($image)]);
        $this->assertResponseStatus(200);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'image_sha' => 'YYY',
        ]);
        // Check that the old data is missing.
        $this->missingFromDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image2',
            'image_sha' => 'XXX',
        ]);

        // Posting a second image should work.
        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image2', 'image' => base64_encode($image)]);
        $this->assertResponseStatus(200);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image2',
            'image_sha' => 'YYY',
        ]);

        // The first image should still be there.
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'image_sha' => 'YYY',
        ]);

        $this->delete('/batch/' . $batch_id);
        $this->assertResponseStatus(200);

        // A new batch on another snapshot should be able to add the same image.
        $id2 = str_repeat('2', 40);
        $batch_id = $this->startBatch($id2);

        $this->json('POST', '/batch/' . $batch_id . '/checkpoint', ['name' => 'test image', 'image' => base64_encode($image)]);
        $this->assertResponseStatus(200);
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id2,
            'name' => 'test image',
            'image_sha' => 'YYY',
        ]);

        // The image from the other run should still be there.
        $this->seeInDatabase('checkpoints', [
            'snapshot_id' => $id,
            'name' => 'test image',
            'image_sha' => 'YYY',
        ]);
    }

    /**
     * !!!Test that SHAs are normalized to lowercase.
     */
    // public function testRegressionShaCasing()
    // {
    //     $sha_uppercase = str_repeat('A', 40);
    //     $sha_lowercase = str_repeat('a', 40);
    //     $batch_id = $this->startBatch($sha_uppercase);
    //     $this->seeInDatabase('batches', ['id' => $batch_id, 'sha' => $sha_lowercase]);

    //     $batch_id = $this->startBatch($sha_lowercase);
    //     $this->seeInDatabase('batches', ['id' => $batch_id, 'sha' => $sha_lowercase]);
    // }

    /**
     * Start a batch and return the id.
     */
    public function startBatch($id)
    {
        $this->json('POST', '/batch', ['id' => $id]);

        $this->assertResponseStatus(200);
        $this->seeJsonStructure(['id']);
        $json = json_decode($this->response->getContent());
        return $json->id;
    }
}
