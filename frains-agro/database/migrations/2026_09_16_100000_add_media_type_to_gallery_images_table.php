<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            if (! Schema::hasColumn('gallery_images', 'media_type')) {
                $table->string('media_type', 20)->default('image')->after('category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            if (Schema::hasColumn('gallery_images', 'media_type')) {
                $table->dropColumn('media_type');
            }
        });
    }
};