
/**
 * Main JavaScript file for Potato Credit Tracker
 */

document.addEventListener('DOMContentLoaded', function() {
    // Set active navigation item based on current page
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.main-nav a');
    
    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        if (linkHref === currentPage) {
            link.classList.add('active');
        }
        
        // Add hover animation
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
        });
        
        link.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'translateX(0)';
            }
        });
    });
    
    // Initialize mobile menu toggle if it exists
    const menuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
    
    // Handle alert dismissal with animation
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        // Add a close button
        const closeBtn = document.createElement('span');
        closeBtn.innerHTML = '&times;';
        closeBtn.className = 'alert-close';
        closeBtn.style.float = 'right';
        closeBtn.style.cursor = 'pointer';
        closeBtn.style.fontWeight = 'bold';
        closeBtn.onclick = function() {
            fadeOut(alert);
        };
        alert.prepend(closeBtn);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            if (alert) {
                fadeOut(alert);
            }
        }, 5000);
    });
    
    // Add card hover effects
    const cards = document.querySelectorAll('.card, .stat-card, .vehicle-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });
    
    // Animate stat values on page load
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach(stat => {
        animateCounter(stat);
    });
    
    // Initialize tooltips
    initTooltips();
});

/**
 * Fade out an element
 * @param {HTMLElement} element - The element to fade out
 */
function fadeOut(element) {
    element.style.transition = 'opacity 0.3s ease';
    element.style.opacity = '0';
    setTimeout(() => {
        element.style.display = 'none';
    }, 300);
}

/**
 * Animate counter for statistics
 * @param {HTMLElement} element - The element containing the statistic
 */
function animateCounter(element) {
    const value = element.textContent;
    const numericValue = parseFloat(value.replace(/[^0-9.-]+/g, ""));
    
    if (!isNaN(numericValue) && numericValue > 0) {
        element.textContent = "0";
        let current = 0;
        const increment = numericValue / 20;
        const timer = setInterval(() => {
            current += increment;
            if (current >= numericValue) {
                element.textContent = value; // Reset to original formatted value
                clearInterval(timer);
            } else {
                // Handle currency formatting
                if (value.includes('₦')) {
                    element.textContent = '₦' + Math.round(current).toLocaleString();
                } else {
                    element.textContent = Math.round(current).toString();
                }
            }
        }, 50);
    }
}

/**
 * Initialize tooltips for elements with data-tooltip attribute
 */
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.style.position = 'relative';
        
        element.addEventListener('mouseenter', function() {
            const tooltipText = this.getAttribute('data-tooltip');
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = tooltipText;
            tooltip.style.position = 'absolute';
            tooltip.style.bottom = '100%';
            tooltip.style.left = '50%';
            tooltip.style.transform = 'translateX(-50%)';
            tooltip.style.backgroundColor = '#8B4513';
            tooltip.style.color = 'white';
            tooltip.style.padding = '5px 10px';
            tooltip.style.borderRadius = '4px';
            tooltip.style.fontSize = '12px';
            tooltip.style.zIndex = '1000';
            tooltip.style.opacity = '0';
            tooltip.style.transition = 'opacity 0.3s';
            
            this.appendChild(tooltip);
            
            setTimeout(() => {
                tooltip.style.opacity = '1';
            }, 10);
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.tooltip');
            if (tooltip) {
                tooltip.style.opacity = '0';
                setTimeout(() => {
                    tooltip.remove();
                }, 300);
            }
        });
    });
}

/**
 * Format a number as currency
 * @param {number} amount - The amount to format
 * @returns {string} - Formatted currency string
 */
function formatCurrency(amount) {
    return parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Confirm action before proceeding
 * @param {string} message - Confirmation message
 * @returns {boolean} - Whether the action was confirmed
 */
function confirmAction(message) {
    return confirm(message || 'Are you sure you want to proceed?');
}
