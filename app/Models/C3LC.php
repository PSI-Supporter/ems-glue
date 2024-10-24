<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class C3LC extends Model
{
    use HasFactory;
    protected $table = 'C3LC_TBL';
    protected $fillable = [
        'C3LC_ITMCD',
        'C3LC_NLOTNO',
        'C3LC_NQTY',
        'C3LC_LOTNO',
        'C3LC_QTY',
        'C3LC_REFF',
        'C3LC_LINE',
        'C3LC_USRID',
        'C3LC_LUPTD',
        'C3LC_COMID',
        'C3LC_NEWID',
        'C3LC_DOC'
    ];
}
