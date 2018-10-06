<?php

namespace Ogle\Assessor;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{

    protected $fillable = ['id', 'name', 'image_sha'];

    protected $keyType = 'string';

    /**
     * Get the run for the image.
     */
    public function run()
    {
        return $this->belongsTo('Ogle\Assessor\Run');
    }
}
