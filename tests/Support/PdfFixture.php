<?php

declare(strict_types=1);

namespace Tests\Support;

final class PdfFixture
{
    /** PDF mínimo, sintético e sem dependência de um gerador de provas.
     *  @param list<string> $pages Texto WinAnsi por página, convertido de UTF-8.
     */
    public static function bytes(array $pages): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        foreach ($pages as $index => $text) {
            $pageId = 4 + 2 * $index;
            $contentId = $pageId + 1;
            $kids[] = "$pageId 0 R";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
                . "/Resources << /Font << /F1 3 0 R >> >> /Contents $contentId 0 R >>";
            $encoded = iconv('UTF-8', 'Windows-1252', $text);
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
            $content = "BT /F1 12 Tf 40 780 Td ($escaped) Tj ET";
            $objects[$contentId] = '<< /Length ' . strlen($content) . ">>\nstream\n$content\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Count ' . count($pages) . ' /Kids [' . implode(' ', $kids) . '] >>';
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 $size\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        return $pdf . "trailer\n<< /Size $size /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
