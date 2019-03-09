<?php

use Appocular\Assessor\Repo;
use Laravel\Lumen\Testing\DatabaseMigrations;

class RepoModelTest extends TestCase
{

    use DatabaseMigrations;

    public function testTokenGeneration()
    {
        $repo = new Repo(['uri' => 'generation test']);
        $repo->save();

        $repo = Repo::find('generation test');
        $this->assertNotEmpty($repo->token);
        $this->assertInternalType('string', $repo->token);
        // Generated tokens are SHAs at the moment.
        $this->assertRegexp('/^[0-9a-f]{64}$/', $repo->token);
    }

    public function testProvidedToken()
    {
        $repo = new Repo(['uri' => 'provided test']);
        $repo->token = 'the token';
        $repo->save();

        $repo = Repo::find('provided test');
        $this->assertEquals('the token', $repo->token);
    }
}
