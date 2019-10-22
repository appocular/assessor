<?php

namespace Jobs;

use Appocular\Assessor\Jobs\GitHubStatusUpdate;
use Appocular\Assessor\Repo;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Log;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Prophecy\Argument;

class GitHubStatusUpdateTest extends \TestCase
{

    public function setUp(): void
    {
        parent::setUp();
        // Common setup.
        putenv('GITHUB_USER=guser');
        putenv('GITHUB_PASSWORD=gpassword');
        $this->snapshot = factory(Snapshot::class)->make([
            'status' => Snapshot::STATUS_UNKNOWN,
            'run_status' => Snapshot::RUN_STATUS_PENDING,
        ]);
        $this->snapshot->repo = factory(Repo::class)->make([
            'uri' => 'git@github.com:appocular/assessor',
        ]);
        // Pretend the above has been saved to database.
        $this->snapshot->syncOriginal();
    }

    /**
     * Test that GitHub URLs as properly recognized.
     */
    public function testUriMatching()
    {
        $this->assertTrue(GitHubStatusUpdate::isGitHubUri('git@github.com:appocular/assessor.git'));
        $this->assertTrue(GitHubStatusUpdate::isGitHubUri('https://github.com/appocular/assessor'));

        $this->assertFalse(GitHubStatusUpdate::isGitHubUri('https://github.com/banana'));
        $this->assertFalse(GitHubStatusUpdate::isGitHubUri('https://github.com/banana/apple/pear'));
    }

    /**
     * @dataProvider uriProvider
     */
    public function testUriParsing($uri, $expected)
    {
        $this->assertEquals($expected, GitHubStatusUpdate::parseUri($uri));
    }

    public function uriProvider()
    {
        return [
            ['https://github.com/appocular/assessor', ['appocular', 'assessor']],
            ['https://github.com/appocular/assessor/', ['appocular', 'assessor']],
            ['git@github.com:appocular/assessor.git', ['appocular', 'assessor']],
            // Technically not valid, but we'll let it pass.
            ['git@github.com:appocular/assessor', ['appocular', 'assessor']],
            ['https://github.com/banana', []],
        ];
    }

    /**
     * Test that statuses is properly sent to GitHub.
     *
     * @dataProvider statusProvider
     */
    public function testStatusUpdateToGithub($status, $run_status, $state, $description)
    {
        Log::shouldReceive('info')
            ->once();

        $this->snapshot->status = $status;
        $this->snapshot->run_status = $run_status;

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()
            ->willReturn(201);
        $client = $this->prophesize(HttpClientInterface::class);
        $client->request(
            'POST',
            'https://api.github.com/repos/appocular/assessor/statuses/' . $this->snapshot->id,
            [
                'auth_basic' => ['guser', 'gpassword'],
                'json' => [
                    'context' => 'Appocular visual regression test',
                    'state' => $state,
                    'description' => $description,
                    'target_url' => 'https://appocular.io/' . $this->snapshot->id,
                ]
            ]
        )
            ->willReturn($response)
            ->shouldBeCalled();

        $job = new GitHubStatusUpdate($this->snapshot);
        $job->handle($client->reveal());
    }

    public function statusProvider()
    {
        return [
            // Undetermined checkpoints (either with differences not
            // human-processed yet, or no diff) and running status should
            // provide running message.
            [Snapshot::STATUS_UNKNOWN, Snapshot::RUN_STATUS_PENDING, 'pending', 'In progress. Please wait.'],
            // All checkpoints passed, approved or ignored, but batch still running. Still pending.
            [Snapshot::STATUS_PASSED, Snapshot::RUN_STATUS_PENDING, 'pending', 'In progress. Please wait.'],
            // Failed checkpoint exists, this is overall failure, even if we're still running.
            [Snapshot::STATUS_FAILED, Snapshot::RUN_STATUS_PENDING, 'failure', 'Failed!'],
            // Run done, but unprocessed checkpoints, signal human processing is needed.
            [Snapshot::STATUS_UNKNOWN, Snapshot::RUN_STATUS_DONE, 'failure', 'Differences detected, please review.'],
            [Snapshot::STATUS_PASSED, Snapshot::RUN_STATUS_DONE, 'success', 'Passed!'],
            [Snapshot::STATUS_FAILED, Snapshot::RUN_STATUS_DONE, 'failure', 'Failed!'],
        ];
    }

    /**
     * Test that exceptions during request is properly logged.
     */
    public function testCatchingExceptions()
    {
        Log::shouldReceive('info')
            ->once();

        Log::shouldReceive('error')
            ->once()
            ->with('Error updating GitHub commit status: random error')
            ->andReturn();

        $this->snapshot->run_status = Snapshot::RUN_STATUS_DONE;
        $this->snapshot->status = Snapshot::STATUS_FAILED;

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()
            ->willReturn(201);
        $client = $this->prophesize(HttpClientInterface::class);
        $client->request(Argument::any(), Argument::any(), Argument::any())
            ->willThrow(new \Exception('random error'))
            ->shouldBeCalled();

        $job = new GitHubStatusUpdate($this->snapshot);
        $job->handle($client->reveal());
    }

    /**
     * Test that bad response code from GitHub is logged.
     */
    public function testLoggingBadResponses()
    {
        $url = 'https://api.github.com/repos/appocular/assessor/statuses/' . $this->snapshot->id;
        Log::shouldReceive('info')
            ->once();

        Log::shouldReceive('error')
            ->once()
            ->with(sprintf(
                'Unexpected 202 response code from GitHub on "%s", user "%s", pass "%s"',
                $url,
                'guser',
                '*********'
            ))
            ->andReturn();

        $this->snapshot->run_status = Snapshot::RUN_STATUS_DONE;
        $this->snapshot->status = Snapshot::STATUS_FAILED;

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()
            ->willReturn(202)
            ->shouldBeCalled();
        $client = $this->prophesize(HttpClientInterface::class);
        $client->request(Argument::any(), Argument::any(), Argument::any())
            ->willReturn($response)
            ->shouldBeCalled();

        $job = new GitHubStatusUpdate($this->snapshot);
        $job->handle($client->reveal());
    }
}
