<?php
require __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription participants</title>
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
                    <span class="nav-link active">Nouvelle inscription</span>
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
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    Choisir une image du document
                </div>
                <div class="card-body">
                    <form action="process_document.php" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Téléverser une image</label>
                            <input type="file" name="document_image" accept="image/*" class="form-control">
                        </div>
                        <input type="hidden" name="camera_image" id="camera_image_input">
                        <div class="mb-3">
                            <label class="form-label">Ou utiliser la caméra</label>
                            <div class="d-flex flex-column align-items-center">
                                <video id="video" class="border rounded mb-2" autoplay playsinline style="max-width: 100%; height: auto;"></video>
                                <canvas id="canvas" class="d-none"></canvas>
                                <div class="d-flex gap-2">
                                    <button type="button" id="startCamera" class="btn btn-outline-primary btn-sm">Activer la caméra</button>
                                    <button type="button" id="capture" class="btn btn-primary btn-sm" disabled>Capturer</button>
                                </div>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">Analyser le document</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let stream;
const startButton = document.getElementById('startCamera');
const captureButton = document.getElementById('capture');
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const hiddenInput = document.getElementById('camera_image_input');

if (startButton && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    startButton.addEventListener('click', async function () {
        try {
            stream = await navigator.mediaDevices.getUserMedia({video: true});
            video.srcObject = stream;
            captureButton.disabled = false;
        } catch (e) {
        }
    });
}

if (captureButton) {
    captureButton.addEventListener('click', function () {
        if (!stream) {
            return;
        }
        const trackSettings = stream.getVideoTracks()[0].getSettings();
        const width = trackSettings.width || 640;
        const height = trackSettings.height || 480;
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, width, height);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        hiddenInput.value = dataUrl;
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

