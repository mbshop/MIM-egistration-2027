<?php
require __DIR__ . '/config.php';

function decodeBase64Image(string $dataUrl): ?string
{
    if (strpos($dataUrl, 'base64,') === false) {
        return null;
    }
    $parts = explode('base64,', $dataUrl, 2);
    if (count($parts) !== 2) {
        return null;
    }
    $binary = base64_decode($parts[1], true);
    if ($binary === false) {
        return null;
    }
    $tmpFile = tempnam(sys_get_temp_dir(), 'doc_');
    if ($tmpFile === false) {
        return null;
    }
    file_put_contents($tmpFile, $binary);
    return $tmpFile;
}

function runTesseract(string $imagePath): ?string
{
    $outputFile = tempnam(sys_get_temp_dir(), 'ocr_');
    if ($outputFile === false) {
        return null;
    }
    $command = 'tesseract ' . escapeshellarg($imagePath) . ' ' . escapeshellarg($outputFile) . ' -l fra+ara eng 2>&1';
    exec($command, $output, $code);
    if ($code !== 0) {
        return null;
    }
    $txtFile = $outputFile . '.txt';
    if (!file_exists($txtFile)) {
        return null;
    }
    $text = file_get_contents($txtFile);
    return $text === false ? null : $text;
}

function normalizeOcrText(string $text): array
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $normalized = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $normalized[] = $line;
        }
    }
    return $normalized;
}

function extractDateFromLines(array $lines): string
{
    foreach ($lines as $line) {
        if (preg_match('/(\d{2})[\/\-.](\d{2})[\/\-.](\d{4})/', $line, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
        if (preg_match('/(\d{4})[\/\-.](\d{2})[\/\-.](\d{2})/', $line, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }
    }
    return '';
}

function extractSexFromLines(array $lines): string
{
    foreach ($lines as $line) {
        if (preg_match('/\b(M|F)\b/i', $line, $m)) {
            return strtoupper($m[1]);
        }
    }
    return '';
}

function extractNameFromLines(array $lines): array
{
    $nom = '';
    $prenom = '';
    foreach ($lines as $line) {
        if (stripos($line, 'NOM') !== false && strpos($line, ':') !== false) {
            $parts = explode(':', $line, 2);
            $value = trim($parts[1]);
            if ($value !== '' && $nom === '') {
                $nom = $value;
            }
        } elseif (stripos($line, 'PRENOM') !== false && strpos($line, ':') !== false) {
            $parts = explode(':', $line, 2);
            $value = trim($parts[1]);
            if ($value !== '' && $prenom === '') {
                $prenom = $value;
            }
        }
    }
    return [$nom, $prenom];
}

function extractCountryAndCityFromLines(array $lines): array
{
    $pays = '';
    $ville = '';
    foreach ($lines as $line) {
        if (preg_match('/\b(MAROC|MOROCCO|FRANCE|TUNISIE|TUNISIA|ALGERIE|ALGERIA)\b/i', $line, $m)) {
            $pays = mapCountryToEnglish($m[1]);
        }
    }
    if ($pays !== '') {
        $lastIndex = count($lines) - 1;
        for ($i = $lastIndex; $i >= 0; $i--) {
            $candidate = trim($lines[$i]);
            if ($candidate !== '' && !preg_match('/\d/', $candidate)) {
                $ville = $candidate;
                break;
            }
        }
    }
    return [$pays, $ville];
}

function extractFromPassportMrz(array $lines): array
{
    $fields = [
        'nom' => '',
        'prenom' => '',
        'date_naissance' => '',
        'sexe' => '',
        'pays' => '',
        'ville' => '',
        'document_id' => '',
    ];
    $mrzLines = [];
    foreach ($lines as $line) {
        if (strpos($line, '<<') !== false) {
            $mrzLines[] = str_replace(' ', '', $line);
        }
    }
    if (count($mrzLines) < 2) {
        return $fields;
    }
    $line1 = $mrzLines[0];
    $line2 = $mrzLines[1];
    if (strlen($line1) >= 44 && strlen($line2) >= 44) {
        $namePart = substr($line1, 5);
        $nameParts = explode('<<', $namePart);
        if (count($nameParts) >= 2) {
            $fields['nom'] = str_replace('<', ' ', $nameParts[0]);
            $fields['prenom'] = str_replace('<', ' ', $nameParts[1]);
        }
        $documentNumber = substr($line2, 0, 9);
        $documentNumber = str_replace('<', '', $documentNumber);
        $yy = substr($line2, 13, 2);
        $mm = substr($line2, 2, 2);
        $dd = substr($line2, 4, 2);
        $sex = substr($line2, 20, 1);
        $currentYear = (int)date('Y');
        $century = $currentYear - ((int)$yy > ($currentYear % 100) ? 2000 : 1900);
        $yearFull = (int)$yy + ($century - $currentYear);
        $fields['date_naissance'] = sprintf('%04d-%02d-%02d', $yearFull, (int)$mm, (int)$dd);
        if ($documentNumber !== '') {
            $fields['document_id'] = $documentNumber;
        }
        if ($sex === 'M' || $sex === 'F') {
            $fields['sexe'] = $sex;
        }
    }
    return $fields;
}

function extractFromNationalIdCard(array $lines): array
{
    $fields = [
        'nom' => '',
        'prenom' => '',
        'date_naissance' => '',
        'sexe' => '',
        'pays' => '',
        'ville' => '',
        'document_id' => '',
    ];
    $normalized = [];
    foreach ($lines as $line) {
        $normalized[] = preg_replace('/\s+/u', ' ', trim($line));
    }
    foreach ($normalized as $line) {
        if (stripos($line, 'NOM') !== false && strpos($line, ':') !== false) {
            $parts = explode(':', $line, 2);
            $value = trim($parts[1]);
            if ($value !== '' && $fields['nom'] === '') {
                $fields['nom'] = $value;
            }
        }
        if (stripos($line, 'PRENOM') !== false && strpos($line, ':') !== false) {
            $parts = explode(':', $line, 2);
            $value = trim($parts[1]);
            if ($value !== '' && $fields['prenom'] === '') {
                $fields['prenom'] = $value;
            }
        }
        if (stripos($line, 'DATE') !== false && stripos($line, 'NAISS') !== false) {
            if ($fields['date_naissance'] === '') {
                $fields['date_naissance'] = extractDateFromLines([$line]);
            }
        }
        if ($fields['sexe'] === '' && (stripos($line, 'SEXE') !== false || stripos($line, 'SEX') !== false)) {
            if (preg_match('/\b(M|F)\b/i', $line, $m)) {
                $fields['sexe'] = strtoupper($m[1]);
            }
        }
        if (
            $fields['pays'] === '' &&
            (stripos($line, 'PAYS') !== false ||
                stripos($line, 'COUNTRY') !== false ||
                stripos($line, 'NATIONALITE') !== false ||
                stripos($line, 'NATIONALITÉ') !== false)
        ) {
            if (preg_match('/\b(MAROC|MOROCCO|FRANCE|TUNISIE|TUNISIA|ALGERIE|ALGERIA)\b/i', $line, $m)) {
                $fields['pays'] = mapCountryToEnglish($m[1]);
            }
        }
        if ($fields['ville'] === '' && (stripos($line, 'LIEU') !== false && stripos($line, 'NAISS') !== false)) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $value = trim($parts[1]);
                if ($value !== '') {
                    $fields['ville'] = $value;
                }
            }
        }
    }
    return $fields;
}

function detectDocumentTypeFromLines(array $lines): string
{
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        if (strpos($trimmed, 'P<') === 0 || (strlen($trimmed) >= 30 && substr_count($trimmed, '<') > 5)) {
            return 'passport';
        }
    }
    return 'cin';
}

