<?php
/**
 * Minimal, dependency-free .xlsx reader.
 *
 * An .xlsx file is a zip of XML parts. For the budget importer we only need to
 * read cell values from the first worksheet, so this reads sharedStrings + the
 * first sheet and returns a grid keyed by row number -> column letter -> value.
 * It intentionally does NOT handle styles, formulas' cached types beyond strings,
 * multiple sheets, or dates-as-numbers (the importer works off text + numbers).
 *
 * Avoids a PhpSpreadsheet dependency (which would add ~50 packages to the cPanel
 * deploy) since the budget template is small and consistent.
 */
class XlsxReader
{
    /**
     * Read the first worksheet of an .xlsx file.
     *
     * @param string $path Path to the .xlsx file
     * @return array<int, array<string, string>> rows[rowNum][colLetter] = value
     * @throws RuntimeException on unreadable/invalid file
     */
    public static function read($path)
    {
        if (!is_readable($path)) {
            throw new RuntimeException("Cannot read file: $path");
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Not a valid .xlsx (zip) file: $path");
        }

        try {
            $shared = self::readSharedStrings($zip);
            $sheetXml = self::readFirstSheetXml($zip);
        } finally {
            $zip->close();
        }

        return self::parseSheet($sheetXml, $shared);
    }

    private static function readSharedStrings(ZipArchive $zip)
    {
        $data = $zip->getFromName('xl/sharedStrings.xml');
        if ($data === false) {
            return [];
        }
        $xml = @simplexml_load_string($data);
        if ($xml === false) {
            return [];
        }
        $strings = [];
        foreach ($xml->si as $si) {
            $strings[] = self::extractText($si);
        }
        return $strings;
    }

    /** A shared-string <si> is either a plain <t> or rich text made of <r><t> runs. */
    private static function extractText(SimpleXMLElement $si)
    {
        if (isset($si->t)) {
            return (string) $si->t;
        }
        $text = '';
        foreach ($si->r as $run) {
            $text .= (string) $run->t;
        }
        return $text;
    }

    private static function readFirstSheetXml(ZipArchive $zip)
    {
        // Prefer the workbook-declared first sheet; fall back to sheet1.xml.
        $candidates = ['xl/worksheets/sheet1.xml'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $candidates[] = $name;
            }
        }
        foreach ($candidates as $name) {
            $data = $zip->getFromName($name);
            if ($data !== false) {
                return $data;
            }
        }
        throw new RuntimeException('No worksheet found in workbook.');
    }

    private static function parseSheet($sheetXml, array $shared)
    {
        $xml = @simplexml_load_string($sheetXml);
        if ($xml === false || !isset($xml->sheetData)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $rowNum = (int) $row['r'];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];              // e.g. "C14"
                $col = preg_replace('/\d+/', '', $ref); // "C"
                $type = (string) $c['t'];
                $value = self::cellValue($c, $type, $shared);
                if ($value !== null && $value !== '') {
                    $rows[$rowNum][$col] = $value;
                }
            }
        }
        return $rows;
    }

    private static function cellValue(SimpleXMLElement $c, $type, array $shared)
    {
        switch ($type) {
            case 's': // shared string
                $idx = (int) $c->v;
                return $shared[$idx] ?? '';
            case 'inlineStr':
                return isset($c->is) ? self::extractText($c->is) : '';
            case 'str': // formula result string
                return (string) $c->v;
            case 'b': // boolean
                return ((string) $c->v) === '1' ? 'TRUE' : 'FALSE';
            default: // number (may be a date serial; importer doesn't rely on those)
                return isset($c->v) ? (string) $c->v : null;
        }
    }
}
