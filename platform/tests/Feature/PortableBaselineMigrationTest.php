<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortableBaselineMigrationTest extends TestCase
{
    public function test_portable_baseline_rebuilds_an_empty_database_and_is_idempotent(): void
    {
        $databasePath = tempnam(storage_path('framework/testing'), 'tembo-baseline-');
        $originalConnection = config('database.default');

        $this->assertNotFalse($databasePath);

        config()->set('database.connections.baseline_test', [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ]);
        config()->set('database.default', 'baseline_test');
        DB::purge('baseline_test');
        DB::setDefaultConnection('baseline_test');

        try {
            $migration = require database_path(
                'migrations/2026_08_02_000000_create_portable_baseline_schema.php'
            );

            $migration->up();

            $tables = collect(DB::select(
                "select name from sqlite_master where type = 'table' and name not like 'sqlite_%'"
            ))->pluck('name');

            $this->assertCount(65, $tables);
            $this->assertTrue($tables->contains('organizations'));
            $this->assertTrue($tables->contains('users'));
            $this->assertTrue($tables->contains('exam_submissions'));
            $this->assertTrue($tables->contains('omr_scans'));
            $this->assertTrue($tables->contains('learning_material_progress'));

            $columnCount = $tables->sum(
                fn (string $table): int => count(DB::select(
                    'PRAGMA table_info("'.str_replace('"', '""', $table).'")'
                ))
            );
            $foreignKeyCount = $tables->sum(
                fn (string $table): int => count(DB::select(
                    'PRAGMA foreign_key_list("'.str_replace('"', '""', $table).'")'
                ))
            );
            $explicitIndexCount = $tables->sum(
                fn (string $table): int => collect(DB::select(
                    'PRAGMA index_list("'.str_replace('"', '""', $table).'")'
                ))->reject(fn (object $index): bool => str_starts_with($index->name, 'sqlite_autoindex_'))->count()
            );

            $this->assertSame(579, $columnCount);
            $this->assertSame(106, $foreignKeyCount);
            $this->assertSame(67, $explicitIndexCount);

            $examAnswerForeignKeys = DB::select('PRAGMA foreign_key_list("exam_answers")');
            $this->assertCount(2, $examAnswerForeignKeys);

            $configIndexes = collect(DB::select('PRAGMA index_list("config_rules")'))
                ->pluck('name');
            $this->assertTrue($configIndexes->contains('idx_scope_lookup'));

            $tableCountBeforeSecondRun = $tables->count();
            $migration->up();
            $tableCountAfterSecondRun = DB::scalar(
                "select count(*) from sqlite_master where type = 'table' and name not like 'sqlite_%'"
            );

            $this->assertSame($tableCountBeforeSecondRun, $tableCountAfterSecondRun);
        } finally {
            DB::disconnect('baseline_test');
            DB::purge('baseline_test');
            DB::setDefaultConnection($originalConnection);
            config()->set('database.default', $originalConnection);

            if (is_string($databasePath) && str_starts_with($databasePath, storage_path('framework/testing'))) {
                @unlink($databasePath);
            }
        }
    }
}
