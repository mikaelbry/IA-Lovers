<?php

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/PasswordResetService.php';
require_once __DIR__ . '/../services/RegistrationService.php';

class AuthController {

    public static function register() {
        RegistrationService::register();
    }

    public static function startRegistration() {
        RegistrationService::startRegistration();
    }

    public static function mobileStartRegistration() {
        RegistrationService::mobileStartRegistration();
    }

    public static function verifyRegistration() {
        RegistrationService::verifyRegistration();
    }

    public static function mobileVerifyRegistration() {
        RegistrationService::mobileVerifyRegistration();
    }

    public static function resendRegistrationCode() {
        RegistrationService::resendRegistrationCode();
    }

    public static function mobileResendRegistrationCode() {
        RegistrationService::mobileResendRegistrationCode();
    }

    public static function cancelPendingRegistration() {
        RegistrationService::cancelPendingRegistration();
    }

    public static function mobileCancelPendingRegistration() {
        RegistrationService::mobileCancelPendingRegistration();
    }

    public static function session() {
        AuthService::session();
    }

    public static function logout() {
        AuthService::logout();
    }

    public static function login() {
        AuthService::login();
    }

    public static function mobileLogin() {
        AuthService::mobileLogin();
    }

    public static function startPasswordReset() {
        PasswordResetService::startPasswordReset();
    }

    public static function mobileStartPasswordReset() {
        PasswordResetService::mobileStartPasswordReset();
    }

    public static function resendPasswordResetCode() {
        PasswordResetService::resendPasswordResetCode();
    }

    public static function mobileResendPasswordResetCode() {
        PasswordResetService::mobileResendPasswordResetCode();
    }

    public static function completePasswordReset() {
        PasswordResetService::completePasswordReset();
    }

    public static function mobileCompletePasswordReset() {
        PasswordResetService::mobileCompletePasswordReset();
    }

    public static function cancelPasswordReset() {
        PasswordResetService::cancelPasswordReset();
    }

    public static function mobileCancelPasswordReset() {
        PasswordResetService::mobileCancelPasswordReset();
    }
}
