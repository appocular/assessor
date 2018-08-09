<?php

namespace Ogle\Assessor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ogle\Assessor\Batch;
use Laravel\Lumen\Routing\Controller as BaseController;

class BatchController extends BaseController
{
    public function create(Request $request)
    {
        $this->validate($request, [
            'sha' => 'required|regex:/[0-9A-F]{40}/',
        ]);

        $batch = new Batch($request->all());
        $batch->save();
        return (new Response(['id' => $batch->id]));
    }

    public function delete($batchId)
    {
        $batch = Batch::findOrFail($batchId);
        $batch->delete();
    }
}
