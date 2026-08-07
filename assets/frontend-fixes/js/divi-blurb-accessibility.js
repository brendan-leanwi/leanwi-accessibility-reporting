document.addEventListener("DOMContentLoaded", function () {
  const blurbs = document.querySelectorAll(".et_pb_blurb");

  blurbs.forEach((blurb) => {
    const heading = blurb.querySelector(".et_pb_module_header span");
    const description = blurb.querySelector(".et_pb_blurb_description");
    const img = blurb.querySelector("img");

    let headingText = heading ? heading.textContent.trim() : "";
    let descText = description ? description.textContent.trim() : "";
    let label = headingText || "Resource";

    if (!headingText && img && img.alt) {
      label = img.alt.trim() || label;
    }

    if (descText) {
      label = `${label}. ${descText}`;
    }

    let linkWrapper = blurb.querySelector(".blurb-link-wrapper");

    if (!linkWrapper) {
      linkWrapper = document.createElement("div");
      linkWrapper.className = "blurb-link-wrapper";
      linkWrapper.setAttribute("role", "link");
      linkWrapper.setAttribute("tabindex", "0");
      blurb.appendChild(linkWrapper);
    }

    linkWrapper.setAttribute("aria-label", `${label} (opens in a new window)`);

    linkWrapper.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        blurb.click();
        e.preventDefault();
      }
    });
  });
});