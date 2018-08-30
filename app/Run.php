<?php

namespace Ogle\Assessor;

use Illuminate\Database\Eloquent\Model;

class Run extends Model
{
    protected $fillable = ['id'];

    protected $keyType = 'string';

    /**
     * Get the images for the run.
     */
    public function images()
    {
        return $this->hasMany('Ogle\Assessor\Image');
    }
}
