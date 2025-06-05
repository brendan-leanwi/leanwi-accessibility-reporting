document.addEventListener("DOMContentLoaded", () => {
  // Make table rows clickable based on anchor tag inside
  document.querySelectorAll("table.widefat tbody tr").forEach((row) => {
    const link = row.querySelector("a");
    if (link) {
      row.style.cursor = "pointer";
      row.addEventListener("click", () => {
        window.location.href = link.href;
      });
    }
  });

  // Snapshot button click handler
  const button = document.getElementById("leanwi-make-snapshot");
  const status = document.getElementById("leanwi-snapshot-status");
  const spinner = document.getElementById("leanwi-snapshot-spinner");

  if (button) {
    button.addEventListener("click", function () {
      status.textContent = "Processing...";
      status.style.color = "black";
      spinner.style.visibility = "visible";

      fetch(leanwiAccessibility.ajax_url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          action: "leanwi_take_snapshot",
          nonce: leanwiAccessibility.nonce,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          status.textContent = data.message || "Snapshot complete.";
          status.style.color = "green";
          spinner.style.visibility = "hidden";
          location.reload();
        })
        .catch((err) => {
          console.error("Snapshot error:", err);
          status.textContent = "Snapshot failed.";
          status.style.color = "red";
          spinner.style.visibility = "hidden";
        });
    });
  }
});
