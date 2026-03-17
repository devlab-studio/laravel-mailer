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
        Schema::create('emails_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained('emails');
            $table->string('name', 75);
            $table->string('path', 150);
            $table->string('extension', 45)->nullable();
            $table->string('mime_type', 100)->nullable();
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
        Schema::dropIfExists('emails_attachments');
    }
};
