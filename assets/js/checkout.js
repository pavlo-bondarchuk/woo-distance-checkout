// Woo Distance Checkout Frontend JavaScript

(function () {
  "use strict";

  function isDebugModeEnabled() {
    return (
      typeof wdcCheckout !== "undefined" &&
      wdcCheckout &&
      wdcCheckout.debugMode === "yes"
    );
  }

  function debugLog(message, payload) {
    if (!isDebugModeEnabled()) {
      return;
    }

    if (typeof payload === "undefined") {
      console.debug("WDC Debug:", message);
      return;
    }

    console.debug("WDC Debug:", message, payload);
  }

  document.addEventListener("DOMContentLoaded", function () {
    initializeFulfillmentMethod();
    initializeStoreSelector();
    initializeAddressAutocomplete();
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

            if (typeof response.data.pickupStoreAddress !== "undefined") {
              wdcCheckout.pickupStoreAddress = response.data.pickupStoreAddress;
            }

            if (method === "pickup") {
              applyPickupStoreAddressToBilling();
              debugLog("checkout refresh triggered after pickup autofill");
              triggerCheckoutRefresh();
              return;
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
    debugLog("checkout refresh trigger requested", {
      hasJQuery: typeof jQuery !== "undefined",
    });

    if (typeof jQuery !== "undefined") {
      jQuery(document.body).trigger("update_checkout");
      debugLog("checkout refresh triggered", {
        method: "jQuery update_checkout",
      });
    } else {
      console.warn("WDC: jQuery not available for checkout refresh");
      debugLog("checkout refresh NOT triggered", {
        reason: "jQuery not available",
      });
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

  function parsePickupStoreAddress(address) {
    var parsed = {
      street: "",
      city: "",
      state: "",
      postcode: "",
      country: "US",
    };

    if (!address) {
      return parsed;
    }

    var parts = address
      .split(",")
      .map(function (part) {
        return part.trim();
      })
      .filter(function (part) {
        return !!part;
      });

    if (!parts.length) {
      return parsed;
    }

    parsed.street = parts[0] || "";
    parsed.city = parts.length > 1 ? parts[1] : "";

    var stateZipPart = parts.length > 2 ? parts[2] : "";
    var stateZipMatch = stateZipPart.match(
      /^([A-Za-z]{2})\s+(\d{5}(?:-\d{4})?)$/,
    );
    if (stateZipMatch) {
      parsed.state = stateZipMatch[1].toUpperCase();
      parsed.postcode = stateZipMatch[2];
    }

    if (parts.length > 3) {
      parsed.country = normalizeCountryCode(parts[3]);
    }

    return parsed;
  }

  function normalizeCountryCode(countryPart) {
    if (!countryPart) {
      return "US";
    }

    var upper = countryPart.toUpperCase();
    if (
      upper === "USA" ||
      upper === "UNITED STATES" ||
      upper === "UNITED STATES OF AMERICA"
    ) {
      return "US";
    }

    return upper.length === 2 ? upper : "US";
  }

  function applyPickupStoreAddressToBilling() {
    var address =
      wdcCheckout && wdcCheckout.pickupStoreAddress
        ? wdcCheckout.pickupStoreAddress
        : "";

    debugLog("pickup autofill started", {
      storeAddress: address,
    });

    var parsed = parsePickupStoreAddress(address);
    debugLog("parsed pickup store address", parsed);

    setCheckoutFieldValue("billing_address_1", parsed.street);
    setCheckoutFieldValue("billing_city", parsed.city);
    setCheckoutFieldValue("billing_state", parsed.state);
    setCheckoutFieldValue("billing_postcode", parsed.postcode);
    setCheckoutFieldValue("billing_country", parsed.country);

    debugLog("pickup billing fields applied", {
      billing_address_1: getFieldValue("billing_address_1"),
      billing_city: getFieldValue("billing_city"),
      billing_state: getFieldValue("billing_state"),
      billing_postcode: getFieldValue("billing_postcode"),
      billing_country: getFieldValue("billing_country"),
    });
  }

  function initializeAddressAutocomplete() {
    debugLog("autocomplete initialization started", {
      hasCheckoutConfig: !!wdcCheckout,
      hasApiKey: !!(wdcCheckout && wdcCheckout.googleMapsApiKey),
      hasGoogle: typeof google !== "undefined",
      hasMaps: typeof google !== "undefined" && !!google.maps,
      hasPlaces:
        typeof google !== "undefined" && !!google.maps && !!google.maps.places,
    });

    if (
      !wdcCheckout ||
      !wdcCheckout.googleMapsApiKey ||
      typeof google === "undefined" ||
      !google.maps ||
      !google.maps.places
    ) {
      debugLog("autocomplete initialization skipped", {
        reason: "missing config or google maps places",
      });
      return;
    }

    var addressInput = document.getElementById("billing_address_1");
    debugLog("selector check", {
      selector: "#billing_address_1",
      exists: !!addressInput,
      value: addressInput ? addressInput.value : null,
    });

    if (!addressInput) {
      return;
    }

    addressInput.addEventListener("keydown", function (event) {
      if (event.key !== "Enter") {
        return;
      }

      var pacItemSelected = !!document.querySelector(".pac-item-selected");
      var pacContainerVisible = !!document.querySelector(
        ".pac-container:not([style*='display: none']) .pac-item",
      );

      debugLog("Enter key intercepted in billing_address_1", {
        suggestionSelectionDetected: pacItemSelected,
        suggestionListVisible: pacContainerVisible,
      });

      if (pacContainerVisible) {
        event.preventDefault();
        debugLog("suggestion selection detected / not detected", {
          detected: pacItemSelected,
          action: "prevented native enter submit while suggestions visible",
        });
      } else {
        debugLog("suggestion selection detected / not detected", {
          detected: false,
          action: "no forced checkout refresh on plain enter",
        });
      }
    });

    var autocomplete = new google.maps.places.Autocomplete(addressInput, {
      fields: ["address_components"],
      types: ["address"],
    });

    debugLog("autocomplete initialized successfully", {
      fieldId: "billing_address_1",
      fields: ["address_components"],
      types: ["address"],
    });

    autocomplete.addListener("place_changed", function () {
      debugLog("place_changed fired");

      var place = autocomplete.getPlace();
      debugLog("raw place summary", {
        hasPlace: !!place,
        placeId: place && place.place_id ? place.place_id : null,
        name: place && place.name ? place.name : null,
        formattedAddress:
          place && place.formatted_address ? place.formatted_address : null,
        hasAddressComponents: !!(place && place.address_components),
      });

      if (!place || !place.address_components) {
        debugLog("place_changed aborted", {
          reason: "missing place or address_components",
        });
        return;
      }

      debugLog("raw address_components", place.address_components);

      var parsedAddress = parseGoogleAddressComponents(
        place.address_components,
      );
      var normalizedPayload = buildNormalizedAddressPayload(parsedAddress);

      debugLog("parsed mapped values", {
        street: normalizedPayload.street,
        city: normalizedPayload.city,
        state: normalizedPayload.state,
        zip: normalizedPayload.postcode,
        country: normalizedPayload.country,
      });

      debugLog("parsed payload before apply", normalizedPayload);
      applyParsedAddressToCheckoutFields(normalizedPayload);

      debugLog("final DOM snapshot after autofill", {
        billing_address_1: getFieldValue("billing_address_1"),
        billing_city: getFieldValue("billing_city"),
        billing_state: getFieldValue("billing_state"),
        billing_postcode: getFieldValue("billing_postcode"),
        billing_country: getFieldValue("billing_country"),
      });

      if (!isAutofillPayloadComplete()) {
        debugLog("skipped checkout refresh because payload incomplete", {
          billing_city: getFieldValue("billing_city"),
          billing_state: getFieldValue("billing_state"),
          billing_postcode: getFieldValue("billing_postcode"),
        });
        return;
      }

      debugLog("checkout refresh fired after complete autofill");
      triggerCheckoutRefresh();
    });
  }

  function parseGoogleAddressComponents(components) {
    var parsed = {
      streetNumber: "",
      route: "",
      city: "",
      state: "",
      postcode: "",
      country: "",
    };

    components.forEach(function (component) {
      var types = component.types || [];

      if (types.indexOf("street_number") !== -1) {
        parsed.streetNumber = component.long_name || "";
      }
      if (types.indexOf("route") !== -1) {
        parsed.route = component.long_name || "";
      }
      if (types.indexOf("locality") !== -1) {
        parsed.city = component.long_name || "";
      }
      if (types.indexOf("administrative_area_level_1") !== -1) {
        parsed.state = component.short_name || "";
      }
      if (types.indexOf("postal_code") !== -1) {
        parsed.postcode = component.long_name || "";
      }
      if (types.indexOf("country") !== -1) {
        parsed.country = component.short_name || "";
      }
    });

    return parsed;
  }

  function buildNormalizedAddressPayload(parsed) {
    return {
      street: [parsed.streetNumber, parsed.route]
        .filter(function (part) {
          return !!part;
        })
        .join(" "),
      city: parsed.city || "",
      state: parsed.state || "",
      postcode: parsed.postcode || "",
      country: parsed.country || "",
    };
  }

  function applyParsedAddressToCheckoutFields(payload) {
    debugLog("atomic apply start", payload);

    clearCheckoutFieldValue("billing_city", "atomic replacement");
    clearCheckoutFieldValue("billing_state", "atomic replacement");
    clearCheckoutFieldValue("billing_postcode", "atomic replacement");
    clearCheckoutFieldValue("billing_country", "atomic replacement");

    setCheckoutFieldValue("billing_address_1", payload.street);
    setCheckoutFieldValue("billing_city", payload.city);
    setCheckoutFieldValue("billing_state", payload.state);
    setCheckoutFieldValue("billing_postcode", payload.postcode);
    setCheckoutFieldValue("billing_country", payload.country);

    debugLog("atomic apply complete", {
      billing_address_1: getFieldValue("billing_address_1"),
      billing_city: getFieldValue("billing_city"),
      billing_state: getFieldValue("billing_state"),
      billing_postcode: getFieldValue("billing_postcode"),
      billing_country: getFieldValue("billing_country"),
    });

    debugLog("final normalized address payload", payload);
  }

  function clearCheckoutFieldValue(fieldId, reason) {
    var field = document.getElementById(fieldId);
    if (!field) {
      return;
    }

    if (!field.value) {
      return;
    }

    debugLog("clearing/replacing old field values", {
      fieldId: fieldId,
      oldValue: field.value,
      reason: reason,
    });

    field.value = "";

    if (typeof jQuery !== "undefined") {
      jQuery(field).trigger("change");
      jQuery(field).trigger("input");
    } else {
      field.dispatchEvent(new Event("change", { bubbles: true }));
      field.dispatchEvent(new Event("input", { bubbles: true }));
    }
  }

  function isAutofillPayloadComplete() {
    return !!(
      getFieldValue("billing_city") &&
      getFieldValue("billing_postcode") &&
      getFieldValue("billing_state")
    );
  }

  function getFieldValue(fieldId) {
    var field = document.getElementById(fieldId);
    return field ? field.value : null;
  }

  function getSelectOptionDetails(field, desiredValue) {
    var options = field && field.options ? field.options : [];
    var desiredExists = false;

    for (var i = 0; i < options.length; i += 1) {
      if (options[i].value === desiredValue) {
        desiredExists = true;
        break;
      }
    }

    return {
      optionCount: options.length,
      desiredExists: desiredExists,
    };
  }

  function setCheckoutFieldValue(fieldId, value) {
    debugLog("field set requested", {
      fieldId: fieldId,
      desiredValue: value,
      exists: !!document.getElementById(fieldId),
    });

    if (!value) {
      debugLog("field set skipped", {
        fieldId: fieldId,
        reason: "empty mapped value",
      });
      return;
    }

    var field = document.getElementById(fieldId);
    if (!field) {
      debugLog("field set skipped", {
        fieldId: fieldId,
        reason: "field not found",
      });
      return;
    }

    debugLog("field before set", {
      fieldId: fieldId,
      tagName: field.tagName,
      type: field.type,
      beforeValue: field.value,
    });

    if (
      (fieldId === "billing_state" || fieldId === "billing_country") &&
      field.tagName === "SELECT"
    ) {
      var beforeSelectDetails = getSelectOptionDetails(field, value);
      debugLog("select options before set", {
        fieldId: fieldId,
        optionCount: beforeSelectDetails.optionCount,
        desiredValueExists: beforeSelectDetails.desiredExists,
      });
    }

    field.value = value;

    if (typeof jQuery !== "undefined") {
      jQuery(field).trigger("change");
      jQuery(field).trigger("input");
      debugLog("field events triggered", {
        fieldId: fieldId,
        usedJQuery: true,
        triggeredEvents: ["change", "input"],
      });
    } else {
      field.dispatchEvent(new Event("change", { bubbles: true }));
      field.dispatchEvent(new Event("input", { bubbles: true }));
      debugLog("field events triggered", {
        fieldId: fieldId,
        usedJQuery: false,
        triggeredEvents: ["change", "input"],
      });
    }

    if (
      (fieldId === "billing_state" || fieldId === "billing_country") &&
      field.tagName === "SELECT"
    ) {
      var afterSelectDetails = getSelectOptionDetails(field, value);
      debugLog("select status after set", {
        fieldId: fieldId,
        optionCount: afterSelectDetails.optionCount,
        desiredValueExists: afterSelectDetails.desiredExists,
        selectedValueAfterSet: field.value,
      });
    }

    debugLog("field after set", {
      fieldId: fieldId,
      afterValue: field.value,
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

            if (typeof response.data.pickupStoreAddress !== "undefined") {
              wdcCheckout.pickupStoreAddress = response.data.pickupStoreAddress;
            }

            applyPickupStoreAddressToBilling();
            debugLog("checkout refresh triggered after pickup autofill");
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

    var matchingNotice = Array.prototype.some.call(
      noticesContainer.querySelectorAll(".wdc-notice"),
      function (noticeItem) {
        return noticeItem.textContent.trim() === outOfZoneState.message;
      },
    );

    if (matchingNotice) {
      return;
    }

    var notice = document.createElement("div");
    notice.id = "wdc-out-of-zone-notice";
    notice.className = "wdc-notice wdc-notice-error";
    notice.innerHTML = "<p>" + outOfZoneState.message + "</p>";

    noticesContainer.appendChild(notice);
  }
})();
