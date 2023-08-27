<?php

namespace App\Console\Commands;

use App\Services\CurrencyConverter;
use DateTime;
use Exception;
use Illuminate\Console\Command;

class CalculateFees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fees:calculate {input}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate commission fees based on CSV input';

    protected array $userOperations = [];
    protected CurrencyConverter $converter;

    public function __construct(CurrencyConverter $converter)
    {
        parent::__construct();
        $this->converter = $converter;
    }

    /**
     * Execute the console command.
     * @throws Exception
     */
    public function handle()
    {
        $filePath = $this->argument('input');
        if (!file_exists($filePath)) {
            $this->error("File does not exist: {$filePath}");
            return;
        }

        $this->processCSV($filePath);
        foreach ($this->userOperations as $operations) {
            foreach ($operations as $operation) {
                echo number_format($this->calculateCommission($operation), 2, '.', '') . "\n";
            }
        }
    }

    protected function processCSV($filePath): void
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Unable to read the file: {$filePath}");
            return;
        }

        while (($row = fgetcsv($handle)) !== false) {
            $operation = [
                'date' => $row[0],
                'user_id' => (int)$row[1],
                'user_type' => $row[2],
                'operation_type' => $row[3],
                'amount' => (float)$row[4],
                'currency' => $row[5]
            ];

            $this->userOperations[$operation['user_id']][] = $operation;
        }
        fclose($handle);
    }

    /**
     * @throws Exception
     */
    protected function calculateCommission($operation): float
    {
        $commission = 0;

        if ($operation['operation_type'] === 'deposit') {
            $commission = $operation['amount'] * 0.0003;
        } elseif ($operation['operation_type'] === 'withdraw') {
            $rate = $operation['currency'] !== 'EUR'
                ? $this->converter->getConversionRate($operation['currency'])
                : 1;

            $amountInEur = $operation['amount'] * $rate;

            if ($operation['user_type'] === 'private') {
                $commission = $this->calculatePrivateWithdrawCommission($operation, $amountInEur);
            } elseif ($operation['user_type'] === 'business') {
                $commission = $operation['amount'] * 0.005;
            }
        }

        return $this->roundUp($commission, $operation['currency']);
    }

    /**
     * @throws Exception
     */
    protected function calculatePrivateWithdrawCommission($operation, $amountInEur): float
    {
        $commission = $operation['amount'] * 0.003;

        $weeklyWithdrawals = $this->countWeekWithdrawals($operation);
        if ($weeklyWithdrawals <= 3) {
            $chargeableAmountEur = max(0, $amountInEur - 1000);

            $rate = $operation['currency'] !== 'EUR'
                ? $this->converter->getConversionRate($operation['currency'])
                : 1;

            $chargeableAmount = $chargeableAmountEur * $rate;
            $commission = $chargeableAmount * 0.003;
        }

        return $commission;
    }

    /**
     * @throws Exception
     */
    protected function countWeekWithdrawals($operation): int
    {
        $date = new DateTime($operation['date']);
        $weekStart = clone $date;
        $weekStart->modify('last monday');
        $weekEnd = clone $date;
        $weekEnd->modify('next sunday');

        $withdrawalsCount = 0;

        if (isset($this->userOperations[$operation['user_id']])) {
            foreach ($this->userOperations[$operation['user_id']] as $userOperation) {
                $operationDate = new DateTime($userOperation['date']);
                if (
                    $userOperation['operation_type'] === 'withdraw'
                    && $operationDate >= $weekStart
                    && $operationDate <= $weekEnd
                ) {
                    $withdrawalsCount++;
                }
            }
        }

        return $withdrawalsCount;
    }

    protected function roundUp($value, $currency): float
    {
        return match ($currency) {
            'JPY' => ceil($value),
            default => ceil($value * 100) / 100,
        };
    }
}
