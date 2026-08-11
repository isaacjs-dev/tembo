<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->json('content_snapshot');
            $table->string('snapshot_hash', 64);
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['lesson_id', 'student_id']);
            $table->index(['organization_id', 'student_id', 'status']);
        });

        Schema::create('activity_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 20)->default('in_progress');
            $table->json('content_snapshot');
            $table->string('snapshot_hash', 64);
            $table->decimal('score', 10, 2)->default(0);
            $table->decimal('total_points', 10, 2)->default(0);
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['activity_id', 'student_id', 'attempt_number'], 'activity_student_attempt_unique');
            $table->index(['organization_id', 'student_id', 'status']);
        });

        Schema::create('activity_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_attempt_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('snapshot_question_key');
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->json('answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_awarded', 10, 2)->default(0);
            $table->text('feedback')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(['activity_attempt_id', 'snapshot_question_key'], 'activity_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_responses');
        Schema::dropIfExists('activity_attempts');
        Schema::dropIfExists('lesson_progress');
    }
};
