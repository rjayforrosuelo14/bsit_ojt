<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $updateData = ['email_verified_at' => DB::raw('current_timestamp')];

        // Only include otp_verified if the column already exists
        if (Schema::hasColumn('users', 'otp_verified')) {
            $updateData['otp_verified'] = 1;
        }

        DB::table('users')
            ->where('email', 'rjay@gmail.com')
            ->update($updateData);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
