<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    use HasFactory;
    protected $table = 'SER_TBL';
    protected $fillable = [
        'SER_ID', 'SER_DOC', 'SER_ITMID', 'SER_QTY', 'SER_QTYLOT', 'SER_LOTNO', 'SER_REFNO', 'SER_SHEET'        
    ];
}
