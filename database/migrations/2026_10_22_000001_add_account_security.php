<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system_admin')->default(false)->index();
            $table->unsignedInteger('auth_version')->default(1);
        });

        // A stable row serializes bootstrap even when there are no users to lock.
        Schema::create('account_administration', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->timestamp('bootstrapped_at')->nullable();
        });
        DB::table('account_administration')->insert(['id' => 1]);

        Schema::create('account_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->foreignId('target_user_id')->nullable()->constrained('users');
            $table->foreignId('account_invitation_id')->nullable()->constrained('account_invitations');
            $table->string('action')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('account_invitations');
        Schema::dropIfExists('account_administration');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'is_system_admin', 'auth_version']);
        });
    }
};
