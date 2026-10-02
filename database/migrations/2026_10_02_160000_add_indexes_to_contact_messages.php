<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The 24-hour duplicate check looks enquiries up by phone / e-mail and date.
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->index('phone');
            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['email']);
            $table->dropIndex(['created_at']);
        });
    }
};
