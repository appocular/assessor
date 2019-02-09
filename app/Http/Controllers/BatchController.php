<?php

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Batch;
use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\ImageStore;
use Appocular\Assessor\Snapshot;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BatchController extends BaseController
{
    /**
     * @var ImageStore
     */
    protected $imageStore;

    public function __construct(ImageStore $imageStore)
    {
        $this->imageStore = $imageStore;
    }

    public function create(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|string|min:1|max:255',
            'history' => 'string',
            // 'variants' => 'array',
        ]);

        $batch = new Batch();
        $snapshot = Snapshot::firstOrCreate(['id' => $request->input('id')]);
        // Add history if this is a new snapshot.
        if ($request->has('history') && $snapshot->wasRecentlyCreated) {
            $history = $snapshot->history()->create(['history' => $request->input('history')]);
        }
        $batch->snapshot()->associate($snapshot);
        $batch->save();

        return (new Response(['id' => $batch->id]));
    }

    public function delete($batchId)
    {
        $batch = Batch::findOrFail($batchId);
        $batch->delete();
    }

    public function addCheckpoint(Request $request, $batchId)
    {
        $batch = Batch::findOrFail($batchId);
        $snapshot = $batch->snapshot;

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

        $image = $snapshot->checkpoints()->firstOrNew([
            'id' => hash('sha1', $snapshot->id . $request->input('name')),
            'name' => $request->input('name'),
        ]);
        $image->image_sha = $sha;
        $image->save();
    }
}
