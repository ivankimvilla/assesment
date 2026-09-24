<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = ['owner_id', 'title', 'content', 'paper_size', 'source_file_path', 'source_file_name', 'access_mode', 'share_token'];

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function shares() { return $this->hasMany(DocumentShare::class); }
}