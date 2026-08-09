<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_copies', function (Blueprint $table): void {
            $table->foreignId('student_id')->nullable()->after('school_class_id')
                ->constrained('users')->nullOnDelete();
            $table->uuid('generation_uuid')->nullable()->after('student_id')->index();
            $table->unsignedInteger('exam_version')->default(1)->after('copy_number');
            $table->foreignId('card_template_id')->nullable()->after('exam_version')
                ->constrained('omr_templates')->nullOnDelete();
            $table->unsignedInteger('card_template_version')->nullable()->after('card_template_id');
            $table->string('output_type', 32)->default('both')->after('card_template_version');
            $table->json('template_snapshot')->nullable()->after('output_type');
            $table->json('question_snapshot')->nullable()->after('options_map');

            $table->index(['exam_id', 'exam_version'], 'exam_copy_exam_version_idx');
            $table->index(['exam_id', 'student_id'], 'exam_copy_student_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exam_copies', function (Blueprint $table): void {
            $table->dropIndex('exam_copy_exam_version_idx');
            $table->dropIndex('exam_copy_student_idx');
            $table->dropForeign(['student_id']);
            $table->dropForeign(['card_template_id']);
            $table->dropColumn([
                'student_id',
                'generation_uuid',
                'exam_version',
                'card_template_id',
                'card_template_version',
                'output_type',
                'template_snapshot',
                'question_snapshot',
            ]);
        });
    }
};
