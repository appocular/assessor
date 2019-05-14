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
            'image_kid' => 'required|string|min:1|max:255',
            'baseline_kid' => 'required|string|min:1|max:255',
            'diff_kid' => 'required|string|min:1|max:255',
            'different' => 'required|boolean',
        ]);

        dispatch(new UpdateDiff(
            $request->input('image_kid'),
            $request->input('baseline_kid'),
            $request->input('diff_kid'),
            $request->input('different')
        ));

        // Always return success.
        return new Response();
    }
}
