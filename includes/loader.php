<div class="loader holo">
  <div class="ring"></div>
  <div class="title">NEXUS BANK</div>
  <div class="subtitle">Establishing Secure Link</div>
</div>

<style>
.loader.holo{position:fixed;inset:0;background:#020617;color:#38bdf8;
display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:99999;}
.ring{width:80px;height:80px;border:3px solid transparent;border-top:3px solid #38bdf8;
border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{100%{transform:rotate(360deg)}}
.title{margin-top:15px;font-size:22px;font-weight:700}
.subtitle{opacity:.7;font-size:13px}
</style>

<script>
window.addEventListener("load", function () {
    const loader = document.querySelector(".loader.holo");

    setTimeout(() => {
        loader.style.transition = "opacity .3s ease";
        loader.style.opacity = "0";

        setTimeout(() => {
            loader.style.display = "none";
        }, 600);
    }, 400);
});
</script>
