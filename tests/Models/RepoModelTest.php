<?php

use Appocular\Assessor\Repo;
use Laravel\Lumen\Testing\DatabaseMigrations;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
class RepoModelTest extends TestCase
{

    use DatabaseMigrations;

    public function testTokenGeneration()
    {
        $repo = new Repo(['uri' => 'generation test']);
        $repo->save();

        $repo = Repo::find('generation test');
        $this->assertNotEmpty($repo->api_token);
        $this->assertInternalType('string', $repo->api_token);
        // Generated tokens are SHAs at the moment.
        $this->assertRegexp('/^[0-9a-f]{64}$/', $repo->api_token);
    }

    public function testProvidedToken()
    {
        $repo = new Repo(['uri' => 'provided test']);
        $repo->api_token = 'the token';
        $repo->save();

        $repo = Repo::find('provided test');
        $this->assertEquals('the token', $repo->api_token);
    }
}
