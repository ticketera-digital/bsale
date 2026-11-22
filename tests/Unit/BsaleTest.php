<?php

namespace ticketeradigital\bsale\tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use ticketeradigital\bsale\Bsale;
use ticketeradigital\bsale\BsaleException;
use ticketeradigital\bsale\BsaleServiceProvider;

class BsaleTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [BsaleServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('bsale.base_url', 'https://api.bsale.io');
    }

    public function test_no_access_token()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Config access_token not set.');
        Bsale::makeRequest('/v1/products.json');
    }

    public function test_no_base_url()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Config base_url not set.');
        config(['bsale.access_token' => '123']);
        Bsale::makeRequest('/v1/products.json');
    }

    public function test_create_document_propagates_connection_errors_instead_of_exiting()
    {
        config(['bsale.access_token' => '123']);
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->expectException(ConnectionException::class);
        Bsale::createDocument(['salesId' => 'TD-1']);
    }

    public function test_fetch_all_propagates_connection_errors_instead_of_exiting()
    {
        config(['bsale.access_token' => '123']);
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->expectException(ConnectionException::class);
        Bsale::fetchAllAndCallback('/v1/products.json', fn () => null);
    }

    public function test_exception_uses_the_bsale_error_message()
    {
        config(['bsale.access_token' => '123']);
        Http::fake(['*' => Http::response(['error' => 'seller is not active'], 400)]);

        $this->expectException(BsaleException::class);
        $this->expectExceptionMessage('seller is not active');
        $this->expectExceptionCode(400);
        Bsale::makeRequest('/v1/documents.json', [], 'POST');
    }

    public function test_exception_survives_a_non_json_error_body()
    {
        config(['bsale.access_token' => '123']);
        Http::fake(['*' => Http::response('<html>Bad Gateway</html>', 502)]);

        $this->expectException(BsaleException::class);
        $this->expectExceptionMessage('Bad Gateway');
        $this->expectExceptionCode(502);
        Bsale::makeRequest('/v1/documents.json', [], 'POST');
    }
}
