<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_resources', function (Blueprint $table): void {
            $table->index(
                ['visibility_scope', 'status', 'deleted_at', 'created_at'],
                'question_resources_platform_library_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('question_resources', function (Blueprint $table): void {
            $table->dropIndex('question_resources_platform_library_index');
        });
    }
};
