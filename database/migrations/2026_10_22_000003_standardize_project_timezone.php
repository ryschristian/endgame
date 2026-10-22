<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('account_administration')->where('id', 1)->lockForUpdate()->first();
            DB::table('projects')->where('timezone', '!=', 'Asia/Manila')->orderBy('id')->lockForUpdate()->chunkById(100, function ($projects) {
                foreach ($projects as $project) {
                    DB::table('audit_events')->insert([
                        'project_id' => $project->id, 'action' => 'project.timezone_standardized',
                        'metadata' => json_encode(['before' => ['timezone' => $project->timezone], 'after' => ['timezone' => 'Asia/Manila']], JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                    ]);
                    DB::table('projects')->where('id', $project->id)->update(['timezone' => 'Asia/Manila', 'revision' => $project->revision + 1, 'updated_at' => now()]);
                }
            });
        });
    }

    public function down(): void
    {
        // Historical timezone choices remain in audit records, never guessed on rollback.
    }
};
