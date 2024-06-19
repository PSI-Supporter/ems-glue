<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SPLSCN_LOG extends Model
{
    use HasFactory;
    protected $table = 'SPLSCN_LOG';
    protected $fillable = [
        'SPLSCN_ID', 'SPLSCN_DATATYPE', 'SPLSCN_OLDQTY', 'SPLSCN_NEWQTY', 'created_by', 'updated_by', 'deleted_by',  'deleted_at'
    ];
}
