<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('receipt_footer')->nullable();
            $table->boolean('whatsapp_enabled')->default(false);
            $table->boolean('email_enabled')->default(true);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('low_stock_alerts')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
