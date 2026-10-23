<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('key', 10)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('timezone', 64);
            $table->timestamp('archived_at')->nullable()->index();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('joined_at');
            $table->timestamp('removed_at')->nullable();
            $table->unique(['project_id', 'user_id']);
            $table->unique(['project_id', 'id']);
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->string('name', 80);
            $table->unique(['project_id', 'name']);
            $table->unique(['project_id', 'id']);
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->string('label');
        });
        // Required system data travels with the forward migration, not sample users.
        DB::table('permissions')->insert([
            ['key' => 'project.view', 'label' => 'View project'],
            ['key' => 'project.update', 'label' => 'Edit project settings'],
            ['key' => 'project.members.manage', 'label' => 'Manage members'],
            ['key' => 'project.roles.manage', 'label' => 'Manage roles'],
            ['key' => 'project.archive', 'label' => 'Archive project'],
            ['key' => 'project.restore', 'label' => 'Restore project'],
        ]);
        Schema::create('member_roles', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained();
            $table->unsignedBigInteger('project_member_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['project_member_id', 'role_id']);
            $table->foreign(['project_id', 'project_member_id'])->references(['project_id', 'id'])->on('project_members');
            $table->foreign(['project_id', 'role_id'])->references(['project_id', 'id'])->on('roles');
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained();
            $table->string('permission_key', 80);
            $table->foreign('permission_key')->references('key')->on('permissions');
            $table->primary(['role_id', 'permission_key']);
        });
        Schema::create('project_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('email', 254);
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'email']);
            $table->unique(['project_id', 'id']);
        });
        Schema::create('project_invitation_roles', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained();
            $table->unsignedBigInteger('project_invitation_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['project_invitation_id', 'role_id']);
            $table->foreign(['project_id', 'project_invitation_id'], 'pir_invitation_fk')->references(['project_id', 'id'])->on('project_invitations');
            $table->foreign(['project_id', 'role_id'], 'pir_role_fk')->references(['project_id', 'id'])->on('roles');
        });
        Schema::table('audit_events', fn (Blueprint $table) => $table->foreignId('project_id')->nullable()->constrained());
    }

    public function down(): void
    {
        Schema::table('audit_events', fn (Blueprint $table) => $table->dropConstrainedForeignId('project_id'));
        foreach (['project_invitation_roles', 'project_invitations', 'role_permissions', 'member_roles', 'permissions', 'roles', 'project_members', 'projects'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
