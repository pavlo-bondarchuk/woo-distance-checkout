// Woo Distance Checkout Frontend JavaScript

(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    initializeFulfillmentMethod();
    initializeStoreSelector();
    updateCheckoutBlockedState();
    updateOutOfZoneNotice();

    // Re-sync UI visibility at load, based on current selection
    var selected = document.querySelector(
      ".wdc-fulfillment-method-input:checked",
    );
    if (selected) {
      setStoreSelectorVisibility(selected.value);
    }

    // Listen for checkout updates and reapply blocked state styling and out-of-zone notice
    if (typeof jQuery !== "undefined") {
      jQuery(document.body).on("updated_checkout", function () {
        updateCheckoutBlockedState();
        updateOutOfZoneNotice();
      });
    }
  });

  function initializeFulfillmentMethod() {
    var inputs = document.querySelectorAll(".wdc-fulfillment-method-input");

    inputs.forEach(function (input) {
      input.addEventListener("change", function () {
        handleFulfillmentMethodChange(this.value);
      });
    });
  }

  function handleFulfillmentMethodChange(method) {
    setStoreSelectorVisibility(method);

    // Clear any WDC notices immediately when switching methods,
    // then request a checkout refresh to re-render the updated state.
    clearNotices();

    var xhr = new XMLHttpRequest();
    var formData = new FormData();

    formData.append("action", "wdc_update_fulfillment");
    formData.append("nonce", wdcCheckout.nonce);
    formData.append("fulfillment_method", method);

    xhr.open("POST", wdcCheckout.ajaxurl, true);

    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.success) {
            // Update blocked state from fresh response before triggering refresh
            if (typeof response.data.isCheckoutBlockedForTax !== "undefined") {
              wdcCheckout.isCheckoutBlockedForTax =
                response.data.isCheckoutBlockedForTax;
            }
            // Update out-of-zone state from fresh response
            if (typeof response.data.outOfZoneState !== "undefined") {
              wdcCheckout.outOfZoneState = response.data.outOfZoneState;
            }
            triggerCheckoutRefresh();
          } else {
            console.error(
              "WDC: Fulfillment update failed - " +
                (response.data && response.data.message
                  ? response.data.message
                  : "unknown"),
            );
          }
        } catch (e) {
          console.error("WDC: Failed to parse AJAX response");
        }
      }
    };

    xhr.onerror = function () {
      console.error("WDC: AJAX request failed");
    };

    xhr.send(formData);
  }

  function clearNotices() {
    var notices = document.getElementById("wdc-notices");
    if (notices) {
      notices.innerHTML = "";
    }
  }

  function triggerCheckoutRefresh() {
    if (typeof jQuery !== "undefined") {
      jQuery(document.body).trigger("update_checkout");
    } else {
      console.warn("WDC: jQuery not available for checkout refresh");
    }
  }

  function initializeStoreSelector() {
    var storeSelect = document.getElementById("wdc-store-selector-input");

    if (!storeSelect) {
      return;
    }

    storeSelect.addEventListener("change", function () {
      handleStoreSelectionChange(this.value);
    });
  }

  function setStoreSelectorVisibility(method) {
    var storeBlock = document.getElementById("wdc-store-selector");

    if (!storeBlock) {
      return;
    }

    if (method === "pickup") {
      storeBlock.classList.add("wdc-store-selector--visible");
      storeBlock.classList.remove("wdc-store-selector--hidden");
    } else {
      storeBlock.classList.add("wdc-store-selector--hidden");
      storeBlock.classList.remove("wdc-store-selector--visible");
    }
  }
  function handleStoreSelectionChange(storeId) {
    var xhr = new XMLHttpRequest();
    var formData = new FormData();

    formData.append("action", "wdc_update_store");
    formData.append("nonce", wdcCheckout.nonce);
    formData.append("store_id", storeId);

    xhr.open("POST", wdcCheckout.ajaxurl, true);

    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.success) {
            // Update blocked state from fresh response before triggering refresh
            if (typeof response.data.isCheckoutBlockedForTax !== "undefined") {
              wdcCheckout.isCheckoutBlockedForTax =
                response.data.isCheckoutBlockedForTax;
            }
            // Update out-of-zone state from fresh response
            if (typeof response.data.outOfZoneState !== "undefined") {
              wdcCheckout.outOfZoneState = response.data.outOfZoneState;
            }
            triggerCheckoutRefresh();
          } else {
            console.error(
              "WDC: Store update failed - " + response.data.message,
            );
          }
        } catch (e) {
          console.error("WDC: Failed to parse store update response");
        }
      }
    };

    xhr.onerror = function () {
      console.error("WDC: Store update AJAX request failed");
    };

    xhr.send(formData);
  }

  /**
   * Update checkout UI based on blocked state
   *
   * If checkout is blocked for tax API failure:
   * - Disable the Place Order button
   * - Show error notice
   * - Hide tax rows
   * - Show placeholder for total
   */
  function updateCheckoutBlockedState() {
    var isBlocked = wdcCheckout && wdcCheckout.isCheckoutBlockedForTax;

    if (!isBlocked) {
      // Clear any blocked state styling
      clearCheckoutBlockedState();
      return;
    }

    // Apply blocked state
    disablePlaceOrderButton();
    showCheckoutBlockedNotice();
    hideTaxRows();
    showTotalPlaceholder();
  }

  /**
   * Clear any checkout blocked state styling
   */
  function clearCheckoutBlockedState() {
    var placeOrderBtn = document.querySelector(
      'button[name="woocommerce_checkout_place_order"]',
    );
    if (placeOrderBtn) {
      placeOrderBtn.disabled = false;
      placeOrderBtn.classList.remove("wdc-blocked");
    }

    var helperText = document.getElementById("wdc-blocked-helper-text");
    if (helperText) {
      helperText.style.display = "none";
    }

    // Re-enable tax rows
    var taxRows = document.querySelectorAll(".wdc-tax-row");
    taxRows.forEach(function (row) {
      row.style.display = "";
      row.classList.remove("wdc-hidden");
    });

    var totalPlaceholder = document.getElementById("wdc-total-placeholder");
    if (totalPlaceholder) {
      totalPlaceholder.style.display = "none";
    }
  }

  /**
   * Disable the Place Order button
   */
  function disablePlaceOrderButton() {
    var placeOrderBtn = document.querySelector(
      'button[name="woocommerce_checkout_place_order"]',
    );
    if (placeOrderBtn) {
      placeOrderBtn.disabled = true;
      placeOrderBtn.classList.add("wdc-blocked");

      var helperText = document.getElementById("wdc-blocked-helper-text");
      if (!helperText) {
        helperText = document.createElement("div");
        helperText.id = "wdc-blocked-helper-text";
        helperText.className = "wdc-blocked-helper-text";
        helperText.innerText = wdcCheckout.notices.blockedHelperText;
        placeOrderBtn.parentNode.insertBefore(
          helperText,
          placeOrderBtn.nextSibling,
        );
      }
      helperText.style.display = "block";
    }
  }

  /**
   * Get the currently selected fulfillment method
   */
  function getCurrentFulfillmentMethod() {
    var selected = document.querySelector(
      ".wdc-fulfillment-method-input:checked",
    );
    return selected ? selected.value : "delivery";
  }

  /**
   * Show the tax API failure notice (context-aware based on fulfillment method)
   */
  function showCheckoutBlockedNotice() {
    var noticesContainer = document.getElementById("wdc-notices");
    if (!noticesContainer) {
      return;
    }

    var outOfZoneNotice = document.getElementById("wdc-out-of-zone-notice");
    if (outOfZoneNotice) {
      outOfZoneNotice.remove();
    }

    var existingNotice = document.getElementById("wdc-blocked-notice");
    if (existingNotice) {
      return;
    }

    var method = getCurrentFulfillmentMethod();
    var message =
      method === "pickup"
        ? wdcCheckout.notices.blockedNoticePickup
        : wdcCheckout.notices.blockedNoticeDelivery;

    var notice = document.createElement("div");
    notice.id = "wdc-blocked-notice";
    notice.className = "wdc-notice wdc-notice-error";
    notice.innerHTML = "<p>" + message + "</p>";

    noticesContainer.appendChild(notice);
  }

  /**
   * Hide the tax rows
   */
  function hideTaxRows() {
    var taxRows = document.querySelectorAll(".wc-cart-totals .cart-tax");
    taxRows.forEach(function (row) {
      row.style.display = "none";
      row.classList.add("wdc-hidden");
    });

    // Also hide WDC tax rows if they exist by different selectors
    var wdcTaxRows = document.querySelectorAll(
      '[data-title="Sales Tax"], [data-title="Shipping Tax"]',
    );
    wdcTaxRows.forEach(function (row) {
      row.style.display = "none";
      row.classList.add("wdc-hidden");
    });
  }

  /**
   * Show placeholder for total
   */
  function showTotalPlaceholder() {
    var totalRow = document.querySelector(".order-total");

    if (!totalRow) {
      return;
    }

    var existingPlaceholder = document.getElementById("wdc-total-placeholder");
    if (existingPlaceholder) {
      existingPlaceholder.style.display = "block";
      return;
    }

    var placeholder = document.createElement("div");
    placeholder.id = "wdc-total-placeholder";
    placeholder.className = "wdc-total-placeholder";
    placeholder.innerHTML =
      "<p>" + wdcCheckout.notices.totalPlaceholder + "</p>";

    totalRow.style.display = "none";

    totalRow.parentNode.insertBefore(placeholder, totalRow.nextSibling);
  }

  /**
   * Update out-of-zone notice based on current state
   *
   * Shows/hides notice when address is outside delivery zone (delivery mode only).
   * Does not show if checkout is blocked (blocked takes precedence).
   * Called on page load and after each checkout update.
   */
  function updateOutOfZoneNotice() {
    var outOfZoneState = wdcCheckout && wdcCheckout.outOfZoneState;
    var isBlocked = wdcCheckout && wdcCheckout.isCheckoutBlockedForTax;

    if (isBlocked) {
      return;
    }

    var existingNotice = document.getElementById("wdc-out-of-zone-notice");

    if (!outOfZoneState || !outOfZoneState.is_out_of_zone) {
      if (existingNotice) {
        existingNotice.remove();
      }
      return;
    }

    if (existingNotice) {
      return;
    }

    var noticesContainer = document.getElementById("wdc-notices");
    if (!noticesContainer) {
      return;
    }

    var notice = document.createElement("div");
    notice.id = "wdc-out-of-zone-notice";
    notice.className = "wdc-notice wdc-notice-error";
    notice.innerHTML = "<p>" + outOfZoneState.message + "</p>";

    noticesContainer.appendChild(notice);
  }
})();
