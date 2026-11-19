<?php

function catat_log(PDO $pdo, ?int $user_id, string $action, string $description = '', ?string $ip = null): void
{
    $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$user_id, $action, $description, $ip]);
    } catch (PDOException $e) {
        error_log('Gagal catat log: ' . $e->getMessage());
    }
}
