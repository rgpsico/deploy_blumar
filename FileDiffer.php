<?php
/**
 * Sistema de Comparação de Arquivos
 */

class FileDiffer {
    
    /**
     * Compara dois arquivos e retorna informações sobre diferenças
     */
    public static function compareFiles($file1, $file2) {
        if (!file_exists($file1)) {
            return ['status' => 'not_found_source', 'message' => 'Arquivo origem não existe'];
        }

        if (!file_exists($file2)) {
            return ['status' => 'new_file', 'message' => 'Arquivo não existe no destino (novo)'];
        }

        $hash1 = md5_file($file1);
        $hash2 = md5_file($file2);

        if ($hash1 === $hash2) {
            return ['status' => 'identical', 'message' => 'Arquivos idênticos'];
        }

        $time1 = filemtime($file1);
        $time2 = filemtime($file2);
        $size1 = filesize($file1);
        $size2 = filesize($file2);

        return [
            'status' => 'different',
            'message' => 'Arquivos diferentes',
            'source' => [
                'hash' => $hash1,
                'modified' => date('Y-m-d H:i:s', $time1),
                'size' => $size1,
                'size_formatted' => self::formatBytes($size1)
            ],
            'target' => [
                'hash' => $hash2,
                'modified' => date('Y-m-d H:i:s', $time2),
                'size' => $size2,
                'size_formatted' => self::formatBytes($size2)
            ],
            'newer_in_source' => $time1 > $time2,
            'size_diff' => $size1 - $size2
        ];
    }

    /**
     * Compara uma lista de arquivos entre dois ambientes
     */
    public static function compareBatch($files, $sourcePath, $targetPath, $sourceEnv, $targetEnv) {
        $results = [];

        foreach ($files as $file) {
            $sourceFile = $sourcePath . $file;
            $targetFile = $targetPath . $file;

            $comparison = self::compareFiles($sourceFile, $targetFile);
            $comparison['file'] = $file;
            $comparison['source_env'] = $sourceEnv;
            $comparison['target_env'] = $targetEnv;

            $results[] = $comparison;
        }

        return [
            'total' => count($results),
            'identical' => count(array_filter($results, fn($r) => $r['status'] === 'identical')),
            'different' => count(array_filter($results, fn($r) => $r['status'] === 'different')),
            'new' => count(array_filter($results, fn($r) => $r['status'] === 'new_file')),
            'not_found' => count(array_filter($results, fn($r) => $r['status'] === 'not_found_source')),
            'files' => $results
        ];
    }

    /**
     * Obtém diferenças textuais entre dois arquivos (para arquivos de texto)
     */
    public static function getTextDiff($file1, $file2, $contextLines = 3) {
        if (!file_exists($file1) || !file_exists($file2)) {
            return null;
        }

        // Verifica se são arquivos de texto
        if (!self::isTextFile($file1) || !self::isTextFile($file2)) {
            return null;
        }

        $content1 = file($file1);
        $content2 = file($file2);

        $diff = self::simpleDiff($content1, $content2);

        return $diff;
    }

    private static function simpleDiff($lines1, $lines2) {
        $diff = [];
        $maxLines = max(count($lines1), count($lines2));

        for ($i = 0; $i < $maxLines; $i++) {
            $line1 = $lines1[$i] ?? null;
            $line2 = $lines2[$i] ?? null;

            if ($line1 === null) {
                $diff[] = ['type' => 'added', 'line' => $i + 1, 'content' => $line2];
            } elseif ($line2 === null) {
                $diff[] = ['type' => 'removed', 'line' => $i + 1, 'content' => $line1];
            } elseif ($line1 !== $line2) {
                $diff[] = [
                    'type' => 'modified',
                    'line' => $i + 1,
                    'old' => $line1,
                    'new' => $line2
                ];
            }
        }

        return $diff;
    }

    private static function isTextFile($file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $textExtensions = ['php', 'html', 'css', 'js', 'txt', 'json', 'xml', 'sql', 'md'];
        return in_array($ext, $textExtensions);
    }

    private static function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Verifica conflitos antes de fazer push/pull
     */
    public static function checkConflicts($files, $sourcePath, $targetPath) {
        $conflicts = [];

        foreach ($files as $file) {
            $sourceFile = $sourcePath . $file;
            $targetFile = $targetPath . $file;

            if (!file_exists($sourceFile)) {
                $conflicts[] = [
                    'file' => $file,
                    'type' => 'missing_source',
                    'message' => 'Arquivo não existe na origem'
                ];
                continue;
            }

            if (file_exists($targetFile)) {
                $sourceTime = filemtime($sourceFile);
                $targetTime = filemtime($targetFile);

                // Se o arquivo no destino é mais recente, pode ser conflito
                if ($targetTime > $sourceTime) {
                    $conflicts[] = [
                        'file' => $file,
                        'type' => 'newer_in_target',
                        'message' => 'Arquivo no destino é mais recente',
                        'source_time' => date('Y-m-d H:i:s', $sourceTime),
                        'target_time' => date('Y-m-d H:i:s', $targetTime)
                    ];
                }
            }
        }

        return $conflicts;
    }
}
