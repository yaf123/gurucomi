<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GurucomiBase extends Model
{
    use HasFactory;

    // created_at, updated_at への書き込みをしない
    public $timestamps = false;
}
