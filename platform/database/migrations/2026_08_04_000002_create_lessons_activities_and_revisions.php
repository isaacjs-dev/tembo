<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('discipline_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('objectives')->nullable();
            $table->longText('content')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('generate_review')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'author_id', 'status']);
        });
        Schema::create('lesson_school_class', function (Blueprint $table): void {
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['lesson_id', 'school_class_id']);
        });

        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('discipline_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->longText('instructions')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->unsignedInteger('max_attempts')->default(1);
            $table->decimal('points', 10, 2)->default(0);
            $table->string('modality', 20)->default('online');
            $table->string('status', 30)->default('draft');
            $table->boolean('generate_review')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'author_id', 'status']);
        });
        Schema::create('activity_school_class', function (Blueprint $table): void {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['activity_id', 'school_class_id']);
        });
        Schema::create('activity_question', function (Blueprint $table): void {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->decimal('points', 8, 2)->default(1);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->primary(['activity_id', 'question_id']);
        });

        Schema::create('revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('discipline_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('is_required')->default(false);
            $table->string('timing', 20)->default('after');
            $table->boolean('block_exam')->default(false);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->unsignedInteger('max_attempts')->default(1);
            $table->string('feedback_mode', 20)->default('end');
            $table->boolean('gamification_enabled')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'author_id', 'status']);
        });
        Schema::create('revision_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained()->cascadeOnDelete();
            $table->morphs('source');
            $table->timestamps();
            $table->unique(['revision_id', 'source_type', 'source_id'], 'revision_source_unique');
        });
        Schema::create('revision_school_class', function (Blueprint $table): void {
            $table->foreignId('revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['revision_id', 'school_class_id']);
        });
        Schema::create('revision_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_skill_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bncc_node_id')->nullable()->constrained('bncc_nodes')->nullOnDelete();
            $table->string('type', 40);
            $table->unsignedInteger('order')->default(0);
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->text('prompt');
            $table->json('content')->nullable();
            $table->json('solution')->nullable();
            $table->text('explanation')->nullable();
            $table->json('hints')->nullable();
            $table->decimal('points', 8, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['revision_id', 'is_active', 'order']);
        });
        Schema::create('revision_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('schema_version');
            $table->json('payload');
            $table->unsignedInteger('items_imported')->default(0);
            $table->string('status', 30);
            $table->json('validation_errors')->nullable();
            $table->timestamps();
        });
        Schema::create('revision_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 30)->default('in_progress');
            $table->unsignedInteger('current_position')->default(0);
            $table->decimal('score', 10, 2)->default(0);
            $table->decimal('total_points', 10, 2)->default(0);
            $table->unsignedInteger('xp_earned')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['revision_id', 'student_id', 'attempt_number'], 'revision_student_attempt_unique');
        });
        Schema::create('revision_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revision_item_id')->nullable()->constrained()->nullOnDelete();
            $table->json('answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_awarded', 8, 2)->default(0);
            $table->unsignedInteger('response_time_seconds')->nullable();
            $table->json('item_snapshot');
            $table->text('feedback')->nullable();
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->unique(['revision_attempt_id', 'revision_item_id'], 'revision_attempt_item_unique');
        });

        Schema::create('student_gamification_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('xp')->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_study_date')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'student_id']);
        });
        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('icon', 80)->default('workspace_premium');
            $table->json('criteria');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });
        Schema::create('student_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revision_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('awarded_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'badge_id']);
        });
    }

    public function down(): void
    {
        foreach (['student_badges', 'badges', 'student_gamification_profiles', 'revision_responses', 'revision_attempts', 'revision_imports', 'revision_items', 'revision_school_class', 'revision_sources', 'revisions', 'activity_question', 'activity_school_class', 'activities', 'lesson_school_class', 'lessons'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
