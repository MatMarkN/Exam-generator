<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Application\Bncc\ImportException;
use XMLReader;

final class PopplerLayoutExtractor
{
    public function __construct(private readonly ProcessRunner $runner)
    {
    }

    /** Blocos independentes preservam colunas; coordenadas são pontos PDF (72 por polegada). */
    public function extract(string $path, int $first, int $last): array
    {
        $result = $this->runner->run([
            '/usr/bin/pdftotext', '-bbox-layout', '-cropbox', '-enc', 'UTF-8',
            '-f', (string) $first, '-l', (string) $last, $path, '-',
        ], 90, 32 * 1024 * 1024);
        if ($result['exit_code'] !== 0) {
            throw new ImportException('invalid_pdf', 'Não foi possível extrair as posições do texto.');
        }
        // Alguns títulos do PDF contêm controles que o Poppler repassa e o XML 1.0 não admite.
        // A remoção é contabilizada no relatório. O texto original por página permanece intacto.
        $xml = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x{fffe}\x{ffff}]/u', '', $result['stdout'], -1, $removed);
        if ($xml === null) {
            throw new ImportException('invalid_encoding', 'O layout extraído não é UTF-8 válido.');
        }
        unset($result['stdout']);
        $reader = new XMLReader();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $pages = [];
        $pageNumber = $first - 1;
        try {
            $reader->XML($xml, null, LIBXML_NONET);
            $reader->setParserProperty(XMLReader::LOADDTD, false);
            $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);
            while ($reader->read()) {
                $name = $reader->localName;
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    if ($name === 'page') {
                        $pageNumber++;
                        $pages[$pageNumber] = ['width' => (float) $reader->getAttribute('width'),
                            'height' => (float) $reader->getAttribute('height'), 'blocks' => []];
                    } elseif ($name === 'block') {
                        $block = [...$this->box($reader), 'lines' => []];
                    } elseif ($name === 'line') {
                        $line = $this->box($reader);
                        $words = [];
                    } elseif ($name === 'word') {
                        $words[] = $reader->readString();
                    }
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                    if ($name === 'line') {
                        $block['lines'][] = [...$line, 'text' => implode(' ', $words)];
                    } elseif ($name === 'block') {
                        $block['text'] = implode("\n", array_column($block['lines'], 'text'));
                        $pages[$pageNumber]['blocks'][] = $block;
                    }
                }
            }
            if (libxml_get_errors() !== [] || $pageNumber !== $last) {
                throw new ImportException('invalid_layout', 'O layout extraído está incompleto ou inválido.');
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $version = $this->runner->run(['/usr/bin/pdftotext', '-v'], 5, 4096);
        if (!preg_match('/pdftotext version ([\w.\-]+)/', $version['stderr'] . $version['stdout'], $match)) {
            throw new ImportException('invalid_layout', 'Não foi possível identificar a versão do extrator de layout.');
        }
        return ['pages' => $pages, 'extractor_version' => 'poppler-' . $match[1] . '/bbox-layout-cropbox-v1', 'removed_control_characters' => $removed,
            'parser_reported_warnings' => trim($result['stderr']) !== ''];
    }

    private function box(XMLReader $reader): array
    {
        $box = [];
        foreach (['xMin', 'yMin', 'xMax', 'yMax'] as $key) {
            $box[$key] = round((float) $reader->getAttribute($key), 3);
        }
        return $box;
    }
}
