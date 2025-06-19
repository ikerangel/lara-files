<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Master extends Model
{
    protected $fillable = [
        'path', 'master_revision', 'parent_path',
        'part_name', 'extension', 'content_hash',
        'slave_path', 'slave_revision',
        'modified_at',

    ];

    protected $dates = ['modified_at'];
}
