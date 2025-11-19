<?php
/**
 * Sistema de Histórico e Versionamento
 */

class HistoryManager {
    private $historyFile;
    private $history = [];

    public function __construct($historyFile) {
        $this->historyFile = $historyFile;
        $this->loadHistory();
    }

    private function loadHistory() {
        if (file_exists($this->historyFile)) {
            $content = file_get_contents($this->historyFile);
            $this->history = json_decode($content, true) ?: [];
        }
    }

    private function saveHistory() {
        file_put_contents(
            $this->historyFile,
            json_encode($this->history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function addEntry($action, $files, $from, $to, $user, $notes = '') {
        $entry = [
            'id' => uniqid(),
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $user,
            'action' => $action, // 'push', 'pull', 'rollback'
            'from' => $from,
            'to' => $to,
            'files' => $files,
            'file_count' => count($files),
            'notes' => $notes,
            'backup_file' => null
        ];

        array_unshift($this->history, $entry);
        
        // Limitar histórico a 1000 entradas
        if (count($this->history) > 1000) {
            $this->history = array_slice($this->history, 0, 1000);
        }

        $this->saveHistory();
        return $entry['id'];
    }

    public function setBackupFile($id, $backupFile) {
        foreach ($this->history as &$entry) {
            if ($entry['id'] === $id) {
                $entry['backup_file'] = $backupFile;
                $this->saveHistory();
                return true;
            }
        }
        return false;
    }

    public function getHistory($limit = 50, $user = null, $action = null) {
        $filtered = $this->history;

        if ($user) {
            $filtered = array_filter($filtered, function($entry) use ($user) {
                return $entry['user'] === $user;
            });
        }

        if ($action) {
            $filtered = array_filter($filtered, function($entry) use ($action) {
                return $entry['action'] === $action;
            });
        }

        return array_slice($filtered, 0, $limit);
    }

    public function getEntry($id) {
        foreach ($this->history as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }
        return null;
    }

    public function getRecentByFile($filename, $limit = 10) {
        $results = [];
        foreach ($this->history as $entry) {
            if (in_array($filename, $entry['files'])) {
                $results[] = $entry;
                if (count($results) >= $limit) break;
            }
        }
        return $results;
    }

    public function getStats() {
        $stats = [
            'total_operations' => count($this->history),
            'by_action' => [],
            'by_user' => [],
            'by_day' => []
        ];

        foreach ($this->history as $entry) {
            // Por ação
            $action = $entry['action'];
            $stats['by_action'][$action] = ($stats['by_action'][$action] ?? 0) + 1;

            // Por usuário
            $user = $entry['user'];
            $stats['by_user'][$user] = ($stats['by_user'][$user] ?? 0) + 1;

            // Por dia
            $day = substr($entry['timestamp'], 0, 10);
            $stats['by_day'][$day] = ($stats['by_day'][$day] ?? 0) + 1;
        }

        return $stats;
    }
}
