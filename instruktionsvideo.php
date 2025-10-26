<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instruktionsvideo - PTW System</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: var(--background-secondary, #f5f7fa);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .card {
            background: var(--background-primary, #ffffff);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 700px;
            width: 100%;
            margin: 0 auto;
        }
        
        .card h2 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary, #1a1a1a);
        }
        
        .card p {
            color: var(--text-secondary, #666);
            font-size: 1.125rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .video-container {
            position: relative;
            width: 100%;
            max-width: 600px;
            margin: 0 auto 1.5rem auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }
        
        .video-container video {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 12px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: var(--primary-color, #2563eb);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .back-link:hover {
            color: var(--primary-dark, #1e40af);
            text-decoration: underline;
        }
        
        /* Mobiloptimering */
        @media (max-width: 600px) {
            .card {
                padding: 1rem;
            }
            
            .video-container {
                max-width: 100%;
            }
            
            .card h2 {
                font-size: 1.25rem;
            }
            
            .card p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>📹 Instruktionsvideo for PTW System</h2>
        <p>Denne video viser dig hvordan du bruger PTW systemet som entreprenør. Vi anbefaler at du ser videoen inden du starter med at bruge appen.</p>
        
        <div class="video-container">
            <video controls playsinline preload="metadata">
                <source src="/assets/videos/ptw_instruktionsvideo.mp4" type="video/mp4" />
                Din browser understøtter ikke videoafspilning.
            </video>
        </div>
        
        <a href="login.php" class="back-link">← Tilbage til login</a>
    </div>
</body>
</html>
