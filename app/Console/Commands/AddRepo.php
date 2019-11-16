<?php

declare(strict_types=1);

namespace Appocular\Assessor\Console\Commands;

use Appocular\Assessor\Repo;
use Illuminate\Console\Command;

class AddRepo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assessor:add-repo {uri} {token?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a new repo.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): ?int
    {
        $repo = new Repo(['uri' => $this->argument('uri')]);
        $token = $this->argument('token');

        if ($token) {
            $repo->api_token = $token;
        }

        $repo->save();
        $this->line($repo->uri . ' added with token: ' . $repo->api_token);

        return 0;
    }
}
