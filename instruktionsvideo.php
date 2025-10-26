<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Instruktionsvideo - PTW System</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
    <style>
        .video-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .video-card {
            background: var(--background-primary);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
        }
        
        .video-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
        }
        
        .video-header {
            margin-bottom: 1.5rem;
        }
        
        .video-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .video-description {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            height: 0;
            overflow: hidden;
            border-radius: var(--radius-md);
            background: #000;
        }
        
        .video-wrapper video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: var(--radius-md);
        }
        
        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .back-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .video-container {
                margin: 1rem auto;
            }
            
            .video-card {
                padding: 1.5rem;
            }
            
            .video-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="video-container">
        <div class="video-card">
            <div class="video-header">
                <h1>📹 Instruktionsvideo for PTW System</h1>
            </div>
            
            <div class="video-description">
                <p>Denne video viser dig hvordan du bruger PTW systemet som entreprenør. Vi anbefaler at du ser videoen inden du starter med at bruge appen.</p>
            </div>
            
            <div class="video-wrapper">
                <video controls preload="metadata">
                    <source src="assets/videos/ptw_instruktionsvideo.mp4" type="video/mp4">
                    Din browser understøtter ikke videoafspilning. Du kan downloade videoen direkte ved at højreklikke på siden.
                </video>
            </div>
            
            <a href="login.php" class="back-link">← Tilbage til login</a>
        </div>
    </div>
</body>
</html>
