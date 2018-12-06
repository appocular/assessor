<?php

namespace Appocular\Assessor;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{

    protected $fillable = ['id', 'name', 'commit_sha', 'image_sha'];
    protected $visible = ['name', 'image_sha'];
    protected $keyType = 'string';

    /**
     * Get the commit for the image.
     */
    public function commit()
    {
        return $this->belongsTo('Appocular\Assessor\Commit');
    }
}
