<?php

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use RuntimeException;
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

    public static function isGitHubUri($uri): bool
    {
        return (preg_match(self::URI_PATTERN, $uri));
    }

    public static function parseUri(string $uri)
    {
        if (preg_match(self::URI_PATTERN, $uri, $matches)) {
            return [$matches['org'], preg_replace('{.git$}', '', $matches['repo'])];
        }

        return [];
    }

    /**
     * @var \Appocular\Assessor\Snapshot
     */
    protected $snapshot;

    public function __construct(Snapshot $snapshot)
    {
        $this->snapshot = $snapshot;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(HttpClientInterface $client = null)
    {
        $client = $client ?? HttpClient::create();
        list($org, $repo) = self::parseUri($this->snapshot->repo->uri);
        if (empty($org)) {
            throw new RuntimeError('Not a GitHub repo, cannot update commit status');
        }

        switch ([$this->snapshot->status, $this->snapshot->run_status]) {
            case [Snapshot::STATUS_UNKNOWN, Snapshot::RUN_STATUS_PENDING]:
            case [Snapshot::STATUS_PASSED, Snapshot::RUN_STATUS_PENDING]:
                $state = 'pending';
                $description = 'In progress. Please wait.';
                break;

            case [Snapshot::STATUS_FAILED, Snapshot::RUN_STATUS_PENDING]:
            case [Snapshot::STATUS_FAILED, Snapshot::RUN_STATUS_DONE]:
                $state = 'failure';
                $description = 'Failed!';
                break;

            case [Snapshot::STATUS_UNKNOWN, Snapshot::RUN_STATUS_DONE]:
                $state = 'failure';
                $description = 'Differences detected, please review.';
                break;

            case [Snapshot::STATUS_PASSED, Snapshot::RUN_STATUS_DONE]:
                $state = 'success';
                $description = 'Passed!';
                break;

            default:
                // Shouldn't happen.
                $state = 'error';
                $description = 'snapshot in unknown state? Please seek help.';
        }

        $uri = 'https://api.github.com/repos/' . $org . '/' .
            $repo . '/statuses/' . $this->snapshot->id;
        try {
            Log::info('Sending status update for ' . $this->snapshot->repo->uri);
            $res = $client->request(
                'POST',
                $uri,
                [
                    'auth_basic' => [env('GITHUB_USER', ''), env('GITHUB_PASSWORD', '')],
                    'json' => [
                        'context' => 'Appocular visual regression test',
                        'state' => $state,
                        'description' => $description,
                        'target_url' => rtrim(env('STOPGAP_BASE_URI', 'https://appocular.io/'), '/') .
                        '/' . $this->snapshot->id
                    ]
                ]
            );

            if ($res->getStatusCode() != 201) {
                Log::error(sprintf(
                    'Unexpected %d response code from GitHub on "%s", user "%s", pass "%s"',
                    $res->getStatusCode(),
                    $uri,
                    env('GITHUB_USER', ''),
                    preg_replace('{.}', '*', env('GITHUB_PASSWORD', ''))
                ));
            }
        } catch (Throwable $e) {
            Log::error('Error updating GitHub commit status: ' . $e->getMessage());
        }
    }
}
