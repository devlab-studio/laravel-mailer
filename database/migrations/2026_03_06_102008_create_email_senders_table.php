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
        Schema::create('email_senders', function (Blueprint $table) {
            $table->id();
            $table->string('address', 75);
            $table->string('name', 150);
            $table->string('server', 45);
            $table->integer('port');
            $table->tinyInteger('use_auth')->default(1);
            $table->string('auth_protocol', 45);
            $table->string('auth_user', 45);
            $table->string('auth_password', 45);
            $table->timestamps();
            $table->softDeletes()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_senders');
    }
};
