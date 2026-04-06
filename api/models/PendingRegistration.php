<?php

require_once __DIR__ . '/../config/database.php';

class PendingRegistration {

    public static function purgeExpired() {
        $pdo = Database::getConnection();
        $pdo->prepare('
            DELETE FROM pending_registrations
            WHERE verification_expires_at < CURRENT_TIMESTAMP
        ')->execute();
    }

    public static function findByFlowToken($flowToken) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT *
            FROM pending_registrations
            WHERE flow_token = ?
        ');
        $stmt->execute([$flowToken]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function findByEmail($email) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT *
            FROM pending_registrations
            WHERE email = ?
        ');
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function findByUsername($username) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT *
            FROM pending_registrations
            WHERE username = ?
        ');
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($username, $email, $passwordHash, $flowToken, $codeHash, $expiresAt) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO pending_registrations (
                flow_token,
                username,
                email,
                password_hash,
                verification_code_hash,
                verification_expires_at,
                verification_attempts,
                last_sent_at,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');

        return $stmt->execute([
            $flowToken,
            $username,
            $email,
            $passwordHash,
            $codeHash,
            $expiresAt,
        ]);
    }

    public static function updateFlow($id, $username, $passwordHash, $flowToken, $codeHash, $expiresAt) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE pending_registrations
            SET username = ?,
                password_hash = ?,
                flow_token = ?,
                verification_code_hash = ?,
                verification_expires_at = ?,
                verification_attempts = 0,
                last_sent_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');

        return $stmt->execute([
            $username,
            $passwordHash,
            $flowToken,
            $codeHash,
            $expiresAt,
            $id,
        ]);
    }

    public static function updateCode($id, $flowToken, $codeHash, $expiresAt) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE pending_registrations
            SET flow_token = ?,
                verification_code_hash = ?,
                verification_expires_at = ?,
                verification_attempts = 0,
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
            UPDATE pending_registrations
            SET verification_attempts = verification_attempts + 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');

        return $stmt->execute([$id]);
    }

    public static function deleteByFlowToken($flowToken) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_registrations
            WHERE flow_token = ?
        ');

        return $stmt->execute([$flowToken]);
    }

    public static function deleteById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_registrations
            WHERE id = ?
        ');

        return $stmt->execute([$id]);
    }
}
