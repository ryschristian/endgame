<?php

use App\Models\Project;
use App\Support\WorkflowDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['project_id', 'version']);
            $table->unique(['project_id', 'id']);
        });
        Schema::table('projects', fn (Blueprint $table) => $table->unsignedInteger('board_revision')->default(1));
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('workflow_version_id')->nullable();
            $table->foreign(['project_id', 'workflow_version_id'])->references(['project_id', 'id'])->on('workflow_versions');
            $table->unsignedBigInteger('board_rank')->default(0);
            $table->date('start_date')->nullable();
            $table->string('resolution', 40)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->index(['project_id', 'state', 'board_rank']);
            $table->index(['project_id', 'due_date']);
        });
        Project::orderBy('id')->chunkById(100, function ($projects) {
            foreach ($projects as $project) {
                $workflow = WorkflowDefaults::initialize($project);
                DB::table('tickets')->where('project_id', $project->id)->update(['workflow_version_id' => $workflow->id, 'board_rank' => DB::raw('number')]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['project_id', 'workflow_version_id']);
            $table->dropIndex(['project_id', 'state', 'board_rank']);
            $table->dropIndex(['project_id', 'due_date']);
            $table->dropColumn(['workflow_version_id', 'board_rank', 'start_date', 'resolution', 'completed_at']);
        });
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('board_revision'));
        Schema::dropIfExists('workflow_versions');
    }
};
