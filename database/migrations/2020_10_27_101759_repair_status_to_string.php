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
        // Function creation removed due to permission issues in production
        // The same functionality is implemented using triggers in repair_status_to_string_data.php
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No function to drop
    }
};
