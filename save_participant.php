<?php


require __DIR__ . '/config.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

function insertParticipant(PDO $pdo, array $data): int
{
    $sql = 'INSERT INTO participants (nom, prenom, date_naissance, sexe, pays, ville, document_id)
            VALUES (:nom, :prenom, :date_naissance, :sexe, :pays, :ville, :document_id)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nom' => $data['nom'],
        ':prenom' => $data['prenom'],
        ':date_naissance' => $data['date_naissance'],
        ':sexe' => $data['sexe'],
        ':pays' => $data['pays'],
        ':ville' => $data['ville'],
        ':document_id' => $data['document_id'],
    ]);
    return (int)$pdo->lastInsertId();
}

function appendRowToGoogleSheet(int $id, array $data): void
{
    if (!file_exists(GOOGLE_SERVICE_ACCOUNT_JSON)) {
        return;
    }
    if (!class_exists(\Google\Client::class)) {
        return;
    }
    $client = new Google\Client();
    $client->setAuthConfig(GOOGLE_SERVICE_ACCOUNT_JSON);
    $client->setScopes([\Google\Service\Sheets::SPREADSHEETS]);
    $service = new Google\Service\Sheets($client);
    $values = [[
        $id,
        $data['document_id'] ?? '',
        $data['nom'],
        $data['prenom'],
        $data['date_naissance'],
        $data['sexe'],
        $data['pays'],
        $data['ville'],
    ]];
    $body = new Google\Service\Sheets\ValueRange([
        'values' => $values,
    ]);
    $params = ['valueInputOption' => 'RAW'];
    $service->spreadsheets_values->append(
        GOOGLE_SHEETS_SPREADSHEET_ID,
        GOOGLE_SHEETS_RANGE,
        $body,
        $params
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Méthode non autorisée.';
    exit;
}

$nom = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$dateNaissance = $_POST['date_naissance'] ?? '';
$sexe = $_POST['sexe'] ?? '';
$pays = trim($_POST['pays'] ?? '');
$ville = trim($_POST['ville'] ?? '');
$documentId = trim($_POST['document_id'] ?? '');

if ($nom === '' || $prenom === '' || $dateNaissance === '' || $sexe === '' || $pays === '' || $ville === '') {
    http_response_code(400);
    echo 'Tous les champs sont obligatoires.';
    exit;
}

try {
    $pdo = getPdo();
    $participantId = insertParticipant($pdo, [
        'nom' => $nom,
        'prenom' => $prenom,
        'date_naissance' => $dateNaissance,
        'sexe' => $sexe,
        'pays' => $pays,
        'ville' => $ville,
        'document_id' => $documentId,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erreur serveur: ' . htmlspecialchars($e->getMessage());
    exit;
}

try {
    appendRowToGoogleSheet(
        $participantId,
        [
            'nom' => $nom,
            'prenom' => $prenom,
            'date_naissance' => $dateNaissance,
            'sexe' => $sexe,
            'pays' => $pays,
            'ville' => $ville,
            'document_id' => $documentId,
        ]
    );
} catch (Throwable $e) {
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Participant enregistré</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body.marrakech-body {
            min-height: 100vh;
            background: radial-gradient(circle at top left, #ffd8a6 0, #f4a261 40%, #e76f51 75%, #6b2c1a 100%);
            background-attachment: fixed;
        }

        .navbar-marrakech {
            background: linear-gradient(90deg, rgba(107, 44, 26, 0.95), rgba(231, 111, 81, 0.95));
        }

        .card-marrakech {
            border: 0;
            border-radius: 1.25rem;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.25);
        }

        .btn-marrakech-primary {
            background: linear-gradient(90deg, #e76f51, #f4a261);
            border: none;
        }

        .btn-marrakech-primary:hover {
            background: linear-gradient(90deg, #f4a261, #e76f51);
        }

        .btn-marrakech-outline {
            border-color: #e76f51;
            color: #6b2c1a;
        }

        .btn-marrakech-outline:hover {
            background-color: #e76f51;
            border-color: #e76f51;
            color: #fff;
        }

        .marrakech-brand {
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        @media (max-width: 576px) {
            .card-marrakech {
                margin-top: 0.5rem;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>

<body class="marrakech-body">
    <nav class="navbar navbar-expand-lg navbar-dark navbar-marrakech mb-4">
        <div class="container">
            <a class="navbar-brand marrakech-brand" href="index.php">Inscription Marrakech</a>
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
            <div class="col-12 col-lg-5 col-xl-4">
                <div class="card card-marrakech">
                    <div class="card-body">
                        <h1 class="h4 mb-3">Participant enregistré</h1>
                        <p class="fs-5 mb-2">
                            L’identifiant unique du participant est
                            <strong><?php echo htmlspecialchars((string)$participantId); ?></strong>
                        </p>
                        <?php if ($documentId !== ''): ?>
                            <p class="mb-2">
                                Numéro du document :
                                <strong><?php echo htmlspecialchars($documentId); ?></strong>
                            </p>
                        <?php endif; ?>
                        <p class="mb-3">
                            Les informations ont été enregistrées en base de données.
                            L’ajout dans Google Sheets est effectué si la configuration est correcte.
                        </p>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="index.php" class="btn btn-marrakech-primary text-white flex-fill">Ajouter un autre participant</a>
                            <a href="list_participants.php" class="btn btn-marrakech-outline flex-fill">Voir les participants</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>