function callGeminiOcr(string $imagePath): ?array
{
    if (GEMINI_API_KEY === '') {
        return null;
    }
    $imageData = file_get_contents($imagePath);
    if ($imageData === false) {
        return null;
    }
    $base64 = base64_encode($imageData);
    $payload = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => 'You are an OCR extraction engine for Moroccan national ID cards and passports, and similar French/Arabic identity documents. Detect automatically if the document is a national ID card or a passport. Read the image and extract exactly these fields in JSON with keys: nom, prenom, date_naissance, sexe, pays, ville, document_id. date_naissance must be in format YYYY-MM-DD. sexe must be "M" or "F" or empty string. pays must be the country name in English. ville is the city of birth or residence. document_id is the national ID card number (CIN) or passport number. Return only pure JSON, no explanation.',
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => 'image/jpeg',
                            'data' => $base64,
                        ],
                    ],
                ],
            ],
        ],
    ];
    $ch = curl_init();
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode(GEMINI_MODEL) . ':generateContent?key=' . urlencode(GEMINI_API_KEY);
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return null;
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode < 200 || $httpCode >= 300) {
        return null;
    }
    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return null;
    }
    return extractFieldsFromGeminiResponse($data['candidates'][0]['content']['parts'][0]['text']);
}

function extractFieldsFromGeminiResponse(string $text): array
{
    $fields = [
        'nom' => '',
        'prenom' => '',
        'date_naissance' => '',
        'sexe' => '',
        'pays' => '',
        'ville' => '',
        'document_id' => '',
    ];
    $text = trim($text);
    $json = null;
    if ($text !== '') {
        if ($text[0] !== '{' && $text[0] !== '[') {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $text = substr($text, $start, $end - $start + 1);
            }
        }
        $json = json_decode($text, true);
    }
    if (!is_array($json)) {
        return $fields;
    }
    foreach ($fields as $key => $_) {
        if (isset($json[$key]) && is_string($json[$key])) {
            $fields[$key] = trim($json[$key]);
        }
    }
    if ($fields['date_naissance'] !== '') {
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $fields['date_naissance'], $m)) {
            $fields['date_naissance'] = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        } elseif (preg_match('/(\d{2})[\/\-.](\d{2})[\/\-.](\d{4})/', $fields['date_naissance'], $m)) {
            $fields['date_naissance'] = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        } else {
            $fields['date_naissance'] = '';
        }
    }
    if ($fields['sexe'] !== '') {
        $fields['sexe'] = strtoupper(substr($fields['sexe'], 0, 1));
        if ($fields['sexe'] !== 'M' && $fields['sexe'] !== 'F') {
            $fields['sexe'] = '';
        }
    }
    return $fields;
}

