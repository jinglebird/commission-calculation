<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

use function base_path;
use function collect;

class CalculateFeesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock the Http facade for currency conversion API.
        Http::fake([
            'https://developers.paysera.com/tasks/api/currency-exchange-rates' => Http::response([
                'rates' => [
                    'USD' => 1.2,
                    'JPY' => 130
                ]
            ], 200)
        ]);
    }

    public function testMultipleOperationsCommissions()
    {
        $sampleCSVContent = <<<CSV
2014-12-31,4,private,withdraw,1200.00,EUR
2015-01-01,4,private,withdraw,1000.00,EUR
2016-01-05,4,private,withdraw,1000.00,EUR
2016-01-05,1,private,deposit,200.00,EUR
2016-01-06,2,business,withdraw,300.00,EUR
CSV;

        file_put_contents(base_path('tests/Data/input.csv'), $sampleCSVContent);

        ob_start();
        Artisan::call('fees:calculate', ['input' => base_path('tests/Data/input.csv')]);
        $output = ob_get_clean();

        $expectedOutput = [
            "0.60",
            "0.00",
            "0.00",
            "0.06",
            "1.50",
        ];

        $outputLines = collect(explode("\n", trim($output)))
            ->filter(function ($line) {
                return trim($line) !== '';
            })
            ->values();

        $this->assertCount(count($expectedOutput), $outputLines, "Mismatch in the number of output lines.");
        foreach ($expectedOutput as $index => $expectedCommission) {
            $this->assertEquals($expectedCommission, trim($outputLines[$index]), "Mismatch on line " . ($index + 1));
        }
    }

    public function testDepositCommissionCalculation()
    {
        $sampleCSVContent = <<<CSV
2023-08-25,1,private,deposit,100,USD
CSV;

        file_put_contents(base_path('tests/Data/sample_deposit.csv'), $sampleCSVContent);

        ob_start();
        Artisan::call('fees:calculate', ['input' => base_path('tests/Data/sample_deposit.csv')]);
        $output = ob_get_clean();

        $outputLines = collect(explode("\n", trim($output)))->filter(function ($line) {
            return trim($line) !== '';
        });

        $this->assertEquals("0.03", trim($outputLines[0]));
    }

    public function testPrivateWithdrawCommissionCalculationWithinFreeLimit()
    {
        $sampleCSVContent = <<<CSV
2023-08-25,1,private,withdraw,500,EUR
CSV;

        file_put_contents(base_path('tests/Data/sample_withdraw_private_within_limit.csv'), $sampleCSVContent);

        ob_start();
        Artisan::call('fees:calculate', ['input' => base_path('tests/Data/sample_withdraw_private_within_limit.csv')]);
        $output = ob_get_clean();

        $this->assertEquals("0.00", trim($output));  // Since it's within the free limit
    }


    public function testPrivateWithdrawCommissionCalculationExceedFreeLimit()
    {
        $sampleCSVContent = <<<CSV
2023-08-25,1,private,withdraw,1500,EUR
CSV;

        file_put_contents(base_path('tests/Data/sample_withdraw_private_exceed_limit.csv'), $sampleCSVContent);

        ob_start();
        Artisan::call('fees:calculate', ['input' => base_path('tests/Data/sample_withdraw_private_exceed_limit.csv')]);
        $output = ob_get_clean();

        $this->assertEquals("1.50", trim($output));  // 0.003 * (1500 - 1000)
    }

    public function testBusinessWithdrawCommissionCalculation()
    {
        $sampleCSVContent = <<<CSV
2023-08-25,1,business,withdraw,1000,EUR
CSV;

        file_put_contents(base_path('tests/Data/sample_withdraw_business.csv'), $sampleCSVContent);

        ob_start();
        Artisan::call('fees:calculate', ['input' => base_path('tests/Data/sample_withdraw_business.csv')]);
        $output = ob_get_clean();

        $this->assertEquals("5.00", trim($output));  // 0.005 * 1000
    }

    public function testCurrencyConversionAndRounding()
    {
        $sampleCSVContent = <<<CSV
2023-08-25,1,private,withdraw,100,USD
CSV;

        file_put_contents(base_path('tests/Data/sample_withdraw_conversion.csv'), $sampleCSVContent);

        ob_start();
        Artisan::call('fees:calculate', ['input' => base_path('tests/Data/sample_withdraw_conversion.csv')]);
        $output = ob_get_clean();

        $convertedAmount = 100 * 1.2;  // Assuming the conversion rate from your mock is 1.2 for USD to EUR
        $commission = 0.003 * (max(0, $convertedAmount - 1000));
        $expectedOutput = number_format(ceil($commission * 100) / 100, 2, '.', '');

        $this->assertEquals((string)$expectedOutput, trim($output));
    }
}
