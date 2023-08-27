<?php

namespace Tests\Unit;

use App\Services\CurrencyConverter;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;

class CurrencyConverterTest extends TestCase
{
    protected CurrencyConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new CurrencyConverter();
    }

    /**
     * @throws Exception
     */
    public function testGetConversionRateFromCache()
    {
        Cache::shouldReceive('has')
            ->once()
            ->andReturn(true);

        Cache::shouldReceive('get')
            ->once()
            ->andReturn(1.1);

        $rate = $this->converter->getConversionRate('USD');
        $this->assertEquals(1.1, $rate);
    }

    /**
     * @throws Exception
     */
    public function testGetConversionRateFromAPI()
    {
        Cache::shouldReceive('has')
            ->once()
            ->andReturn(false);

        Http::shouldReceive('get')
            ->once()
            ->andReturnSelf();

        Http::shouldReceive('failed')
            ->once()
            ->andReturn(false);

        Http::shouldReceive('json')
            ->once()
            ->andReturn(['rates' => ['USD' => 1.1]]);

        Cache::shouldReceive('put')
            ->once();

        $rate = $this->converter->getConversionRate('USD');
        $this->assertEquals(1.1, $rate);
    }
}
