document.addEventListener("DOMContentLoaded", function () {
    const tabs = document.querySelectorAll(".nav-tab");
    const contents = document.querySelectorAll(".tab-content");
    const toggleButtons = document.querySelectorAll(".toggle-btn");

    // Get saved active tab from localStorage or default to first
    let activeTabId = localStorage.getItem("active_cpd_email_tab") || tabs[0].getAttribute("href").replace("#", "");

    // Set the active tab based on saved value
    tabs.forEach(tab => {
        const tabId = tab.getAttribute("href").replace("#", "");
        if (tabId === activeTabId) {
            tab.classList.add("nav-tab-active");
            const content = document.getElementById(tabId);
            if (content) {
                content.style.display = "block";
            }
        } else {
            tab.classList.remove("nav-tab-active");
            const content = document.getElementById(tabId);
            if (content) {
                content.style.display = "none";
            }
        }
    });

    // Handle tab clicks and save selected tab to localStorage
    tabs.forEach(tab => {
        tab.addEventListener("click", function (e) {
            e.preventDefault();
            const targetID = this.getAttribute("href").replace("#", "");
            localStorage.setItem("active_cpd_email_tab", targetID);

            tabs.forEach(t => t.classList.remove("nav-tab-active"));
            contents.forEach(c => {
                if (c) {
                    c.style.display = "none";
                }
            });

            this.classList.add("nav-tab-active");
            const targetContent = document.getElementById(targetID);
            if (targetContent) {
                targetContent.style.display = "block";
            }
        });
    });

    // Toggle email content sections and persist their open/closed state
    toggleButtons.forEach(button => {
        const targetID = button.getAttribute("data-target");
        const content = document.getElementById(targetID);

        if (!content) {
            return;
        }

        // Restore state
        if (localStorage.getItem("cpd_" + targetID) === "open") {
            content.style.display = "block";
            button.innerHTML = "▲ " + button.innerHTML.substring(2);
        }

        // Toggle logic
        button.addEventListener("click", function () {
            const isVisible = content.style.display === "block";
            content.style.display = isVisible ? "none" : "block";
            this.innerHTML = (isVisible ? "▼ " : "▲ ") + this.innerHTML.substring(2);
            localStorage.setItem("cpd_" + targetID, isVisible ? "closed" : "open");
        });
    });
});

