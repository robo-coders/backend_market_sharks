<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE trading_signals MODIFY COLUMN status ENUM('pending','open','closed','cancelled') NOT NULL DEFAULT 'open'");

        Schema::table('trading_signals', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('trading_signals', function (Blueprint $table) {
            $table->dropColumn('activated_at');
        });

        // Collapse any new states back into the original two before shrinking.
        DB::statement("UPDATE trading_signals SET status = 'open' WHERE status = 'pending'");
        DB::statement("UPDATE trading_signals SET status = 'closed' WHERE status = 'cancelled'");
        DB::statement("ALTER TABLE trading_signals MODIFY COLUMN status ENUM('open','closed') NOT NULL DEFAULT 'open'");
    }
};