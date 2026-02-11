

let recognition;
let voicesLoaded = false;

speechSynthesis.onvoiceschanged = () => voicesLoaded = true;

const btn = document.getElementById("voiceBtn");
const output = document.getElementById("voice-text");

btn.addEventListener("click", () => {

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    if (!SpeechRecognition) {
        output.innerText = "Speech recognition not supported";
        return;
    }

    recognition = new SpeechRecognition();
    recognition.lang = "en-IN";
    recognition.interimResults = false;
    recognition.continuous = false;

    recognition.start();
    output.innerText = "🎧 Listening...";

    recognition.onresult = (event) => {
        const text = event.results[0][0].transcript.toLowerCase();
        output.innerText = "🗣 You said: " + text;
        handleCommand(text);
    };

    recognition.onerror = (event) => {
        output.innerText = "Error: " + event.error;
    };
});

function handleCommand(cmd){

    if(cmd.includes("balance")){
        const bal = document.getElementById("accountBalance").innerText;
        speak("Your current account balance is rupees " + bal);
    }

    else if(cmd.includes("transfer")){
        speak("Opening money transfer page");
        window.location.href = "transfer.php";
    }

    else if(cmd.includes("deposit")){
        speak("Opening deposit page");
        window.location.href = "deposit.php";
    }

    else if(cmd.includes("withdraw")){
        speak("Opening withdraw page");
        window.location.href = "withdraw.php";
    }

    else if(cmd.includes("loan")){
        speak("Opening loan section");
        window.location.href = "loan.php";
    }

    else{
        speak("Sorry, I did not understand the command");
    }
}

function speak(text){

    if(!voicesLoaded){
        setTimeout(() => speak(text), 300);
        return;
    }

    const msg = new SpeechSynthesisUtterance(text);
    msg.lang = "en-IN";
    msg.volume = 1;
    msg.rate = 1;
    msg.pitch = 1;

    const voices = speechSynthesis.getVoices();
    if(voices.length) msg.voice = voices.find(v => v.lang.includes("en")) || voices[0];

    speechSynthesis.cancel();
    speechSynthesis.speak(msg);
}

