<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Application\Bncc\ImportException;
use RuntimeException;

final class PopplerPdfExtractor
{
    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly int $maxPages = 1000,
    ) {
    }

    /** @return array{pages: list<array{page_number: int, source_text: string, detected_skill_codes: list<string>}>,
     *      extractor_version: string, warnings: array<string, mixed>}
     */
    public function extract(string $absolutePath): array
    {
        $info = $this->runner->run(['/usr/bin/pdfinfo', $absolutePath], 15, 65536);
        if ($info['exit_code'] !== 0 || !preg_match('/^Pages:\s+(\d+)\s*$/m', $info['stdout'], $matches)) {
            throw new ImportException('invalid_pdf', 'Não foi possível ler o PDF. Verifique se ele está íntegro e sem senha.');
        }
        if (preg_match('/^Encrypted:\s+yes\b/m', $info['stdout'])) {
            throw new ImportException('encrypted_pdf', 'Use uma cópia do PDF sem criptografia ou senha.');
        }
        $count = (int) $matches[1];
        if ($count < 1 || $count > $this->maxPages) {
            throw new ImportException('page_limit', "O PDF deve ter de 1 a {$this->maxPages} páginas.");
        }

        $result = $this->runner->run([
            '/usr/bin/pdftotext', '-layout', '-enc', 'UTF-8', '-eol', 'unix', $absolutePath, '-',
        ], 90, 32 * 1024 * 1024);
        if ($result['exit_code'] !== 0) {
            throw new ImportException('invalid_pdf', 'O extrator não conseguiu ler todo o texto do PDF.');
        }
        $text = $result['stdout'];
        if (preg_match('//u', $text) !== 1) {
            throw new ImportException('invalid_encoding', 'O texto extraído não possui codificação UTF-8 válida.');
        }
        // O form feed é a fronteira física emitida pelo Poppler, inclusive para páginas vazias.
        $texts = explode("\f", $text);
        if (end($texts) === '') {
            array_pop($texts);
        }
        if (count($texts) !== $count) {
            throw new ImportException('page_mismatch', 'A quantidade de páginas extraídas não confere com o PDF.');
        }

        $pages = [];
        $emptyPages = [];
        foreach ($texts as $index => $pageText) {
            if (strlen($pageText) > 1024 * 1024) {
                throw new ImportException('extraction_limit', 'Uma página excedeu o limite de texto permitido.');
            }
            // Preservar colunas, linhas, hifens e acentos: não reconstruir frases por adivinhação.
            $pageText = str_replace(["\r\n", "\r"], "\n", $pageText);
            $pageText = rtrim(preg_replace('/[\t ]+$/m', '', $pageText), "\n");
            preg_match_all('/\bEF[0-9]{2}[A-Z]{2}[0-9]{2}\b/', $pageText, $codes);
            $pages[] = [
                'page_number' => $index + 1, 'source_text' => $pageText,
                'detected_skill_codes' => array_values(array_unique($codes[0])),
            ];
            if (trim($pageText) === '') {
                $emptyPages[] = $index + 1;
            }
        }
        if (count($emptyPages) === $count) {
            throw new ImportException('no_text', 'Este PDF não contém texto extraível. Ele pode precisar de OCR, que ainda não está implementado.');
        }

        $version = $this->runner->run(['/usr/bin/pdftotext', '-v'], 5, 4096);
        if (!preg_match('/pdftotext version ([\w.\-]+)/', $version['stderr'] . $version['stdout'], $match)) {
            throw new RuntimeException('Não foi possível identificar a versão do extrator.');
        }

        return [
            'pages' => $pages,
            'extractor_version' => 'poppler-' . $match[1] . '/layout-v1',
            'warnings' => [
                'empty_pages' => $emptyPages,
                'parser_reported_warnings' => trim($info['stderr'] . $result['stderr']) !== '',
                'requires_curriculum_review' => true,
            ],
        ];
    }
}
