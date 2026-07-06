<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_structures', function (Blueprint $table) {
            $table->id();
            $table->decimal('support_1', 12, 2)->default(0);
            $table->decimal('support_2', 12, 2)->default(0);
            $table->decimal('support_3', 12, 2)->default(0);
            $table->decimal('resistance_1', 12, 2)->default(0);
            $table->decimal('resistance_2', 12, 2)->default(0);
            $table->decimal('resistance_3', 12, 2)->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_structures');
    }
};