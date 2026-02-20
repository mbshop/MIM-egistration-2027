<?php

const DB_HOST = 'localhost';
const DB_NAME = 'marapuwa_projet01';
const DB_USER = 'marapuwa_batixx';
const DB_PASS = 'marapuwa_batixx';

const GEMINI_API_KEY = 'AIzaSyAvFq3rO9e1yQGvqPrYyf2fuLAUD2_cXVA';
const GEMINI_MODEL = 'gemini-3-flash-preview';

const GOOGLE_SERVICE_ACCOUNT_JSON = __DIR__ . '/google-service-account.json';
const GOOGLE_SHEETS_SPREADSHEET_ID = '';
const GOOGLE_SHEETS_RANGE = 'Feuille1!A2:H';

function getPdo(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function mapCountryToEnglish(string $value): string
{
    $valueUpper = mb_strtoupper(trim($value), 'UTF-8');
    $map = [
        'MAROC' => 'Morocco',
        'MOROCCO' => 'Morocco',
        'FRANCE' => 'France',
        'TUNISIE' => 'Tunisia',
        'TUNISIA' => 'Tunisia',
        'ALGERIE' => 'Algeria',
        'ALGERIA' => 'Algeria',
    ];
    return $map[$valueUpper] ?? '';
}
