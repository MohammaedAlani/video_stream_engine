<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stream_channels', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->unsignedBigInteger('parent_id')->default(0)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_channels', function (Blueprint $table) {
            $table->dropColumn('parent_id');
            $table->dropColumn('name');
        });
    }
};
