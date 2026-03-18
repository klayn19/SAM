// ========== FORM TOGGLE FUNCTIONALITY ==========
function showForm(formId) {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    
    if (formId === 'register-form') {
        loginForm.classList.remove('active');
        registerForm.classList.add('active');
    } else if (formId === 'login-form') {
        registerForm.classList.remove('active');
        loginForm.classList.add('active');
    }
}

// ========== DASHBOARD FUNCTIONALITY ==========

// Profile form functionality
function resetForm() {
  const form = document.getElementById("profileForm");
  if (form) {
    form.reset();
    // Clear any error messages
    const errorMessages = document.querySelectorAll(".error-message");
    errorMessages.forEach((msg) => msg.remove());
  }
}

// Profile form validation
function validateProfileForm() {
  const form = document.getElementById("profileForm");
  if (!form) return false;

  const firstName = form.querySelector('input[name="firstName"]').value.trim();
  const lastName = form.querySelector('input[name="lastName"]').value.trim();
  const email = form.querySelector('input[name="email"]').value.trim();
  const currentPassword = form.querySelector(
    'input[name="current_password"]'
  ).value;
  const newPassword = form.querySelector('input[name="new_password"]').value;
  const confirmPassword = form.querySelector(
    'input[name="confirm_password"]'
  ).value;

  const errors = [];

  // Required field validation
  if (!firstName) errors.push("First name is required");
  if (!lastName) errors.push("Last name is required");
  if (!email) errors.push("Email is required");

  // Email validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (email && !emailRegex.test(email)) {
    errors.push("Please enter a valid email address");
  }

  // Password validation
  if (newPassword) {
    if (!currentPassword) {
      errors.push("Current password is required to change password");
    }
    if (newPassword.length < 6) {
      errors.push("New password must be at least 6 characters long");
    }
    if (newPassword !== confirmPassword) {
      errors.push("New password and confirm password do not match");
    }
  }

  // Display errors
  if (errors.length > 0) {
    showFormErrors(errors);
    return false;
  }

  return true;
}

function showFormErrors(errors) {
  // Remove existing error messages
  const existingErrors = document.querySelectorAll(".form-error-message");
  existingErrors.forEach((error) => error.remove());

  // Create error message element
  const errorDiv = document.createElement("div");
  errorDiv.className = "error-message form-error-message";
  errorDiv.innerHTML = `
        <ul style="margin: 0; padding-left: 20px;">
            ${errors.map((error) => `<li>${error}</li>`).join("")}
        </ul>
    `;

  // Insert error message before the form
  const form = document.getElementById("profileForm");
  if (form) {
    form.parentNode.insertBefore(errorDiv, form);
    errorDiv.scrollIntoView({ behavior: "smooth", block: "center" });
  }
}

// Enhanced form submission
document.addEventListener("DOMContentLoaded", function () {
  const profileForm = document.getElementById("profileForm");
  if (profileForm) {
    profileForm.addEventListener("submit", function (e) {
      if (!validateProfileForm()) {
        e.preventDefault();
      }
    });

    // Real-time password confirmation validation
    const newPasswordField = profileForm.querySelector(
      'input[name="new_password"]'
    );
    const confirmPasswordField = profileForm.querySelector(
      'input[name="confirm_password"]'
    );

    if (newPasswordField && confirmPasswordField) {
      function validatePasswordMatch() {
        const newPassword = newPasswordField.value;
        const confirmPassword = confirmPasswordField.value;

        if (confirmPassword && newPassword !== confirmPassword) {
          confirmPasswordField.setCustomValidity("Passwords do not match");
        } else {
          confirmPasswordField.setCustomValidity("");
        }
      }

      newPasswordField.addEventListener("input", validatePasswordMatch);
      confirmPasswordField.addEventListener("input", validatePasswordMatch);
    }
  }
});

// Theme toggle logic
(function () {
  const root = document.documentElement;
  const toggle = () => {
    const current = root.getAttribute("data-theme") || "dark";
    const next = current === "light" ? "dark" : "light";
    root.setAttribute("data-theme", next);
    try {
      localStorage.setItem("sam_theme", next);
    } catch (e) {}
  };
  const btn = document.getElementById("themeToggle");
  if (btn) {
    btn.addEventListener("click", toggle);
  }
})();

// ========== PRINT FUNCTIONALITY ==========

