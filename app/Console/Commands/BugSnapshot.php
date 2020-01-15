<?php

declare(strict_types=1);

namespace Appocular\Assessor\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Webpatser\Uuid\Uuid;

class BugSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assessor:bug-snapshot
                            {email : Reporter email}
                            {url : Frontend URL where the bug was seen}
                            {description : Description of the problem}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Take a snapshot for a bug report.';

    /**
     * The ID assigned to the report.
     *
     * @var string
     */
    protected $id;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        // Create bugreports directory if it doesn't exist.
        if (!\is_dir(\storage_path('bugreports'))) {
            \mkdir(\storage_path('bugreports'));
        }

        $this->id = (string) Uuid::generate(4);
    }

    /**
     * Execute the console command.
     */
    public function handle(): ?int
    {
        // Copying mysqldump option in verbatim, to allow for tricks like
        // --mysqldump="mysqldump --some-arg".
        $commandLine = $this->laravel['config']->get('assessor.mysqldump', 'mysqldump') .
            ' --compact --skip-comments -h"${:HOSTNAME}" -u"${:USERNAME}" -p"${:PASSWORD}" "${:DATABASE}" > "${:FILENAME}"';
        $process = Process::fromShellCommandline(
            $commandLine,
            null,
            [
                'USERNAME' => \config('database.connections.mysql.username'),
                'PASSWORD' => \config('database.connections.mysql.password'),
                'DATABASE' => \config('database.connections.mysql.database'),
                'HOSTNAME' => \config('database.connections.mysql.host'),
                'FILENAME' => \storage_path('bugreports/' . $this->id . '.sql'),
            ],
        );

        $process->mustRun();

        $yaml = YAML::dump([
            'email' => $this->argument('email'),
            'url' => $this->argument('url'),
            'description' => $this->argument('description'),
        ]);

        \file_put_contents(\storage_path('bugreports/' . $this->id . '.yml'), $yaml);

        $this->line($this->id);

        return 0;
    }
}
