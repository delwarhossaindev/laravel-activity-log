<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $table      = config('activitylog.table_name', 'activity_log');

        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $table->id();

            $table->string('log_name')->default('default')->index();
            $table->text('description');

            // The model that was acted upon (e.g. Post, Invoice)
            $table->nullableMorphs('subject');

            // The user/model that performed the action
            $table->nullableMorphs('causer');

            // Stores old/new values as JSON
            $table->json('properties')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        $connection = config('activitylog.database_connection');
        $table      = config('activitylog.table_name', 'activity_log');

        Schema::connection($connection)->dropIfExists($table);
    }
};
