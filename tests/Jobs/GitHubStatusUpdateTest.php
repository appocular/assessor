<?php

namespace Jobs;

use Appocular\Assessor\Jobs\GitHubStatusUpdate;
use Appocular\Assessor\Repo;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Log;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

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
     * Test that pending status is properly sent to GitHub.
     */
    public function testSendPendingUpdateToGithub()
    {
        Log::shouldReceive('info')
            ->once();

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
                    'state' => 'pending',
                    'description' => 'In progress. Please wait.',
                    'target_url' => route('snapshot.show', ['id' => $this->snapshot->id]),
                ]
            ]
        )
            ->willReturn($response)
            ->shouldBeCalled();

        $job = new GitHubStatusUpdate($this->snapshot);
        $job->handle($client->reveal());
    }

    /**
     * Test that passed status is properly sent to GitHub.
     */
    public function testSendPassedUpdateToGithub()
    {
        Log::shouldReceive('info')
            ->once();

        $this->snapshot->run_status = Snapshot::RUN_STATUS_DONE;
        $this->snapshot->status = Snapshot::STATUS_PASSED;

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
                    'state' => 'success',
                    'description' => 'Passed',
                    'target_url' => route('snapshot.show', ['id' => $this->snapshot->id]),
                ]
            ]
        )
            ->willReturn($response)
            ->shouldBeCalled();

        $job = new GitHubStatusUpdate($this->snapshot);
        $job->handle($client->reveal());
    }

    /**
     * Test that failed status is properly sent to GitHub.
     */
    public function testSendFailedUpdateToGithub()
    {
        Log::shouldReceive('info')
            ->once();

        $this->snapshot->run_status = Snapshot::RUN_STATUS_DONE;
        $this->snapshot->status = Snapshot::STATUS_FAILED;

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
                    'state' => 'failure',
                    'description' => 'Failed',
                    'target_url' => route('snapshot.show', ['id' => $this->snapshot->id]),
                ]
            ]
        )
            ->willReturn($response)
            ->shouldBeCalled();

        $job = new GitHubStatusUpdate($this->snapshot);
        $job->handle($client->reveal());
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
        $client->request(
            'POST',
            'https://api.github.com/repos/appocular/assessor/statuses/' . $this->snapshot->id,
            [
                'auth_basic' => ['guser', 'gpassword'],
                'json' => [
                    'context' => 'Appocular visual regression test',
                    'state' => 'failure',
                    'description' => 'Failed',
                    'target_url' => route('snapshot.show', ['id' => $this->snapshot->id]),
                ]
            ]
        )
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
        $client->request(
            'POST',
            $url,
            [
                'auth_basic' => ['guser', 'gpassword'],
                'json' => [
                    'context' => 'Appocular visual regression test',
                    'state' => 'failure',
                    'description' => 'Failed',
                    'target_url' => route('snapshot.show', ['id' => $this->snapshot->id]),
                ]
            ]
        )
            ->willReturn($response)
            ->shouldBeCalled();

        $job = new GitHubStatusUpdate($this->snapshot);
        $job->handle($client->reveal());
    }
}
