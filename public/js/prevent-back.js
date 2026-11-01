// Prevent accessing cached pages after logout using browser back button
(function() {
    'use strict';
    
    // Disable browser back button for login pages when user is authenticated
    if (window.location.pathname.includes('/login') || 
        window.location.pathname.includes('/register')) {
        
        // Push current state to prevent back navigation
        window.history.pushState(null, null, window.location.href);
        
        // Listen for popstate (back button) and prevent it
        window.addEventListener('popstate', function(event) {
            window.history.pushState(null, null, window.location.href);
        });
        
        // Prevent using keyboard shortcuts to go back
        document.addEventListener('keydown', function(event) {
            // Alt + Left Arrow (back)
            if (event.altKey && event.keyCode === 37) {
                event.preventDefault();
                return false;
            }
            // Backspace key (in some browsers)
            if (event.keyCode === 8 && 
                event.target.tagName !== 'INPUT' && 
                event.target.tagName !== 'TEXTAREA') {
                event.preventDefault();
                return false;
            }
        });
    }
    
    // Clear sensitive data from browser cache on page unload
    window.addEventListener('beforeunload', function() {
        // Clear form data
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            if (form.method.toLowerCase() === 'post') {
                form.reset();
            }
        });
        
        // Clear password fields
        const passwordFields = document.querySelectorAll('input[type="password"]');
        passwordFields.forEach(field => {
            field.value = '';
        });
    });
    
    // A page brought back from the browser's memory by Back/Forward is asked for again, on every page
    // (the shop's too): after signing out, the server then shows the signed-out page, not the old account.
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
})();