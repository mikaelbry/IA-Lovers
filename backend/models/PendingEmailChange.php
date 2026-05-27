<?php
/**
 * Modelo de acceso a datos de solicitudes de cambio de email pendientes.
 */
require_once __DIR__ . '/../config/Database.php';

class PendingEmailChange {

    /** Elimina solicitudes de cambio de email vencidas. */
    public static function purgeExpired() {
        $pdo = Database::getConnection();
        $pdo->prepare('
            DELETE FROM pending_email_changes
            WHERE verification_expires_at < CURRENT_TIMESTAMP
        ')->execute();
    }

    /** Busca solicitud por ID de usuario. */
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

    /** Busca solicitud por el nuevo email solicitado. */
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

    /** Crea una nueva solicitud de cambio de email con intentos en 0. */
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

    /** Actualiza solicitud existente con nuevo codigo, resetea intentos y actualiza last_sent_at. */
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

    /** Incrementa el contador de intentos de verificacion. */
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

    /** Elimina solicitudes de cambio de email de un usuario. */
    public static function deleteByUserId($userId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_email_changes
            WHERE user_id = ?
        ');

        return $stmt->execute([$userId]);
    }

    /** Elimina una solicitud por su ID. */
    public static function deleteById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            DELETE FROM pending_email_changes
            WHERE id = ?
        ');

        return $stmt->execute([$id]);
    }
}
