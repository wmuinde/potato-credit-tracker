// Initialize Feather icons
document.addEventListener("DOMContentLoaded", () => {
  // Declare feather variable
  let feather

  // Initialize Feather icons if available
  if (typeof feather !== "undefined") {
    feather.replace()
  }

  // Set up modals with dynamic content
  const forwardFundsModal = document.getElementById("forwardFundsModal")
  if (forwardFundsModal) {
    forwardFundsModal.addEventListener("show.bs.modal", (event) => {
      const button = event.relatedTarget
      const paymentId = button.getAttribute("data-payment-id")
      const heldAmount = button.getAttribute("data-held-amount")

      const paymentIdInput = forwardFundsModal.querySelector("#payment_id")
      const amountInput = forwardFundsModal.querySelector("#forward_amount")
      const maxAmountSpan = forwardFundsModal.querySelector("#max_amount")

      paymentIdInput.value = paymentId
      amountInput.max = heldAmount
      amountInput.value = heldAmount
      // Use a default currency symbol if CURRENCY is not available
      const currencySymbol = "$"
      maxAmountSpan.textContent = currencySymbol + " " + Number.parseFloat(heldAmount).toFixed(2)
    })
  }

  // Set up alerts to auto-dismiss
  const alerts = document.querySelectorAll(".alert-dismissible")
  alerts.forEach((alert) => {
    setTimeout(() => {
      const closeButton = alert.querySelector(".btn-close")
      if (closeButton) {
        closeButton.click()
      }
    }, 5000)
  })
})
