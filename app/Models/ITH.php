<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ITH extends Model
{
    use HasFactory;
    protected $table = 'ITH_TBL';
    protected $fillable = [
        'ITH_ITMCD', 'ITH_DATE', 'ITH_FORM', 'ITH_DOC', 'ITH_QTY', 'ITH_WH', 'ITH_REMARK',  'ITH_LUPDT', 'ITH_USRID'
    ];
}
