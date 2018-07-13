<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

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
        $this->json('POST', '/api/v1/batch', ['sha' => str_repeat('0', 40)]);

        $this->assertResponseStatus(200);
        $this->seeJsonStructure(['id']);
        $json = json_decode($this->response->getContent());
        $this->seeInDatabase('batches', ['id' => $json->id, 'sha' => str_repeat('0', 40)]);

        $this->delete('/api/v1/batch/' . $json->id);
        $this->assertResponseStatus(200);
        $this->missingFromDatabase('batches', ['id' => $json->id, 'sha' => str_repeat('0', 40)]);
    }

    public function testUnknownBatch()
    {
        $this->delete('/api/v1/batch/random');
        $this->assertResponseStatus(404);
    }
}
