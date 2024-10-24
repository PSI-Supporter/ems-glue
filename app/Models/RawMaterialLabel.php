<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterialLabel extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'item_code',
        'doc_code',
        'parent_code',
        'quantity',
        'lot_code',
        'splitted',
        'combined',
        'composed',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
        'item_value',
    ];
}
