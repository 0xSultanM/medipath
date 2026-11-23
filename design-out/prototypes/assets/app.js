/* MediPath prototypes — progressive enhancement ONLY (app.js)
   No frameworks. All screens work with JS disabled; this adds:
   1) theme persistence across pages (writes data-theme + syncs #mp-theme),
   2) aria-expanded on CSS-driven drawer/panel checkboxes,
   3) Escape closes any open drawer/modal/panel. */
(function () {
  "use strict";
  var root = document.documentElement;

  // 1) Theme persistence
  var theme = localStorage.getItem("mp-theme") || "light";
  root.setAttribute("data-theme", theme);
  var t = document.getElementById("mp-theme");
  if (t) {
    t.checked = theme === "dark";
    t.addEventListener("change", function () {
      var v = t.checked ? "dark" : "light";
      root.setAttribute("data-theme", v);
      localStorage.setItem("mp-theme", v);
    });
  }

  // 2) Mirror checkbox state onto its label buttons (aria-expanded)
  var map = { "drawer-nav": "nav-menu", "drawer-filters": "filter-drawer",
              "drawer-notif": "notif-drawer", "bot-open": "chatbot-panel",
              "m-leave": "modal", "m-slot": "modal", "m-type": "modal",
              "m-emg": "emergency-modal" };
  Object.keys(map).forEach(function (id) {
    var c = document.getElementById(id);
    if (!c) return;
    c.addEventListener("change", function () {
      var trig = document.querySelector('[data-controls="' + id + '"]');
      if (trig) trig.setAttribute("aria-expanded", String(c.checked));
    });
  });

  // 3) Escape closes everything open
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    ["drawer-nav", "drawer-filters", "drawer-notif", "bot-open",
     "m-leave", "m-slot", "m-type", "m-emg"].forEach(function (id) {
      var c = document.getElementById(id);
      if (c && c.checked) { c.checked = false;
        c.dispatchEvent(new Event("change")); }
    });
  });
})();
