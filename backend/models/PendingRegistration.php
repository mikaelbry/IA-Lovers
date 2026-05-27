<?php
/**
 * Modelo de acceso a datos de registros pendientes de verificacion por email.
 */
require_once __DIR__ . '/../config/Database.php';

class PendingRegistration {

    /** Elimina registros pendientes vencidos. */
    public static function purgeExpired() {
        $pdo = Database::getConnection();
        $pdo->prepare('
            DELETE FROM pending_registrations
            WHERE verification_expires_at < CURRENT_TIMESTAMP
        ')->execute();
    }

    /** Busca registro pendiente por token de flujo. */
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

    /** Busca registro pendiente por email. */
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

    /** Busca registro pendiente por nombre de usuario. */
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

    /** Crea un nuevo registro pendiente con datos del usuario, flow_token y codigo de verificacion. */
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

    /** Actualiza todos los datos del flujo de registro (username, password, flow_token, codigo). */
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

    /** Actualiza solo el codigo de verificacion (para reenvio). */
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

    /** Incrementa el contador de intentos de verificacion. */
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

    /** Elimina registro pendiente por token de flujo. */
    public static function deleteByFlowToken($flowToken) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_registrations
            WHERE flow_token = ?
        ');

        return $stmt->execute([$flowToken]);
    }

    /** Elimina registro pendiente por ID. */
    public static function deleteById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_registrations
            WHERE id = ?
        ');

        return $stmt->execute([$id]);
    }
}
