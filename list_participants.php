<?php
require __DIR__ . '/config.php';

try {
    $pdo = getPdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erreur de connexion à la base de données';
    exit;
}

$q = trim($_GET['q'] ?? '');

$sql = 'SELECT id, nom, prenom, date_naissance, sexe, pays, ville, document_id
        FROM participants';
$params = [];

if ($q !== '') {
    $sql .= ' WHERE id = :idExact OR nom LIKE :like OR prenom LIKE :like OR document_id LIKE :like';
    $params[':like'] = '%' . $q . '%';
    $params[':idExact'] = ctype_digit($q) ? (int)$q : 0;
}

$sql .= ' ORDER BY id DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$participants = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des participants</title>
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
                    <span class="nav-link active">Participants</span>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center mb-3">
        <div class="col-12 col-lg-8">
            <form class="card card-body shadow-sm mb-3" method="get" action="list_participants.php">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-9">
                        <label class="form-label">Rechercher par ID, nom, prénom ou numéro de document</label>
                        <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>" placeholder="Ex: 12, EL ALAMI, AB123456">
                    </div>
                    <div class="col-12 col-md-3 d-grid">
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h1 class="h5 mb-0">Participants</h1>
                    <small class="text-muted">
                        <?php echo count($participants); ?> enregistrements (max 100)
                    </small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Document</th>
                                <th scope="col">Nom</th>
                                <th scope="col">Prénom</th>
                                <th scope="col">Date de naissance</th>
                                <th scope="col">Sexe</th>
                                <th scope="col">Pays</th>
                                <th scope="col">Ville</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($participants)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        Aucun participant trouvé.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($participants as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$row['id']); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['document_id'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['nom']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['prenom']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['date_naissance']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['sexe']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['pays']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['ville']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-muted small">
                    Les résultats sont triés par ID décroissant.
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

