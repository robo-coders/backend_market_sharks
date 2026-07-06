<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_trends', function (Blueprint $table) {
            $table->id();
            $table->enum('gold_trend', ['buy', 'neutral', 'sell'])->default('neutral');
            $table->enum('dollar_trend', ['buy', 'neutral', 'sell'])->default('neutral');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_trends');
    }
};