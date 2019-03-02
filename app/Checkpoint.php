<?php

namespace Appocular\Assessor;

use Appocular\Assessor\Events\CheckpointUpdated;
use Illuminate\Database\Eloquent\Model;

class Checkpoint extends Model
{
    const STATUS_UNKNOWN = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;
    const STATUS_IGNORED = 3;

    protected $fillable = ['id', 'name', 'snapshot_id', 'image_sha'];
    protected $visible = ['id', 'name', 'image_sha'];
    public $incrementing = false;
    protected $keyType = 'string';

    protected $dispatchesEvents = [
        'updated' => CheckpointUpdated::class,
    ];

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

    /**
     * Should this propagate to later snapshots?
     *
     * Unless it's an approved removal, it should.
     */
    public function shouldPropagate() : bool
    {
        return !(empty($this->image_sha) && $this->status == self::STATUS_APPROVED);
    }
}