function performOcrOnImage(string $imagePath): array
{
    $fields = [
        'nom' => '',
        'prenom' => '',
        'date_naissance' => '',
        'sexe' => '',
        'pays' => '',
        'ville' => '',
        'document_id' => '',
    ];
    $geminiFields = callGeminiOcr($imagePath);
    if (is_array($geminiFields)) {
        foreach ($geminiFields as $k => $v) {
            if (array_key_exists($k, $fields) && is_string($v)) {
                $fields[$k] = $v;
            }
        }
    }
    $text = runTesseract($imagePath);
    if ($text === null) {
        return $fields;
    }
    $lines = normalizeOcrText($text);
    $documentType = detectDocumentTypeFromLines($lines);
    if ($documentType === 'passport') {
        $mrzFields = extractFromPassportMrz($lines);
        foreach ($mrzFields as $k => $v) {
            if ($v !== '') {
                $fields[$k] = $v;
            }
        }
    } else {
        $cinFields = extractFromNationalIdCard($lines);
        foreach ($cinFields as $k => $v) {
            if ($v !== '' && $fields[$k] === '') {
                $fields[$k] = $v;
            }
        }
    }
    if ($fields['date_naissance'] === '') {
        $fields['date_naissance'] = extractDateFromLines($lines);
    }
    if ($fields['sexe'] === '') {
        $fields['sexe'] = extractSexFromLines($lines);
    }
    if ($fields['nom'] === '' && $fields['prenom'] === '') {
        [$nom, $prenom] = extractNameFromLines($lines);
        $fields['nom'] = $nom;
        $fields['prenom'] = $prenom;
    }
    if ($fields['pays'] === '' && $fields['ville'] === '') {
        [$pays, $ville] = extractCountryAndCityFromLines($lines);
        $fields['pays'] = $pays;
        $fields['ville'] = $ville;
    }
    return $fields;
}

$imagePath = null;
if (!empty($_FILES['document_image']['tmp_name'])) {
    $imagePath = $_FILES['document_image']['tmp_name'];
} elseif (!empty($_POST['camera_image'])) {
    $decoded = decodeBase64Image($_POST['camera_image']);
    if ($decoded !== null) {
        $imagePath = $decoded;
    }
}
if ($imagePath === null) {
    http_response_code(400);
    echo 'Aucune image fournie.';
    exit;
}
$extracted = performOcrOnImage($imagePath);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Vérification des informations</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php">Inscription participants</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Nouvelle inscription</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="list_participants.php">Participants</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    Vérifier et compléter les informations
                </div>
                <div class="card-body">
                    <form action="save_participant.php" method="post">
                        <div class="mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" name="nom" class="form-control" value="<?php echo htmlspecialchars($extracted['nom']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Prénom</label>
                            <input type="text" name="prenom" class="form-control" value="<?php echo htmlspecialchars($extracted['prenom']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Numéro du document (CIN / Passeport)</label>
                            <input type="text" name="document_id" class="form-control" value="<?php echo htmlspecialchars($extracted['document_id']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date de naissance</label>
                            <input type="date" name="date_naissance" class="form-control" value="<?php echo htmlspecialchars($extracted['date_naissance']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sexe</label>
                            <select name="sexe" class="form-select" required>
                                <option value="">Choisir</option>
                                <option value="M" <?php echo $extracted['sexe'] === 'M' ? 'selected' : ''; ?>>Masculin</option>
                                <option value="F" <?php echo $extracted['sexe'] === 'F' ? 'selected' : ''; ?>>Féminin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pays</label>
                            <input type="text" name="pays" list="country-list" class="form-control" value="<?php echo htmlspecialchars($extracted['pays']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ville</label>
                            <input type="text" name="ville" class="form-control" value="<?php echo htmlspecialchars($extracted['ville']); ?>" required>
                        </div>
                        <datalist id="country-list">
                            <option value="Morocco">
                            <option value="Algeria">
                            <option value="Tunisia">
                            <option value="France">
                            <option value="Spain">
                            <option value="Italy">
                            <option value="Germany">
                            <option value="Portugal">
                            <option value="Netherlands">
                            <option value="Belgium">
                            <option value="United Kingdom">
                            <option value="Ireland">
                            <option value="Switzerland">
                            <option value="Canada">
                            <option value="United States">
                            <option value="Mexico">
                            <option value="Brazil">
                            <option value="Argentina">
                            <option value="Turkey">
                            <option value="Saudi Arabia">
                            <option value="United Arab Emirates">
                            <option value="Qatar">
                            <option value="Egypt">
                            <option value="South Africa">
                            <option value="India">
                            <option value="China">
                            <option value="Japan">
                            <option value="South Korea">
                            <option value="Indonesia">
                            <option value="Australia">
                            <option value="New Zealand">
                        </datalist>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">Enregistrer le participant</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
