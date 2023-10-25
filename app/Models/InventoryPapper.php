<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryPapper extends Model
{
    use HasFactory;
    protected $table = 'inventory_pappers';
    protected $fillable = [
        'nomor_urut', 'item_code', 'item_qty', 'item_box', 'checker_id', 'auditor_id', 'created_by',  'updated_by',
        'deleted_at', 'deleted_by', 'confirm_at'
    ];
}
