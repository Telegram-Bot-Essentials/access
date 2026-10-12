<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * A role is a name and a flat list of permission names, per bot. The list
 * is a JSON column rather than its own table: a role holds a handful of
 * strings, and nothing ever asks "which roles grant X" across a bot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Bot::class)->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->json('permissions');
            $table->timestamps();

            $table->unique(['bot_id', 'name']);
        });

        Schema::create('access_role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('access_roles')->cascadeOnDelete();
            $table->foreignIdFor(BotUser::class)->constrained()->cascadeOnDelete();

            $table->primary(['role_id', 'bot_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_role_user');
        Schema::dropIfExists('access_roles');
    }
};
