<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ExcelHelper
 *
 * Helper terpusat untuk semua operasi baca/tulis Excel (.xlsx) di sistem treasury.
 * Menangani:
 *   - Baca file upload (.xlsx / .xls / .csv) → array of rows
 *   - Tulis file download dengan styling korporat (header biru tua + gold)
 *   - Tambah dropdown validation per kolom
 */
class ExcelHelper
{
    // Warna tema (disesuaikan dengan dashboard)
    const COLOR_HEADER_BG   = '0A1628';  // navy
    const COLOR_HEADER_FONT = 'C9A96E';  // gold
    const COLOR_SUBHEADER   = '112240';  // navy-mid
    const COLOR_LOCKED_BG   = 'F0F4F8';  // abu muda — kolom read-only
    const COLOR_INPUT_BG    = 'FFFEF5';  // kuning sangat muda — kolom yang harus diisi
    const COLOR_BORDER      = 'CBD5E0';

    // ── Baca file upload → array of associative rows ──────────────────────────
    /**
     * @param  string  $filePath  Path file yang diupload (dari $request->file()->getRealPath())
     * @return array   [ 'headers' => [...], 'rows' => [ ['kolom' => 'nilai', ...], ... ] ]
     */
    public static function read(string $filePath): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Untuk CSV, pakai reader native PHP agar tidak bergantung PhpSpreadsheet
        if ($ext === 'csv' || $ext === 'txt') {
            return self::readCsv($filePath);
        }

