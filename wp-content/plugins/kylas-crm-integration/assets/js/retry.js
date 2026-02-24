(() => {
  const cfg = window.KylasCrmRetry || {};
  const ajaxUrl = cfg.ajaxUrl;
  const action = cfg.action || "kylas_crm_retry_lead";

  if (!ajaxUrl) return;

  const customRetryBtn = document.getElementById("kf-retry");
  const customStatus = document.getElementById("kf-retry-status");

  function getRetryElements(form) {
    if (customRetryBtn && customStatus) {
      return { retryBtn: customRetryBtn, status: customStatus };
    }

    const retryBtn = form.querySelector(".kylas-crm-retry");
    const status = form.querySelector(".kylas-crm-retry-status");
    return { retryBtn, status };
  }

  function ensureRetryUI(form) {
    if (!form || form.dataset.kylasRetryUi === "1") return;
    form.dataset.kylasRetryUi = "1";

    let { retryBtn, status } = getRetryElements(form);

    if (!retryBtn || !status) {
      const submit = form.querySelector(".wpcf7-submit");
      if (!submit) return;

      retryBtn = document.createElement("button");
      retryBtn.type = "button";
      retryBtn.className = "kylas-crm-retry";
      retryBtn.textContent = "Retry";

      status = document.createElement("div");
      status.className = "kylas-crm-retry-status";

      submit.insertAdjacentElement("afterend", retryBtn);
      retryBtn.insertAdjacentElement("afterend", status);
    }

    if (retryBtn.dataset.kylasRetryBound === "1") return;

    retryBtn.disabled = true;
    retryBtn.title = "Retry is available only if Kylas API fails after submission.";
    retryBtn.dataset.kylasRetryBound = "1";

    retryBtn.addEventListener("click", async () => {
      const leadId = retryBtn.dataset.leadId;
      const nonce = retryBtn.dataset.nonce;

      if (!leadId || !nonce) {
        status.textContent = "Retry data missing. Please refresh the page and try again.";
        return;
      }

      retryBtn.disabled = true;
      status.textContent = "Retrying…";

      try {
        const fd = new FormData();
        fd.append("action", action);
        fd.append("lead_id", leadId);
        fd.append("nonce", nonce);

        const res = await fetch(ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          body: fd,
        });

        const data = await res.json().catch(() => null);

        if (!res.ok || !data) {
          throw new Error("Bad response");
        }

        if (data.success) {
          const payload = data.data || {};
          if (payload.status === "success") {
            status.textContent = "Submitted successfully.";

            // Also clear any previous 'API failed' message near the main button.
            const resBox = document.getElementById("kf-response");
            if (resBox) {
              resBox.innerHTML = '<span style="color:#4ade80;">Your details are now fully synced with our CRM.</span>';
            }

            retryBtn.dataset.leadId = "";
            retryBtn.dataset.nonce = "";
            retryBtn.disabled = true;
          } else {
            status.textContent = payload.message || "Still failing. Please try again.";
            retryBtn.disabled = false;
            if (payload.retry_nonce) retryBtn.dataset.nonce = payload.retry_nonce;
          }
        } else {
          const payload = data.data || {};
          status.textContent = payload.message || "Retry failed. Please try again.";
          retryBtn.disabled = false;
          if (payload.retry_nonce) retryBtn.dataset.nonce = payload.retry_nonce;
        }
      } catch (e) {
        status.textContent = "Retry failed. Please try again.";
        retryBtn.disabled = false;
      }
    });
  }

  function getFormFromEvent(e) {
    const target = e && e.target;
    if (target && target.matches && target.matches("form.wpcf7-form")) return target;
    if (target && target.closest) return target.closest("form.wpcf7-form");
    return null;
  }

  function applyApiResponseToUI(form, apiResponse) {
    ensureRetryUI(form);

    const { retryBtn, status } = getRetryElements(form);
    if (!retryBtn || !status) return;

    const kylas = apiResponse && apiResponse.kylas;

    if (kylas && kylas.retry_available && kylas.lead_id && kylas.retry_nonce) {
      retryBtn.dataset.leadId = String(kylas.lead_id);
      retryBtn.dataset.nonce = String(kylas.retry_nonce);
      retryBtn.disabled = false;
      status.textContent = kylas.message || "Kylas API failed. You can retry.";
    } else {
      retryBtn.dataset.leadId = "";
      retryBtn.dataset.nonce = "";
      retryBtn.disabled = true;
      status.textContent = "";
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("form.wpcf7-form").forEach(ensureRetryUI);
  });

  // CF7 event includes API response in e.detail.apiResponse
  document.addEventListener("wpcf7submit", (e) => {
    const form = getFormFromEvent(e);
    if (!form) return;
    const apiResponse = e && e.detail && e.detail.apiResponse;
    applyApiResponseToUI(form, apiResponse);
  });

  document.addEventListener("wpcf7invalid", (e) => {
    const form = getFormFromEvent(e);
    if (!form) return;
    ensureRetryUI(form);

    const status = form.querySelector(".kylas-crm-retry-status");
    if (!status) return;

    const apiResponse = e && e.detail && e.detail.apiResponse;
    const invalidFields = (apiResponse && apiResponse.invalid_fields) || [];
    const fieldNames = Array.isArray(invalidFields)
      ? invalidFields.map((f) => f && f.field).filter(Boolean)
      : [];

    if (fieldNames.length) {
      status.textContent = `Validation failed. Check: ${fieldNames.join(", ")}.`;
    } else {
      status.textContent = "Validation failed. Please check the highlighted fields.";
    }
  });
})();

