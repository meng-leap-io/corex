<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('plan', 50)->default('free');
            $table->string('status', 50)->default('active');

            $table->string('stripe_id')->nullable()->unique();
            $table->string('stripe_status')->nullable();
            $table->string('stripe_price')->nullable();

            $table->integer('quantity')->default(1);

            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();

            $table->timestampsTz();

            $table->index(['user_id', 'status']);
            $table->index('plan');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