        $reader      = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return ['headers' => [], 'rows' => []];
        }

        // Cari baris header yang sesungguhnya: lewati baris metadata (judul/merged) yang hanya
        // memiliki 1 sel terisi — baris header kolom sesungguhnya memiliki ≥2 sel terisi.
        $rawHeaders = null;
        while (! empty($rows)) {
            $candidate = array_shift($rows);
            $nonEmpty  = count(array_filter(array_map('strval', $candidate), fn($v) => trim($v) !== ''));
            if ($nonEmpty >= 2) {
                $rawHeaders = $candidate;
                break;
            }
        }

        if ($rawHeaders === null) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(fn($h) => self::normalizeKey((string) $h), $rawHeaders);

        $result = [];
        foreach ($rows as $row) {
            // Skip baris benar-benar kosong
            $values = array_map('strval', $row);
            if (count(array_filter($values, fn($v) => trim($v) !== '')) === 0) {
                continue;
            }
            $result[] = array_combine($headers, $values);
        }

        return ['headers' => $headers, 'rows' => $result];
    }

    private static function readCsv(string $filePath): array
    {
        $handle  = fopen($filePath, 'r');
        $rawHeaders = fgetcsv($handle);
        if (! $rawHeaders) {
            fclose($handle);
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(fn($h) => self::normalizeKey(
            preg_replace('/[\x{FEFF}\x{200B}]/u', '', $h)
        ), $rawHeaders);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue;
            // Pad jika kolom kurang (baris tidak lengkap)
            while (count($row) < count($headers)) $row[] = '';
            $rows[] = array_combine($headers, array_map('strval', $row));
        }

        fclose($handle);
        return ['headers' => $headers, 'rows' => $rows];
    }

    // ── Tulis file download ────────────────────────────────────────────────────
    /**
     * Buat response download file .xlsx dengan styling.
     *
     * @param  string  $filename   Nama file tanpa ekstensi
     * @param  array   $columns    [ ['key'=>'bankCode','label'=>'Kode Bank','width'=>15,'locked'=>true,'note'=>'...'], ... ]
     * @param  array   $rows       [ ['bankCode'=>'BMRI', ...], ... ]
     * @param  string  $sheetTitle Judul sheet (muncul di tab bawah Excel)
     * @param  array   $meta       Info tambahan di header sheet: ['Periode'=>'...', 'Tanggal Export'=>'...']
     */
    public static function download(
        string $filename,
        array  $columns,
        array  $rows,
        string $sheetTitle = 'Data',
        array  $meta = []
    ): StreamedResponse {
        $spreadsheet = self::buildSpreadsheet($columns, $rows, $sheetTitle, $meta);

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        });

        $response->headers->set('Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition',
            'attachment; filename="' . $filename . '_' . now()->format('Ymd_His') . '.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    // ── Builder internal ───────────────────────────────────────────────────────
    private static function buildSpreadsheet(
        array  $columns,
        array  $rows,
        string $sheetTitle,
        array  $meta
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        $colCount = count($columns);
        $lastCol  = self::colLetter($colCount);

        // ── Blok info di baris 1–N ──────────────────────────────────────────
        $infoRow = 1;

        // Judul
        $sheet->mergeCells("A{$infoRow}:{$lastCol}{$infoRow}");
        $sheet->setCellValue("A{$infoRow}", 'TREASURY DASHBOARD — ' . strtoupper($sheetTitle));
        $sheet->getStyle("A{$infoRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => self::COLOR_HEADER_FONT]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::COLOR_HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension($infoRow)->setRowHeight(28);
        $infoRow++;

        // Meta info (Periode, Tanggal Export, dll.)
        foreach ($meta as $label => $value) {
            $sheet->mergeCells("A{$infoRow}:{$lastCol}{$infoRow}");
            $sheet->setCellValue("A{$infoRow}", "{$label}: {$value}");
            $sheet->getStyle("A{$infoRow}")->applyFromArray([
                'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => 'AABBCC']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::COLOR_SUBHEADER]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                                'indent' => 1],
            ]);
            $infoRow++;
        }

        // Legenda warna
        $sheet->mergeCells("A{$infoRow}:{$lastCol}{$infoRow}");
        $sheet->setCellValue("A{$infoRow}",
            '⚠  Kolom berlatar kuning WAJIB diisi. Kolom berlatar abu-abu adalah referensi — jangan diubah.');
        $sheet->getStyle("A{$infoRow}")->applyFromArray([
            'font'      => ['size' => 9, 'color' => ['rgb' => '7A5C00'], 'bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFFACD']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
        ]);
        $infoRow++;

        $headerRow = $infoRow;

        // ── Baris header kolom ──────────────────────────────────────────────
        foreach ($columns as $i => $col) {
            $cellRef = self::colLetter($i + 1) . $headerRow;
            $sheet->setCellValue($cellRef, $col['label']);

            $bgColor = ($col['locked'] ?? false) ? '1A3A5C' : '0A2540';

            $sheet->getStyle($cellRef)->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10,
                                'color' => ['rgb' => self::COLOR_HEADER_FONT]],
                'fill'      => ['fillType' => Fill::FILL_SOLID,
                                'color'    => ['rgb' => $bgColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                                'vertical'   => Alignment::VERTICAL_CENTER,
                                'wrapText'   => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                                  'color'       => ['rgb' => '2D5A8E']]],
            ]);

            // Lebar kolom
            $width = $col['width'] ?? 18;
            $sheet->getColumnDimension(self::colLetter($i + 1))->setWidth($width);

            // Note/komentar di header
            if (! empty($col['note'])) {
                $comment = $sheet->getComment($cellRef);
                $comment->getText()->createTextRun($col['note']);
                $comment->setVisible(false);
            }
        }

        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        // ── Baris data ──────────────────────────────────────────────────────
        $dataStartRow = $headerRow + 1;
        foreach ($rows as $rIdx => $row) {
            $excelRow = $dataStartRow + $rIdx;

            foreach ($columns as $cIdx => $col) {
                $colLetter = self::colLetter($cIdx + 1);
                $cellRef   = $colLetter . $excelRow;
                $value     = $row[$col['key']] ?? '';

                // Kolom bertanda 'text' selalu disimpan sebagai string (nomor rekening dll.)
                if (!($col['text'] ?? false) && is_numeric($value) && $value !== '') {
                    $sheet->setCellValueExplicit(
                        $cellRef, $value,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                    );
                } else {
                    $sheet->setCellValueExplicit(
                        $cellRef, $value,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                    );
                }

                $isLocked = $col['locked'] ?? false;
                $bgColor  = $isLocked ? self::COLOR_LOCKED_BG : self::COLOR_INPUT_BG;

                $sheet->getStyle($cellRef)->applyFromArray([
                    'fill'    => ['fillType' => Fill::FILL_SOLID,
                                  'color'    => ['rgb' => $bgColor]],
                    'font'    => ['size' => 10,
                                  'color' => ['rgb' => $isLocked ? '666666' : '000000']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                                    'color'       => ['rgb' => self::COLOR_BORDER]]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Format number cells
                if (isset($col['format'])) {
                    $sheet->getStyle($cellRef)
                          ->getNumberFormat()
                          ->setFormatCode($col['format']);
                }
            }
        }

        // ── Freeze pane di baris pertama data ───────────────────────────────
        $sheet->freezePane('A' . $dataStartRow);

        // ── Auto filter pada header row ──────────────────────────────────────
        if (! empty($rows)) {
            $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$headerRow}");
        }

        // ── Proteksi kolom locked ────────────────────────────────────────────
        // Kolom locked dibuat read-only agar tidak tidak tidak diubah
        $sheet->getProtection()->setSheet(false); // tidak lock sheet, hanya visual

        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        return $spreadsheet;
    }

    // ── Utility ───────────────────────────────────────────────────────────────
    /** Konversi index kolom (1-based) ke huruf Excel: 1→A, 26→Z, 27→AA */
    public static function colLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $rem    = ($index - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $index  = (int) (($index - $rem - 1) / 26);
        }
        return $letter;
    }

    /** Normalisasi header key: "Bank Code" → "bankcode", "rateActual" → "rateactual" */
    public static function normalizeKey(string $key): string
    {
        // Hapus BOM dan karakter tidak terlihat
        $key = preg_replace('/[\x{FEFF}\x{200B}\x{00A0}]/u', '', $key);
        // Trim whitespace
        $key = trim($key);
        // Lowercase dan hapus spasi
        return strtolower(str_replace([' ', '_', '-'], '', $key));
    }

    /** Map normalizeKey → nama kolom asli untuk lookup */
    public static function buildColumnIndex(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $h) {
            $map[self::normalizeKey($h)] = $i;
        }
        return $map;
    }
}
