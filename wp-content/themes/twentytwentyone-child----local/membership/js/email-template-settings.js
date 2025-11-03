document.addEventListener("DOMContentLoaded", function () {
    const tabs = document.querySelectorAll(".nav-tab");
    const contents = document.querySelectorAll(".tab-content");
    const toggleButtons = document.querySelectorAll(".toggle-btn");

    // Get saved active tab from localStorage or default to first
    let activeTabId = localStorage.getItem("active_email_tab") || tabs[0].getAttribute("href").replace("#", "");

    // Set the active tab based on saved value
    tabs.forEach(tab => {
        const tabId = tab.getAttribute("href").replace("#", "");
        if (tabId === activeTabId) {
            tab.classList.add("nav-tab-active");
            document.getElementById(tabId).style.display = "block";
        } else {
            tab.classList.remove("nav-tab-active");
            document.getElementById(tabId).style.display = "none";
        }
    });

    // Handle tab clicks and save selected tab to localStorage
    tabs.forEach(tab => {
        tab.addEventListener("click", function (e) {
            e.preventDefault();
            const targetID = this.getAttribute("href").replace("#", "");
            localStorage.setItem("active_email_tab", targetID);

            tabs.forEach(t => t.classList.remove("nav-tab-active"));
            contents.forEach(c => c.style.display = "none");

            this.classList.add("nav-tab-active");
            document.getElementById(targetID).style.display = "block";
        });
    });

    // Toggle email content sections and persist their open/closed state
    toggleButtons.forEach(button => {
        const targetID = button.getAttribute("data-target");
        const content = document.getElementById(targetID);

        // Restore state
        if (localStorage.getItem(targetID) === "open") {
            content.style.display = "block";
            button.innerHTML = "▲ " + button.innerHTML.substring(2);
        }

        // Toggle logic
        button.addEventListener("click", function () {
            const isVisible = content.style.display === "block";
            content.style.display = isVisible ? "none" : "block";
            this.innerHTML = (isVisible ? "▼ " : "▲ ") + this.innerHTML.substring(2);
            localStorage.setItem(targetID, isVisible ? "closed" : "open");
        });
    });
});