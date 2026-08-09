<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_catalog_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitter_id')->constrained('users')->restrictOnDelete();
            $table->morphs('submittable');
            $table->foreignId('previous_submission_id')->nullable()->constrained('public_catalog_submissions')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->char('content_hash', 64);
            $table->char('active_fingerprint', 64)->nullable()->unique();
            $table->char('similarity_key', 64);
            $table->json('snapshot_json');
            $table->string('rights_basis', 40);
            $table->text('rights_notes')->nullable();
            $table->string('terms_version', 40);
            $table->string('attribution', 500)->nullable();
            $table->string('evidence_url', 2048)->nullable();
            $table->timestamp('rights_confirmed_at');
            $table->string('idempotency_key', 190)->unique();
            $table->json('duplicate_candidates_json')->nullable();
            $table->foreignId('duplicate_of_submission_id')->nullable()->constrained('public_catalog_submissions')->nullOnDelete();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('review_started_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at'], 'public_catalog_submissions_queue_index');
            $table->index(['submitter_id', 'status', 'submitted_at'], 'public_catalog_submissions_owner_index');
            $table->index(['submittable_type', 'content_hash', 'status'], 'public_catalog_submissions_dedup_index');
            $table->index(['submittable_type', 'similarity_key', 'status'], 'public_catalog_submissions_similarity_index');
        });

        Schema::create('public_catalog_submission_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained('public_catalog_submissions')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['submission_id', 'created_at'], 'public_catalog_submission_events_history_index');
        });

        Schema::create('public_catalog_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->unique()->constrained('public_catalog_submissions')->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('publisher_id')->constrained('users')->restrictOnDelete();
            $table->morphs('entryable');
            $table->char('fingerprint', 64);
            $table->char('canonical_fingerprint', 64)->nullable()->unique();
            $table->string('status', 30)->default('published');
            $table->timestamp('published_at');
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at'], 'public_catalog_entries_browse_index');
            $table->index(['fingerprint', 'status'], 'public_catalog_entries_fingerprint_index');
        });

        Schema::create('public_catalog_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('public_catalog_entry_id')->constrained('public_catalog_entries')->restrictOnDelete();
            $table->string('reason_code', 40);
            $table->text('details');
            $table->string('status', 30)->default('open');
            $table->string('idempotency_key', 190)->unique();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'public_catalog_reports_queue_index');
            $table->index(['reporter_id', 'status', 'created_at'], 'public_catalog_reports_reporter_index');
            $table->index(['public_catalog_entry_id', 'status'], 'public_catalog_reports_entry_index');
        });

        Schema::create('public_catalog_reputation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 50);
            $table->integer('points');
            $table->string('rule_version', 40);
            $table->string('idempotency_key', 190)->unique();
            $table->nullableMorphs('source');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at'], 'public_catalog_reputation_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_catalog_reputation_events');
        Schema::dropIfExists('public_catalog_reports');
        Schema::dropIfExists('public_catalog_entries');
        Schema::dropIfExists('public_catalog_submission_events');
        Schema::dropIfExists('public_catalog_submissions');
    }
};
