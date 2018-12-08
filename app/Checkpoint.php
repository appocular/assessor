<?php

namespace Appocular\Assessor;

use Illuminate\Database\Eloquent\Model;

class Checkpoint extends Model
{

    protected $fillable = ['id', 'name', 'snapshot_id', 'image_sha'];
    protected $visible = ['name', 'image_sha'];
    protected $keyType = 'string';

    /**
     * Get the snapshot for the checkpoint.
     */
    public function snapshot()
    {
        return $this->belongsTo('Appocular\Assessor\Snapshot');
    }
}
