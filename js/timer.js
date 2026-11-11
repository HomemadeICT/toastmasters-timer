let startTime;
let elapsedTime = 0;
let timerInterval;

const display = document.getElementById('timer-display');
const body = document.getElementById('timer-body');
const startBtn = document.getElementById('startBtn');
const stopBtn = document.getElementById('stopBtn');
const resetBtn = document.getElementById('resetBtn');

function timeToString(time) {
    let diffInMin = time / 60000;
    let mm = Math.floor(diffInMin);

    let diffInSec = (diffInMin - mm) * 60;
    let ss = Math.floor(diffInSec);

    let formattedMM = mm.toString().padStart(2, "0");
    let formattedSS = ss.toString().padStart(2, "0");

    return `${formattedMM}:${formattedSS}`;
}

function updateColor(seconds) {
    body.classList.remove('bg-green', 'bg-yellow', 'bg-red');
    if (seconds >= config.max) {
        body.classList.add('bg-red');
    } else if (config.role !== 'Round Robin') {
        if (seconds >= config.target) {
            body.classList.add('bg-yellow');
        } else if (seconds >= config.min) {
            body.classList.add('bg-green');
        }
    }
}

function start() {
    startTime = Date.now() - elapsedTime;
    timerInterval = setInterval(function printTime() {
        elapsedTime = Date.now() - startTime;
        display.innerHTML = timeToString(elapsedTime);
        updateColor(Math.floor(elapsedTime / 1000));
    }, 100);
    showButton("STOP");
}

function stop() {
    clearInterval(timerInterval);
    showButton("START");
    saveTime();
}

function reset() {
    clearInterval(timerInterval);
    display.innerHTML = "00:00";
    elapsedTime = 0;
    body.classList.remove('bg-green', 'bg-yellow', 'bg-red');
    showButton("START");
}

function showButton(buttonKey) {
    if (buttonKey === "STOP") {
        startBtn.style.display = "none";
        stopBtn.style.display = "block";
    } else {
        startBtn.style.display = "block";
        stopBtn.style.display = "none";
    }
}

function saveTime() {
    const finalTimeString = display.innerHTML;
    fetch('src/save_time.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${config.speakerId}&time=${finalTimeString}`
    })
        .then(response => response.json())
        .then(data => {
            console.log('Saved:', data);
        })
        .catch(error => console.error('Error saving time:', error));
}

startBtn.addEventListener("click", start);
stopBtn.addEventListener("click", stop);
resetBtn.addEventListener("click", reset);
