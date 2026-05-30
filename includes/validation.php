<?php
/**
 * Общие правила валидации ввода для форм сайта.
 */

function normalizeSpaces(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function isValidHumanName(string $value, int $min = 2, int $max = 150): bool
{
    $value = normalizeSpaces($value);
    if (mb_strlen($value) < $min || mb_strlen($value) > $max) {
        return false;
    }

    return (bool)preg_match('/^(?!.*[- ]{2})(?![- ])(?:[\p{L}]+(?:-[\p{L}]+)?)(?: [\p{L}]+(?:-[\p{L}]+)?)*$/u', $value);
}

function validateHumanName(string $value, string $fieldLabel = 'Имя', int $min = 2, int $max = 150): ?string
{
    $value = normalizeSpaces($value);
    if ($value === '') {
        return $fieldLabel . ' обязательно для заполнения';
    }
    if (mb_strlen($value) < $min) {
        return $fieldLabel . ' должно содержать минимум ' . $min . ' символа';
    }
    if (mb_strlen($value) > $max) {
        return $fieldLabel . ' не должно превышать ' . $max . ' символов';
    }
    if (!isValidHumanName($value, $min, $max)) {
        return $fieldLabel . ' может содержать только буквы, одинарный дефис внутри слова и пробел между словами';
    }
    return null;
}

function formatPhoneMask(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if ($digits === '') {
        return '';
    }

    if (strlen($digits) === 11 && ($digits[0] === '8' || $digits[0] === '7')) {
        $digits = substr($digits, 1);
    } elseif (strlen($digits) > 10 && str_starts_with($digits, '7')) {
        $digits = substr($digits, 1, 10);
    } else {
        $digits = substr($digits, 0, 10);
    }

    $result = '+7';
    if (strlen($digits) > 0) {
        $result .= ' (' . substr($digits, 0, 3);
    }
    if (strlen($digits) >= 3) {
        $result .= ')';
    }
    if (strlen($digits) > 3) {
        $result .= ' ' . substr($digits, 3, 3);
    }
    if (strlen($digits) > 6) {
        $result .= '-' . substr($digits, 6, 2);
    }
    if (strlen($digits) > 8) {
        $result .= '-' . substr($digits, 8, 2);
    }

    return substr($result, 0, 18);
}

function validatePhone(string $value, bool $required = false): ?string
{
    $value = trim($value);
    if ($value === '') {
        return $required ? 'Введите номер телефона в формате +7 (XXX) XXX-XX-XX' : null;
    }

    if (mb_strlen($value) > 18) {
        return 'Номер телефона не должен превышать 18 символов и должен соответствовать маске +7 (XXX) XXX-XX-XX';
    }

    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
        return null;
    }

    return 'Введите номер телефона в формате +7 (XXX) XXX-XX-XX';
}

function validateEmailValue(string $value, bool $required = true): ?string
{
    $value = trim($value);
    if ($value === '') {
        return $required ? 'Введите адрес электронной почты' : null;
    }
    if (strlen($value) > 190) {
        return 'Email не должен превышать 190 символов';
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return 'Введите корректный email в формате имя@домен.зона';
    }
    return null;
}

function validatePasswordRules(string $value, bool $required = true): ?string
{
    if ($value === '') {
        return $required ? 'Введите пароль' : null;
    }
    if (strlen($value) > 72) {
        return 'Пароль не должен превышать 72 символа';
    }
    if (strlen($value) < 4 || !preg_match('/\d/', $value) || !preg_match('/[A-ZА-ЯЁ]/u', $value) || !preg_match('/[a-zа-яё]/u', $value)) {
        return 'Пароль должен содержать минимум 4 символа, включая цифру и заглавную букву';
    }
    return null;
}

function validateSearchQuery(string $value, bool $required = false): ?string
{
    $value = normalizeSpaces($value);
    if ($value === '') {
        return $required ? 'Введите поисковый запрос' : null;
    }
    if (mb_strlen($value) > 100) {
        return 'Поисковый запрос не должен превышать 100 символов';
    }
    if (preg_match('/<[^>]*>|[;\\\'"`]|--|\/\*|\*\//u', $value)) {
        return 'Поиск может содержать только буквы, цифры, пробел и дефис';
    }
    if (!preg_match('/^[\p{L}\p{N}\- ]+$/u', $value)) {
        return 'Поиск может содержать только буквы, цифры, пробел и дефис';
    }
    return null;
}

function validateFutureDate(string $value, int $maxYears = 2): ?string
{
    if ($value === '') {
        return 'Выберите дату';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return 'Введите дату в корректном формате';
    }

    $today = new DateTime('today');
    $maxDate = (clone $today)->modify('+' . $maxYears . ' years');
    if ($date < $today) {
        return 'Дата не может быть раньше сегодняшнего дня';
    }
    if ($date > $maxDate) {
        return 'Дата не может быть позднее чем через ' . $maxYears . ' года';
    }
    return null;
}

function validatePlainText(string $value, string $fieldLabel, int $min = 0, int $max = 1000): ?string
{
    $value = trim($value);
    if ($min > 0 && mb_strlen($value) < $min) {
        return $fieldLabel . ' должно содержать минимум ' . $min . ' символов';
    }
    if (mb_strlen($value) > $max) {
        return $fieldLabel . ' не должно превышать ' . $max . ' символов';
    }
    if (preg_match('/<[^>]*>/u', $value)) {
        return $fieldLabel . ' не должно содержать HTML-теги';
    }
    return null;
}

function validateCarText(string $value, string $fieldLabel, bool $required = true, int $max = 100): ?string
{
    $value = normalizeSpaces($value);
    if ($value === '') {
        return $required ? $fieldLabel . ' обязательно для заполнения' : null;
    }
    if (mb_strlen($value) > $max) {
        return $fieldLabel . ' не должно превышать ' . $max . ' символов';
    }
    if (!preg_match('/^[\p{L}\p{N}\- ]+$/u', $value)) {
        return $fieldLabel . ' может содержать только буквы, цифры, пробел и дефис';
    }
    return null;
}
