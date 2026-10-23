<?php

use App\Models\Project;
use App\Support\TicketDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->unsignedInteger('ticket_catalog_revision')->default(1));
        foreach (['work_types', 'priorities'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained();
                $table->string('name', 80);
                $table->unsignedInteger('position')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->unique(['project_id', 'name']);
                $table->unique(['project_id', 'id']);
            });
        }
        Schema::create('project_counters', function (Blueprint $table) {
            $table->foreignId('project_id')->primary()->constrained();
            $table->unsignedBigInteger('next_number')->default(1);
        });
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->unsignedBigInteger('number');
            $table->string('key', 40)->unique();
            $table->string('summary');
            $table->longText('description')->nullable();
            $table->longText('acceptance_criteria')->nullable();
            $table->unsignedBigInteger('work_type_id');
            $table->unsignedBigInteger('priority_id');
            $table->foreign(['project_id', 'work_type_id'])->references(['project_id', 'id'])->on('work_types');
            $table->foreign(['project_id', 'priority_id'])->references(['project_id', 'id'])->on('priorities');
            $table->foreignId('reporter_id')->constrained('users');
            $table->foreignId('assignee_id')->nullable()->constrained('users');
            $table->decimal('estimate', 6, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->string('state', 30)->default('open');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['project_id', 'number']);
            $table->index(['project_id', 'updated_at']);
        });
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 40);
            $table->json('changes');
            $table->timestamp('created_at');
            $table->index(['ticket_id', 'id']);
        });
        foreach (['view' => 'View tickets', 'create' => 'Create tickets', 'update' => 'Edit and assign tickets', 'configure' => 'Configure ticket catalogs'] as $key => $label) {
            DB::table('permissions')->insertOrIgnore(['key' => 'ticket.'.$key, 'label' => $label]);
        }
        Project::orderBy('id')->chunkById(100, fn ($projects) => $projects->each(fn ($project) => TicketDefaults::initialize($project)));
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('project_counters');
        Schema::dropIfExists('priorities');
        Schema::dropIfExists('work_types');
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('ticket_catalog_revision'));
        DB::table('role_permissions')->where('permission_key', 'like', 'ticket.%')->delete();
        DB::table('permissions')->where('key', 'like', 'ticket.%')->delete();
    }
};
