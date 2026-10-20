<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Application\Bncc\ImportException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class LocalPdfStorage
{
    public const MAX_BYTES = 50 * 1024 * 1024;

    public function __construct(private readonly string $root)
    {
    }

    /** @return array{storage_path: string, absolute_path: string, sha256: string, file_size: int, original_filename: string} */
    public function receive(UploadedFileInterface $upload): array
    {
        if (in_array($upload->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || ($upload->getSize() ?? 0) > self::MAX_BYTES) {
            throw new ImportException('file_too_large', 'O PDF deve ter no máximo 50 MiB.');
        }
        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw new ImportException('invalid_upload', 'O upload não foi concluído. Envie novamente o campo pdf.');
        }
        $name = basename(str_replace('\\', '/', $upload->getClientFilename() ?? 'document.pdf'));
        if ($name === '' || strlen($name) > 255 || preg_match('//u', $name) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $name)) {
            throw new ImportException('invalid_filename', 'O nome do arquivo é inválido ou muito longo.');
        }
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) {
            throw new RuntimeException('Não foi possível criar a pasta privada de documentos.');
        }
        $relative = bin2hex(random_bytes(16)) . '.pdf';
        $absolute = $this->root . '/' . $relative;
        $target = fopen($absolute, 'xb');
        if ($target === false) {
            throw new RuntimeException('Não foi possível armazenar o upload.');
        }
        $hash = hash_init('sha256');
        $size = 0;

        try {
            $stream = $upload->getStream();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            while (!$stream->eof()) {
                $chunk = $stream->read(65536);
                if ($chunk === '' && !$stream->eof()) {
                    throw new ImportException('invalid_upload', 'Não foi possível ler todo o upload.');
                }
                $size += strlen($chunk);
                if ($size > self::MAX_BYTES) {
                    throw new ImportException('file_too_large', 'O PDF deve ter no máximo 50 MiB.');
                }
                if (fwrite($target, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Falha ao armazenar o PDF.');
                }
                hash_update($hash, $chunk);
            }
            fclose($target);
            $target = null;

            if ($size === 0 || file_get_contents($absolute, false, null, 0, 5) !== '%PDF-'
                || (new \finfo(FILEINFO_MIME_TYPE))->file($absolute) !== 'application/pdf') {
                throw new ImportException('invalid_pdf', 'Envie um arquivo PDF válido no campo pdf.');
            }
            return [
                'storage_path' => $relative, 'absolute_path' => $absolute,
                'sha256' => hash_final($hash), 'file_size' => $size, 'original_filename' => $name,
            ];
        } catch (\Throwable $exception) {
            if (is_resource($target)) {
                fclose($target);
            }
            $this->remove($relative);
            throw $exception;
        }
    }

    public function verifiedPath(string $relative, string $expectedHash): string
    {
        $absolute = $this->path($relative);
        if (!is_file($absolute) || !hash_equals($expectedHash, hash_file('sha256', $absolute))) {
            throw new ImportException('stored_file_changed', 'O PDF armazenado está ausente ou foi alterado.');
        }
        return $absolute;
    }

    public function remove(string $relative): void
    {
        $path = $this->path($relative);
        if (is_file($path) && !unlink($path)) {
            error_log('bncc_upload_cleanup_failed');
        }
    }

    private function path(string $relative): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.pdf$/D', $relative)) {
            throw new ImportException('invalid_storage_path', 'O documento não possui um arquivo válido no armazenamento atual.');
        }
        return $this->root . '/' . $relative;
    }
}
