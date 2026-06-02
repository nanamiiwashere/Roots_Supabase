const demoForm = document.querySelector("#demoForm");
const formNote = document.querySelector("#formNote");

function formatCounter(value, element) {
    const decimals = Number(element.dataset.decimals || 0);
    const prefix = element.dataset.prefix || "";
    const suffix = element.dataset.suffix || "";
    const usesComma = element.dataset.comma === "true";
    const formatted = value.toFixed(decimals);
    return `${prefix}${usesComma ? formatted.replace(".", ",") : formatted}${suffix}`;
}

function animateCounter(element, force = false) {
    if (element.dataset.animated === "true" && !force) return;
    element.dataset.animated = "true";
    element.classList.add("is-counting");
    const target = Number(element.dataset.target || 0);
    const start = performance.now();
    const duration = 1200;

    function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        element.textContent = formatCounter(target * eased, element);
        if (progress < 1) {
            requestAnimationFrame(tick);
        } else {
            element.classList.remove("is-counting");
            if (element.dataset.loop === "true") {
                setTimeout(() => {
                    element.dataset.animated = "false";
                    animateCounter(element, true);
                }, 5200);
            }
        }
    }
    requestAnimationFrame(tick);
}

function setupTypingEffect() {
    const target = document.querySelector(".typewriter");
    if (!target) return;
    const words = (target.dataset.words || "Startup,Future,Business").split(",").map((word) => word.trim()).filter(Boolean);
    let wordIndex = 0;
    let letterIndex = words[0].length;
    let deleting = false;

    function type() {
        const word = words[wordIndex];
        target.textContent = word.slice(0, letterIndex);
        if (!deleting && letterIndex < word.length) {
            letterIndex += 1;
            setTimeout(type, 105);
            return;
        }
        if (!deleting && letterIndex === word.length) {
            deleting = true;
            setTimeout(type, 1000);
            return;
        }
        if (deleting && letterIndex > 0) {
            letterIndex -= 1;
            setTimeout(type, 58);
            return;
        }
        deleting = false;
        wordIndex = (wordIndex + 1) % words.length;
        setTimeout(type, 180);
    }
    setTimeout(type, 700);
}

function setupScrollAnimations() {
    const counters = document.querySelectorAll(".count-up");
    const revealItems = document.querySelectorAll(".reveal");
    counters.forEach(animateCounter);
    if (!("IntersectionObserver" in window)) {
        revealItems.forEach((item) => item.classList.add("is-visible"));
        return;
    }
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add("is-visible");
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.35 });
    revealItems.forEach((item) => observer.observe(item));
}

function formatIdrPriceJs(valueInK) {
    const value = parseInt(valueInK, 10);
    if (value === 0) {
        return '<span class="price-currency">IDR</span> 0';
    }
    return '<span class="price-currency">IDR</span> ' + value + 'k';
}

function setupBillingToggle() {
    const switcher = document.querySelector(".billing-switch");
    const prices = document.querySelectorAll(".price strong");
    const labels = document.querySelectorAll(".billing-label");
    const priceUnits = document.querySelectorAll(".price span:not(.price-currency)");

    if (!switcher || prices.length === 0) return;

    switcher.addEventListener("click", () => {
        const isYearly = switcher.getAttribute("aria-pressed") !== "true";
        switcher.setAttribute("aria-pressed", String(isYearly));
        switcher.setAttribute("aria-label", isYearly ? "Switch to monthly billing" : "Switch to yearly billing");
        switcher.classList.toggle("is-yearly", isYearly);

        labels.forEach((label, index) => {
            label.classList.toggle("is-selected", isYearly ? index === 1 : index === 0);
        });

        prices.forEach((price) => {
            price.classList.add("is-switching");
            setTimeout(() => {
                const value = isYearly ? price.dataset.yearly : price.dataset.monthly;
                price.innerHTML = formatIdrPriceJs(value);
                price.classList.remove("is-switching");
            }, 160);
        });

        priceUnits.forEach((unit) => {
            unit.classList.add("is-switching");
            setTimeout(() => {
                unit.textContent = isYearly ? "/ year" : "/ month";
                unit.classList.remove("is-switching");
            }, 160);
        });
    });
}

