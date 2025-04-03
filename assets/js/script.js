
/**
 * Main JavaScript file for Potato Credit Tracker
 */

document.addEventListener('DOMContentLoaded', function() {
    // Set active navigation item based on current page
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.main-nav a');
    
    // Enhanced navigation animations
    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        if (linkHref === currentPage) {
            link.classList.add('active');
        }
        
        // Add hover animation with pulse effect
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(8px)';
            this.style.transition = 'all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            this.style.color = '#5D4037';
            
            // Add pulse glow effect
            const glowEffect = document.createElement('span');
            glowEffect.className = 'nav-glow';
            glowEffect.style.position = 'absolute';
            glowEffect.style.left = '-10px';
            glowEffect.style.borderRadius = '50%';
            glowEffect.style.width = '5px';
            glowEffect.style.height = '5px';
            glowEffect.style.background = '#8B4513';
            glowEffect.style.boxShadow = '0 0 8px 3px rgba(139, 69, 19, 0.4)';
            glowEffect.style.opacity = '0.8';
            glowEffect.style.transition = 'all 0.3s ease';
            
            // Only append if not already there
            if (!this.querySelector('.nav-glow')) {
                this.style.position = 'relative';
                this.appendChild(glowEffect);
            }
        });
        
        link.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'translateX(0)';
            }
            this.style.color = '';
            
            // Remove glow effect
            const glow = this.querySelector('.nav-glow');
            if (glow) {
                glow.style.opacity = '0';
                setTimeout(() => glow.remove(), 300);
            }
        });
    });
    
    // Initialize mobile menu toggle with animation
    const menuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            
            // Add animation to the toggle button
            this.classList.toggle('active');
            if (this.classList.contains('active')) {
                this.style.transform = 'rotate(90deg)';
            } else {
                this.style.transform = 'rotate(0deg)';
            }
        });
    }
    
    // Handle alert dismissal with enhanced animation
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        // Add a close button with animation
        const closeBtn = document.createElement('span');
        closeBtn.innerHTML = '&times;';
        closeBtn.className = 'alert-close';
        closeBtn.style.float = 'right';
        closeBtn.style.cursor = 'pointer';
        closeBtn.style.fontWeight = 'bold';
        closeBtn.style.transition = 'all 0.3s ease';
        
        closeBtn.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.2) rotate(90deg)';
            this.style.opacity = '0.8';
        });
        
        closeBtn.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1) rotate(0deg)';
            this.style.opacity = '1';
        });
        
        closeBtn.onclick = function() {
            fadeOut(alert);
        };
        
        alert.prepend(closeBtn);
        
        // Slide in animation on load
        alert.style.animation = 'slideInRight 0.5s forwards';
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            if (alert) {
                fadeOut(alert);
            }
        }, 5000);
    });
    
    // Add enhanced card hover effects with 3D perspective
    const cards = document.querySelectorAll('.card, .stat-card, .vehicle-card');
    cards.forEach(card => {
        // Add tilt effect on mouse move
        card.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left; 
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const angleX = (y - centerY) / 20;
            const angleY = (centerX - x) / 20;
            
            this.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg) translateY(-5px)`;
            this.style.boxShadow = '0 15px 25px -10px rgba(139, 69, 19, 0.3)';
            this.style.transition = 'transform 0.1s ease, box-shadow 0.3s ease';
            
            // Add gradient highlight
            this.style.background = `
                linear-gradient(
                    135deg, 
                    rgba(255, 255, 255, 0.1) 0%, 
                    rgba(255, 255, 255, 0) 50%, 
                    rgba(0, 0, 0, 0.05) 100%
                ),
                ${this.dataset.originalBg || '#fff'}
            `;
            
            // Store original background if not stored yet
            if (!this.dataset.originalBg) {
                this.dataset.originalBg = getComputedStyle(this).backgroundColor;
            }
            
            // Add subtle scaling to child elements for enhanced depth
            const cardElements = this.querySelectorAll('h3, h4, p, .stat-value, .progress-bar');
            cardElements.forEach(el => {
                el.style.transform = 'translateZ(10px)';
                el.style.transition = 'transform 0.3s ease';
            });
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) rotateX(0) rotateY(0)';
            this.style.boxShadow = '';
            this.style.background = this.dataset.originalBg || '';
            this.style.transition = 'all 0.5s ease';
            
            // Reset child elements
            const cardElements = this.querySelectorAll('h3, h4, p, .stat-value, .progress-bar');
            cardElements.forEach(el => {
                el.style.transform = 'translateZ(0)';
            });
        });
        
        // Add click animation
        card.addEventListener('mousedown', function() {
            this.style.transform = 'translateY(0) scale(0.98)';
            this.style.boxShadow = '0 5px 10px -5px rgba(139, 69, 19, 0.3)';
        });
        
        card.addEventListener('mouseup', function() {
            this.style.transform = 'translateY(-5px) scale(1)';
            this.style.boxShadow = '0 15px 25px -10px rgba(139, 69, 19, 0.3)';
        });
    });
    
    // Animate stat values on page load with enhanced counting
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach(stat => {
        animateCounter(stat);
    });
    
    // Add reveal animations for sections
    const sections = document.querySelectorAll('section, .section, .data-container');
    sections.forEach((section, index) => {
        // Set initial state
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        
        // Stagger the reveal animations
        setTimeout(() => {
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, 100 * index);
    });
    
    // Enhance form inputs with focus effects
    const formInputs = document.querySelectorAll('input, textarea, select');
    formInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.style.boxShadow = '0 0 0 2px rgba(139, 69, 19, 0.2)';
            this.style.transition = 'all 0.3s ease';
            
            // Add subtle scale to the label if it exists
            const label = this.previousElementSibling;
            if (label && label.tagName === 'LABEL') {
                label.style.color = '#8B4513';
                label.style.transform = 'translateY(-2px) scale(0.95)';
                label.style.transformOrigin = 'left';
                label.style.transition = 'all 0.3s ease';
            }
        });
        
        input.addEventListener('blur', function() {
            this.style.boxShadow = '';
            
            // Reset label
            const label = this.previousElementSibling;
            if (label && label.tagName === 'LABEL') {
                label.style.color = '';
                label.style.transform = '';
            }
        });
    });
    
    // Enhance buttons with hover and click effects
    const buttons = document.querySelectorAll('button, .btn, [type="submit"]');
    buttons.forEach(button => {
        if (!button.classList.contains('alert-close')) {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 8px rgba(139, 69, 19, 0.2)';
                this.style.transition = 'all 0.3s ease';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = '';
                this.style.boxShadow = '';
            });
            
            button.addEventListener('mousedown', function() {
                this.style.transform = 'translateY(1px)';
                this.style.boxShadow = '0 2px 4px rgba(139, 69, 19, 0.1)';
            });
            
            button.addEventListener('mouseup', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 8px rgba(139, 69, 19, 0.2)';
            });
        }
    });
    
    // Enhance progress bars with animation
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const value = bar.getAttribute('data-value') || bar.style.width;
        if (value) {
            // Reset width to 0 for animation
            bar.style.width = '0%';
            bar.style.transition = 'width 1.5s cubic-bezier(0.1, 0.5, 0.1, 1)';
            
            // Animate to the target width
            setTimeout(() => {
                bar.style.width = value;
            }, 300);
        }
    });
    
    // Initialize enhanced tooltips
    initTooltips();
    
    // Initialize table row hover effects
    initTableEffects();
});

/**
 * Fade out an element with enhanced animation
 * @param {HTMLElement} element - The element to fade out
 */
function fadeOut(element) {
    element.style.transition = 'all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1)';
    element.style.opacity = '0';
    element.style.transform = 'translateX(20px)';
    setTimeout(() => {
        element.style.height = element.offsetHeight + 'px';
        setTimeout(() => {
            element.style.height = '0';
            element.style.margin = '0';
            element.style.padding = '0';
            setTimeout(() => {
                element.style.display = 'none';
            }, 300);
        }, 50);
    }, 300);
}

/**
 * Animate counter for statistics with easing
 * @param {HTMLElement} element - The element containing the statistic
 */
function animateCounter(element) {
    const value = element.textContent;
    const numericValue = parseFloat(value.replace(/[^0-9.-]+/g, ""));
    
    if (!isNaN(numericValue) && numericValue > 0) {
        element.textContent = "0";
        let current = 0;
        const duration = 1500; // ms
        const framesPerSecond = 60;
        const totalFrames = duration / 1000 * framesPerSecond;
        let frame = 0;
        
        const timer = setInterval(() => {
            frame++;
            // Use easeOutExpo for smooth counting
            const progress = frame / totalFrames;
            const easedProgress = progress === 1 
                ? 1 
                : 1 - Math.pow(2, -10 * progress);
            
            current = easedProgress * numericValue;
            
            if (frame >= totalFrames) {
                element.textContent = value; // Reset to original formatted value
                clearInterval(timer);
                
                // Add a small bounce effect at the end
                element.style.transform = 'scale(1.1)';
                element.style.color = '#8B4513';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                    element.style.transition = 'transform 0.3s ease, color 0.3s ease';
                }, 100);
            } else {
                // Handle currency formatting
                if (value.includes('₦')) {
                    element.textContent = '₦' + Math.round(current).toLocaleString();
                } else {
                    element.textContent = Math.round(current).toString();
                }
            }
        }, 1000 / framesPerSecond);
    }
}

/**
 * Initialize tooltips for elements with data-tooltip attribute
 * Enhanced version with animations
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
            tooltip.style.transform = 'translateX(-50%) translateY(10px)';
            tooltip.style.backgroundColor = 'rgba(139, 69, 19, 0.9)';
            tooltip.style.color = 'white';
            tooltip.style.padding = '6px 12px';
            tooltip.style.borderRadius = '6px';
            tooltip.style.fontSize = '12px';
            tooltip.style.fontWeight = '500';
            tooltip.style.zIndex = '1000';
            tooltip.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.2)';
            tooltip.style.opacity = '0';
            tooltip.style.transition = 'all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            tooltip.style.pointerEvents = 'none';
            tooltip.style.whiteSpace = 'nowrap';
            
            // Add arrow
            const arrow = document.createElement('div');
            arrow.style.position = 'absolute';
            arrow.style.width = '0';
            arrow.style.height = '0';
            arrow.style.borderLeft = '6px solid transparent';
            arrow.style.borderRight = '6px solid transparent';
            arrow.style.borderTop = '6px solid rgba(139, 69, 19, 0.9)';
            arrow.style.bottom = '-6px';
            arrow.style.left = '50%';
            arrow.style.transform = 'translateX(-50%)';
            tooltip.appendChild(arrow);
            
            this.appendChild(tooltip);
            
            // Animate tooltip
            setTimeout(() => {
                tooltip.style.opacity = '1';
                tooltip.style.transform = 'translateX(-50%) translateY(0)';
            }, 10);
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.tooltip');
            if (tooltip) {
                tooltip.style.opacity = '0';
                tooltip.style.transform = 'translateX(-50%) translateY(10px)';
                setTimeout(() => {
                    tooltip.remove();
                }, 300);
            }
        });
    });
}

/**
 * Initialize table row hover effects
 */
function initTableEffects() {
    const tableRows = document.querySelectorAll('tbody tr');
    
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(139, 69, 19, 0.05)';
            this.style.transform = 'translateX(5px)';
            this.style.transition = 'all 0.3s ease';
            
            // Highlight cells
            const cells = this.querySelectorAll('td');
            cells.forEach(cell => {
                cell.style.transition = 'all 0.3s ease';
                cell.style.color = '#5D4037';
            });
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
            this.style.transform = '';
            
            // Reset cells
            const cells = this.querySelectorAll('td');
            cells.forEach(cell => {
                cell.style.color = '';
            });
        });
        
        // Click effect
        row.addEventListener('mousedown', function() {
            this.style.transform = 'translateX(2px)';
            this.style.backgroundColor = 'rgba(139, 69, 19, 0.1)';
        });
        
        row.addEventListener('mouseup', function() {
            this.style.transform = 'translateX(5px)';
            this.style.backgroundColor = 'rgba(139, 69, 19, 0.05)';
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
 * Confirm action before proceeding with enhanced modal
 * @param {string} message - Confirmation message
 * @returns {boolean} - Whether the action was confirmed
 */
function confirmAction(message) {
    message = message || 'Are you sure you want to proceed?';
    
    // Check if we should use a custom modal
    if (document.querySelector('body')) {
        // Create modal overlay
        const overlay = document.createElement('div');
        overlay.className = 'confirm-overlay';
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.right = '0';
        overlay.style.bottom = '0';
        overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.zIndex = '9999';
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.3s ease';
        
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'confirm-modal';
        modal.style.backgroundColor = 'white';
        modal.style.padding = '20px';
        modal.style.borderRadius = '8px';
        modal.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.2)';
        modal.style.width = '90%';
        modal.style.maxWidth = '400px';
        modal.style.transform = 'translateY(20px)';
        modal.style.transition = 'transform 0.3s ease';
        
        // Add message
        const messageEl = document.createElement('p');
        messageEl.textContent = message;
        messageEl.style.margin = '0 0 20px 0';
        messageEl.style.fontSize = '16px';
        messageEl.style.color = '#5D4037';
        modal.appendChild(messageEl);
        
        // Add buttons container
        const buttonsContainer = document.createElement('div');
        buttonsContainer.style.display = 'flex';
        buttonsContainer.style.justifyContent = 'flex-end';
        buttonsContainer.style.gap = '10px';
        
        // Add cancel button
        const cancelButton = document.createElement('button');
        cancelButton.textContent = 'Cancel';
        cancelButton.style.padding = '8px 16px';
        cancelButton.style.border = 'none';
        cancelButton.style.borderRadius = '4px';
        cancelButton.style.backgroundColor = '#f1f1f1';
        cancelButton.style.color = '#333';
        cancelButton.style.cursor = 'pointer';
        cancelButton.style.transition = 'all 0.3s ease';
        
        // Add confirm button
        const confirmButton = document.createElement('button');
        confirmButton.textContent = 'Confirm';
        confirmButton.style.padding = '8px 16px';
        confirmButton.style.border = 'none';
        confirmButton.style.borderRadius = '4px';
        confirmButton.style.backgroundColor = '#8B4513';
        confirmButton.style.color = 'white';
        confirmButton.style.cursor = 'pointer';
        confirmButton.style.transition = 'all 0.3s ease';
        
        // Add hover effects
        cancelButton.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#e5e5e5';
        });
        
        cancelButton.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '#f1f1f1';
        });
        
        confirmButton.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#a5631e';
        });
        
        confirmButton.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '#8B4513';
        });
        
        buttonsContainer.appendChild(cancelButton);
        buttonsContainer.appendChild(confirmButton);
        modal.appendChild(buttonsContainer);
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        // Show modal with animation
        setTimeout(() => {
            overlay.style.opacity = '1';
            modal.style.transform = 'translateY(0)';
        }, 10);
        
        // Return promise for async confirmation
        return new Promise(resolve => {
            cancelButton.addEventListener('click', function() {
                closeModal(false);
            });
            
            confirmButton.addEventListener('click', function() {
                closeModal(true);
            });
            
            // Handle backdrop click
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeModal(false);
                }
            });
            
            // Close modal function
            function closeModal(result) {
                overlay.style.opacity = '0';
                modal.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    document.body.removeChild(overlay);
                    resolve(result);
                }, 300);
            }
        });
    } else {
        // Fallback to regular confirm
        return confirm(message);
    }
}

// Add keyframe animations to the document
function addAnimationStyles() {
    if (!document.getElementById('animation-styles')) {
        const styleEl = document.createElement('style');
        styleEl.id = 'animation-styles';
        styleEl.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes pulse {
                0% {
                    transform: scale(1);
                    opacity: 1;
                }
                50% {
                    transform: scale(1.05);
                    opacity: 0.8;
                }
                100% {
                    transform: scale(1);
                    opacity: 1;
                }
            }
            
            .pulse-animation {
                animation: pulse 2s infinite ease-in-out;
            }
            
            .hover-lift {
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .hover-lift:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 15px -3px rgba(139, 69, 19, 0.1), 0 4px 6px -2px rgba(139, 69, 19, 0.05);
            }
        `;
        document.head.appendChild(styleEl);
    }
}

// Call animation styles function when document is ready
document.addEventListener('DOMContentLoaded', addAnimationStyles);

