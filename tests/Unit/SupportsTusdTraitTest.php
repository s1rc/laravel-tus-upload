<?php

namespace Tests\Unit;

use Tests\AbstractTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use OneOffTech\TusUpload\Console\SupportsTusd;

class SupportsTusdTraitTest extends AbstractTestCase
{
    use SupportsTusd;
    
    /**
     * Setup the test environment.
     */
    public function tearDown()
    {
        parent::tearDown();

        static::stopTusd();
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // override the default location of the binary, otherwise it cannot be found
        $app['config']->set('tusupload.executable', __DIR__ .'/../../bin/tusd-');
    }

    /** @test */
    public function tus_executable_is_started_and_stopped()
    {
        static::startTusd();

        $this->assertNotNull(static::$tusProcess);
        $this->assertTrue(static::$tusProcess->isRunning());

        static::stopTusd();
        
        $this->assertFalse(static::$tusProcess->isRunning());
    }

    /** @test */
    public function use_custom_tus_driver_path()
    {
        $path = 'some_directory';

        static::useTusdBinary($path);

        $this->assertEquals($path, static::$tusDriver);

        static::useTusdBinary(null);
    }
    
    /** @test */
    public function get_tus_arguments()
    {
        $arguments = static::tusdArguments();

        $this->assertEquals([
            '-host=127.0.0.1',
            '-port=1080',
            '-base-path=/uploads/',
            '-dir=' . storage_path('app/uploads'),
            '-hooks-dir=' .  static::hooksPath(),
        ], $arguments);

        // change some configuration parameters and check if the result is upgraded

        $this->app['config']->set('tusupload.port', 9999);
        $this->app['config']->set('tusupload.storage_size', 100);
        $this->app['config']->set('tusupload.expose_metrics', true);
        
        $arguments = static::tusdArguments();

        $this->assertEquals([
            '-host=127.0.0.1',
            '-port=9999',
            '-base-path=/uploads/',
            '-dir=' . storage_path('app/uploads'),
            '-hooks-dir=' .  static::hooksPath(),
            '-store-size=100',
            '-expose-metrics'
        ], $arguments);
        
        // make also the hooks config empty

        $this->app['config']->set('tusupload.hooks', '');
        
        $arguments = static::tusdArguments();

        $this->assertEquals([
            '-host=127.0.0.1',
            '-port=9999',
            '-base-path=/uploads/',
            '-dir=' . storage_path('app/uploads'),
            '-store-size=100',
            '-expose-metrics'
        ], $arguments);
    }

    /** @test */
    public function build_tus_process()
    {
        $process = static::buildTusProcess();

        $this->assertNotNull($process);
    }
    
}