function setupLoginModal() {
    const modal = document.querySelector("#signin");
    const triggers = document.querySelectorAll(".login-trigger");
    const closeButtons = document.querySelectorAll("[data-login-close]");
    const emailInput = document.querySelector("#loginEmail");
    const loginReturnTo = document.querySelector("#loginReturnTo");
    const passwordInput = document.querySelector("#loginPassword");
    const passwordTool = document.querySelector(".password-tool");

    if (!modal || triggers.length === 0) return;

    function openModal() {
        const signupModal = document.querySelector("#signup");
        if (signupModal) {
            signupModal.classList.remove("is-open");
            signupModal.setAttribute("aria-hidden", "true");
        }
        if (loginReturnTo) {
            const returnUrl = new URL(window.location.href);
            returnUrl.hash = "";
            loginReturnTo.value = returnUrl.toString();
        }
        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("login-open");
        setTimeout(() => { emailInput?.focus(); }, 120);
    }

    window.openSigninModal = openModal;

    function closeModal() {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("login-open");
        if (window.location.hash === "#signin") {
            history.replaceState(null, "", `${window.location.pathname}${window.location.search}`);
        }
    }

    triggers.forEach((trigger) => {
        trigger.addEventListener("click", (event) => {
            event.preventDefault();
            history.replaceState(null, "", "#signin");
            openModal();
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener("click", closeModal);
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && modal.classList.contains("is-open")) {
            closeModal();
        }
    });

    if (passwordTool) {
        passwordTool.addEventListener("click", () => {
            const showing = passwordInput?.type === "text";
            if (!passwordInput) return;
            passwordInput.type = showing ? "password" : "text";
            passwordTool.setAttribute("aria-label", showing ? "Show password" : "Hide password");
        });
    }

    if (window.location.hash === "#signin") {
        openModal();
    }
}

function setupSignupModal() {
    const modal = document.querySelector("#signup");
    const triggers = document.querySelectorAll(".signup-trigger");
    const closeButtons = document.querySelectorAll("[data-signup-close]");
    const nameInput = document.querySelector("#signupName");
    const signupReturnTo = document.querySelector("#signupReturnTo");
    const passwordInput = document.querySelector("#signupPassword");
    const passwordTool = document.querySelector(".signup-password-tool");

    if (!modal || triggers.length === 0) return;

    function openModal() {
        const signinModal = document.querySelector("#signin");
        if (signinModal) {
            signinModal.classList.remove("is-open");
            signinModal.setAttribute("aria-hidden", "true");
        }
        if (signupReturnTo) {
            const returnUrl = new URL(window.location.href);
            returnUrl.hash = "";
            signupReturnTo.value = returnUrl.toString();
        }
        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("login-open");
        setTimeout(() => { nameInput?.focus(); }, 120);
    }

    window.openSignupModal = openModal;

    function closeModal() {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("login-open");
        if (window.location.hash === "#signup") {
            history.replaceState(null, "", `${window.location.pathname}${window.location.search}`);
        }
    }

    triggers.forEach((trigger) => {
        trigger.addEventListener("click", (event) => {
            event.preventDefault();
            history.replaceState(null, "", "#signup");
            openModal();
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener("click", closeModal);
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && modal.classList.contains("is-open")) {
            closeModal();
        }
    });

    if (passwordTool) {
        passwordTool.addEventListener("click", () => {
            const showing = passwordInput?.type === "text";
            if (!passwordInput) return;
            passwordInput.type = showing ? "password" : "text";
            passwordTool.setAttribute("aria-label", showing ? "Show password" : "Hide password");
        });
    }

    if (window.location.hash === "#signup") {
        openModal();
    }
}

function setupFaqTabs() {
    const tabs = document.querySelectorAll("[data-faq-tab]");
    const panels = document.querySelectorAll("[data-faq-panel]");
    if (tabs.length === 0 || panels.length === 0) return;
    tabs.forEach((tab) => {
        tab.addEventListener("click", () => {
            const target = tab.dataset.faqTab;
            tabs.forEach((item) => {
                const active = item === tab;
                item.classList.toggle("is-active", active);
                item.setAttribute("aria-pressed", String(active));
            });
            panels.forEach((panel) => {
                const active = panel.dataset.faqPanel === target;
                panel.classList.toggle("is-active", active);
                panel.setAttribute("aria-hidden", String(!active));
            });
        });
    });
}

// Inisialisasi semua script
setupTypingEffect();
setupScrollAnimations();
setupBillingToggle();
setupLoginModal();
setupSignupModal();
setupFaqTabs();

// Biarkan form Request Demo hero tetap pake AJAX
if (demoForm && formNote) {
    demoForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        const email = demoForm.email.value.trim();
        formNote.classList.remove("is-error");

        if (!email || !email.includes("@")) {
            formNote.textContent = "Please enter a valid email address.";
            formNote.classList.add("is-error");
            return;
        }

        const button = demoForm.querySelector("button");
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = "Sending...";

        try {
            const response = await fetch(demoForm.action, {
                method: "POST",
                headers: { "Accept": "application/json" },
                body: new FormData(demoForm),
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || "We couldn't send your demo request yet.");
            demoForm.reset();
            formNote.textContent = payload.message || "Thanks, the Roots team will contact you soon.";
        } catch (error) {
            formNote.textContent = error.message;
            formNote.classList.add("is-error");
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    });
}


// ===== Workspace Setup Onboarding =====
function setupWorkspaceOnboarding() {
    const modal = document.querySelector('#workspaceSetup');
    if (!modal) return;

    const form = document.querySelector('#workspaceSetupForm');
    const steps = document.querySelectorAll('.setup-step');
    const progressItems = document.querySelectorAll('.setup-progress li');
    const nextBtn = document.querySelector('[data-setup-next]');
    const backBtn = document.querySelector('[data-setup-back]');
    const noteEl = document.querySelector('#workspaceSetupNote');
    const logoInput = document.querySelector('#workspaceLogo');
    const logoPreview = document.querySelector('#logoPreview');
    const uploadIcon = document.querySelector('#uploadIcon');
    const uploadLabel = document.querySelector('#uploadLabel');

    let currentStep = 1;
    const totalSteps = steps.length;

    // Logo upload preview
    if (logoInput && logoPreview) {
        logoInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    logoPreview.src = event.target.result;
                    logoPreview.style.display = 'block';
                    if (uploadIcon) uploadIcon.style.display = 'none';
                    if (uploadLabel) uploadLabel.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Show specific step
    function showStep(stepNumber) {
        steps.forEach((step, index) => {
            step.classList.toggle('is-active', index + 1 === stepNumber);
        });

        progressItems.forEach((item, index) => {
            item.classList.toggle('is-active', index + 1 === stepNumber);
            item.classList.toggle('is-completed', index + 1 < stepNumber);
        });

        backBtn.style.display = stepNumber === 1 ? 'none' : 'inline-flex';
        
        if (stepNumber === totalSteps) {
            nextBtn.querySelector('span').textContent = 'Complete Setup';
            nextBtn.setAttribute('type', 'submit');
        } else {
            nextBtn.querySelector('span').textContent = 'Continue';
            nextBtn.setAttribute('type', 'button');
        }

        currentStep = stepNumber;
    }

    // Validate current step
    function validateStep(stepNumber) {
        const currentStepEl = document.querySelector(`[data-setup-step="${stepNumber}"]`);
        const inputs = currentStepEl.querySelectorAll('input[required], select[required]');
        let isValid = true;
        let errorMessage = '';

        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.classList.add('is-invalid');
                if (input.name === 'workspace_name') {
                    errorMessage = 'Please enter your workspace name';
                }
            } else {
                input.classList.remove('is-invalid');
            }
        });

        if (!isValid && noteEl) {
            noteEl.textContent = errorMessage;
            noteEl.classList.add('is-error');
            setTimeout(() => {
                noteEl.textContent = '';
                noteEl.classList.remove('is-error');
            }, 3000);
        }

        return isValid;
    }

    // Next button
    if (nextBtn) {
        nextBtn.addEventListener('click', (e) => {
            if (currentStep < totalSteps) {
                e.preventDefault();
                if (validateStep(currentStep)) {
                    showStep(currentStep + 1);
                }
            } else {
                if (validateStep(currentStep)) {
                    form.submit();
                } else {
                    e.preventDefault();
                }
            }
        });
    }

    // Back button
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        });
    }

    // Form submission
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!validateStep(currentStep)) {
                e.preventDefault();
                return;
            }
            if (nextBtn) {
                nextBtn.disabled = true;
                nextBtn.querySelector('span').textContent = 'Creating workspace...';
            }
        });
    }

    showStep(1);
}


