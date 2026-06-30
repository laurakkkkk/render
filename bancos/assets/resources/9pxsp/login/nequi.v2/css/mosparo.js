<script>
  const mosparo = document.getElementById("fakeMosparo");

  mosparo.addEventListener("click", () => {
    if (mosparo.classList.contains("verified")) return;

    // Estado cargando
    mosparo.classList.add("loading");

    // Simula validación
    setTimeout(() => {
      mosparo.classList.remove("loading");
      mosparo.classList.add("verified");
    }, 1500); // tiempo de carga
  });
</script>
