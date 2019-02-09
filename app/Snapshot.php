<?php

namespace Appocular\Assessor;

use Illuminate\Database\Eloquent\Model;

class Snapshot extends Model
{
    protected $fillable = ['id'];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $visible = ['id', 'checkpoints'];

    /**
     * Get the checkpoints for the snapshot.
     */
    public function checkpoints()
    {
        return $this->hasMany('Appocular\Assessor\Checkpoint');
    }

    /**
     * Get the history for the snapshot.
     */
    public function history()
    {
        return $this->hasOne('Appocular\Assessor\History');
    }
}
