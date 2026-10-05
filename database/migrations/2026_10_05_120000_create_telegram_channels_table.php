<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_channels', function (Blueprint $table) {
            $table->id();
            // Идентификатор чата в телеграмм: у супергрупп и каналов он отрицательный
            // и не влезает в int, поэтому bigInteger со знаком.
            $table->bigInteger('chat_id')->unique();
            $table->string('chat_name');
            $table->string('last_event_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_channels');
    }
};