<?php

namespace App\Support\Analytics;

class AnalyticsReportBuilder
{
    public static function fromStore(array $payload): array
    {
        $period = $payload['period'] ?? [];
        $store = $payload['store'] ?? [];

        $sections = [];
        self::addKeyValueSection($sections, 'Key Metrics', $payload['kpis'] ?? []);
        self::addTimeSeriesSection($sections, 'Revenue Over Time', $payload['sales_overview']['revenue_over_time'] ?? []);
        self::addTimeSeriesSection($sections, 'Orders Over Time', $payload['sales_overview']['orders_over_time'] ?? []);
        self::addStatusSection($sections, 'Orders by Status', $payload['orders_by_status'] ?? []);
        self::addTableSection(
            $sections,
            'Top Products',
            ['Product', 'Quantity Sold', 'Revenue'],
            collect($payload['top_products'] ?? [])->map(fn ($row) => [
                $row['name'] ?? '',
                $row['quantity_sold'] ?? 0,
                $row['revenue'] ?? 0,
            ])->all()
        );

        return self::report(
            'Store Analytics Report',
            $store['name'] ?? 'Store',
            $period,
            $sections
        );
    }

    public static function fromService(array $payload): array
    {
        $period = $payload['period'] ?? [];
        $service = $payload['service'] ?? [];

        $sections = [];
        self::addKeyValueSection($sections, 'Key Metrics', $payload['kpis'] ?? []);
        self::addTimeSeriesSection($sections, 'Bookings Over Time', $payload['booking_analytics']['bookings_over_time'] ?? []);
        self::addStatusSection($sections, 'Bookings by Status', $payload['booking_analytics']['bookings_by_status'] ?? []);
        self::addTimeSeriesSection($sections, 'Revenue Over Time', $payload['revenue_analytics']['revenue_over_time'] ?? []);
        self::addTableSection(
            $sections,
            'Most Requested Services',
            ['Service', 'Bookings', 'Revenue'],
            collect($payload['service_performance']['most_requested_services'] ?? [])->map(fn ($row) => [
                $row['name'] ?? '',
                $row['bookings_count'] ?? 0,
                $row['revenue'] ?? 0,
            ])->all()
        );
        self::addTableSection(
            $sections,
            'Least Requested Services',
            ['Service', 'Bookings', 'Revenue'],
            collect($payload['service_performance']['least_requested_services'] ?? [])->map(fn ($row) => [
                $row['name'] ?? '',
                $row['bookings_count'] ?? 0,
                $row['revenue'] ?? 0,
            ])->all()
        );
        self::addTableSection(
            $sections,
            'Employee Performance',
            ['Employee', 'Bookings', 'Completed', 'Revenue'],
            collect($payload['employee_performance'] ?? [])->map(fn ($row) => [
                $row['name'] ?? '',
                $row['bookings_count'] ?? 0,
                $row['completed_bookings'] ?? 0,
                $row['revenue'] ?? 0,
            ])->all()
        );

        return self::report(
            'Service Analytics Report',
            $service['name'] ?? 'Service',
            $period,
            $sections
        );
    }

    public static function fromAdmin(array $payload): array
    {
        $period = $payload['period'] ?? [];

        $sections = [];
        self::addKeyValueSection($sections, 'Platform KPIs', $payload['kpis'] ?? []);
        self::addTimeSeriesSection($sections, 'User Registrations Over Time', $payload['user_analytics']['registrations_over_time'] ?? []);
        self::addTableSection(
            $sections,
            'Users by Role',
            ['Role', 'Count'],
            collect($payload['user_analytics']['users_by_role'] ?? [])->map(fn ($row) => [
                $row['role'] ?? '',
                $row['count'] ?? 0,
            ])->all()
        );
        self::addTimeSeriesSection($sections, 'Orders Over Time', $payload['transaction_analytics']['orders_over_time'] ?? []);
        self::addTimeSeriesSection($sections, 'Bookings Over Time', $payload['transaction_analytics']['bookings_over_time'] ?? []);
        self::addStatusSection($sections, 'Orders by Status', $payload['transaction_analytics']['orders_by_status'] ?? []);
        self::addStatusSection($sections, 'Bookings by Status', $payload['transaction_analytics']['bookings_by_status'] ?? []);
        self::addTimeSeriesSection($sections, 'Platform Revenue Over Time', $payload['revenue_analytics']['platform_revenue_over_time'] ?? []);
        self::addTableSection(
            $sections,
            'Top Stores',
            ['Store', 'Orders', 'Revenue'],
            collect($payload['business_analytics']['top_stores'] ?? [])->map(fn ($row) => [
                $row['name'] ?? '',
                $row['orders_count'] ?? 0,
                $row['revenue'] ?? 0,
            ])->all()
        );
        self::addTableSection(
            $sections,
            'Top Services',
            ['Service', 'Bookings', 'Revenue'],
            collect($payload['business_analytics']['top_services'] ?? [])->map(fn ($row) => [
                $row['name'] ?? '',
                $row['bookings_count'] ?? 0,
                $row['revenue'] ?? 0,
            ])->all()
        );
        self::addKeyValueSection($sections, 'Moderation Summary', $payload['moderation_analytics']['summary'] ?? []);
        self::addStatusSection($sections, 'Reports by Status', $payload['moderation_analytics']['reports_by_status'] ?? [], 'status');

        return self::report(
            'Admin Analytics Report',
            'OneMallX Platform',
            $period,
            $sections
        );
    }

    private static function report(string $title, string $subtitle, array $period, array $sections): array
    {
        $from = $period['from'] ?? '';
        $to = $period['to'] ?? '';

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'period_label' => $from !== '' && $to !== '' ? "{$from} to {$to}" : '',
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'filename_base' => str($title)->slug('_')."_{$from}_{$to}",
            'sections' => $sections,
        ];
    }

    private static function addKeyValueSection(array &$sections, string $title, array $data): void
    {
        if ($data === []) {
            return;
        }

        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = [self::labelize((string) $key), self::formatValue($value)];
        }

        $sections[] = [
            'title' => $title,
            'type' => 'key_value',
            'headers' => ['Metric', 'Value'],
            'rows' => $rows,
        ];
    }

    private static function addTimeSeriesSection(array &$sections, string $title, array $series): void
    {
        if ($series === []) {
            return;
        }

        $sample = $series[0];
        $valueKey = collect(array_keys($sample))->first(fn ($key) => $key !== 'date') ?? 'value';
        $headers = ['Date', self::labelize($valueKey)];

        $rows = collect($series)->map(fn ($row) => [
            $row['date'] ?? '',
            self::formatValue($row[$valueKey] ?? ''),
        ])->all();

        $sections[] = [
            'title' => $title,
            'type' => 'table',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private static function addStatusSection(array &$sections, string $title, array $items, string $labelKey = 'status'): void
    {
        if ($items === []) {
            return;
        }

        $sections[] = [
            'title' => $title,
            'type' => 'table',
            'headers' => [self::labelize($labelKey), 'Count'],
            'rows' => collect($items)->map(fn ($row) => [
                self::formatValue($row[$labelKey] ?? ''),
                self::formatValue($row['count'] ?? 0),
            ])->all(),
        ];
    }

    private static function addTableSection(array &$sections, string $title, array $headers, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $sections[] = [
            'title' => $title,
            'type' => 'table',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private static function labelize(string $key): string
    {
        return str($key)->replace('_', ' ')->title()->toString();
    }

    private static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_float($value)) {
            return (string) $value;
        }

        return (string) $value;
    }
}
