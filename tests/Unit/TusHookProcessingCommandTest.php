<?php

namespace Tests\Unit;

use Tests\AbstractTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Avvertix\TusUpload\TusUpload;

use Avvertix\TusUpload\Contracts\AuthenticationResolver as AuthenticationResolverContract;
use Avvertix\TusUpload\Auth\AuthenticationResolverFake;

class TusHookProcessingCommandTest extends AbstractTestCase
{
    use DatabaseMigrations;

    /**
     * Setup the test environment.
     */
    public function setUp()
    {
        parent::setUp();

        // Bind the Fake authentication resolver, to test the components without having to use real users
        $this->app->bind(AuthenticationResolverContract::class, AuthenticationResolverFake::class);
    }


    private function generateHookPayload($requestId, $tusId = '', $offset = 0)
    {
        return sprintf('{' .
                          '"ID": "%2$s",' .
                          '"Size": 46205,' .
                          '"Offset": %3$s,' .
                          '"IsFinal": false,' .
                          '"IsPartial": false,' .
                          '"PartialUploads": null,' .
                          '"MetaData": {' .
                          '  "filename": "test.png",' .
                          '  "api_token": "AAAAAAAAAAA",' .
                          '  "upload_request_id": "%1$s"' .
                          '}' .
                        '}', $requestId, $tusId, $offset);
    }

    /** @test */
    public function tus_pre_create_hook_is_processed()
    {
        $requestId = '14b1c4c77771671a8479bc0444bbc5ce';

        $hook_content = $this->generateHookPayload($requestId);

        $return_code = $this->artisan('tus:hook', ['hook' => 'pre-create', 'payload' => $hook_content]);

        $upload = TusUpload::where('request_id', $requestId)->first();

        $this->assertEquals(0, $return_code);
        $this->assertNotNull($upload);

    }
    
    /** @test */
    public function tus_post_receive_hook_is_processed()
    {
        $requestId = '14b1c4c77771671a8479bc0444bbc5ce';

        $hook_content = $this->generateHookPayload($requestId, $requestId, 100);

        TusUpload::forceCreate([
            'request_id' => $requestId,
            'user_id' => 1,
            'filename' => 'test.png',
            'size' => 46205,
            'offset' => 0
        ]);

        $return_code = $this->artisan('tus:hook', ['hook' => 'post-receive', 'payload' => $hook_content]);

        $upload = TusUpload::where('request_id', $requestId)->first();

        $this->assertEquals(0, $return_code);
        $this->assertNotNull($upload);
        $this->assertEquals(100, $upload->offset);
    }
    
    /** @test */
    public function tus_post_finish_hook_is_processed()
    {
        $requestId = '14b1c4c77771671a8479bc0444bbc5ce';

        $hook_content = $this->generateHookPayload($requestId, $requestId, 46205);

        TusUpload::forceCreate([
            'request_id' => $requestId,
            'user_id' => 1,
            'filename' => 'test.png',
            'size' => 46205,
            'offset' => 46205
        ]);

        $return_code = $this->artisan('tus:hook', ['hook' => 'post-finish', 'payload' => $hook_content]);

        $upload = TusUpload::where('request_id', $requestId)->first();

        $this->assertEquals(0, $return_code);
        $this->assertNotNull($upload);
        $this->assertTrue($upload->completed);
    }
    
    /** @test */
    public function tus_post_terminate_hook_is_processed()
    {
        $requestId = '14b1c4c77771671a8479bc0444bbc5ce';

        $hook_content = $this->generateHookPayload($requestId, $requestId, 100);

        TusUpload::forceCreate([
            'request_id' => $requestId,
            'user_id' => 1,
            'filename' => 'test.png',
            'size' => 46205,
            'offset' => 0
        ]);

        $return_code = $this->artisan('tus:hook', ['hook' => 'post-terminate', 'payload' => $hook_content]);

        $upload = TusUpload::where('request_id', $requestId)->first();

        $this->assertEquals(0, $return_code);
        $this->assertNotNull($upload);
        $this->assertTrue($upload->cancelled);
    }
}