// ===== Sidebar Collapse Toggle =====
function setupSidebarCollapse() {
    const sidebar = document.querySelector('#sidebar');
    const sidebarBtn = document.querySelector('#sidebarCollapseBtn');

    if (!sidebar) return;

    // Check localStorage for saved state
    const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('is-collapsed');
    }

    function toggleSidebar() {
        const collapsed = sidebar.classList.toggle('is-collapsed');
        localStorage.setItem('sidebar-collapsed', String(collapsed));
    }

    if (sidebarBtn) {
        sidebarBtn.addEventListener('click', toggleSidebar);
    }
}

// ===== Mobile Menu Toggle =====
function setupMobileMenu() {
    const sidebar = document.querySelector('#sidebar');
    const mobileMenuBtn = document.querySelector('#mobileMenuBtn');

    if (!sidebar || !mobileMenuBtn) return;

    // Create overlay element if it doesn't exist
    let overlay = document.querySelector('.dashboard-sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'dashboard-sidebar-overlay';
        document.body.appendChild(overlay);
    }

    function openSidebar() {
        sidebar.classList.add('is-mobile-open');
        overlay.classList.add('is-active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('is-mobile-open');
        overlay.classList.remove('is-active');
        document.body.style.overflow = '';
    }

    mobileMenuBtn.addEventListener('click', openSidebar);
    overlay.addEventListener('click', closeSidebar);

    // Close sidebar on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('is-mobile-open')) {
            closeSidebar();
        }
    });
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    setupSidebarCollapse();
    setupMobileMenu();
    setupWorkspaceOnboarding();
});
