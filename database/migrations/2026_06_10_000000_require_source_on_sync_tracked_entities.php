<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One tracking row per model per source: `source` joins the unique index
     * and becomes NOT NULL (lifecycle rows use the '_lifecycle' sentinel
     * instead of NULL, so the unique index can actually enforce uniqueness —
     * SQL treats NULLs as distinct).
     *
     * Existing installs must clean up their NULL-source rows BEFORE running
     * this migration (deduplicate, then backfill) — see UPGRADE.md. On
     * Laravel 10 the column change additionally requires the doctrine/dbal
     * package.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(config('sync-tracker.table_name', 'sync_tracked_entities'), function (Blueprint $table) {
            $table->dropUnique(['trackable_type', 'trackable_id']);
            $table->unique(['trackable_type', 'trackable_id', 'source']);
        });

        Schema::table(config('sync-tracker.table_name', 'sync_tracked_entities'), function (Blueprint $table) {
            $table->string('source')->nullable(false)->change();
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
            $table->string('source')->nullable()->change();
        });

        Schema::table(config('sync-tracker.table_name', 'sync_tracked_entities'), function (Blueprint $table) {
            $table->dropUnique(['trackable_type', 'trackable_id', 'source']);
            $table->unique(['trackable_type', 'trackable_id']);
        });
    }
};
