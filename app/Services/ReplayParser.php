<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Runs the screp binary on a .rep file and returns its decoded JSON.
 */
class ReplayParser
{
    /**
     * @return array{data?: array, error?: string}
     */
    public function parse(string $path): array
    {
        $command = escapeshellarg(base_path('screp')) . ' ' . escapeshellarg($path) . ' 2>&1';
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            Log::error('Replay parser execution failed', [
                'command' => $command,
                'return_code' => $return_var,
                'output' => implode("\n", $output),
                'tmp_path' => $path,
            ]);
            return ['error' => 'Script execution failed.'];
        }

        $data = json_decode(implode("\n", $output), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to decode replay JSON.'];
        }

        return ['data' => $data];
    }
}
