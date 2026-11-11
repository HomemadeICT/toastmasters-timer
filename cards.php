<?php
$meeting_id = isset($_GET['id']) ? intval($_GET['id']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Timing Cards</title>
    <link rel="icon" type="image/png" href="Toastmasters%20icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=3">
    <style>
        :root {
            --white: #ffffff;
            --green: #22c55e;
            --yellow: #eab308;
            --red: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.3s ease;
            background-color: var(--white);
            color: #1e293b;
            text-align: center;
            overflow: hidden;
            user-select: none;
        }

        #status-text {
            font-size: clamp(4rem, 15vw, 12rem);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            line-height: 1;
        }
        
        .card-logo {
            width: clamp(100px, 18vw, 220px);
            margin-bottom: 2rem;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
            z-index: 10;
        }

        .hint {
            position: absolute;
            bottom: 2rem;
            font-size: 1.2rem;
            opacity: 0.5;
            font-weight: 600;
        }

        .back-btn {
            position: absolute;
            top: 2rem;
            left: 2rem;
            padding: 0.75rem 1.5rem;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            text-decoration: none;
            color: inherit;
            font-weight: 700;
            z-index: 100;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: rgba(0, 0, 0, 0.2);
            transform: scale(1.05);
        }

        /* Color States */
        body.green { background-color: var(--green); color: white; }
        body.yellow { background-color: var(--yellow); color: white; }
        body.red { background-color: var(--red); color: white; }

        body.obs-safe-mode {
            padding-right: 33vw !important;
            transition: padding-right 0.3s ease;
        }
        
        .timer-script {
            max-width: 700px;
            margin: 0 auto 2rem auto;
            text-align: center;
            font-size: 1.3rem;
            color: #334155;
            padding: 0 20px;
        }
        .timer-script h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #004165;
        }
    </style>
</head>
<body id="card-body" onclick="cycleColor(event)">
    <!-- Clean natively balanced logo -->
    <img src="Toastmasters%20icon.png" class="card-logo zoom-logo" alt="Toastmasters">
    <?php if ($meeting_id): ?>
        <a href="meeting.php?id=<?php echo $meeting_id; ?>" class="back-btn">← EXIT</a>
    <?php else: ?>
        <a href="index.php" class="back-btn">← EXIT</a>
    <?php endif; ?>

    <div id="status-text" class="zoom-timer" style="display: none;">WHITE</div>
    <div id="timer-script" class="timer-script zoom-meta">
        <h2>Timer's Introduction Script</h2>
        <p><strong>Mr./Madam Toastmaster, Fellow Toastmasters, and Guests:</strong></p>
        <p style="margin-bottom: 15px;">As Timer, I will time the Table Topics speakers, formal speeches, and the evaluations. I will alert each speaker of the time they have left, using the green, yellow, and red cards, which denote specific times remaining.</p>
    </div>
    <div class="hint">Click anywhere to change card</div>

    <script>
        const states = [
            { id: 'white', label: 'WHITE' },
            { id: 'green', label: 'GREEN' },
            { id: 'yellow', label: 'YELLOW' },
            { id: 'red', label: 'RED' }
        ];
        
        let currentIndex = 0;
        const body = document.getElementById('card-body');
        const text = document.getElementById('status-text');
        const scriptBox = document.getElementById('timer-script');

        function cycleColor(e) {
            // Prevent cycling when clicking action buttons
            if (e && (e.target.tagName === 'BUTTON' || e.target.tagName === 'A')) return;

            currentIndex = (currentIndex + 1) % states.length;
            const state = states[currentIndex];
            
            // Persist obs-safe-mode by not clearing all classes
            body.classList.remove('green', 'yellow', 'red');

            if (state.id !== 'white') {
                body.classList.add(state.id);
                text.style.display = 'block';
                scriptBox.style.display = 'none';
                text.innerText = state.label;
            } else {
                text.style.display = 'none';
                scriptBox.style.display = 'block';
            }
        }
    </script>
    <script src="js/obs-toggle.js"></script>
    <script src="js/zoom-controls.js"></script>
</body>
</html>
