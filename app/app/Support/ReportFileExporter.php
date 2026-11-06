<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportFileExporter
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<list<int|float|string|null>>  $rows
     */
    public function download(
        string $filename,
        array $headings,
        iterable $rows,
        string $format,
        array $numberPrecisions = [],
    ): StreamedResponse {
        return $format === 'csv'
            ? $this->csv($filename, $headings, $rows, $numberPrecisions)
            : $this->xlsx($filename, $headings, $rows, $numberPrecisions);
    }

    /**
     * @param  list<string>  $headings
     * @param  iterable<list<int|float|string|null>>  $rows
     */
    private function csv(string $filename, array $headings, iterable $rows, array $numberPrecisions): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows, $numberPrecisions): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headings, ',', '"', '');
            foreach ($rows as $row) {
                foreach ($numberPrecisions as $index => $precision) {
                    if (isset($row[$index]) && (is_int($row[$index]) || is_float($row[$index]))) {
                        $row[$index] = number_format((float) $row[$index], $precision, '.', '');
                    }
                }
                fputcsv($output, array_map($this->safeCsvValue(...), $row), ',', '"', '');
            }
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $headings
     * @param  iterable<list<int|float|string|null>>  $rows
     */
    private function xlsx(string $filename, array $headings, iterable $rows, array $numberPrecisions): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows, $numberPrecisions): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Reporte');

            foreach ($headings as $index => $heading) {
                $sheet->setCellValueExplicit(
                    [$index + 1, 1],
                    $heading,
                    DataType::TYPE_STRING,
                );
            }

            $rowNumber = 2;
            foreach ($rows as $row) {
                foreach ($row as $index => $value) {
                    $coordinate = [$index + 1, $rowNumber];
                    if (is_int($value) || is_float($value)) {
                        $sheet->setCellValue($coordinate, $value);
                    } else {
                        $sheet->setCellValueExplicit(
                            $coordinate,
                            (string) ($value ?? ''),
                            DataType::TYPE_STRING,
                        );
                    }
                }
                $rowNumber++;
            }

            if ($rowNumber > 2) {
                foreach ($numberPrecisions as $index => $precision) {
                    $column = Coordinate::stringFromColumnIndex($index + 1);
                    $format = $precision === 0 ? '#,##0' : '#,##0.'.str_repeat('0', $precision);
                    $sheet->getStyle("{$column}2:{$column}".($rowNumber - 1))
                        ->getNumberFormat()
                        ->setFormatCode($format);
                }
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function safeCsvValue(int|float|string|null $value): int|float|string
    {
        if (! is_string($value) || is_numeric($value)) {
            return $value ?? '';
        }

        return preg_match('/^[\t\r ]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
