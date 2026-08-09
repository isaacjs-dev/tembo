<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('source_resource_id')->nullable()->constrained('question_resources')->nullOnDelete();
            $table->string('title', 180);
            $table->string('type', 40);
            $table->string('visibility_scope', 40)->default('private');
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'owner_id']);
            $table->index(['organization_id', 'visibility_scope', 'status'], 'question_resources_visibility_index');
        });

        Schema::create('question_resource_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('content');
            $table->char('content_hash', 64);
            $table->string('storage_disk', 40)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['question_resource_id', 'version_number'], 'question_resource_versions_number_unique');
            $table->index(['question_resource_id', 'content_hash']);
        });

        Schema::create('question_resource_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_with_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['question_resource_id', 'shared_with_user_id'], 'question_resource_shares_user_unique');
        });

        Schema::create('question_question_resource', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_resource_id')->constrained()->restrictOnDelete();
            $table->foreignId('question_resource_version_id')->constrained()->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['question_id', 'question_resource_id'], 'question_resource_question_unique');
            $table->index(['question_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_question_resource');
        Schema::dropIfExists('question_resource_shares');
        Schema::dropIfExists('question_resource_versions');
        Schema::dropIfExists('question_resources');
    }
};
