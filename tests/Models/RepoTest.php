<?php

declare(strict_types=1);

namespace Appocular\Assessor\Models;

use Appocular\Assessor\TestCase;
use Laravel\Lumen\Testing\DatabaseMigrations;
use RuntimeException;

class RepoTest extends TestCase
{
    use DatabaseMigrations;

    public function testTokenGeneration(): void
    {
        $repo = new Repo(['uri' => 'generation test']);
        $repo->save();

        $repo = Repo::find('generation test');
        $this->assertNotEmpty($repo->api_token);
        $this->assertIsString('string', $repo->api_token);
        // Generated tokens are SHAs at the moment.
        $this->assertRegexp('/^[0-9a-f]{64}$/', $repo->api_token);
    }

    public function testProvidedToken(): void
    {
        $repo = new Repo(['uri' => 'provided test']);
        $repo->api_token = 'the token';
        $repo->save();

        $repo = Repo::find('provided test');
        $this->assertEquals('the token', $repo->api_token);
    }
    public function testUniqueTokens(): void
    {
        $repo = new Repo(['uri' => 'unique test']);
        $repo->api_token = 'the token';
        $repo->save();

        $this->expectException(RuntimeException::class);
        $repo = new Repo(['uri' => 'unique test 2']);
        $repo->api_token = 'the token';
        $repo->save();
    }
}
