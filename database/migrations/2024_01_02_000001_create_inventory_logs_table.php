<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot, same reasoning as sale_items: a deleted/renamed
            // product should never make old log entries unreadable.
            $table->string('product_name');
            $table->enum('type', ['purchase', 'sale', 'return', 'adjustment', 'void_restock']);
            $table->integer('quantity_change'); // signed: +20 in, -3 out
            $table->integer('quantity_after'); // stock level right after this change
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};
