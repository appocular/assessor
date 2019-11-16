<?php

declare(strict_types=1);

namespace Commands;

use Appocular\Assessor\Repo;
use Laravel\Lumen\Testing\DatabaseMigrations;

class AddRepoCommandTest extends \TestCase
{
    use DatabaseMigrations;

    public function testAddWithTokenGeneration(): void
    {
        $this->artisan('assessor:add-repo', ['uri' => 'repo without token']);
        $this->seeInDatabase('repos', ['uri' => 'repo without token']);
        $repo = Repo::find('repo without token');
        $this->assertNotEmpty($repo->api_token);
    }

    public function testAddWithoutTokenGeneration(): void
    {
        $this->artisan('assessor:add-repo', ['uri' => 'repo with token', 'token' => 'a token']);
        $this->seeInDatabase('repos', ['uri' => 'repo with token', 'api_token' => 'a token']);
    }
}
