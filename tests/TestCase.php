<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use WizardingCode\FlowNetwork\SyncTracker\Models\SyncTrackedEntity;
use WizardingCode\FlowNetwork\SyncTracker\SyncTrackerServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create a test table
        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Run the migrations
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function tearDown(): void
    {
        // Clear the tracking rows before migrations rollback:
        // once a model is tracked against multiple
        // sources, the unique-index migration's down() cannot restore the
        // original two-column unique index over the violating rows.
        $tracker = new SyncTrackedEntity;

        if (Schema::hasTable($tracker->getTable())) {
            $tracker->newQuery()->delete();
        }

        parent::tearDown();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            SyncTrackerServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
