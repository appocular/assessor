<?php

declare(strict_types=1);

namespace Appocular\Assessor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Laravel\Lumen\Routing\Controller as BaseController;

class BugReportController extends BaseController
{
    /**
     * Create a new bug report.
     */
    public function create(Request $request): Response
    {
        $this->validate($request, [
            'url' => 'required|string|min:1|max:255',
            'email' => 'required|string|min:1|max:255',
            'description' => 'required|string|min:1|max:32768',
        ]);

        $rc = Artisan::call('assessor:bug-snapshot', [
            'url' => $request->input('url'),
            'email' => $request->input('email'),
            'description' => $request->input('description'),
        ]);

        if ($rc !== 0) {
            return new Response('Internal error', 500);
        }

        $id = \trim(Artisan::output());

        // Always return success.
        return new Response(['id' => $id], 200);
    }
}
