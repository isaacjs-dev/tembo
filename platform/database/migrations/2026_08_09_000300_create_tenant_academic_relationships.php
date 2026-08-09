<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('class_student')
            && ! Schema::hasIndex('class_student', 'class_student_class_user_unique')) {
            $hasDuplicates = DB::table('class_student')
                ->select('school_class_id', 'user_id')
                ->groupBy('school_class_id', 'user_id')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($hasDuplicates) {
                throw new RuntimeException(
                    'class_student possui matrículas duplicadas. Consolide-as de forma auditada antes desta migration.'
                );
            }

            Schema::table('class_student', function (Blueprint $table): void {
                $table->unique(['school_class_id', 'user_id'], 'class_student_class_user_unique');
            });
        }

        if (! Schema::hasTable('teacher_student')) {
            Schema::create('teacher_student', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(
                    ['organization_id', 'teacher_id', 'student_id'],
                    'teacher_student_org_teacher_student_unique'
                );
                $table->index(['organization_id', 'student_id'], 'teacher_student_org_student_index');
            });
        }

        if (! Schema::hasTable('discipline_teacher')) {
            Schema::create('discipline_teacher', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->useCurrent();
                $table->timestamps();
                $table->unique(
                    ['organization_id', 'discipline_id', 'user_id'],
                    'discipline_teacher_org_discipline_user_unique'
                );
                $table->index(['organization_id', 'user_id'], 'discipline_teacher_org_user_index');
            });
        }

        if (! Schema::hasTable('class_discipline')) {
            Schema::create('class_discipline', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
                $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(
                    ['organization_id', 'school_class_id', 'discipline_id'],
                    'class_discipline_org_class_discipline_unique'
                );
                $table->index(['organization_id', 'discipline_id'], 'class_discipline_org_discipline_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_discipline');
        Schema::dropIfExists('discipline_teacher');
        Schema::dropIfExists('teacher_student');

        if (Schema::hasTable('class_student')
            && Schema::hasIndex('class_student', 'class_student_class_user_unique')) {
            Schema::table('class_student', function (Blueprint $table): void {
                $table->dropUnique('class_student_class_user_unique');
            });
        }
    }
};
