<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    protected $fillable = [
        'path', 'part_name', 'core_name', 'parent',
        'master_path', 'parent_path', 'extension',
        'master_revision', 'slave_path', 'slave_revision',
        'content_hash', 'content_as_master', 'modified_at',
    ];
}
