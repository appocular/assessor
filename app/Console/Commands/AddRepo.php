<?php

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
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $repo = new Repo(['uri' => $this->argument('uri')]);
        $token = $this->argument('token');
        if ($token) {
            $repo->token = $token;
        }
        $repo->save();
        $this->line($repo->uri . ' added with token: ' . $repo->token);
    }
}
