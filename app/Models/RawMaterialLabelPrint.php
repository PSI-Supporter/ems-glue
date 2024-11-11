<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterialLabelPrint extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'item_code',
        'doc_code',
        'parent_code',
        'quantity',
        'lot_code',  
        'created_by',    
        'action',
        'pc_name',
    ];
}
