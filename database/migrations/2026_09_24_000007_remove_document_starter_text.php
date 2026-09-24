<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('documents')
            ->where('content', '<p>Start writing here...</p>')
            ->update(['content' => '<p><br></p>']);
    }

    public function down(): void
    {
        DB::table('documents')
            ->where('content', '<p><br></p>')
            ->update(['content' => '<p>Start writing here...</p>']);
    }
};