// Print with popup window
function printStudentRecordPopup() {
  const recordTable = document.querySelector(".record-table");
  if (!recordTable) {
    console.error("printStudentRecord: .record-table not found");
    alert("Nothing to print: record table not found.");
    return null;
  }

  const clone = recordTable.cloneNode(true);
  clone
    .querySelectorAll("[onclick]")
    .forEach((n) => n.removeAttribute("onclick"));

  const styles = `
        <style>
            body{font-family: Arial, Helvetica, sans-serif; color:#000; margin:20px;}
            .record-table{width:100%; box-sizing:border-box;}
            .record-header{display:grid; grid-template-columns: repeat(4, 1fr); gap:12px; margin-bottom:12px;}
            .record-row{display:grid; grid-template-columns: repeat(4, 1fr); gap:12px; margin-bottom:12px;}
            .record-cell, .header-cell { background:#fff !important; color:#000 !important; padding:12px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;}
            .header-cell { font-weight:700; background:#e9ecef !important; }
            img{max-width:120px; height:auto; display:block; margin:0 auto 8px;}
            @media print { body{margin:8mm;} }
        </style>
    `;

  const html = `<!doctype html>
        <html>
        <head><meta charset="utf-8"><title>Student Attendance Record</title>${styles}</head>
        <body>
            <div style="text-align:center; margin-bottom:14px;">
                <img src="LOGO.png" alt="Logo">
                <h2 style="margin:6px 0;">Student Attendance Record</h2>
                <div style="font-size:0.9em; color:#333;">Generated: ${new Date().toLocaleString()}</div>
            </div>
            ${clone.outerHTML}
        </body>
        </html>`;

  const w = window.open(
    "",
    "_blank",
    "noopener,noreferrer,width=900,height=700"
  );
  if (!w) {
    return null; // popup blocked
  }

  w.document.open();
  w.document.write(html);
  w.document.close();

  setTimeout(() => {
    try {
      w.focus();
      w.print();
    } catch (err) {
      console.error("Popup print failed", err);
    }
  }, 500);

  return w;
}

// Print inline (fallback for blocked popups)
function printStudentRecordInline() {
  const recordTable = document.querySelector(".record-table");
  if (!recordTable) {
    console.error("printStudentRecordInline: .record-table not found");
    alert("Nothing to print: record table not found.");
    return;
  }

  const clone = recordTable.cloneNode(true);
  clone
    .querySelectorAll("[onclick]")
    .forEach((n) => n.removeAttribute("onclick"));

  const styles = `
        <style>
            body{font-family: Arial, Helvetica, sans-serif; color:#000; margin:20px;}
            .record-header{display:grid; grid-template-columns: repeat(4, 1fr); gap:12px; margin-bottom:12px;}
            .record-row{display:grid; grid-template-columns: repeat(4, 1fr); gap:12px; margin-bottom:12px;}
            .record-cell, .header-cell { background:#fff !important; color:#000 !important; padding:12px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;}
            .header-cell { font-weight:700; background:#e9ecef !important; }
            img{max-width:120px; height:auto; display:block; margin:0 auto 8px;}
            @media print { body { margin: 8mm; } }
        </style>
    `;

  const header = `
        <div style="text-align:center; margin-bottom:14px;">
            <img src="LOGO.png" alt="Logo">
            <h2 style="margin:6px 0;">Student Attendance Record</h2>
            <div style="font-size:0.9em; color:#333;">Generated: ${new Date().toLocaleString()}</div>
        </div>
    `;

  const originalBody = document.body.innerHTML;
  document.body.innerHTML = styles + header + clone.outerHTML;

  // allow render then print, then restore by reloading
  setTimeout(() => {
    window.print();
    setTimeout(() => {
      // reload to restore JS state and event handlers
      window.location.reload();
    }, 300);
  }, 250);
}

// Unified handler: try popup, fallback to inline
function handlePrintClick(e) {
  e.preventDefault();
  const popupWindow = printStudentRecordPopup();
  if (popupWindow === null) {
    // popup blocked – use inline print fallback
    printStudentRecordInline();
  }
}

// Print function alias for onclick handlers
function printStudentRecord() {
  const popupWindow = printStudentRecordPopup();
  if (popupWindow === null) {
    printStudentRecordInline();
  }
}

// Attach handler on DOM ready
document.addEventListener("DOMContentLoaded", () => {
  const btn = document.querySelector(".print-btn");
  if (btn) {
    // remove inline onclick to avoid duplicates
    btn.removeAttribute("onclick");
    btn.addEventListener("click", handlePrintClick);
  }
});

  function toggleTheme() {
            const html = document.documentElement;
            const themeIcon = document.getElementById('themeIcon');
            const currentTheme = html.getAttribute('data-theme');
            
            if (currentTheme === 'light') {
                html.setAttribute('data-theme', 'dark');
                themeIcon.textContent = '🌙';
                localStorage.setItem('theme', 'dark');
            } else {
                html.setAttribute('data-theme', 'light');
                themeIcon.textContent = '☀️';
                localStorage.setItem('theme', 'light');
            }
        }

        // Load saved theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            const html = document.documentElement;
            const themeIcon = document.getElementById('themeIcon');
            
            html.setAttribute('data-theme', savedTheme);
            themeIcon.textContent = savedTheme === 'light' ? '☀️' : '🌙';
        });

        function viewStudentProfile(studentId) {
            // Redirect to student dashboard with the student ID as a parameter
            window.location.href = 'dashboard_student.php?student_id=' + encodeURIComponent(studentId);
        }

        function viewStudentRecord(studentId) {
            // Redirect to record view with the student ID
            window.location.href = '?view=record&student_id=' + encodeURIComponent(studentId);
        }
        