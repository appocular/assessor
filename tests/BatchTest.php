<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;
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
        $run_id = $this->startBatch($sha);

        // Assert that we can see the batch and run in the database.
        $this->seeInDatabase('batches', ['id' => $run_id, 'sha' => $sha]);
        $this->seeInDatabase('runs', ['id' => $sha]);

        $this->delete('/api/v1/batch/' . $run_id);
        $this->assertResponseStatus(200);
        // Assert that the batch was deleted but the run still exists.
        $this->missingFromDatabase('batches', ['id' => $run_id, 'sha' => $sha]);
        $this->seeInDatabase('runs', ['id' => $sha]);
    }

    public function testUnknownBatch()
    {
        $this->delete('/api/v1/batch/random');
        $this->assertResponseStatus(404);
    }

    public function testBadImage()
    {
        $sha = str_repeat('1', 40);
        $run_id = $this->startBatch($sha);

        $this->json('POST', '/api/v1/batch/' . $run_id . '/image', ['name' => 'test image', 'image' => 'random data']);
        $this->assertResponseStatus(400);
        $this->seeJson(['error' => ['code' => 400, 'message' => 'Bad image data']]);
    }

    public function testAddingImage()
    {
        $imageStore = $this->prophesize(ImageStore::class);
        $imageStore->store(Argument::any())->willReturn('XXX');
        $this->app->instance(ImageStore::class, $imageStore->reveal());
        $sha = str_repeat('1', 40);
        $run_id = $this->startBatch($sha);

        // Test image taken from http://www.schaik.com/pngsuite/pngsuite_bas_png.html
        $image = file_get_contents(__DIR__ . '/../fixtures/images/basn6a16.png');

        $this->json('POST', '/api/v1/batch/' . $run_id . '/image', ['name' => 'test image', 'image' => base64_encode($image)]);
        $this->assertResponseStatus(200);
        $this->seeInDatabase('images', ['run_id' => $sha, 'name' => 'test image', 'image_sha' => 'XXX']);
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
