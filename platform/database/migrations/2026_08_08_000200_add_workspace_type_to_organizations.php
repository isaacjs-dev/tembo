<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('workspace_type', 20)->default('institutional')->after('name');
            $table->index(['workspace_type', 'active'], 'organizations_workspace_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex('organizations_workspace_active_index');
            $table->dropColumn('workspace_type');
        });
    }
};
