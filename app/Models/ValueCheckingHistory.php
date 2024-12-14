<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValueCheckingHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'item_code',
        'doc_code',
        'quantity',
        'lot_code',
        'item_value',
        'checking_status',
        'created_by',
        'client_ip',
    ];
}
