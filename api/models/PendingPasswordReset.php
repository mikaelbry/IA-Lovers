<?php

require_once __DIR__ . '/../config/database.php';

class PendingPasswordReset {

    public static function purgeExpired() {
        $pdo = Database::getConnection();
        $pdo->prepare('
            DELETE FROM pending_password_resets
            WHERE verification_expires_at < CURRENT_TIMESTAMP
            AND (locked_until IS NULL OR locked_until < CURRENT_TIMESTAMP)
        ')->execute();
    }

    public static function findByFlowToken($flowToken) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT pending_password_resets.*, usuarios.email, usuarios.username
            FROM pending_password_resets
            JOIN usuarios ON usuarios.id = pending_password_resets.user_id
            WHERE flow_token = ?
        ');
        $stmt->execute([$flowToken]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function findByUserId($userId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT *
            FROM pending_password_resets
            WHERE user_id = ?
        ');
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($userId, $flowToken, $codeHash, $expiresAt) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO pending_password_resets (
                user_id,
                flow_token,
                verification_code_hash,
                verification_expires_at,
                verification_attempts,
                locked_until,
                last_sent_at,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, 0, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');

        return $stmt->execute([
            $userId,
            $flowToken,
            $codeHash,
            $expiresAt,
        ]);
    }

    public static function updateRequest($id, $flowToken, $codeHash, $expiresAt) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE pending_password_resets
            SET flow_token = ?,
                verification_code_hash = ?,
                verification_expires_at = ?,
                verification_attempts = 0,
                locked_until = NULL,
                last_sent_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');

        return $stmt->execute([
            $flowToken,
            $codeHash,
            $expiresAt,
            $id,
        ]);
    }

    public static function incrementAttempts($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE pending_password_resets
            SET verification_attempts = verification_attempts + 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');

        return $stmt->execute([$id]);
    }

    public static function lock($id, $lockedUntil) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE pending_password_resets
            SET locked_until = ?,
                verification_attempts = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');

        return $stmt->execute([$lockedUntil, $id]);
    }

    public static function deleteByUserId($userId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_password_resets
            WHERE user_id = ?
        ');

        return $stmt->execute([$userId]);
    }

    public static function deleteById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_password_resets
            WHERE id = ?
        ');

        return $stmt->execute([$id]);
    }

    public static function deleteUnlockedById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_password_resets
            WHERE id = ?
            AND (locked_until IS NULL OR locked_until < CURRENT_TIMESTAMP)
        ');

        return $stmt->execute([$id]);
    }
}
