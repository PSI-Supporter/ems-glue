<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartReturned extends Model
{
    use HasFactory;
    protected $table = 'RETSCN_TBL';
    protected $fillable = [
        'RETSCN_ID', 'RETSCN_SPLDOC', 'RETSCN_CAT', 'RETSCN_LINE', 'RETSCN_FEDR', 'RETSCN_ORDERNO', 'RETSCN_ITMCD', 'RETSCN_LOT', 'RETSCN_QTYBEF', 'RETSCN_QTYAFT', 'RETSCN_CNTRYID', 'RETSCN_ROHS', 'RETSCN_LUPDT', 'RETSCN_USRID'
    ];
    public $timestamps = false;
}
