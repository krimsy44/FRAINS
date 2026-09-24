<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('delivery_zone_id')->references('id')->on('delivery_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropForeign(['delivery_zone_id']));
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['role_id']));
    }
};
