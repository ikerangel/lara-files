<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Master extends Model
{
    protected $fillable = [
        'path', 'revision', 'parent_path',
        'content_hash', 'slave_path', 'modified_at',
        'part_name', 'extension',
    ];

    protected $dates = ['modified_at'];
}
