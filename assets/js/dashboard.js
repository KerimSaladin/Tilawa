// Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Handle navigation active state
    const currentPath = window.location.pathname;
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && (currentPath.includes(href) || href === currentPath)) {
            item.classList.add('active');
        }
    });
    
    // Handle logout - for links without onclick
    const logoutLinks = document.querySelectorAll('a[href*="logout"]:not([onclick])');
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('هل أنت متأكد من تسجيل الخروج؟')) {
                e.preventDefault();
            }
        });
    });
    
    // Handle logout button in header
    const logoutBtn = document.querySelector('.logout-btn');
    if (logoutBtn && !logoutBtn.onclick) {
        logoutBtn.addEventListener('click', function(e) {
            if (!confirm('هل أنت متأكد من تسجيل الخروج؟')) {
                e.preventDefault();
            }
        });
    }
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // User menu dropdown
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenuDropdown = document.getElementById('userMenuDropdown');
    
    if (userMenuBtn && userMenuDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('active');
            // Close notifications if open
            const notificationsDropdown = document.getElementById('notificationsDropdown');
            if (notificationsDropdown) {
                notificationsDropdown.classList.remove('active');
            }
        });
    }
    
    // Notifications dropdown
    const notificationsBtn = document.getElementById('notificationsBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    
    if (notificationsBtn && notificationsDropdown) {
        notificationsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationsDropdown.classList.toggle('active');
            // Close user menu if open
            if (userMenuDropdown) {
                userMenuDropdown.classList.remove('active');
            }
            // Load notifications
            loadNotifications();
        });
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (userMenuDropdown && !userMenuDropdown.contains(e.target) && !userMenuBtn.contains(e.target)) {
            userMenuDropdown.classList.remove('active');
        }
        if (notificationsDropdown && !notificationsDropdown.contains(e.target) && !notificationsBtn.contains(e.target)) {
            notificationsDropdown.classList.remove('active');
        }
    });
    
    // Handle settings view
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('view') === 'settings') {
        showSettings();
    }
});

// Load notifications
function loadNotifications() {
    const notificationsList = document.getElementById('notificationsList');
    if (!notificationsList) return;
    
    // For now, show placeholder. You can add AJAX call here later
    notificationsList.innerHTML = '<div class="notification-item">لا توجد إشعارات جديدة</div>';
}

// Create group function
function createGroup() {
    window.location.href = 'groups.php';
}

// Show settings
function showSettings() {
    alert('صفحة الإعدادات - سيتم إضافتها قريباً');
    // You can create a settings page or modal here
}

