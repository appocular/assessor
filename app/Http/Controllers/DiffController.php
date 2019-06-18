<?php

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Jobs\UpdateDiff;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;

class DiffController extends BaseController
{
    /**
     * Submit new diff.
     *
     * Fires a DiffSubmitted event to update Checkpoints.
     */
    public function submit(Request $request)
    {
        $this->validate($request, [
            'image_url' => 'required|string|min:1|max:255',
            'baseline_url' => 'required|string|min:1|max:255',
            'diff_url' => 'required|string|min:1|max:255',
            'different' => 'required|boolean',
        ]);

        dispatch(new UpdateDiff(
            $request->input('image_url'),
            $request->input('baseline_url'),
            $request->input('diff_url'),
            $request->input('different')
        ));

        // Always return success.
        return new Response();
    }
}
