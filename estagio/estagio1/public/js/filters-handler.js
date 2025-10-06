document.addEventListener("DOMContentLoaded", function () {
  const companySelect = document.getElementById("company-select");
  const officeSelect = document.getElementById("office-select");
  const userSelect = document.getElementById("user-select");
  const startDate = document.getElementById("startDate");
  const endDate = document.getElementById("endDate");
  const workSchedule = document.getElementById("workSchedule-select");

  function updateUrl(param, value, reset = {}) {
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);

    params.set(param, value);

    // Reiniciar valores dependientes si se indica
    for (const [key, val] of Object.entries(reset)) {
      params.set(key, val);
    }

    window.location.href = `${window.location.pathname}?${params.toString()}`;
  }

  if (companySelect) {
    companySelect.addEventListener("change", function () {
      updateUrl("com", this.value, { off: "all", us: "all", ws: "all" });
    });
  }

  if (officeSelect) {
    officeSelect.addEventListener("change", function () {
      updateUrl("off", this.value, { us: "all" });
    });
  }

  if (userSelect) {
    userSelect.addEventListener("change", function () {
      updateUrl("us", this.value);
    });
  }

  if (startDate) {
    startDate.addEventListener("change", function () {
      updateUrl("start", this.value);
    });
  }

  if (endDate) {
    endDate.addEventListener("change", function () {
      updateUrl("end", this.value);
    });
  }

  if (workSchedule) {
    workSchedule.addEventListener("change", function () {
      console.log("Work schedule changed to:", this.value);
      updateUrl("ws", this.value);
    });
  }
});
