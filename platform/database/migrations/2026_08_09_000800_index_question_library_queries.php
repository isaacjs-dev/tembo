<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->index(
                ['organization_id', 'owner_id', 'deleted_at'],
                'questions_library_owner_index',
            );
            $table->index(
                ['organization_id', 'visibility_scope', 'deleted_at', 'created_at'],
                'questions_library_organization_index',
            );
            $table->index(
                ['visibility_scope', 'deleted_at', 'created_at'],
                'questions_library_platform_index',
            );
        });

        Schema::table('question_shares', function (Blueprint $table): void {
            $table->index(
                ['shared_with_user_id', 'question_id'],
                'question_shares_recipient_index',
            );
        });

        Schema::table('question_resource_shares', function (Blueprint $table): void {
            $table->index(
                ['shared_with_user_id', 'question_resource_id'],
                'question_resource_shares_recipient_index',
            );
        });

    }

    public function down(): void
    {
        Schema::table('question_resource_shares', function (Blueprint $table): void {
            $table->dropIndex('question_resource_shares_recipient_index');
        });

        Schema::table('question_shares', function (Blueprint $table): void {
            $table->dropIndex('question_shares_recipient_index');
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropIndex('questions_library_owner_index');
            $table->dropIndex('questions_library_organization_index');
            $table->dropIndex('questions_library_platform_index');
        });
    }
};
