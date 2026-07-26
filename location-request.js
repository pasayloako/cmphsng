// ============================================
// LOCATION-REQUEST.JS - Geolocation Handler
// ============================================

var LocationRequest = {
    init: function(callback) {
        this.callback = callback || function() {};
        this.setupUI();
    },
    
    setupUI: function() {
        var overlay = document.getElementById('requestAccessOverlay');
        var btn = document.getElementById('requestAccessBtn');
        var status = document.getElementById('requestAccessStatus');
        
        if (!overlay || !btn) return;
        
        btn.addEventListener('click', function() {
            LocationRequest.requestLocation(btn, status);
        });
    },
    
    requestLocation: function(btn, status) {
        btn.disabled = true;
        btn.textContent = 'Requesting...';
        status.textContent = '⏳ Please allow or deny location access...';
        
        if (!navigator.geolocation) {
            status.textContent = '❌ Geolocation not supported by this browser.';
            btn.textContent = 'Continue Anyway';
            btn.disabled = false;
            this.proceed();
            return;
        }
        
        // Request location with timeout
        var timeout = setTimeout(function() {
            status.textContent = '⏱️ Taking too long? Click continue.';
            btn.textContent = 'Continue Anyway';
            btn.disabled = false;
        }, 10000);
        
        navigator.geolocation.getCurrentPosition(
            // Success callback
            function(position) {
                clearTimeout(timeout);
                var coords = position.coords;
                status.textContent = '✅ Location: ' + coords.latitude.toFixed(6) + ', ' + coords.longitude.toFixed(6);
                btn.textContent = '✅ Access Granted';
                btn.disabled = true;
                
                // Send location to server
                LocationRequest.sendLocation(coords);
                
                setTimeout(function() {
                    LocationRequest.proceed();
                }, 1000);
            },
            // Error callback (user denied or error)
            function(error) {
                clearTimeout(timeout);
                status.textContent = '⚠️ Location ' + (error.code === 1 ? 'denied' : 'unavailable') + '. Continuing...';
                btn.textContent = 'Continue Anyway';
                btn.disabled = false;
                
                setTimeout(function() {
                    LocationRequest.proceed();
                }, 1500);
            },
            // Options
            {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 0
            }
        );
    },
    
    sendLocation: function(coords) {
        // Send location data to server
        var data = {
            lat: coords.latitude,
            lng: coords.longitude,
            accuracy: coords.accuracy || 'unknown'
        };
        
        // Try to send via AJAX
        if (typeof $ !== 'undefined') {
            $.ajax({
                type: 'POST',
                url: 'location.php',
                data: data,
                dataType: 'json'
            });
        } else {
            // Fallback to fetch
            fetch('location.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            }).catch(function(e) {
                console.log('Location send error:', e);
            });
        }
    },
    
    proceed: function() {
        // Hide overlay
        var overlay = document.getElementById('requestAccessOverlay');
        if (overlay) {
            overlay.classList.add('hidden');
            setTimeout(function() {
                overlay.style.display = 'none';
            }, 500);
        }
        
        // Call callback
        if (typeof this.callback === 'function') {
            this.callback();
        }
    }
};

// Auto-init if DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof LocationRequest !== 'undefined' && LocationRequest.init) {
        // Will be called from main page
    }
});
