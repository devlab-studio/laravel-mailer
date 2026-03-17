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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->timestamp('send_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->string('from', 200);
            $table->text('to')->nullable();
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();
            $table->text('body')->nullable();
            $table->mediumText('subject')->nullable();
            $table->tinyInteger('sent')->default(0);
            $table->smallInteger('state')->default(0);
            $table->smallInteger('retries')->default(0);
            $table->string('shipping_id', 45);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->softDeletes()->index();
            $table->foreignId('created_user')->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
