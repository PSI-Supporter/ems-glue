<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sync_xtrf_h extends Model
{
    use HasFactory;
    protected $fillable = [
        'xdocument_number', 'created_by'
    ];
}
