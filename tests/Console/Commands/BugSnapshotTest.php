<?php

declare(strict_types=1);

namespace Appocular\Assessor\Console\Commands;

use Appocular\Assessor\Models\Repo;
use Appocular\Assessor\TestCase;
use Illuminate\Contracts\Console\Kernel;
use Laravel\Lumen\Testing\DatabaseMigrations;

class BugSnapshotTest extends TestCase
{
    use DatabaseMigrations;

    public function testCreatesBugSnapshot(): void
    {
        $rc = $this->artisan('assessor:bug-snapshot', [
            'email' => 'test@example.com',
            'url' => 'http://example.com/test',
            'description' => "looks wrong\n\nplease fix.",
            // Use echo as mysqldump stand-in.
            '--mysqldump' => 'echo -- ',
        ]);

        $this->assertEquals(0, $rc);

        $output = \trim($this->app[Kernel::class]->output());
        print  $output;

        $sqlFile = \storage_path('bugreports/' . $output . '.sql');
        $this->assertTrue(\file_exists($sqlFile));
        // Hostname and username is the defaults, as is the empty password.
        // The ":memory:" database name is because the test is running with
        // SQLite in-memory database, and they share the same env names.
        $this->assertEquals("-- --compact --skip-comments -h127.0.0.1 -uforge -p :memory:\n", \file_get_contents($sqlFile));

        $yamlFile = \storage_path('bugreports/' . $output . '.yml');
        $this->assertTrue(\file_exists($yamlFile));
        $expectedYaml = <<<EOF
email: test@example.com
url: 'http://example.com/test'
description: "looks wrong\\n\\nplease fix."

EOF;
        $this->assertEquals($expectedYaml, \file_get_contents($yamlFile));
    }
}
