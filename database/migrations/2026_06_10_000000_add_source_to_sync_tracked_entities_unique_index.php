<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(config('sync-tracker.table_name', 'sync_tracked_entities'), function (Blueprint $table) {
            $table->dropUnique(['trackable_type', 'trackable_id']);
            $table->unique(['trackable_type', 'trackable_id', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Note: reverting will fail if a model is already tracked against
     * multiple sources, as the rows would violate the restored index.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(config('sync-tracker.table_name', 'sync_tracked_entities'), function (Blueprint $table) {
            $table->dropUnique(['trackable_type', 'trackable_id', 'source']);
            $table->unique(['trackable_type', 'trackable_id']);
        });
    }
};
