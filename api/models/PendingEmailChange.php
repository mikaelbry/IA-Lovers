<?php

require_once __DIR__ . '/../config/database.php';

class PendingEmailChange {

    public static function purgeExpired() {
        $pdo = Database::getConnection();
        $pdo->prepare('
            DELETE FROM pending_email_changes
            WHERE verification_expires_at < CURRENT_TIMESTAMP
        ')->execute();
    }

    public static function findByUserId($userId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT *
            FROM pending_email_changes
            WHERE user_id = ?
        ');
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function findByNewEmail($email) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT *
            FROM pending_email_changes
            WHERE new_email = ?
        ');
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($userId, $newEmail, $codeHash, $expiresAt) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO pending_email_changes (
                user_id,
                new_email,
                verification_code_hash,
                verification_expires_at,
                verification_attempts,
                last_sent_at,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');

        return $stmt->execute([
            $userId,
            $newEmail,
            $codeHash,
            $expiresAt,
        ]);
    }

    public static function updateRequest($id, $newEmail, $codeHash, $expiresAt) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE pending_email_changes
            SET new_email = ?,
                verification_code_hash = ?,
                verification_expires_at = ?,
                verification_attempts = 0,
                last_sent_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');

        return $stmt->execute([
            $newEmail,
            $codeHash,
            $expiresAt,
            $id,
        ]);
    }

    public static function incrementAttempts($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE pending_email_changes
            SET verification_attempts = verification_attempts + 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');

        return $stmt->execute([$id]);
    }

    public static function deleteByUserId($userId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_email_changes
            WHERE user_id = ?
        ');

        return $stmt->execute([$userId]);
    }

    public static function deleteById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_email_changes
            WHERE id = ?
        ');

        return $stmt->execute([$id]);
    }
}
