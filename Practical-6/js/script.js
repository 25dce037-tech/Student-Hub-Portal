function validateLogin() {
    let username = document.getElementById("username").value.trim();
    let password = document.getElementById("password").value;

    if (username === "") {
        showNotification("Username is required", "error");
        return false;
    }

    if (password.length < 6) {
        showNotification("Password must be at least 6 characters", "error");
        return false;
    }

    // Show the success popup, then continue to the page set in the form's action
    showNotification("Login Successful! Welcome, " + username + ".", "success");

    const form = document.querySelector("form");
    const target = form ? form.getAttribute("action") : "../index.html";

    setTimeout(function() {
        window.location.href = target + "?username=" + encodeURIComponent(username);
    }, 1800);

    return false;
}

function validateRegister() {
    let fullname = document.getElementById("fullname").value.trim();
    let studentid = document.getElementById("studentid").value.trim();
    let mobile = document.getElementById("mobile").value.trim();
    let email = document.getElementById("email").value.trim();
    let division = document.getElementById("division").value.trim();

    if (fullname === "") {
        alert("Full Name is required");
        return false;
    }

    if (studentid === "") {
        alert("Student ID is required");
        return false;
    }

    if (!/^[0-9]{10}$/.test(mobile)) {
        alert("Enter a valid 10-digit Mobile Number");
        return false;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert("Enter a valid Email Address");
        return false;
    }

    if (division === "") {
        alert("Division is required");
        return false;
    }

    alert("Registration Successful!");
    return true;
}

function result(event) {

    if (event) {
        event.preventDefault();
    }

    const name = document.getElementById("name");
    const degree = document.getElementById("degree");
    const sem = document.getElementById("sem");
    const exam = document.getElementById("exam");
    const id = document.getElementById("ID");

    const nameValue = name.value.trim();
    const degreeValue = degree.value.trim();
    const semValue = sem.value.trim();
    const examValue = exam.value.trim();
    const idValue = id.value.trim();

    name.style.border = "1px solid #ccc";
    degree.style.border = "1px solid #ccc";
    sem.style.border = "1px solid #ccc";
    exam.style.border = "1px solid #ccc";
    id.style.border = "1px solid #ccc";

    let isValid = true;

    if (nameValue === "" || nameValue === "Select Institute") {
        name.style.border = "2px solid red";
        isValid = false;
    }

    if (degreeValue === "" || degreeValue === "Select Degree") {
        degree.style.border = "2px solid red";
        isValid = false;
    }

    if (semValue === "" || semValue === "Select Semester") {
        sem.style.border = "2px solid red";
        isValid = false;
    }

    if (examValue === "" || examValue === "Select Month") {
        exam.style.border = "2px solid red";
        isValid = false;
    }

    if (idValue === "") {
        id.style.border = "2px solid red";
        isValid = false;
    }

    if (!isValid) {
        showNotification("Please fill in all required fields.");
        return false;
    }

    showNotification("Result retrieved successfully!");

    setTimeout(function() {
        window.location.href = "resultpage.html";
    }, 1000);

    return false;
}

function clearForm() {
    const form = document.querySelector("form");

    if (form) {
        form.reset();

        const inputs =
            form.querySelectorAll("input, select, textarea");

        inputs.forEach(function(input) {
            input.style.border = "1px solid #ccc";
        });

        showNotification("Form cleared successfully!");
    }
}

function contact() {

    const name = document.getElementById("name");
    const message = document.getElementById("message");

    const nameValue = name.value.trim();
    const messageValue = message.value.trim();

    name.style.border = "1px solid #ccc";
    message.style.border = "1px solid #ccc";

    if (nameValue === "" || messageValue === "") {

        if (nameValue === "") {
            name.style.border = "2px solid red";
        }

        if (messageValue === "") {
            message.style.border = "2px solid red";
        }

        showNotification("Please enter both name and message.");
        return false;
    }

    showNotification("Message sent successfully!");

    name.value = "";
    message.value = "";

    return false;
}

function showNotification(message, type) {
    let notification = document.getElementById("notification");
    let notificationMessage = document.getElementById("notificationMessage");

    // Create the popup if the page does not already have one
    if (!notification) {
        notification = document.createElement("div");
        notification.id = "notification";

        const icon = document.createElement("span");
        icon.id = "notificationIcon";

        notificationMessage = document.createElement("span");
        notificationMessage.id = "notificationMessage";

        notification.appendChild(icon);
        notification.appendChild(notificationMessage);
        document.body.appendChild(notification);
    }

    notificationMessage.textContent = message;
    notification.classList.remove("success", "error");
    notification.classList.add(type === "error" ? "error" : "success");

    const icon = document.getElementById("notificationIcon");
    if (icon) icon.textContent = type === "error" ? "✕" : "✓";

    notification.classList.add("show");

    clearTimeout(showNotification.timer);
    showNotification.timer = setTimeout(function() {
        notification.classList.remove("show");
    }, 3000);
}

function toggleMenu() {
    const navbar = document.getElementById("navbar");
    const menuButton = document.getElementById("menuButton");

    navbar.classList.toggle("active");

    if (navbar.classList.contains("active")) {
        menuButton.textContent = "✕";
    } else {
        menuButton.textContent = "☰";
    }
}

