<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            // Nullable while trialing — a business doesn't pick/pay for a
            // specific plan until it actually converts.
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['trialing', 'active', 'expired', 'suspended', 'cancelled'])->default('trialing');
            $table->date('starts_at');
            $table->date('trial_ends_at')->nullable();
            $table->date('expires_at')->nullable(); // when a paid period runs out
            $table->string('payment_provider')->nullable(); // 'manual' for now, 'paystack' later
            $table->string('provider_subscription_id')->nullable(); // for a future Paystack integration
            $table->date('next_billing_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
