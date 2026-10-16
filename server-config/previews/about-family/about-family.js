(() => {
  "use strict";

  const root = document.querySelector(".harmat-about-preview");
  const menu = root?.querySelector(".hap-mobile-menu");
  if (!menu) return;

  const closeMenu = (restoreFocus = false) => {
    if (!menu.open) return;
    menu.open = false;
    if (restoreFocus) menu.querySelector("summary").focus();
  };

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && menu.open) {
      event.preventDefault();
      closeMenu(true);
    }
  });

  document.addEventListener("click", (event) => {
    if (!menu.contains(event.target)) closeMenu();
  });

  menu.addEventListener("click", (event) => {
    if (event.target.closest("a")) closeMenu();
  });

  menu.addEventListener("focusout", (event) => {
    if (event.relatedTarget && !menu.contains(event.relatedTarget)) closeMenu();
  });

  const desktop = window.matchMedia("(min-width: 761px)");
  desktop.addEventListener("change", (event) => {
    if (event.matches) closeMenu();
  });
})();
