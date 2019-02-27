<?php

namespace Appocular\Assessor;

use Illuminate\Database\Eloquent\Model;

class Checkpoint extends Model
{

    protected $fillable = ['id', 'name', 'snapshot_id', 'image_sha'];
    protected $visible = ['id', 'name', 'image_sha'];
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Get the snapshot for the checkpoint.
     */
    public function snapshot()
    {
        return $this->belongsTo('Appocular\Assessor\Snapshot');
    }

    /**
     * Reset Checkpoint baselines for snapshot.
     *
     * Deletes checkpoints without an image (they're placeholders for deleted
     * files) and sets baseline for the rest to null.
     *
     * @param string $snapshotId
     *   Id of the snapshot to reset for.
     */
    public static function resetBaseline(string $snapshotId) : void
    {
        $checkpoint = new static();
        $checkpoint->newModelQuery()
            ->where([['snapshot_id', $snapshotId], ['image_sha', null]])
            ->delete();
        $checkpoint->newModelQuery()
            ->where('snapshot_id', $snapshotId)
            ->update(['baseline_sha' => null]);
    }
}
