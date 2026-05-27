<?php

require_once __DIR__ . '/../services/AuthService.php';

class AuthController {

    public static function register() {
        AuthService::register();
    }

    public static function startRegistration() {
        AuthService::startRegistration();
    }

    public static function mobileStartRegistration() {
        AuthService::mobileStartRegistration();
    }

    public static function verifyRegistration() {
        AuthService::verifyRegistration();
    }

    public static function mobileVerifyRegistration() {
        AuthService::mobileVerifyRegistration();
    }

    public static function resendRegistrationCode() {
        AuthService::resendRegistrationCode();
    }

    public static function mobileResendRegistrationCode() {
        AuthService::mobileResendRegistrationCode();
    }

    public static function cancelPendingRegistration() {
        AuthService::cancelPendingRegistration();
    }

    public static function mobileCancelPendingRegistration() {
        AuthService::mobileCancelPendingRegistration();
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
        AuthService::startPasswordReset();
    }

    public static function mobileStartPasswordReset() {
        AuthService::mobileStartPasswordReset();
    }

    public static function resendPasswordResetCode() {
        AuthService::resendPasswordResetCode();
    }

    public static function mobileResendPasswordResetCode() {
        AuthService::mobileResendPasswordResetCode();
    }

    public static function completePasswordReset() {
        AuthService::completePasswordReset();
    }

    public static function mobileCompletePasswordReset() {
        AuthService::mobileCompletePasswordReset();
    }

    public static function cancelPasswordReset() {
        AuthService::cancelPasswordReset();
    }

    public static function mobileCancelPasswordReset() {
        AuthService::mobileCancelPasswordReset();
    }
}
