<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use App\Support\Money;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The revenue lines of a period as a CSV, written chunk by chunk as they are
 * read so a long period never sits in memory.
 */
final class RevenueCsvExporter
{
    private const array COLUMNS = ['date', 'transaction', 'type', 'reference_type', 'reference', 'ad', 'ad_title', 'party', 'credit', 'debit', 'net'];

    public function __construct(private readonly RevenueReportService $service) {}

    public function download(RevenueWindow $window): StreamedResponse
    {
        $accountId = $this->service->revenueAccountId();
        $name = 'revenue-' . $window->start->toDateString() . '-' . $window->end->subSecond()->toDateString() . '.csv';

        return response()->streamDownload(function () use ($window, $accountId): void {
            $out = fopen('php://output', 'w') ?: throw new RuntimeException('The response stream could not be opened.');
            // The byte-order mark lets Excel read the Arabic headings as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $this->headings());

            foreach ($this->service->exportLines($window, $accountId) as $chunk) {
                foreach ($chunk as $line) {
                    fputcsv($out, $this->row($line));
                }
                flush();
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * @return list<string>
     */
    private function headings(): array
    {
        return array_map(static fn (string $key): string => __('admin.revenue.export_columns.' . $key), self::COLUMNS);
    }

    /**
     * @return list<string>
     */
    private function row(RevenueLine $line): array
    {
        return [
            $line->at->format('Y-m-d H:i:s'),
            $line->id,
            $line->type,
            (string) $line->referenceType,
            (string) $line->referenceId,
            (string) $line->adId,
            $this->safe((string) $line->adTitle),
            (string) $line->partyId,
            $line->credit,
            $line->debit,
            Money::subtract($line->credit, $line->debit),
        ];
    }

    /**
     * Seller-written text must not turn into a spreadsheet formula.
     */
    private function safe(string $text): string
    {
        return $text !== '' && str_contains("=+-@\t\r", $text[0]) ? "'" . $text : $text;
    }
}
