<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentShare extends Model
{
    protected $fillable = ['document_id', 'user_id'];
    public function user() { return $this->belongsTo(User::class); }
}