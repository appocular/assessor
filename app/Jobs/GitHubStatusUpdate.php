<?php

declare(strict_types=1);

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Models\Snapshot;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class GitHubStatusUpdate extends Job
{

    // This simple regex will do for the moment.
    protected const URI_PATTERN = <<<EOF
{^(git@github.com:|https://github.com/) # Host part, ssh or https.
(?<org>[^/]+)/ # Organization.
(?<repo>[^/]+) # Repo.
/?$ # Optional extension or slash.
}x
EOF;

    public static function isGitHubUri(string $uri): bool
    {
        return (bool) \preg_match(self::URI_PATTERN, $uri);
    }

    /**
     * @return array<string>
     */
    public static function parseUri(string $uri): array
    {
        if (\preg_match(self::URI_PATTERN, $uri, $matches)) {
            return [$matches['org'], \preg_replace('{.git$}', '', $matches['repo'])];
        }

        return [];
    }

    /**
     * Snapshot to report status for.
     *
     * @var \Appocular\Assessor\Models\Snapshot
     */
    protected $snapshot;

    public function __construct(Snapshot $snapshot)
    {
        $this->snapshot = $snapshot;
    }

    /**
     * Execute the job.
     */
    public function handle(?HttpClientInterface $client = null): void
    {
        $client = $client ?? HttpClient::create();
        [$org, $repo] = self::parseUri($this->snapshot->repo->uri);

        if ($org === null) {
            throw new RuntimeError('Not a GitHub repo, cannot update commit status');
        }

        switch ([$this->snapshot->status, $this->snapshot->processing_status, $this->snapshot->run_status]) {
            case [Snapshot::STATUS_UNKNOWN, Snapshot::PROCESSING_STATUS_PENDING, Snapshot::RUN_STATUS_PENDING]:
            case [Snapshot::STATUS_PASSED, Snapshot::PROCESSING_STATUS_PENDING, Snapshot::RUN_STATUS_PENDING]:
            case [Snapshot::STATUS_PASSED, Snapshot::PROCESSING_STATUS_PENDING, Snapshot::RUN_STATUS_DONE]:
            case [Snapshot::STATUS_PASSED, Snapshot::PROCESSING_STATUS_DONE, Snapshot::RUN_STATUS_PENDING]:
                $state = 'pending';
                $description = 'In progress. Please wait.';

                break;
            case [Snapshot::STATUS_FAILED, Snapshot::PROCESSING_STATUS_PENDING, Snapshot::RUN_STATUS_PENDING]:
            case [Snapshot::STATUS_FAILED, Snapshot::PROCESSING_STATUS_DONE, Snapshot::RUN_STATUS_PENDING]:
            case [Snapshot::STATUS_FAILED, Snapshot::PROCESSING_STATUS_DONE, Snapshot::RUN_STATUS_DONE]:
                $state = 'failure';
                $description = 'Failed!';

                break;
            case [Snapshot::STATUS_FAILED, Snapshot::PROCESSING_STATUS_PENDING, Snapshot::RUN_STATUS_DONE]:
                $state = 'failure';
                $description = 'Failed! More differences detected, please review.';

                break;
            case [Snapshot::STATUS_UNKNOWN, Snapshot::PROCESSING_STATUS_PENDING, Snapshot::RUN_STATUS_DONE]:
                $state = 'failure';
                $description = 'Differences detected, please review.';

                break;
            case [Snapshot::STATUS_PASSED, Snapshot::PROCESSING_STATUS_DONE, Snapshot::RUN_STATUS_DONE]:
                $state = 'success';
                $description = 'Passed!';

                break;
            default:
                // Shouldn't happen.
                $state = 'error';
                $description = 'Snapshot in unknown/impossible state? Please seek help.';
        }

        $uri = 'https://api.github.com/repos/' . $org . '/' .
            $repo . '/statuses/' . $this->snapshot->id;

        try {
            Log::info('Sending status update for ' . $this->snapshot->repo->uri);
            $res = $client->request(
                'POST',
                $uri,
                [
                    'auth_basic' => [\env('GITHUB_USER', ''), \env('GITHUB_PASSWORD', '')],
                    'json' => [
                        'context' => 'Appocular visual regression test',
                        'state' => $state,
                        'description' => $description,
                        'target_url' => \rtrim(\env('STOPGAP_BASE_URI', 'https://appocular.io/'), '/') .
                        '/' . $this->snapshot->id,
                    ],
                ],
            );

            if ($res->getStatusCode() !== 201) {
                Log::error(\sprintf(
                    'Unexpected %d response code from GitHub on "%s", user "%s", pass "%s"',
                    $res->getStatusCode(),
                    $uri,
                    \env('GITHUB_USER', ''),
                    \preg_replace('{.}', '*', \env('GITHUB_PASSWORD', '')),
                ));
            }
        } catch (Throwable $e) {
            Log::error('Error updating GitHub commit status: ' . $e->getMessage());
        }
    }
}
