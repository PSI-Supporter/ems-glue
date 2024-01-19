<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class transfer_indirect_rm_header extends Model
{
    use HasFactory;
    protected $fillable = [
        'created_by', 'deleted_at', 'deleted_by', 'doc_code', 'doc_order',
        'issue_date', 'location_from', 'location_to', 'updated_by'
    ];
}
