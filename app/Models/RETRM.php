<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RETRM extends Model
{
    use HasFactory;
    protected $table = 'RETRM_TBL';
    protected $fillable = [
        'RETRM_DOC', 'RETRM_LINE', 'RETRM_ITMCD', 'RETRM_OLDQTY', 'RETRM_NEWQTY', 'RETRM_LOTNUM', 'RETRM_CREATEDAT', 'RETRM_USRID'
    ];
}
