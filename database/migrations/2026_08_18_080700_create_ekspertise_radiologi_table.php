<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ekspertise_radiologi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_radiologi_detail_id')->unique()
                ->constrained('order_radiologi_detail')->cascadeOnDelete();
            $table->text('temuan');
            $table->text('kesan');
            $table->text('saran')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekspertise_radiologi');
    }
};
