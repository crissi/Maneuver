<?php namespace Fadion\Maneuver;

use Illuminate\Support\ServiceProvider;
use Fadion\Maneuver\Commands\DeployCommand;
use Fadion\Maneuver\Commands\ListCommand;
use Fadion\Maneuver\Commands\RollbackCommand;
use Fadion\Maneuver\Commands\SyncCommand;

class ManeuverServiceProvider extends ServiceProvider
{

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/config.php' => config_path('maneuver.php')
        ]);
    }

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
        $this->app->singleton('artisan.maneuver.deploy', function($app) {
            return new DeployCommand;
        });

        $this->app->singleton('artisan.maneuver.list', function($app) {
            return new ListCommand;
        });

        $this->app->singleton('artisan.maneuver.rollback', function($app) {
            return new RollbackCommand;
        });

        $this->app->singleton('artisan.maneuver.sync', function($app) {
            return new SyncCommand;
        });

        $this->commands('artisan.maneuver.deploy');
        $this->commands('artisan.maneuver.list');
        $this->commands('artisan.maneuver.rollback');
        $this->commands('artisan.maneuver.sync');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('maneuver');
	}

}
