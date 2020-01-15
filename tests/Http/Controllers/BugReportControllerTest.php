<?php

declare(strict_types=1);

namespace Appocular\Assessor\Http\Controllers;

class BugReportControllerTest extends ControllerTestBase
{
    /**
     * Test that bug reports are created.
     */
    public function testBugReportCreation(): void
    {
        // Use echo as mysqldump stand-in.
        $this->app['config']->set('assessor.mysqldump', 'echo -- ');

        $data = [
            'url' => 'http://example.com/test',
            'email' => 'user@example.com',
            'description' => "looks bad\n\npls fix",
        ];
        $this->json('POST', '/bugreport', $data, $this->frontendAuthHeaders());

        $this->assertResponseStatus(200);
        $this->seeJsonStructure(['id']);

        $data = \json_decode($this->response->getContent());

        // Just check that the files exists. The artisan command test checks
        // the content.
        $this->assertTrue(\file_exists(\storage_path('bugreports/' . $data->id . '.sql')));
        $this->assertTrue(\file_exists(\storage_path('bugreports/' . $data->id . '.yml')));
    }
}
