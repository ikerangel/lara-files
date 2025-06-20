<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    protected $fillable = [
        'path','name','file_type','extension','revision','part_name', 'core_name',
        'product_main_type','product_sub_type','parent','parent_path','depth', 'parent',
        'origin','content_hash','size','modified_at',
    ];

    protected $casts = [
        'modified_at' => 'datetime',
    ];
}
