<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('dokter_id')->nullable()->after('email')->constrained('dokter')->nullOnDelete();
            $table->boolean('aktif')->default(true)->after('dokter_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dokter_id');
            $table->dropColumn('aktif');
        });
    }
};
