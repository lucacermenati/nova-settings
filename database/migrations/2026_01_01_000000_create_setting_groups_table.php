<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source', 16)->default('database');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_groups');
    }
};
