<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<title>Player</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>

<style>
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{
margin:0;
width:100%;
height:100%;
background:transparent;
overflow:hidden;
font-family:system-ui,Arial
}
#app{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
#wrap{position:relative;width:100%;height:100%;background:transparent;overflow:hidden}
video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;background:transparent}
@media(max-width:900px){video{object-fit:contain}}

#centerIcon{
position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
font-size:90px;color:#ffd400;
background:radial-gradient(circle,rgba(0,0,0,.2),rgba(0,0,0,.6));
opacity:1;pointer-events:none;transition:.25s;z-index:5
}
#centerIcon.hide{opacity:0}

#loader{
position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
background:rgba(0,0,0,.35);opacity:0;pointer-events:none;transition:.25s;z-index:6
}
#loader.show{opacity:1}

.spinner{
width:48px;height:48px;border-radius:50%;
border:5px solid rgba(255,212,0,.25);
border-top:5px solid #ffd400;
animation:spin 1s linear infinite
}
@keyframes spin{to{transform:rotate(360deg)}}

#controls{
position:absolute;left:3%;right:3%;bottom:3%;
background:rgba(15,15,15,.9);
border-radius:28px;padding:12px 16px;
display:flex;align-items:center;gap:10px;
opacity:1;transition:.25s;z-index:10
}
#controls.hide{opacity:0;pointer-events:none}

.btn{width:44px;height:44px;border-radius:50%;background:#111;color:#ffd400;
display:flex;align-items:center;justify-content:center;font-size:18px;cursor:pointer;flex-shrink:0}

#progress{flex:1;height:8px;background:#2a2a2a;border-radius:20px;position:relative;cursor:pointer;min-width:60px}
#buffer,#played{position:absolute;top:0;left:0;height:100%;border-radius:20px}
#buffer{background:rgba(255,212,0,.35)}
#played{background:#ffd400}
#thumb{position:absolute;top:50%;transform:translate(-50%,-50%);width:14px;height:14px;border-radius:50%;background:#ffd400}

#tooltip{
position:absolute;bottom:22px;transform:translateX(-50%);
background:#111;color:#ffd400;font-size:10px;
padding:3px 7px;border-radius:6px;
opacity:0;pointer-events:none;white-space:nowrap
}
#progress:hover #tooltip,#progress.dragging #tooltip{opacity:1}

#time{color:#ffd400;font-size:12px;min-width:110px;text-align:center}
#fs{margin-left:auto}
#live{color:#ff2d2d;font-weight:700;font-size:13px;display:none}

#volumeBox{
position:absolute;top:50%;left:50%;
transform:translate(-50%,-50%) scale(.9);
background:rgba(15,15,15,.95);
border-radius:18px;padding:18px 26px;
text-align:center;opacity:0;pointer-events:none;
transition:.25s;z-index:20
}
#volumeBox.show{opacity:1;transform:translate(-50%,-50%) scale(1)}
#volumeBox .title{font-size:12px;color:#aaa;margin-bottom:6px}
#volumeBox .value{font-size:34px;color:#ffd400;font-weight:700}

