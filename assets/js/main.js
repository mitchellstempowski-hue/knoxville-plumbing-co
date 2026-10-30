/* Site-02 (Knoxville) template JS — vanilla, no dependencies */
(function () {
  "use strict";

  /* Mobile nav */
  var toggle = document.querySelector(".nav-toggle");
  var nav = document.getElementById("mainNav");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  }

  /* FAQ accordion */
  document.querySelectorAll(".faq-item").forEach(function (item) {
    var btn = item.querySelector(".faq-q");
    var panel = item.querySelector(".faq-a");
    if (!btn || !panel) return;
    btn.addEventListener("click", function () {
      var isOpen = item.classList.contains("open");
      document.querySelectorAll(".faq-item.open").forEach(function (other) {
        other.classList.remove("open");
        other.querySelector(".faq-a").style.maxHeight = null;
        other.querySelector(".faq-q").setAttribute("aria-expanded", "false");
      });
      if (!isOpen) {
        item.classList.add("open");
        panel.style.maxHeight = panel.scrollHeight + "px";
        btn.setAttribute("aria-expanded", "true");
      }
    });
  });

  /* Quote form — posts JSON to the mailer on vellops.com (GitHub Pages can't run PHP). */
  var QUOTE_ENDPOINT = "https://vellops.com/quote-knoxville.php"; // live mailer, CORS-enabled
  var form = document.getElementById("quoteForm");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var err = form.querySelector(".form-error");
      var ok = form.querySelector(".form-success");
      err.classList.remove("show");
      ok.classList.remove("show");

      // Honeypot: bots fill this, humans never see it.
      if (form.querySelector('[name="company"]').value !== "") return;

      var name = (form.querySelector('[name="name"]') || {}).value || "";
      name = name.trim();
      var phone = (form.querySelector('[name="phone"]') || {}).value || "";
      phone = phone.trim();
      var serviceEl = form.querySelector('[name="service"]');
      var service = serviceEl ? serviceEl.value : "";
      var detailsEl = form.querySelector('[name="details"]');
      var details = detailsEl ? detailsEl.value.trim() : "";
      var zipEl = form.querySelector('[name="zip"]');
      var zip = zipEl ? zipEl.value.trim() : "";
      var urgencyEl = form.querySelector('[name="urgency"]');
      var urgency = urgencyEl ? urgencyEl.value : "";
      var extra = [];
      if (zip) extra.push("ZIP: " + zip);
      if (urgency) extra.push("Urgency: " + urgency);
      if (extra.length) details = details ? details + "\n" + extra.join(" · ") : extra.join(" · ");

      if (name.length < 2) return showErr("Please enter your name.");
      if (!/^[+()\-.\s\d]{7,20}$/.test(phone)) return showErr("Please enter a valid phone number so we can reach you.");

      var payload = { name: name, phone: phone, service: service, details: details, page: location.href, ts: new Date().toISOString() };

      // Attempt real POST. On failure we show an honest error — never a fake success.
      fetch(QUOTE_ENDPOINT, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      }).then(function (r) {
        if (!r.ok) throw new Error("endpoint not wired");
        return r.json();
      }).then(function () {
        showOk();
      }).catch(function () {
        // Real mailer failed — never fake a success. Ask the visitor to retry.
        console.warn("[quote-form] mailer failed, payload:", payload);
        showErr("Sorry — we couldn't send your request just now. Please try again in a moment.");
      });

      function showErr(msg) { err.textContent = msg; err.classList.add("show"); }
      function showOk() {
        form.querySelectorAll("input, select, textarea, button").forEach(function (el) { el.disabled = true; });
        ok.classList.add("show");
        ok.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    });
  }

  /* Footer year */
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });
})();
