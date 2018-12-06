<?php

namespace Appocular\Assessor\Http\Controllers;

use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;
use Appocular\Assessor\Commit;

class CommitController extends BaseController
{
    public function index($sha)
    {
        $sha = strtolower($sha);

        $commit = Commit::with('images')->findOrFail($sha);

        return (new Response($commit->toJson()));
    }
}
