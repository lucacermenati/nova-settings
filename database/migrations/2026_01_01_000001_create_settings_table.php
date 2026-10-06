<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setting_group_id')->constrained('setting_groups')->cascadeOnDelete();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('type');
            $table->json('options')->nullable();
            $table->json('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source', 16)->default('database');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
