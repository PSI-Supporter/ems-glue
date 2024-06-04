<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionInput extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'wo_code',
        'item_code',
        'production_date',
        'shift_code',
        'line_code',
        'process_code',
        'process_seq',
        'input_qty',
    ];
}
