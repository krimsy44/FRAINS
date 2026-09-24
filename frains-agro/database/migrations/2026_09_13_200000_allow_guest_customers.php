<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('phone', 30)->nullable();
        });
    }

    public function down(): void
    {
        // Guest records must be retained; do not restore a non-null user constraint.
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['first_name', 'last_name', 'phone']));
    }
};
