<?php

namespace Appocular\Assessor;

use Illuminate\Database\Eloquent\Model;

class Commit extends Model
{
    protected $fillable = ['sha'];

    protected $primaryKey = 'sha';
    protected $keyType = 'string';

    /**
     * Get the images for the commit.
     */
    public function images()
    {
        return $this->hasMany('Appocular\Assessor\Image');
    }
}
