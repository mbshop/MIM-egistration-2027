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
            <div class="col-12 col-lg-5 col-xl-4">
                <div class="card card-marrakech mb-4">
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
                                    <div class="d-flex flex-wrap justify-content-center gap-2">
                                        <button type="button" id="startCamera" class="btn btn-outline-primary btn-sm">Activer la caméra</button>
                                        <button type="button" id="switchCamera" class="btn btn-outline-secondary btn-sm" disabled>Inverser caméra</button>
                                        <button type="button" id="toggleFlash" class="btn btn-outline-warning btn-sm d-none">Flash</button>
                                        <button type="button" id="capture" class="btn btn-primary btn-sm" disabled>Capturer</button>
                                    </div>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-marrakech-primary btn-lg text-white">Analyser le document</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let stream;
        let currentTrack;
        let usingBackCamera = true;
        let torchOn = false;

        const startButton = document.getElementById('startCamera');
        const captureButton = document.getElementById('capture');
        const switchButton = document.getElementById('switchCamera');
        const flashButton = document.getElementById('toggleFlash');
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const hiddenInput = document.getElementById('camera_image_input');

        async function stopCurrentStream() {
            if (stream) {
                stream.getTracks().forEach(t => t.stop());
                stream = null;
                currentTrack = null;
            }
        }

        async function startCameraWithConstraints(useBack) {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                return;
            }
            await stopCurrentStream();
            const preferredFacingMode = useBack ? 'environment' : 'user';
            let constraints = {
                video: {
                    facingMode: {
                        ideal: preferredFacingMode
                    }
                }
            };
            try {
                stream = await navigator.mediaDevices.getUserMedia(constraints);
            } catch (e) {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: true
                    });
                } catch (err) {
                    return;
                }
            }
            video.srcObject = stream;
            const tracks = stream.getVideoTracks();
            currentTrack = tracks.length > 0 ? tracks[0] : null;
            captureButton.disabled = !currentTrack;
            switchButton.disabled = false;

            if (currentTrack) {
                const caps = currentTrack.getCapabilities ? currentTrack.getCapabilities() : {};
                if (caps.torch) {
                    flashButton.classList.remove('d-none');
                    flashButton.disabled = false;
                } else {
                    flashButton.classList.add('d-none');
                }
            }
        }

        if (startButton && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            startButton.addEventListener('click', function() {
                usingBackCamera = true;
                startCameraWithConstraints(true);
            });
        }

        if (switchButton && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            switchButton.addEventListener('click', function() {
                usingBackCamera = !usingBackCamera;
                torchOn = false;
                startCameraWithConstraints(usingBackCamera);
            });
        }

        if (flashButton) {
            flashButton.addEventListener('click', async function() {
                if (!currentTrack || !currentTrack.getCapabilities) {
                    return;
                }
                const caps = currentTrack.getCapabilities();
                if (!caps.torch) {
                    return;
                }
                try {
                    torchOn = !torchOn;
                    await currentTrack.applyConstraints({
                        advanced: [{
                            torch: torchOn
                        }]
                    });
                    flashButton.classList.toggle('btn-outline-warning', !torchOn);
                    flashButton.classList.toggle('btn-warning', torchOn);
                } catch (e) {}
            });
        }

        if (captureButton) {
            captureButton.addEventListener('click', function() {
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