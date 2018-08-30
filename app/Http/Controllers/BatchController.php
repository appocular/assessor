<?php

namespace Ogle\Assessor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;
use Ogle\Assessor\Batch;
use Ogle\Assessor\Image;
use Ogle\Assessor\Run;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BatchController extends BaseController
{
    public function create(Request $request)
    {
        $this->validate($request, [
            'sha' => 'required|regex:/[0-9A-F]{40}/',
            // 'variants' => 'array',
        ]);

        $batch = new Batch($request->all());
        $batch->save();

        $run = Run::firstOrCreate(['id' => $request->input('sha')]);

        return (new Response(['id' => $batch->id]));
    }

    public function delete($batchId)
    {
        $batch = Batch::findOrFail($batchId);
        $batch->delete();
    }

    public function addImage(Request $request, $batchId)
    {
        $batch = Batch::findOrFail($batchId);
        $run = $batch->run;

        $this->validate($request, [
            'name' => 'required|string|min:1|max:255',
            'image' => 'required|string',
            // 'metadata' => 'array',
        ]);

        $imageData = base64_decode($request->input('image'), true);

        $pngHeader = chr(137) . chr(80) . chr(78) . chr(71) . chr(13) . chr(10) . chr(26) . chr(10);
        if (!$imageData || substr($imageData, 0, 8) !== $pngHeader) {
            throw new BadRequestHttpException('Bad image data');
        }

        // do something with the image.
        $run->images()->create([
            'id' => hash('sha1', $request->input('name')),
            'name' => $request->input('name'),
        ]);
    }
}
