<?php

declare(strict_types=1);

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Models\Repo;
use Appocular\Assessor\Models\Snapshot;
use Appocular\Assessor\TestCase;
use Exception;
use Illuminate\Support\Facades\Log;
use Prophecy\Argument;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GitHubStatusUpdateTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();
        // Common setup.
        \putenv('GITHUB_USER=guser');
        \putenv('GITHUB_PASSWORD=gpassword');
        $this->snapshot = \factory(Snapshot::class)->make([
            'status' => Snapshot::STATUS_UNKNOWN,
            'processing_status' => Snapshot::PROCESSING_STATUS_PENDING,
            'run_status' => Snapshot::RUN_STATUS_PENDING,
        ]);
        $this->snapshot->repo = \factory(Repo::class)->make([
            'uri' => 'git@github.com:appocular/assessor',
        ]);
        // Pretend the above has been saved to database.
        $this->snapshot->syncOriginal();
    }

    /**
     * Test that GitHub URLs as properly recognized.
     */
    public function testUriMatching(): void
    {
        $this->assertTrue(GitHubStatusUpdate::isGitHubUri('git@github.com:appocular/assessor.git'));
        $this->assertTrue(GitHubStatusUpdate::isGitHubUri('https://github.com/appocular/assessor'));

        $this->assertFalse(GitHubStatusUpdate::isGitHubUri('https://github.com/banana'));
        $this->assertFalse(GitHubStatusUpdate::isGitHubUri('https://github.com/banana/apple/pear'));
    }

    /**
     * @param array<array<string|array>> $expected
     *
     * @dataProvider uriProvider
     */
    public function testUriParsing(string $uri, array $expected): void
    {
        $this->assertEquals($expected, GitHubStatusUpdate::parseUri($uri));
    }

    /**
     * @return array<array<string|array>>
     */
    public function uriProvider(): array
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
    public function testStatusUpdateToGithub(
        string $status,
        string $processing_status,
        string $run_status,
        string $state,
        string $description
    ): void {
        Log::shouldReceive('info')
            ->once();

        $this->snapshot->status = $status;
        $this->snapshot->processing_status = $processing_status;
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
                ],
            ],
        )
            ->willReturn($response)
            ->shouldBeCalled();

        $job = new GitHubStatusUpdate($this->snapshot);
        $job->handle($client->reveal());
    }

    /**
     * @return array<array<string>>
     */
    public function statusProvider(): array
    {
        return [
            // Undetermined checkpoints (either with differences not
            // human-processed yet, or no diff) and running status should
            // provide running message. This state is only possible on a newly
            // created Snapshot.
            [
                Snapshot::STATUS_UNKNOWN,
                Snapshot::PROCESSING_STATUS_PENDING,
                Snapshot::RUN_STATUS_PENDING,
                'pending',
                'In progress. Please wait.',
            ],
            // All checkpoints passed, approved or ignored, but batch still
            // running. Still pending.
            [
                Snapshot::STATUS_PASSED,
                Snapshot::PROCESSING_STATUS_PENDING,
                Snapshot::RUN_STATUS_PENDING,
                'pending',
                'In progress. Please wait.',
            ],
            // Failed checkpoint exists, this is overall failure, even if
            // we're still running.
            [
                Snapshot::STATUS_FAILED,
                Snapshot::PROCESSING_STATUS_PENDING,
                Snapshot::RUN_STATUS_PENDING,
                'failure',
                'Failed!',
            ],
            // Shouldn't happen. Unknown checkpoints causes processing status
            // to be pending.
            [
                Snapshot::STATUS_UNKNOWN,
                Snapshot::PROCESSING_STATUS_DONE,
                Snapshot::RUN_STATUS_PENDING,
                'error',
                'Snapshot in unknown/impossible state? Please seek help.',
            ],
            // This shouldn't happen either, but we'll be open to the
            // possibility that status only considers processed checkpoints.
            [
                Snapshot::STATUS_PASSED,
                Snapshot::PROCESSING_STATUS_DONE,
                Snapshot::RUN_STATUS_PENDING,
                'pending',
                'In progress. Please wait.',
            ],
            // Failed checkpoint exists, this is overall failure, even if
            // we're still running.
            [
                Snapshot::STATUS_FAILED,
                Snapshot::PROCESSING_STATUS_DONE,
                Snapshot::RUN_STATUS_PENDING,
                'failure',
                'Failed!',
            ],
            // Run done, but unprocessed checkpoints, signal human processing
            // is needed.
            [
                Snapshot::STATUS_UNKNOWN,
                Snapshot::PROCESSING_STATUS_PENDING,
                Snapshot::RUN_STATUS_DONE,
                'failure',
                'Differences detected, please review.',
            ],
            // Shouldn't happen, unknown checkpoints cause status to be
            // unknown, but we'll allow it..
            [
                Snapshot::STATUS_PASSED,
                Snapshot::PROCESSING_STATUS_PENDING,
                Snapshot::RUN_STATUS_DONE,
                'pending',
                'In progress. Please wait.',
            ],
            // Rejected checkpoint, but more differences exists.
            [
                Snapshot::STATUS_FAILED,
                Snapshot::PROCESSING_STATUS_PENDING,
                Snapshot::RUN_STATUS_DONE,
                'failure',
                'Failed! More differences detected, please review.',
            ],
            // Shouldn't happen.
            [
                Snapshot::STATUS_UNKNOWN,
                Snapshot::PROCESSING_STATUS_DONE,
                Snapshot::RUN_STATUS_DONE,
                'error',
                'Snapshot in unknown/impossible state? Please seek help.',
            ],
            // All checkpoints passed/approved/ignored and no more batches/pending.
            [
                Snapshot::STATUS_PASSED,
                Snapshot::PROCESSING_STATUS_DONE,
                Snapshot::RUN_STATUS_DONE,
                'success',
                'Passed!',
            ],
            // Rejected checkpoint and no running batches/pending.
            [
                Snapshot::STATUS_FAILED,
                Snapshot::PROCESSING_STATUS_DONE,
                Snapshot::RUN_STATUS_DONE,
                'failure',
                'Failed!',
            ],
        ];
    }

    /**
     * Test that exceptions during request is properly logged.
     */
    public function testCatchingExceptions(): void
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
            ->willThrow(new Exception('random error'))
            ->shouldBeCalled();

        $job = new GitHubStatusUpdate($this->snapshot);
        $job->handle($client->reveal());
    }

    /**
     * Test that bad response code from GitHub is logged.
     */
    public function testLoggingBadResponses(): void
    {
        $url = 'https://api.github.com/repos/appocular/assessor/statuses/' . $this->snapshot->id;
        Log::shouldReceive('info')
            ->once();

        Log::shouldReceive('error')
            ->once()
            ->with(\sprintf(
                'Unexpected 202 response code from GitHub on "%s", user "%s", pass "%s"',
                $url,
                'guser',
                '*********',
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
