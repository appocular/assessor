<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Ogle\Assessor\ImageStore;
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
        $sha = str_repeat('0', 40);
        $batch_id = $this->startBatch($sha);

        // Assert that we can see the batch and run in the database.
        $this->seeInDatabase('batches', ['id' => $batch_id, 'sha' => $sha]);
        $this->seeInDatabase('runs', ['id' => $sha]);

        $this->delete('/api/v1/batch/' . $batch_id);
        $this->assertResponseStatus(200);
        // Assert that the batch was deleted but the run still exists.
        $this->missingFromDatabase('batches', ['id' => $batch_id, 'sha' => $sha]);
        $this->seeInDatabase('runs', ['id' => $sha]);
    }

    public function testUnknownBatch()
    {
        $this->delete('/api/v1/batch/random');
        $this->assertResponseStatus(404);
    }

    public function testBatchValidation()
    {
        $this->json('POST', '/api/v1/batch', []);
        $this->assertResponseStatus(422);
        $this->seeJsonEquals([
            'message' => 'The given data was invalid.',
            'validation_errors' => [
                'sha' => [0 => 'The sha field is required.'],
            ],
        ]);
        $json = json_decode($this->response->getContent());
    }

    public function testImageValidation()
    {
        $sha = str_repeat('1', 40);
        $batch_id = $this->startBatch($sha);

        $this->json('POST', '/api/v1/batch/' . $batch_id . '/image', []);
        $this->assertResponseStatus(422);
        $this->seeJsonEquals([
            'message' => 'The given data was invalid.',
            'validation_errors' => [
                'name' => [0 => 'The name field is required.'],
                'image' => [0 => 'The image field is required.'],
            ],
        ]);
        $json = json_decode($this->response->getContent());
    }

    public function testBadImage()
    {
        $sha = str_repeat('1', 40);
        $batch_id = $this->startBatch($sha);

        $this->json('POST', '/api/v1/batch/' . $batch_id . '/image', ['name' => 'test image', 'image' => 'random data']);
        $this->assertResponseStatus(400);
        $this->seeJson(['message' => 'Bad image data']);
    }

    public function testAddingImage()
    {
        $imageStore = $this->prophesize(ImageStore::class);
        $imageStore->store(Argument::any())->willReturn('XXX');
        $this->app->instance(ImageStore::class, $imageStore->reveal());
        $sha = str_repeat('1', 40);
        $batch_id = $this->startBatch($sha);

        // Test image taken from http://www.schaik.com/pngsuite/pngsuite_bas_png.html
        $image = file_get_contents(__DIR__ . '/../fixtures/images/basn6a16.png');
        //$this->json('POST', '/api/v1/batch/' . $batch_id . '/image', ['name' => '', 'image' => '']);

        $this->json('POST', '/api/v1/batch/' . $batch_id . '/image', ['name' => 'test image', 'image' => base64_encode($image)]);
        $this->seeInDatabase('images', [
            'id' => hash('sha1', 'test image'),
            'run_id' => $sha,
            'name' => 'test image',
            'image_sha' => 'XXX',
        ]);
        $this->assertResponseStatus(200);

        // Submitting an image with the same name should give an error.
        $this->json('POST', '/api/v1/batch/' . $batch_id . '/image', ['name' => 'test image', 'image' => base64_encode($image)]);
        $this->assertResponseStatus(409);
        $this->seeJson(['message' => 'Image already exists']);

        $this->json('POST', '/api/v1/batch/' . $batch_id . '/image', ['name' => 'test image2', 'image' => base64_encode($image)]);
        $this->assertResponseStatus(200);
        $this->seeInDatabase('images', [
            'id' => hash('sha1', 'test image2'),
            'run_id' => $sha,
            'name' => 'test image2',
            'image_sha' => 'XXX',
        ]);

        // Starting a new run (not batch) should enable adding the same image...
    }

    /**
     * Start a batch and return the id.
     */
    public function startBatch($sha)
    {
        $this->json('POST', '/api/v1/batch', ['sha' => $sha]);

        $this->assertResponseStatus(200);
        $this->seeJsonStructure(['id']);
        $json = json_decode($this->response->getContent());
        return $json->id;
    }
}
