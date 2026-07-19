<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->foreignId('return_id')->nullable()->after('sale_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('return_id');
        });
    }
};
