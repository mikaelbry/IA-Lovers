<?php
/**
 * Modelo de acceso a datos de solicitudes de restablecimiento de contrasena.
 */
require_once __DIR__ . '/../config/Database.php';

class PendingPasswordReset {

    /** Elimina solicitudes vencidas (respeta locked_until). */
    public static function purgeExpired() {
        $pdo = Database::getConnection();
        $pdo->prepare('
            DELETE FROM pending_password_resets
            WHERE verification_expires_at < CURRENT_TIMESTAMP
            AND (locked_until IS NULL OR locked_until < CURRENT_TIMESTAMP)
        ')->execute();
    }

    /** Busca por token de flujo, incluye email y username del usuario (JOIN). */
    public static function findByFlowToken($flowToken) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT pending_password_resets.*, users.email, users.username
            FROM pending_password_resets
            JOIN users ON users.id = pending_password_resets.user_id
            WHERE flow_token = ?
        ');
        $stmt->execute([$flowToken]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Busca solicitud por ID de usuario. */
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

    /** Crea una nueva solicitud de restablecimiento. */
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

    /** Actualiza solicitud con nuevo flow_token, codigo, resetea intentos y desbloquea. */
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

    /** Incrementa el contador de intentos de verificacion. */
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

    /** Bloquea la solicitud hasta una fecha (por demasiados intentos fallidos). */
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

    /** Elimina solicitudes de un usuario. */
    public static function deleteByUserId($userId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_password_resets
            WHERE user_id = ?
        ');

        return $stmt->execute([$userId]);
    }

    /** Elimina solicitud por ID. */
    public static function deleteById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_password_resets
            WHERE id = ?
        ');

        return $stmt->execute([$id]);
    }

    /** Elimina solicitud solo si no esta bloqueada o el bloqueo expiro. */
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
