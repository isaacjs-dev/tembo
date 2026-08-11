<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CourtesyController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PublicCatalogModerationController;
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
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuestionResourceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\Settings\PrintPreferencesController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentLearningController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\StudentPedagogicalController;
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
        Route::get('/pedagogical', [StudentPedagogicalController::class, 'index'])->name('pedagogical.index');
        Route::get('/lessons/{lesson}', [StudentPedagogicalController::class, 'lesson'])->name('pedagogical.lessons.show');
        Route::post('/lessons/{lesson}/complete', [StudentPedagogicalController::class, 'completeLesson'])
            ->middleware('throttle:20,1')->name('pedagogical.lessons.complete');
        Route::get('/activities/{activity}', [StudentPedagogicalController::class, 'activity'])->name('pedagogical.activities.show');
        Route::post('/activities/{activity}/start', [StudentPedagogicalController::class, 'startActivity'])
            ->middleware('throttle:30,1')->name('pedagogical.activities.start');
        Route::get('/activities/{activity}/attempts/{attempt}', [StudentPedagogicalController::class, 'executeActivity'])
            ->name('pedagogical.activities.execute');
        Route::post('/activities/{activity}/attempts/{attempt}/save', [StudentPedagogicalController::class, 'saveActivity'])
            ->middleware('throttle:120,1')->name('pedagogical.activities.save');
        Route::post('/activities/{activity}/attempts/{attempt}/submit', [StudentPedagogicalController::class, 'submitActivity'])
            ->middleware('throttle:30,1')->name('pedagogical.activities.submit');
        Route::get('/activities/{activity}/attempts/{attempt}/result', [StudentPedagogicalController::class, 'activityResult'])
            ->name('pedagogical.activities.result');
        Route::get('/activities/{activity}/attempts/{attempt}/questions/{question}/resources/{version}', [StudentPedagogicalController::class, 'activityResource'])
            ->name('pedagogical.activities.resource');
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
        Route::get('/exam/{exam}/resources/{version}', [StudentPortalController::class, 'resource'])
            ->name('exam.resource');
        Route::get('/revisions', [StudentRevisionController::class, 'index'])->name('revisions.index');
        Route::get('/revisions/{revision}', [StudentRevisionController::class, 'show'])->name('revisions.show');
        Route::post('/revisions/{revision}/start', [StudentRevisionController::class, 'start'])->middleware('throttle:30,1')->name('revisions.start');
        Route::get('/revisions/{revision}/attempts/{attempt}', [StudentRevisionController::class, 'execute'])->name('revisions.execute');
        Route::get('/revisions/{revision}/attempts/{attempt}/items/{item}/resources/{version}', [StudentRevisionController::class, 'resource'])
            ->name('revisions.resource');
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

    Route::prefix('institution')->name('institution.')->middleware('workspace_role:admin,institution_admin,director,coordinator,pedagogue,teacher,global_admin')->group(function () {
        Route::get('/dashboard', [InstitutionController::class, 'dashboard'])->name('dashboard');

        Route::middleware('workspace_role:admin,institution_admin,global_admin')->group(function () {
            Route::get('/settings', [InstitutionController::class, 'settings'])->name('settings');
            Route::put('/settings', [InstitutionController::class, 'updateSettings'])->name('settings.update');

            Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
            Route::post('billing/change-plan', [BillingController::class, 'changePlan'])->name('billing.changePlan');
            Route::post('billing/cancel', [BillingController::class, 'cancelPlan'])->name('billing.cancelPlan');

            Route::resource('roles', InstitutionRoleController::class);
            Route::post('roles/assign', [InstitutionRoleController::class, 'assign'])->name('roles.assign');
        });

        Route::get('invites', [InviteController::class, 'index'])
            ->middleware('inst_perm:view_invites')->name('invites.index');
        Route::get('invites/create', [InviteController::class, 'create'])
            ->middleware('inst_perm:manage_invites')->name('invites.create');
        Route::post('invites', [InviteController::class, 'store'])
            ->middleware('inst_perm:manage_invites')->name('invites.store');
        Route::delete('invites/{invite}', [InviteController::class, 'destroy'])
            ->middleware('inst_perm:manage_invites')->name('invites.destroy');

        Route::get('teachers', [TeacherController::class, 'index'])
            ->middleware('inst_perm:view_teachers')->name('teachers.index');
        Route::post('teachers/search', [TeacherController::class, 'search'])
            ->middleware('inst_perm:view_teachers')->name('teachers.search');
        Route::get('teachers/create', [TeacherController::class, 'create'])
            ->middleware('inst_perm:manage_teachers')->name('teachers.create');
        Route::post('teachers', [TeacherController::class, 'store'])
            ->middleware('inst_perm:manage_teachers')->name('teachers.store');
        Route::get('teachers/{teacher}/edit', [TeacherController::class, 'edit'])
            ->middleware('inst_perm:manage_teachers')->name('teachers.edit');
        Route::put('teachers/{teacher}', [TeacherController::class, 'update'])
            ->middleware('inst_perm:manage_teachers')->name('teachers.update');
        Route::delete('teachers/{teacher}', [TeacherController::class, 'destroy'])
            ->middleware('inst_perm:manage_teachers')->name('teachers.destroy');
        Route::get('teachers/{teacher}/permissions', [TeacherController::class, 'permissions'])
            ->middleware('workspace_role:admin,institution_admin,global_admin')->name('teachers.permissions');
        Route::put('teachers/{teacher}/permissions', [TeacherController::class, 'updatePermissions'])
            ->middleware('workspace_role:admin,institution_admin,global_admin')->name('teachers.permissions.update');

        Route::get('students', [StudentController::class, 'index'])
            ->middleware('inst_perm:view_students')->name('students.index');
        Route::post('students/search', [StudentController::class, 'search'])
            ->middleware('inst_perm:view_students')->name('students.search');
        Route::get('students/create', [StudentController::class, 'create'])
            ->middleware('inst_perm:manage_students')->name('students.create');
        Route::post('students', [StudentController::class, 'store'])
            ->middleware('inst_perm:manage_students')->name('students.store');
        Route::get('students/{student}/edit', [StudentController::class, 'edit'])
            ->middleware('inst_perm:manage_students')->name('students.edit');
        Route::put('students/{student}', [StudentController::class, 'update'])
            ->middleware('inst_perm:manage_students')->name('students.update');
        Route::delete('students/{student}', [StudentController::class, 'destroy'])
            ->middleware('inst_perm:manage_students')->name('students.destroy');

        Route::get('guardians', [GuardianLinkController::class, 'index'])
            ->middleware('inst_perm:view_students')->name('guardians.index');
        Route::post('guardians', [GuardianLinkController::class, 'store'])
            ->middleware('inst_perm:manage_students')->name('guardians.store');
        Route::delete('guardians/{guardianLink}', [GuardianLinkController::class, 'destroy'])
            ->middleware('inst_perm:manage_students')->name('guardians.destroy');

        Route::resource('classes', SchoolClassController::class);
        Route::post('classes/{class}/enroll', [SchoolClassController::class, 'enroll'])->name('classes.enroll');
        Route::post('classes/{class}/transfer', [SchoolClassController::class, 'initiateTransfer'])->name('classes.transfer');
        Route::post('classes/{class}/transfer/cancel', [SchoolClassController::class, 'cancelTransfer'])->name('classes.transfer.cancel');

        Route::get('reports', [ReportController::class, 'index'])
            ->middleware('inst_perm:view_reports')->name('reports');

        Route::middleware('restrict_trash')->group(function () {
            Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
            Route::post('trash/restore', [TrashController::class, 'restore'])->name('trash.restore');
            Route::post('trash/force-delete', [TrashController::class, 'forceDelete'])->name('trash.forceDelete');
        });

        Route::get('logs', [LogController::class, 'index'])
            ->middleware('restrict_logs')
            ->name('logs');

        // Taxonomy AJAX
        Route::post('knowledge-areas', [TaxonomyController::class, 'storeKnowledgeArea'])
            ->middleware('inst_perm:manage_questions')->name('knowledge-areas.store');
        Route::post('disciplines', [TaxonomyController::class, 'storeDiscipline'])
            ->middleware('inst_perm:manage_questions')->name('disciplines.store');

        // BNCC AJAX
        Route::get('bncc/schema', [BNCcController::class, 'schema'])
            ->middleware('inst_perm:view_questions')->name('bncc.schema');
        Route::get('bncc/nodes', [BNCcController::class, 'nodes'])
            ->middleware('inst_perm:view_questions')->name('bncc.nodes');
        Route::get('bncc/search', [BNCcController::class, 'search'])
            ->middleware('inst_perm:view_questions')->name('bncc.search');

        // Custom Skills AJAX
        Route::get('custom-skills/search', [CustomSkillController::class, 'search'])
            ->middleware('inst_perm:view_questions')->name('custom-skills.search');
        Route::post('custom-skills/store', [CustomSkillController::class, 'store'])
            ->middleware('inst_perm:manage_questions')->name('custom-skills.store');

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

    Route::middleware('inst_perm:view_pedagogical_content')->group(function () {
        Route::get('lessons/{lesson}/report', [LessonController::class, 'report'])->name('lessons.report');
        Route::get('activities/{activity}/report', [ActivityController::class, 'report'])->name('activities.report');
        Route::post('activities/{activity}/attempts/{attempt}/grade', [ActivityController::class, 'grade'])
            ->name('activities.attempts.grade');
        Route::resource('lessons', LessonController::class);
        Route::resource('activities', ActivityController::class);
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
        Route::get('question-resources/{questionResource}/versions/{version}/download', [QuestionResourceController::class, 'download'])
            ->name('question-resources.versions.download');
        Route::resource('question-resources', QuestionResourceController::class)
            ->parameters(['question-resources' => 'questionResource'])
            ->except(['show']);
        Route::prefix('public-catalog')->name('public-catalog.')->group(function () {
            Route::get('submissions', [PublicCatalogController::class, 'index'])->name('index');
            Route::get('submit', [PublicCatalogController::class, 'createSubmission'])->name('submissions.create');
            Route::post('submissions', [PublicCatalogController::class, 'storeSubmission'])
                ->middleware('throttle:20,1')->name('submissions.store');
            Route::post('submissions/{submission}/withdraw', [PublicCatalogController::class, 'withdraw'])
                ->middleware('throttle:10,1')->name('submissions.withdraw');
            Route::get('report', [PublicCatalogController::class, 'createReport'])->name('reports.create');
            Route::post('reports', [PublicCatalogController::class, 'storeReport'])
                ->middleware('throttle:20,1')->name('reports.store');
        });

        Route::resource('exams', ExamController::class);
        Route::patch('exams/{exam}/draft', [ExamController::class, 'autosaveDraft'])->name('exams.autosaveDraft');
        Route::resource('learning-materials', LearningMaterialController::class)
            ->parameters(['learning-materials' => 'learningMaterial'])
            ->except(['show']);
        Route::post('exams/{exam}/questions', [ExamController::class, 'addQuestion'])->name('exams.addQuestion');
        Route::delete('exams/{exam}/questions/{question}', [ExamController::class, 'removeQuestion'])->name('exams.removeQuestion');
        Route::post('exams/{exam}/classes', [ExamController::class, 'syncClasses'])->name('exams.syncClasses');
        Route::post('exams/{exam}/audience', [ExamController::class, 'syncAudience'])->name('exams.syncAudience');
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
        Route::get('public-catalog', [PublicCatalogModerationController::class, 'index'])->name('public-catalog.index');
        Route::get('public-catalog/submissions/{submission}', [PublicCatalogModerationController::class, 'show'])->name('public-catalog.show');
        Route::post('public-catalog/submissions/{submission}/start', [PublicCatalogModerationController::class, 'start'])->name('public-catalog.start');
        Route::post('public-catalog/submissions/{submission}/decide', [PublicCatalogModerationController::class, 'decide'])->name('public-catalog.decide');
        Route::get('public-catalog/submissions/{submission}/evidence', [PublicCatalogModerationController::class, 'evidence'])->name('public-catalog.evidence');
        Route::post('public-catalog/reports/{report}/resolve', [PublicCatalogModerationController::class, 'resolveReport'])->name('public-catalog.reports.resolve');
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
