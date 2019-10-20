<?php

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Batch;
use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\History;
use Appocular\Assessor\Snapshot;
use Appocular\Clients\Contracts\Keeper;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Laravel\Lumen\Routing\UrlGenerator;
use PDOException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BatchController extends BaseController
{
    /**
     * @var Keeper
     */
    protected $keeper;

    public function __construct(Keeper $keeper)
    {
        $this->keeper = $keeper;
    }

    public function create(Request $request, UrlGenerator $urlGenerator)
    {
        $this->validate($request, [
            'id' => 'required|string|min:1|max:255',
            'history' => 'string',
            // 'variants' => 'array',
        ]);

        $batch = new Batch();
        $snapshot = Snapshot::find($request->input('id'));
        if (!$snapshot) {
            // New snapshot, try to create it, and if that throws an error,
            // try to load it again in case we're in a race condition with
            // another process.
            try {
                $snapshot = Snapshot::create([
                    'id' => $request->input('id'),
                    'repo_id' => $request->user()->uri,
                ]);
            } catch (PDOException $e) {
                $snapshot = Snapshot::findOrFail($request->input('id'));
            }
        }
        // Add history if the snapshot has no baseline.
        if ($request->has('history') && !$snapshot->baselineIdentified()) {
            try {
                History::create([
                    'snapshot_id' => $snapshot->id,
                    'history' => $request->input('history'),
                ]);
            } catch (PDOException $e) {
                // We assume it's because it already exist.
            }
        }
        $batch->snapshot()->associate($snapshot);
        $batch->save();

        Log::info(sprintf('Starting batch %s for snapshot %s', $batch->id, $snapshot->id));

        return (new Response('', 201))->header('Location', $urlGenerator->to('/batch', $batch->id));
    }

    public function delete($batchId, Request $request)
    {
        $batch = Batch::findOrFail($batchId);
        Log::info(sprintf('Ending batch %s for snapshot %s', $batch->id, $batch->snapshot->id));
        $batch->delete();
    }

    public function addCheckpoint(Request $request, UrlGenerator $urlGenerator, $batchId)
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
            Log::error(sprintf(
                'Error saving image for checkpoint "%s" in batch %s',
                $request->input('name'),
                $batch->id
            ));
        }
        $image_url = $this->keeper->store($imageData);

        try {
            $checkpoint = $snapshot->checkpoints()->create([
                'id' => hash('sha256', $snapshot->id . $request->input('name')),
                'name' => $request->input('name'),
                'image_url' => $image_url,
            ]);
        } catch (PDOException $e) {
            $checkpoint = $snapshot->checkpoints()->find(hash('sha256', $snapshot->id . $request->input('name')));
            $checkpoint->image_url = $image_url;
            $checkpoint->save();
        }

        Log::info(sprintf('Added checkpoint "%s" in batch %s', $request->input('name'), $batch->id));

        return (new Response('', 201))->header('Location', $urlGenerator->route('checkpoint.show', ['id' => $checkpoint->id]));
    }
}
