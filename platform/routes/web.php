<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CourtesyController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\UsageAdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\BNCcController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\CustomSkillController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\GuardianPortalController;
use App\Http\Controllers\Institution\BillingController;
use App\Http\Controllers\Institution\GuardianLinkController;
use App\Http\Controllers\Institution\InviteController;
use App\Http\Controllers\Institution\LogController;
use App\Http\Controllers\Institution\TrashController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\InstitutionRoleController;
use App\Http\Controllers\LearningMaterialController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\OmrController;
use App\Http\Controllers\OmrTemplateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\Settings\PrintPreferencesController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentLearningController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\StudentRevisionController;
use App\Http\Controllers\TaxonomyController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\WorkspaceController;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

Route::get('/storage/{path}', PublicStorageController::class)
    ->where('path', '.*')
    ->name('storage.public');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    $plans = Plan::visibleOnHome()->with(['planLimits', 'planFeatures'])->get();

    return view('welcome', compact('plans'));
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'workspace_context', 'active_account', 'verified_account', 'password_changed'])->group(function () {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces/personal', [WorkspaceController::class, 'storePersonal'])->name('workspaces.personal.store');
    Route::post('/workspaces/{organization}', [WorkspaceController::class, 'select'])->name('workspaces.switch');

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::prefix('student')->name('student.')->middleware('workspace_role:student')->group(function () {
        Route::get('/dashboard', [StudentPortalController::class, 'index'])->name('dashboard');
        Route::get('/learning', [StudentLearningController::class, 'index'])->name('learning.index');
        Route::get('/learning/{learningMaterial}', [StudentLearningController::class, 'show'])->name('learning.show');
        Route::post('/learning/{learningMaterial}/complete', [StudentLearningController::class, 'complete'])
            ->middleware('throttle:20,1')
            ->name('learning.complete');
        Route::post('/join', [StudentPortalController::class, 'joinByCode'])
            ->middleware('throttle:10,1')
            ->name('joinByCode');
        Route::get('/exam/{exam}', [StudentPortalController::class, 'show'])->name('exam.show');
        Route::post('/exam/{exam}/start', [StudentPortalController::class, 'start'])
            ->middleware('throttle:30,1')
            ->name('exam.start');
        Route::get('/exam/{exam}/execute', [StudentPortalController::class, 'execution'])->name('exam.execution');
        Route::patch('/exam/{exam}/autosave', [StudentPortalController::class, 'autosave'])
            ->middleware('throttle:120,1')
            ->name('exam.autosave');
        Route::post('/exam/{exam}/submit', [StudentPortalController::class, 'submit'])
            ->middleware('throttle:30,1')
            ->name('exam.submit');
        Route::get('/exam/{exam}/results', [StudentPortalController::class, 'results'])->name('exam.results');
        Route::get('/revisions', [StudentRevisionController::class, 'index'])->name('revisions.index');
        Route::get('/revisions/{revision}', [StudentRevisionController::class, 'show'])->name('revisions.show');
        Route::post('/revisions/{revision}/start', [StudentRevisionController::class, 'start'])->middleware('throttle:30,1')->name('revisions.start');
        Route::get('/revisions/{revision}/attempts/{attempt}', [StudentRevisionController::class, 'execute'])->name('revisions.execute');
        Route::post('/revisions/{revision}/attempts/{attempt}/items/{item}', [StudentRevisionController::class, 'answer'])->middleware('throttle:120,1')->name('revisions.answer');
        Route::post('/revisions/{revision}/attempts/{attempt}/complete', [StudentRevisionController::class, 'complete'])->middleware('throttle:30,1')->name('revisions.complete');
        Route::get('/revisions/{revision}/attempts/{attempt}/result', [StudentRevisionController::class, 'result'])->name('revisions.result');
    });

    Route::prefix('guardian')->name('guardian.')->middleware('workspace_role:guardian')->group(function () {
        Route::get('/dashboard', [GuardianPortalController::class, 'index'])
            ->name('dashboard');
        Route::get('/students/{student}', [GuardianPortalController::class, 'show'])
            ->name('students.show');
    });

    Route::prefix('institution')->name('institution.')->middleware('workspace_role:admin,institution_admin,teacher,global_admin')->group(function () {
        Route::get('/dashboard', [InstitutionController::class, 'dashboard'])->name('dashboard');

        Route::middleware('workspace_role:admin,institution_admin,global_admin')->group(function () {
            Route::get('/settings', [InstitutionController::class, 'settings'])->name('settings');
            Route::put('/settings', [InstitutionController::class, 'updateSettings'])->name('settings.update');

            Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
            Route::post('billing/change-plan', [BillingController::class, 'changePlan'])->name('billing.changePlan');
            Route::post('billing/cancel', [BillingController::class, 'cancelPlan'])->name('billing.cancelPlan');

            // Convites (gestão institucional)
            Route::resource('invites', InviteController::class)->only(['index', 'create', 'store', 'destroy']);

            Route::get('teachers/{teacher}/permissions', [TeacherController::class, 'permissions'])->name('teachers.permissions');
            Route::put('teachers/{teacher}/permissions', [TeacherController::class, 'updatePermissions'])->name('teachers.permissions.update');
            Route::post('teachers/search', [TeacherController::class, 'search'])->name('teachers.search');

            Route::resource('teachers', TeacherController::class);

            Route::post('students/search', [StudentController::class, 'search'])->name('students.search');
            Route::resource('students', StudentController::class);

            Route::get('guardians', [GuardianLinkController::class, 'index'])
                ->name('guardians.index');
            Route::post('guardians', [GuardianLinkController::class, 'store'])
                ->name('guardians.store');
            Route::delete(
                'guardians/{guardianLink}',
                [GuardianLinkController::class, 'destroy']
            )->name('guardians.destroy');

            Route::resource('roles', InstitutionRoleController::class);
            Route::post('roles/assign', [InstitutionRoleController::class, 'assign'])->name('roles.assign');
        });

        Route::resource('classes', SchoolClassController::class);
        Route::post('classes/{class}/enroll', [SchoolClassController::class, 'enroll'])->name('classes.enroll');
        Route::post('classes/{class}/transfer', [SchoolClassController::class, 'initiateTransfer'])->name('classes.transfer');
        Route::post('classes/{class}/transfer/cancel', [SchoolClassController::class, 'cancelTransfer'])->name('classes.transfer.cancel');

        Route::get('reports', [ReportController::class, 'index'])->name('reports');

        Route::middleware('restrict_trash')->group(function () {
            Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
            Route::post('trash/restore', [TrashController::class, 'restore'])->name('trash.restore');
            Route::post('trash/force-delete', [TrashController::class, 'forceDelete'])->name('trash.forceDelete');
        });

        Route::get('logs', [LogController::class, 'index'])
            ->middleware('restrict_logs')
            ->name('logs');

        // Taxonomy AJAX
        Route::post('knowledge-areas', [TaxonomyController::class, 'storeKnowledgeArea'])->name('knowledge-areas.store');
        Route::post('disciplines', [TaxonomyController::class, 'storeDiscipline'])->name('disciplines.store');

        // BNCC AJAX
        Route::get('bncc/schema', [BNCcController::class, 'schema'])->name('bncc.schema');
        Route::get('bncc/nodes', [BNCcController::class, 'nodes'])->name('bncc.nodes');
        Route::get('bncc/search', [BNCcController::class, 'search'])->name('bncc.search');

        // Custom Skills AJAX
        Route::get('custom-skills/search', [CustomSkillController::class, 'search'])->name('custom-skills.search');
        Route::post('custom-skills/store', [CustomSkillController::class, 'store'])->name('custom-skills.store');

        // OMR
        Route::prefix('omr')->name('omr.')->middleware('omr_api:permission-only')->group(function () {
            Route::get('/', [OmrController::class, 'index'])->name('index');
            Route::post('/batch-update', [OmrController::class, 'batchUpdate'])->name('batchUpdate');
            Route::get('/reports', [OmrController::class, 'reports'])->name('reports');
            Route::get('/upload', [OmrController::class, 'create'])->name('create');
            Route::post('/upload', [OmrController::class, 'store'])->name('store');
            Route::get('/{scan}/image/{variant?}', [OmrController::class, 'image'])
                ->where('variant', 'original|warped|debug')
                ->name('image');
            Route::get('/{scan}/pages/{page}/image', [OmrController::class, 'pageImage'])->name('pages.image');
            Route::get('/{scan}/review', [OmrController::class, 'review'])->name('review');
            Route::post('/{scan}/confirm', [OmrController::class, 'confirm'])->name('confirm');
            Route::post('/{scan}/reject', [OmrController::class, 'reject'])->name('reject');
            Route::post('/{scan}/update-local', [OmrController::class, 'updateLocal'])->name('updateLocal');
            Route::get('/webscan', [OmrController::class, 'webscan'])->name('webscan');
            Route::get('/debug', [OmrController::class, 'debug'])->name('debug');

            // Templates CRUD
            Route::prefix('templates')->name('templates.')->group(function () {
                Route::get('/', [OmrTemplateController::class, 'index'])->name('index');
                Route::get('/create', [OmrTemplateController::class, 'create'])->name('create');
                Route::post('/', [OmrTemplateController::class, 'store'])->name('store');
                Route::get('/{template}/edit', [OmrTemplateController::class, 'edit'])->name('edit');
                Route::put('/{template}', [OmrTemplateController::class, 'update'])->name('update');
                Route::delete('/{template}', [OmrTemplateController::class, 'destroy'])->name('destroy');
                Route::get('/{template}/export', [OmrTemplateController::class, 'exportJson'])->name('export');
                Route::post('/{template}/generate-rois', [OmrTemplateController::class, 'generateRois'])->name('generateRois');
            });
        });
    });

    Route::middleware('workspace_role:admin,institution_admin,teacher,global_admin')->group(function () {
        Route::resource('lessons', LessonController::class)->except('show');
        Route::resource('activities', ActivityController::class)->except('show');
        Route::post('revisions/{revision}/items', [RevisionController::class, 'storeItem'])->name('revisions.items.store');
        Route::put('revisions/{revision}/items/{item}', [RevisionController::class, 'updateItem'])->name('revisions.items.update');
        Route::delete('revisions/{revision}/items/{item}', [RevisionController::class, 'destroyItem'])->name('revisions.items.destroy');
        Route::post('revisions/{revision}/items/reorder', [RevisionController::class, 'reorder'])->name('revisions.items.reorder');
        Route::match(['get', 'post'], 'revisions/{revision}/prompt', [RevisionController::class, 'prompt'])->name('revisions.prompt');
        Route::post('revisions/{revision}/import', [RevisionController::class, 'import'])->name('revisions.import');
        Route::post('revisions/{revision}/status', [RevisionController::class, 'status'])->name('revisions.status');
        Route::get('revisions/{revision}/report', [RevisionController::class, 'report'])->name('revisions.report');
        Route::resource('revisions', RevisionController::class)->except('show');
        Route::get('questions/{question}/share', [QuestionController::class, 'share'])->name('questions.share');
        Route::post('questions/{question}/share', [QuestionController::class, 'storeShare'])->name('questions.storeShare');
        Route::post('questions/{question}/duplicate', [QuestionController::class, 'duplicate'])->name('questions.duplicate');
        Route::resource('questions', QuestionController::class);

        Route::resource('exams', ExamController::class);
        Route::resource('learning-materials', LearningMaterialController::class)
            ->parameters(['learning-materials' => 'learningMaterial'])
            ->except(['show']);
        Route::post('exams/{exam}/questions', [ExamController::class, 'addQuestion'])->name('exams.addQuestion');
        Route::delete('exams/{exam}/questions/{question}', [ExamController::class, 'removeQuestion'])->name('exams.removeQuestion');
        Route::post('exams/{exam}/classes', [ExamController::class, 'syncClasses'])->name('exams.syncClasses');
        Route::get('exams/{exam}/submissions/{submission}/grade', [ExamController::class, 'gradeSubmission'])->name('exams.gradeSubmission');
        Route::post('exams/{exam}/submissions/{submission}/grade', [ExamController::class, 'storeGrade'])->name('exams.storeGrade');
        Route::get('exams/{exam}/export', [ExamController::class, 'exportPdf'])->name('exams.exportPdf');
        Route::post('exams/{exam}/print-advanced', [ExamController::class, 'printAdvanced'])->name('exams.printAdvanced');
        Route::post('exams/{exam}/export-answer-sheet', [ExamController::class, 'exportAnswerSheet'])->name('exams.exportAnswerSheet');
        Route::post('exams/{exam}/duplicate', [ExamController::class, 'duplicate'])->name('exams.duplicate');
        Route::get('exams/{exam}/questions/search', [ExamController::class, 'searchQuestions'])->name('exams.searchQuestions');
        Route::post('exams/{exam}/questions/bulk', [ExamController::class, 'addQuestions'])->name('exams.addQuestions');
        Route::post('exams/{exam}/questions/reorder', [ExamController::class, 'reorderQuestions'])->name('exams.reorderQuestions');
        Route::put('exams/{exam}/questions/{question}/points', [ExamController::class, 'updateQuestionPoints'])->name('exams.updateQuestionPoints');
    });

    // Convites recebidos (qualquer autenticado)
    Route::get('/invites/received', [InviteController::class, 'received'])->name('institution.invites.received');
    Route::post('/invites/{token}/accept', [InviteController::class, 'accept'])->name('institution.invites.accept.token');
    Route::post('/invites/{token}/decline', [InviteController::class, 'decline'])->name('institution.invites.decline.token');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/settings/print-preferences', [PrintPreferencesController::class, 'edit'])->name('settings.print');
    Route::put('/settings/print-preferences', [PrintPreferencesController::class, 'update'])->name('settings.print.update');

    Route::prefix('admin')->name('admin.')->middleware('role:global_admin')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::resource('plans', PlanController::class);
        Route::resource('users', UserController::class);
        Route::get('usage', [UsageAdminController::class, 'index'])->name('usage.index');
        Route::post('usage/preview', [UsageAdminController::class, 'preview'])->name('usage.preview');
        Route::post('usage/reset', [UsageAdminController::class, 'reset'])->name('usage.reset');
        Route::post('courtesies/{courtesy}/suspend', [CourtesyController::class, 'suspend'])->name('courtesies.suspend');
        Route::post('courtesies/{courtesy}/activate', [CourtesyController::class, 'activate'])->name('courtesies.activate');
        Route::post('courtesies/{courtesy}/cancel', [CourtesyController::class, 'cancel'])->name('courtesies.cancel');
        Route::resource('courtesies', CourtesyController::class)->except(['show', 'destroy']);

        Route::get('logs', [App\Http\Controllers\Admin\LogController::class, 'index'])->name('logs');

        Route::get('trash', [App\Http\Controllers\Admin\TrashController::class, 'index'])->name('trash.index');
        Route::post('trash/restore', [App\Http\Controllers\Admin\TrashController::class, 'restore'])->name('trash.restore');
        Route::post('trash/force-delete', [App\Http\Controllers\Admin\TrashController::class, 'forceDelete'])->name('trash.forceDelete');

        // OMR Configuration Management
        Route::prefix('config')->name('config.')->group(function () {
            Route::get('/', [ConfigController::class, 'index'])->name('index');
            Route::get('/audit', [ConfigController::class, 'audit'])->name('audit');
            Route::get('/simulate/{userId}', [ConfigController::class, 'simulate'])->name('simulate');
            Route::post('/rules', [ConfigController::class, 'storeRule'])->name('rules.store');
            Route::put('/rules/{id}', [ConfigController::class, 'updateRule'])->name('rules.update');
            Route::delete('/rules/{id}', [ConfigController::class, 'destroyRule'])->name('rules.destroy');
        });
    });
});
