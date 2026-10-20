<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Application\Bncc\ImportException;
use RuntimeException;

final class ProcessRunner
{
    /** @param list<string> $command
     *  @return array{stdout: string, stderr: string, exit_code: int}
     */
    public function run(array $command, float $timeoutSeconds, int $maxBytes): array
    {
        // prlimit execs the child directly: native memory/CPU ceilings also apply outside PHP's memory_limit.
        $command = ['/usr/bin/prlimit', '--as=268435456:268435456', '--cpu=60:60', '--nofile=128:128', '--', ...$command];
        // Array de argumentos: não há shell interpretando nomes de arquivos ou metacaracteres.
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
            null, ['PATH' => '/usr/local/bin:/usr/bin:/bin', 'LC_ALL' => 'C']);
        if (!is_resource($process)) {
            throw new RuntimeException('Não foi possível iniciar o extrator.');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = [1 => '', 2 => ''];
        $started = hrtime(true);

        try {
            do {
                foreach ([1, 2] as $index) {
                    $chunk = stream_get_contents($pipes[$index], 65536);
                    if ($chunk === false) {
                        throw new RuntimeException('Falha na leitura da saída do extrator.');
                    }
                    $output[$index] .= $chunk;
                }
                if (strlen($output[1]) + strlen($output[2]) > $maxBytes) {
                    throw new ImportException('extraction_limit', 'O texto extraído ultrapassa o limite permitido.');
                }
                if ((hrtime(true) - $started) / 1e9 > $timeoutSeconds) {
                    throw new ImportException('extraction_timeout', 'A extração excedeu o tempo limite.');
                }
                $status = proc_get_status($process);
                $finished = !$status['running'] && feof($pipes[1]) && feof($pipes[2]);
                if (!$finished) {
                    usleep(10000);
                }
            } while (!$finished);

            return ['stdout' => $output[1], 'stderr' => $output[2], 'exit_code' => $status['exitcode']];
        } finally {
            // Em timeout/erro, impedir que o subprocesso continue trabalhando em segundo plano.
            if (proc_get_status($process)['running']) {
                proc_terminate($process, 9);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }
}
