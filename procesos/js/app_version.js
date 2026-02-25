async function checkAppVersion() {
  try {
    const r = await fetch("procesos/php/app_version.php", { cache: "no-store" });
    const j = await r.json();
    if (!j.success) return;

    const server = j.data.version;
    const force = parseInt(j.data.force_update || "0", 10);
    const minV = j.data.min_version || null;

    const local = window.APP_VERSION;

    if (isNewerVersion(server, local)) {
      // Mostrar aviso
      // Si force o local < minV => bloquear
      showUpdateModal({
        serverVersion: server,
        message: j.data.message,
        force: force || (minV && isNewerVersion(minV, local) === false ? 1 : 0),
      });
    }
  } catch (e) {
    console.log("Version check failed:", e);
  }
}

// comparador semver simple "1.4.12"
function isNewerVersion(a, b) {
  const pa = String(a)
    .split(".")
    .map((n) => parseInt(n, 10) || 0);
  const pb = String(b)
    .split(".")
    .map((n) => parseInt(n, 10) || 0);
  for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
    const x = pa[i] || 0,
      y = pb[i] || 0;
    if (x > y) return true;
    if (x < y) return false;
  }
  return false;
}
