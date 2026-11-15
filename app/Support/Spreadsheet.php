<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Tiny CSV / XLSX reader & writer for bulk orders (no extra package).
 * Every cell is read as TEXT so IMEIs keep their leading zeros.
 */
class Spreadsheet
{
    public const MAX_COLUMNS = 100;

    public const MAX_SHEET_BYTES = 20 * 1024 * 1024; // unzipped sheet limit (zip-bomb guard)

    /** @return list<list<string>> rows of cells */
    public static function read(string $path, string $extension, int $maxRows): array
    {
        return strtolower($extension) === 'xlsx'
            ? self::readXlsx($path, $maxRows)
            : self::readCsv($path, $maxRows);
    }

    public static function readCsv(string $path, int $maxRows): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);                        // UTF-8 BOM
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE,Windows-1252,ISO-8859-1');
        }

        $first = strtok($raw, "\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($first, $d))->first();

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);

        $rows = [];
        while (($cells = fgetcsv($fh, 0, $delimiter, '"', '')) !== false) {
            if ($cells === [null]) {
                continue;
            }
            $rows[] = array_map(fn ($c) => trim((string) $c), $cells);
            if (count($rows) > $maxRows + 1) {
                break; // header + max rows, the caller reports "too many"
            }
        }
        fclose($fh);

        return $rows;
    }

    public static function readXlsx(string $path, int $maxRows): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('This .xlsx file could not be opened.');
        }

        try {
            $sheetPath = self::firstSheetPath($zip);
            $stat = $zip->statName($sheetPath);
            if (! $stat || $stat['size'] > self::MAX_SHEET_BYTES) {
                throw new RuntimeException('The sheet is missing or too large.');
            }

            $strings = [];
            if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                if (strlen($ss) > self::MAX_SHEET_BYTES) {
                    throw new RuntimeException('The file is too large.');
                }
                foreach (self::xml($ss)->si as $si) {
                    $strings[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: []));
                }
            }

            $sheet = self::xml((string) $zip->getFromName($sheetPath));
        } finally {
            $zip->close();
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::colIndex(preg_replace('/\d+/', '', (string) $c['r']));
                if ($col > self::MAX_COLUMNS) {
                    continue; // a crafted "ZZZZZZ1" cell must not allocate millions of columns
                }
                $type = (string) $c['t'];
                $v = match ($type) {
                    's' => $strings[(int) $c->v] ?? '',
                    'inlineStr' => (string) ($c->is->t ?? ''),
                    'b' => ((string) $c->v) === '1' ? 'TRUE' : 'FALSE',
                    default => self::numberAsText((string) $c->v),
                };
                $cells[$col] = trim($v);
            }
            if ($cells) {
                $line = array_fill(0, max(array_keys($cells)) + 1, '');
                foreach ($cells as $i => $v) {
                    $line[$i] = $v;
                }
                $rows[] = $line;
            } else {
                $rows[] = [];
            }
            if (count($rows) > $maxRows + 1) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Stop CSV/formula injection: a text cell starting with = + - @ (or tab/CR)
     * would run as a formula when opened in Excel. Numbers are left alone.
     */
    public static function safeCell(mixed $v): mixed
    {
        if (! is_string($v) || $v === '' || is_numeric($v)) {
            return $v;
        }

        return in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }

    /** @param  array<int, mixed>  $row */
    public static function safeRow(array $row): array
    {
        return array_map([self::class, 'safeCell'], $row);
    }

    /** CSV download (UTF-8 BOM so Excel shows Burmese / symbols correctly). */
    public static function csv(array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($fh, self::safeRow($r), ',', '"', '');
        }
        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);

        return $out;
    }

    /** Minimal .xlsx where every column is Text (keeps leading zeros when people type IMEIs). */
    public static function xlsx(array $rows): string
    {
        $cols = max(1, ...array_map('count', $rows ?: [[1]]));
        $sheetRows = '';
        foreach ($rows as $r => $cells) {
            $sheetRows .= '<row r="'.($r + 1).'">';
            foreach (array_values($cells) as $i => $v) {
                $sheetRows .= '<c r="'.self::colName($i).($r + 1).'" t="inlineStr" s="'.($r === 0 ? 2 : 1).'"><is><t xml:space="preserve">'.htmlspecialchars((string) $v, ENT_XML1).'</t></is></c>';
            }
            $sheetRows .= '</row>';
        }

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Orders" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            // style 1 = Text (@), style 2 = Text + bold (header)
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="49" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="'.$cols.'" width="24" style="1" customWidth="1"/></cols><sheetData>'.$sheetRows.'</sheetData></worksheet>',
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $out = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $out;
    }

    // ------------------------------------------------------------------ helpers

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $book = self::xml($wb);
        $book->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $first = $book->xpath('//m:sheets/m:sheet')[0] ?? null;
        $rid = $first ? (string) ($first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '') : '';

        foreach (self::xml($rels)->Relationship as $r) {
            if ((string) $r['Id'] === $rid) {
                $target = ltrim((string) $r['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function xml(string $xml): \SimpleXMLElement
    {
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        libxml_use_internal_errors($prev);

        if ($doc === false) {
            throw new RuntimeException('The file is not a valid Excel workbook.');
        }

        return $doc;
    }

    /** 3.56938035643809E+14 → 356938035643809, 12.0 → 12 */
    private static function numberAsText(string $v): string
    {
        if (preg_match('/^-?\d+(\.\d+)?E[+-]?\d+$/i', $v)) {
            return number_format((float) $v, 0, '', '');
        }
        if (preg_match('/^-?\d+\.0+$/', $v)) {
            return explode('.', $v)[0];
        }

        return $v;
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }

    public static function colName(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }
}
