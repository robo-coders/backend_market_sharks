<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_signal_id')
                ->nullable()
                ->constrained('trading_signals')
                ->nullOnDelete();

            $table->string('symbol')->default('XAUUSD');
            $table->enum('signal_type', ['buy', 'sell']);
            $table->decimal('gold_price_at_entry', 12, 2)->nullable();
            $table->decimal('gold_price_at_close', 12, 2)->nullable();
            $table->decimal('entry_price', 12, 2);
            $table->decimal('close_price', 12, 2)->nullable();
            $table->decimal('stop_loss', 12, 2)->nullable();
            $table->decimal('take_profit', 12, 2)->nullable();
            $table->decimal('profit_loss', 12, 2)->default(0);
            $table->enum('result', ['profit', 'loss', 'breakeven'])->nullable();
            $table->enum('close_reason', ['tp', 'sl', 'manual', 'cancelled'])->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('closed_at');
            $table->index(['symbol', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_logs');
    }
};