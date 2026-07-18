<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // who saved it
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // Deliberately JSON, not a sale_items-style table: this cart
            // isn't a real transaction yet, so it shouldn't touch stock,
            // and it needs to survive even if a product is deleted before
            // the draft is resumed.
            $table->json('items');
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_sales');
    }
};