/* ---------- Light / Dark theme (all handled in JS) ---------- */

function injectThemeStyles() {
    if (document.getElementById("themeStyles")) return;

    const style = document.createElement("style");
    style.id = "themeStyles";
    style.textContent = `
        body, body * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        /* ----- Dark theme ----- */
        body.dark-theme {
            background-color: #0F1720 !important;
            color: #E5E7EB !important;
        }
        body.dark-theme header,
        body.dark-theme .portal-header,
        body.dark-theme > h1,
        body.dark-theme .page-header,
        body.dark-theme footer,
        body.dark-theme .portal-footer,
        body.dark-theme th {
            background-color: #0A2540 !important;
            color: #FFFFFF !important;
        }
        body.dark-theme nav,
        body.dark-theme .nav-container,
        body.dark-theme .card,
        body.dark-theme .portal-card,
        body.dark-theme .stat-card,
        body.dark-theme .form-box-wrapper,
        body.dark-theme .content,
        body.dark-theme main > div,
        body.dark-theme table {
            background-color: #1B2733 !important;
            border-color: #2F4A63 !important;
            color: #E5E7EB !important;
        }
        body.dark-theme h1,
        body.dark-theme h2,
        body.dark-theme h3,
        body.dark-theme h4,
        body.dark-theme .section-title,
        body.dark-theme .form-group label,
        body.dark-theme label {
            color: #8FD3F4 !important;
        }
        body.dark-theme .page-header {
            color: #FFFFFF !important;
        }
        body.dark-theme .stat-card h4 {
            color: #9CA3AF !important;
        }
        body.dark-theme .note-box,
        body.dark-theme .heading {
            background-color: #16354A !important;
            color: #E5E7EB !important;
        }
        body.dark-theme td {
            color: #E5E7EB !important;
            border-bottom-color: #2F4A63 !important;
        }
        body.dark-theme tr:nth-child(even) { background-color: #16222E !important; }
        body.dark-theme tr:nth-child(odd)  { background-color: #1B2733 !important; }
        body.dark-theme tr:hover           { background-color: #23405A !important; }
        body.dark-theme input:not([type="submit"]):not([type="button"]),
        body.dark-theme select,
        body.dark-theme textarea {
            background-color: #0F1720 !important;
            color: #E5E7EB !important;
            border-color: #2F4A63 !important;
        }
        body.dark-theme p,
        body.dark-theme li,
        body.dark-theme span {
            color: #E5E7EB;
        }
        body.dark-theme a:not(.btn) {
            color: #8FD3F4;
        }
        body.dark-theme hr {
            border-color: #2F4A63;
        }

        /* ----- Theme toggle button ----- */
        #themeButton {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 9999;
            width: 44px;
            height: 44px;
            padding: 0 !important;
            border: 2px solid #2A9DCC;
            border-radius: 50%;
            background-color: #FFFFFF;
            color: #12355B;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.25);
        }
        #themeButton:hover {
            transform: scale(1.08);
        }
        body.dark-theme #themeButton {
            background-color: #1B2733;
            color: #FFFFFF;
        }

        /* ----- Popup notification ----- */
        #notification {
            position: fixed;
            top: 25px;
            left: 50%;
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 260px;
            max-width: 90%;
            padding: 16px 24px;
            border-radius: 10px;
            border-left: 6px solid #2E9E5B;
            background-color: #FFFFFF;
            color: #1F2937;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            opacity: 0;
            pointer-events: none;
            transform: translate(-50%, -30px);
            transition: opacity 0.35s ease, transform 0.35s ease;
        }
        #notification.show {
            opacity: 1;
            transform: translate(-50%, 0);
        }
        #notification.error {
            border-left-color: #D64545;
        }
        #notificationIcon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: #2E9E5B;
            color: #FFFFFF;
            font-weight: bold;
        }
        #notification.error #notificationIcon {
            background-color: #D64545;
        }
        body.dark-theme #notification {
            background-color: #1B2733 !important;
            color: #E5E7EB !important;
        }
        body.dark-theme #notificationMessage {
            color: #E5E7EB;
        }
    `;
    document.head.appendChild(style);
}

function updateThemeButton() {
    const themeButton = document.getElementById("themeButton");
    if (!themeButton) return;

    const isDark = document.body.classList.contains("dark-theme");
    themeButton.textContent = isDark ? "🌙" : "☀️";
    themeButton.title = isDark ? "Switch to light mode" : "Switch to dark mode";
    themeButton.setAttribute("aria-label", themeButton.title);
}

function initTheme() {
    injectThemeStyles();

    let savedTheme = "light";
    try {
        savedTheme = localStorage.getItem("theme") || "light";
    } catch (e) {}

    document.body.classList.toggle("dark-theme", savedTheme === "dark");

    // Add the toggle button if the page does not have one
    if (!document.getElementById("themeButton")) {
        const button = document.createElement("button");
        button.id = "themeButton";
        button.type = "button";
        button.addEventListener("click", toggleTheme);
        document.body.appendChild(button);
    }

    updateThemeButton();
}

function toggleTheme() {
    const isDark = document.body.classList.toggle("dark-theme");

    try {
        localStorage.setItem("theme", isDark ? "dark" : "light");
    } catch (e) {}

    updateThemeButton();
}

window.addEventListener("DOMContentLoaded", initTheme);
