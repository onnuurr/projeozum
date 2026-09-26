<?php

namespace Modules\Finance\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Finance\Services\TenantPurchaseReportService;
use Modules\Product\Models\Order;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Bayi Satış Raporu'nun Excel çıktısı — ekrandaki filtrelerle aynı veri, dört sayfa:
 * Bayiler, Kanallar, Ürünler, Siparişler.
 */
class TenantSalesReportExport implements WithMultipleSheets
{
    public function __construct(
        private TenantPurchaseReportService $report,
        private array $filters,
    ) {}

    public function sheets(): array
    {
        $statuses = Order::statuses();

        return [
            $this->sheet('Bayiler',
                ['Bayi', 'Kod', 'Sipariş', 'Brüt Ciro', 'İade', 'Net Ciro', 'İade Oranı %', 'Ort. Sipariş', 'Son Sipariş', 'Cari Limit', 'Cari Bakiye'],
                $this->report->byTenant($this->filters)->map(fn ($r) => [
                    $r['tenant'], $r['tenantCode'], $r['orderCount'], $r['grossAmount'], $r['refundedAmount'], $r['netAmount'],
                    $r['returnRate'], $r['averageOrder'], $r['lastOrderAt'], $r['creditLimit'], $r['balance'],
                ])->all(),
            ),
            $this->sheet('Kanallar',
                ['Kanal', 'Sipariş', 'Brüt Ciro', 'Pay %'],
                $this->report->byChannel($this->filters)->map(fn ($r) => [$r['label'], $r['orderCount'], $r['grossAmount'], $r['share']])->all(),
            ),
            $this->sheet('Ürünler',
                ['Ürün', 'Marka', 'Adet', 'Sipariş', 'Tutar'],
                $this->report->byProduct($this->filters)->map(fn ($r) => [$r['productName'], $r['brand'], $r['qty'], $r['orderCount'], $r['totalAmount']])->all(),
            ),
            $this->sheet('Siparişler',
                ['Tarih', 'Sipariş No', 'Bayi', 'Kod', 'Kanal', 'Ödeme', 'Kalem', 'Tutar', 'Durum'],
                $this->report->list($this->filters)->map(fn ($r) => [
                    $r['createdAt'], $r['orderNo'], $r['tenant'], $r['tenantCode'], $r['channel'], $r['payment'],
                    $r['itemCount'], $r['amount'], $statuses[$r['status']] ?? $r['status'],
                ])->all(),
            ),
        ];
    }

    private function sheet(string $title, array $headings, array $rows): object
    {
        return new class($title, $headings, $rows) implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
        {
            public function __construct(private string $title, private array $headings, private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }

            public function title(): string
            {
                return $this->title;
            }

            public function styles(Worksheet $sheet): array
            {
                return [1 => ['font' => ['bold' => true]]];
            }
        };
    }
}
