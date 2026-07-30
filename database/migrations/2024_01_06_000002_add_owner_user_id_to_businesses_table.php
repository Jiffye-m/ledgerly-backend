<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        // Backfill: whoever was users.role='owner' for this business becomes
        // its explicit owner_user_id, before users.business_id/role go away.
        DB::table('businesses')->select('id')->orderBy('id')->each(function ($business) {
            $owner = DB::table('users')
                ->where('business_id', $business->id)
                ->where('role', 'owner')
                ->first();

            if ($owner) {
                DB::table('businesses')->where('id', $business->id)->update(['owner_user_id' => $owner->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
        });
    }
};
