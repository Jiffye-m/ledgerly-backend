<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'admin', 'staff'])->default('staff');
            // Null branch_id = access to every branch (how owner/admin work by
            // default). Staff are normally scoped to exactly one branch.
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['active', 'invited', 'deactivated'])->default('active');
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
        });

        // Backfill: every existing user.business_id/role becomes their
        // membership row, before those columns are dropped from users.
        DB::table('users')->whereNotNull('business_id')->orderBy('id')->each(function ($user) {
            DB::table('business_members')->insert([
                'business_id' => $user->business_id,
                'user_id' => $user->id,
                'role' => $user->role,
                'branch_id' => null,
                'status' => $user->is_active ? 'active' : 'deactivated',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_members');
    }
};
