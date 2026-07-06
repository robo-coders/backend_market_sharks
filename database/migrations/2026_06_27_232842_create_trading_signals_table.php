<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_signals', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->default('XAUUSD');
            $table->enum('signal_type', ['buy', 'sell']);
            $table->decimal('gold_price_at_entry', 12, 2)->nullable();
            $table->decimal('entry_price', 12, 2);
            $table->decimal('stop_loss', 12, 2)->nullable();
            $table->decimal('take_profit', 12, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_signals');
    }
};