#hint{
position:absolute;top:20px;left:50%;
transform:translateX(-50%);
background:linear-gradient(135deg,#111,#1b1b1b);
border-radius:18px;padding:12px 20px;
display:none;gap:18px;align-items:center;
box-shadow:0 10px 30px rgba(0,0,0,.6);z-index:15
}
.hkey{display:flex;align-items:center;gap:6px;color:#ddd;font-size:12px}
.k{background:#000;border:1px solid #333;border-radius:6px;padding:2px 7px;color:#ffd400;font-size:11px;font-weight:600}
</style>
</head>

<body>
<div id="app">
<div id="wrap">

<video id="video" playsinline preload="auto"></video>

<div id="centerIcon">▶</div>
<div id="loader"><div class="spinner"></div></div>

<div id="hint">
<div class="hkey"><span class="k">▲</span><span class="k">▼</span>Volume</div>
<div class="hkey"><span class="k">M</span>Mute</div>
<div class="hkey"><span class="k">Space</span>Play</div>
</div>

<div id="volumeBox">
<div class="title">Volume</div>
<div class="value" id="volumeVal">100%</div>
</div>

<div id="controls">
<div class="btn" id="play">▶</div>

<div id="progress">
<div id="buffer"></div>
<div id="played"></div>
<div id="thumb"></div>
<div id="tooltip">00:00</div>
</div>

<div id="time">00:00 / 00:00</div>
<div id="live">LIVE</div>
<div class="btn" id="fs">⛶</div>
</div>

</div>
</div>

<script>
const video=document.getElementById("video"),
playBtn=document.getElementById("play"),
progress=document.getElementById("progress"),
played=document.getElementById("played"),
buffer=document.getElementById("buffer"),
thumb=document.getElementById("thumb"),
timeEl=document.getElementById("time"),
tooltip=document.getElementById("tooltip"),
centerIcon=document.getElementById("centerIcon"),
loader=document.getElementById("loader"),
controls=document.getElementById("controls"),
fs=document.getElementById("fs"),
wrap=document.getElementById("wrap"),
volumeBox=document.getElementById("volumeBox"),
volumeVal=document.getElementById("volumeVal"),
hint=document.getElementById("hint"),
liveEl=document.getElementById("live");

const isDesktop=!("ontouchstart" in window);
const params=new URLSearchParams(location.search);
const src=params.get("link");
if(!src){document.body.innerHTML="NO LINK";throw"";}

let isLive=false,readyToPlay=false;
const START_DELAY=30;

if(Hls.isSupported()){
const hls=new Hls({
startPosition:-START_DELAY,
liveSyncDurationCount:10,
liveMaxLatencyDurationCount:20,
maxBufferLength:90,
maxMaxBufferLength:180,
maxLiveSyncPlaybackRate:1
});
hls.loadSource(src);
hls.attachMedia(video);

hls.on(Hls.Events.LEVEL_LOADED,(e,d)=>{
isLive=d.details.live;
if(isLive){
progress.style.display="none";
timeEl.style.display="none";
liveEl.style.display="block";
}
});

hls.on(Hls.Events.FRAG_BUFFERED,()=>{
if(isLive&&video.buffered.length){
const end=video.buffered.end(video.buffered.length-1);
if(!readyToPlay&&end>=START_DELAY){
readyToPlay=true;
video.currentTime=Math.max(0,end-START_DELAY);
video.play().catch(()=>{});
}
}
});
}else video.src=src;

function fmt(t){
t=Math.max(0,Math.min(t,video.duration||0));
return String(Math.floor(t/60)).padStart(2,"0")+":"+String(Math.floor(t%60)).padStart(2,"0");
}
function togglePlay(){video.paused?video.play().catch(()=>{}):video.pause()}

video.onclick=togglePlay;
playBtn.onclick=togglePlay;

let hideTimer;
function showControls(){
controls.classList.remove("hide");
clearTimeout(hideTimer);
if(!video.paused)hideTimer=setTimeout(()=>controls.classList.add("hide"),2000);
}

wrap.onmousemove=showControls;
wrap.ontouchstart=showControls;

video.onplay=()=>{
playBtn.textContent="⏸";
centerIcon.classList.add("hide");
loader.classList.remove("show");
hint.style.display="none";
showControls();
};
video.onpause=()=>{
playBtn.textContent="▶";
centerIcon.classList.remove("hide");
controls.classList.remove("hide");
if(isDesktop)hint.style.display="flex";
};

video.onwaiting=()=>{
loader.classList.add("show");
centerIcon.classList.add("hide");
};
video.onplaying=()=>{
loader.classList.remove("show");
if(!video.paused)centerIcon.classList.add("hide");
};

video.ontimeupdate=()=>{
if(isLive||!video.duration)return;
const p=video.currentTime/video.duration*100;
played.style.width=p+"%";
thumb.style.left=p+"%";
timeEl.textContent=`${fmt(video.currentTime)} / ${fmt(video.duration)}`;
};
video.onprogress=()=>{
if(isLive||!video.buffered.length||!video.duration)return;
buffer.style.width=video.buffered.end(video.buffered.length-1)/video.duration*100+"%";
};

progress.onpointermove=e=>{
if(isLive)return;
const r=progress.getBoundingClientRect();
let p=(e.clientX-r.left)/r.width;
p=Math.min(Math.max(p,0),1);
tooltip.style.left=p*100+"%";
tooltip.textContent=fmt(p*video.duration);
};
progress.onpointerdown=e=>{
if(isLive)return;
const r=progress.getBoundingClientRect();
video.currentTime=(e.clientX-r.left)/r.width*video.duration;
};

let drag=false;
thumb.onpointerdown=e=>{
if(isLive)return;
drag=true;progress.classList.add("dragging");
thumb.setPointerCapture(e.pointerId);
};
window.onpointermove=e=>{
if(!drag||isLive)return;
const r=progress.getBoundingClientRect();
let p=(e.clientX-r.left)/r.width;
p=Math.min(Math.max(p,0),1);
video.currentTime=p*video.duration;
};
window.onpointerup=()=>{drag=false;progress.classList.remove("dragging")};

fs.onclick=()=>{
!document.fullscreenElement?wrap.requestFullscreen().catch(()=>{}):document.exitFullscreen();
};

let volTimer,lastVol=0.5;
function showVolume(){
volumeVal.textContent=Math.round(video.muted?0:video.volume*100)+"%";
volumeBox.classList.add("show");
clearTimeout(volTimer);
volTimer=setTimeout(()=>volumeBox.classList.remove("show"),800);
}

document.addEventListener("keydown",e=>{
if(["INPUT","TEXTAREA"].includes(document.activeElement.tagName))return;
switch(e.code){
case "Space":
case "KeyK":e.preventDefault();togglePlay();break;
case "ArrowRight":
case "KeyL":if(!isLive)video.currentTime+=5;break;
case "ArrowLeft":
case "KeyJ":if(!isLive)video.currentTime-=5;break;
case "ArrowUp":
video.muted=false;video.volume=Math.min(1,video.volume+0.05);showVolume();break;
case "ArrowDown":
video.volume=Math.max(0,video.volume-0.05);
if(video.volume===0)video.muted=true;showVolume();break;
case "KeyM":
if(video.muted){video.muted=false;video.volume=lastVol||0.5;}
else{lastVol=video.volume||0.5;video.muted=true;}
showVolume();break;
case "KeyF":fs.click();break;
}
showControls();
});
</script>

<!-- OFFLINE / ONLINE NETWORK HANDLER -->
<style>
#netStatus{
position:absolute;
inset:0;
display:flex;
flex-direction:column;
align-items:center;
justify-content:center;
background:linear-gradient(145deg,#000,#111);
z-index:999;
opacity:0;
pointer-events:none;
transition:.35s ease;
}
#netStatus.show{opacity:1;pointer-events:auto}

#netStatus .ring{
width:70px;height:70px;
border-radius:50%;
border:6px solid rgba(255,212,0,.25);
border-top:6px solid #ffd400;
animation:spin 1s linear infinite
}
@keyframes spin{to{transform:rotate(360deg)}}

#netStatus .title{
margin-top:22px;
font-size:28px;
font-weight:900;
letter-spacing:2px;
color:#ffd400
}
#netStatus .sub{
margin-top:6px;
font-size:13px;
color:#aaa
}
</style>

<div id="netStatus">
<div class="ring"></div>
<div class="title">NO INTERNET</div>
<div class="sub">Waiting for connection...</div>
</div>

<script>
const netBox=document.getElementById("netStatus");

function checkNet(){
if(!navigator.onLine){
netBox.classList.add("show");
}else{
netBox.classList.remove("show");
}
}

window.addEventListener("offline",checkNet);
window.addEventListener("online",()=>location.reload());
checkNet();
</script>
<script>
document.addEventListener('contextmenu',e=>e.preventDefault());
document.addEventListener('keydown',e=>{if(e.key==="F12")e.preventDefault();});
</script>
</body>
</html>
