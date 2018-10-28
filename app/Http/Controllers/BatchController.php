<?php

namespace Ogle\Assessor\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;
use Ogle\Assessor\Batch;
use Ogle\Assessor\Commit;
use Ogle\Assessor\Image;
use Ogle\Assessor\ImageStore;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BatchController extends BaseController
{
    /**
     * @var ImageStore
     */
    protected $ImageStore;

    public function __construct(ImageStore $imageStore)
    {
        $this->imageStore = $imageStore;
    }

    public function create(Request $request)
    {
        $this->validate($request, [
            'sha' => 'required|regex:/[0-9A-F]{40}/',
            // 'variants' => 'array',
        ]);

        $batch = new Batch($request->all());
        $batch->save();

        $commit = Commit::firstOrCreate(['sha' => $request->input('sha')]);

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
        $commit = $batch->commit;

        $this->validate($request, [
            'name' => 'required|string|min:1|max:255',
            'image' => 'required|string',
            // 'metadata' => 'array',
        ]);

        $imageData = base64_decode($request->input('image'), true);

        // Check that the data has a PNG header. Else we can bail out early
        // and avoid sending the data to the image store.
        $pngHeader = chr(137) . chr(80) . chr(78) . chr(71) . chr(13) . chr(10) . chr(26) . chr(10);
        if (!$imageData || substr($imageData, 0, 8) !== $pngHeader) {
            throw new BadRequestHttpException('Bad image data');
        }
        $sha = $this->imageStore->store($imageData);

        $image = $commit->images()->firstOrNew([
            'id' => hash('sha1', $commit->sha . $request->input('name')),
            'name' => $request->input('name'),
        ]);
        $image->image_sha = $sha;
        $image->save();
    }
}
