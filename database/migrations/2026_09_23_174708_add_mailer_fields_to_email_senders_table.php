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
        if (! Schema::hasColumn('email_senders', 'mailer')) {
            Schema::table('email_senders', function (Blueprint $table) {
                $table->string('mailer', 45)->default('smtp')->after('auth_password');
            });
        }
        if (! Schema::hasColumn('email_senders', 'mailer_data')) {
            Schema::table('email_senders', function (Blueprint $table) {
                $table->json('mailer_data')->nullable()->after('mailer');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('email_senders', 'mailer')) {
            Schema::table('email_senders', function (Blueprint $table) {
                $table->dropColumn('mailer');
            });
        }
        if (Schema::hasColumn('email_senders', 'mailer_data')) {
            Schema::table('email_senders', function (Blueprint $table) {
                $table->dropColumn('mailer_data');
            });
        }
    }
};
