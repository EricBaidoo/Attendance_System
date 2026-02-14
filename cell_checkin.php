<?php
require_once 'config/database.php';

$service_id = 5; // Fixed for RESTORERS

// Fetch all active cell centers
$stmt = $pdo->prepare("
    SELECT id, name, address, latitude, longitude, radius_meters,
           DATE_FORMAT(meeting_time, '%h:%i %p') as meeting_time
    FROM cell_centers 
    WHERE service_id = ? AND status = 'active'
    ORDER BY name
");
$stmt->execute([$service_id]);
$cell_centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$cell_centers_json = json_encode($cell_centers);
?>
<!DOCTYPE html>
<html>

<head>
    <title>RESTORERS Cell Meeting Check-in</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            width: 100%;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h1 {
            color: #1e3c72;
            text-align: center;
            margin-bottom: 10px;
        }

        .service-badge {
            background: #4caf50;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            display: inline-block;
            margin: 0 auto 30px;
            text-align: center;
            width: fit-content;
        }

        .location-card {
            background: #f8fafc;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 5px solid #ff9800;
            transition: all 0.3s;
        }

        .location-card.success {
            border-left-color: #4caf50;
            background: #e8f5e9;
        }

        .location-card.error {
            border-left-color: #f44336;
            background: #ffebee;
        }

        .permission-option {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .permission-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .permission-option label {
            margin: 0;
            font-weight: normal;
            color: #475569;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #1e293b;
            font-weight: 600;
            font-size: 14px;
        }

        input,
        select {
            width: 100%;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #2a5298;
            box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1);
        }

        /* Phone autocomplete dropdown */
        .phone-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0 0 10px 10px;
            border-top: none;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .phone-dropdown.show {
            display: block;
        }

        .phone-dropdown-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .phone-dropdown-item:hover {
            background: #f8fafc;
        }

        .phone-dropdown-item .member-name {
            font-weight: 600;
            color: #1e293b;
        }

        .phone-dropdown-item .member-phone {
            color: #64748b;
            font-size: 14px;
        }

        .phone-dropdown-item .member-dept {
            font-size: 12px;
            color: #2a5298;
            background: #e6f0ff;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            margin-bottom: 15px;
        }

        .btn-primary {
            background: #2a5298;
        }

        .btn-primary:hover:not(:disabled) {
            background: #1e3c72;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #4caf50;
        }

        .btn-success:hover:not(:disabled) {
            background: #388e3c;
            transform: translateY(-2px);
        }

        .btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }

        .member-card {
            background: #f0f9ff;
            border-radius: 15px;
            padding: 25px;
            margin-top: 25px;
            border: 2px solid #b8e1ff;
            display: none;
        }

        .member-card.active {
            display: block;
        }

        .member-detail {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: white;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .match-status {
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .match-success {
            background: #e8f5e9;
            border-left: 5px solid #4caf50;
        }

        .match-error {
            background: #ffebee;
            border-left: 5px solid #f44336;
        }

        .accuracy-badge {
            background: #e2e8f0;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: #475569;
            display: inline-block;
            margin-left: 10px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #e6f7e6;
            color: #1e4620;
            border: 1px solid #a5d6a5;
        }

        .alert-error {
            background: #ffebee;
            color: #b71c1c;
            border: 1px solid #ef9a9a;
        }

        .spinner {
            display: none;
            width: 50px;
            height: 50px;
            margin: 20px auto;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #2a5298;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .spinner.show {
            display: block;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .location-btn {
            background: #ff9800;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 15px;
            width: 100%;
            transition: all 0.3s;
            font-weight: 600;
        }

        .location-btn:hover {
            background: #f57c00;
            transform: translateY(-2px);
        }

        .location-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }

        .quick-select {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .quick-select-btn {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            color: #334155;
        }

        .quick-select-btn:hover {
            background: #e2e8f0;
        }

        .last-updated {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Debug panel */
        .debug-panel {
            background: #fff3cd;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <h1>RESTORERS</h1>
            <div style="text-align: center;" class="service-badge">
                Cell Meeting Check-in
            </div>

            <!-- Debug Panel - Hidden by default, show with ?debug=true -->
            <div id="debugPanel" class="debug-panel">
                <strong>🔧 Debug Mode</strong><br>
                <button onclick="localStorage.removeItem('locationPermission'); location.reload();" style="padding: 5px 10px; margin-top: 5px;">Reset Permission</button>
                <button onclick="requestLocation();" style="padding: 5px 10px; margin-top: 5px;">Force Location</button>
                <span id="debugStatus" style="display: block; margin-top: 5px;"></span>
            </div>

            <div id="alertMessage" class="alert"></div>

            <!-- Location Status -->
            <div id="locationCard" class="location-card">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <span style="font-size: 24px;"></span>
                    <div>
                        <strong id="locationTitle">Initializing location...</strong>
                        <p id="locationSubtitle" style="margin: 5px 0 0; color: #64748b; font-size: 14px;">
                            Checking permission status...
                        </p>
                    </div>
                </div>

                <!-- Remember Permission Checkbox -->
                <div id="permissionOption" class="permission-option">
                    <input type="checkbox" id="rememberLocation" checked>
                    <label for="rememberLocation">Remember my location permission for future visits</label>
                </div>

                <button id="requestLocationBtn" class="location-btn" style="display: none;">
                    Enable Location Access
                </button>

                <div id="locationAccuracy" class="accuracy-badge" style="display: none;"></div>
                <div id="lastUpdated" class="last-updated" style="display: none;"></div>
            </div>

            <!-- Phone Number with Autocomplete -->
            <div class="form-group">
                <label> Phone Number</label>
                <input type="tel" id="phone" placeholder="Type phone number to search... (e.g 0270000002)"
                    autocomplete="off" oninput="searchMembers()" onfocus="searchMembers()">
                <div id="phoneDropdown" class="phone-dropdown"></div>
                <div id="quickSelectContainer" class="quick-select"></div>
            </div>

            <!-- Cell Center Selection -->
            <div class="form-group">
                <label>Select Your Cell Center</label>
                <select id="cellCenter" onchange="handleCenterChange()" disabled>
                    <option value="">-- First enable location --</option>
                    <?php foreach ($cell_centers as $center): ?>
                        <option value="<?= $center['id'] ?>"
                            data-lat="<?= $center['latitude'] ?>"
                            data-lng="<?= $center['longitude'] ?>"
                            data-radius="<?= $center['radius_meters'] ?>">
                            <?= htmlspecialchars($center['name']) ?> - <?= htmlspecialchars($center['address']) ?>
                            <?= $center['meeting_time'] ? "( {$center['meeting_time']})" : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Verify Button (Hidden - auto-verify on selection) -->
            <button id="verifyBtn" class="btn btn-primary" onclick="verifyMember()" disabled style="display: none;">
                🔍 Verify & Continue
            </button>

            <!-- Member Info Card - Shows immediately when phone is selected -->
            <div id="memberCard" class="member-card">
                <h3 style="color: #2a5298; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <span style="background: #2a5298; color: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">✓</span>
                    Member Verified
                </h3>
                <div id="memberDetails"></div>

                <!-- Location Match Status -->
                <div id="matchStatus" class="match-status">
                    <span style="font-size: 24px;"></span>
                    <div>
                        <strong id="matchTitle">Checking location...</strong>
                        <p id="matchMessage" style="margin: 5px 0 0; font-size: 14px;"></p>
                    </div>
                </div>

                <!-- Mark Attendance Button -->
                <button id="markBtn" class="btn btn-success" onclick="markAttendance()" disabled>
                    Mark Attendance
                </button>
            </div>

            <div id="loadingSpinner" class="spinner"></div>
        </div>
    </div>

    <script>
        const cellCenters = <?php echo $cell_centers_json; ?>;
        let userLocation = null;
        let selectedCenter = null;
        let currentMember = null;
        let searchTimeout = null;
        let watchId = null;
        let locationAttempts = 0;

        // Show debug panel if URL has ?debug=true
        if (window.location.search.includes('debug=true')) {
            document.getElementById('debugPanel').style.display = 'block';
        }

        // Check for saved permission on page load
        window.addEventListener('load', function() {
            console.log('Page loaded, checking location permission...');

            // Check if geolocation is supported
            if (!navigator.geolocation) {
                showAlert('Geolocation is not supported by your browser', 'error');
                document.getElementById('locationTitle').textContent = '❌ Location not supported';
                document.getElementById('locationSubtitle').textContent = 'Your browser does not support geolocation';
                document.getElementById('requestLocationBtn').style.display = 'none';
                document.getElementById('permissionOption').style.display = 'none';
                return;
            }

            const permissionGranted = localStorage.getItem('locationPermission');
            document.getElementById('debugStatus').textContent = 'Permission saved: ' + (permissionGranted || 'none');

            if (permissionGranted === 'granted') {
                console.log('Permission previously granted, attempting silent location...');
                requestLocationSilent();
            } else {
                console.log('No permission saved, showing prompt...');
                showLocationPrompt();
            }
        });

        // Silent location request
        function requestLocationSilent() {
            const locationTitle = document.getElementById('locationTitle');
            const locationSubtitle = document.getElementById('locationSubtitle');

            locationTitle.textContent = 'Getting your location...';
            locationSubtitle.textContent = 'This will only take a moment';

            navigator.geolocation.getCurrentPosition(
                positionSuccess,
                function(error) {
                    console.log('Silent location failed:', error.code);
                    document.getElementById('debugStatus').textContent = 'Silent failed: ' + error.code;

                    if (error.code === error.PERMISSION_DENIED) {
                        localStorage.removeItem('locationPermission');
                    }
                    showLocationPrompt();
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000
                }
            );
        }

        // Show location prompt
        function showLocationPrompt() {
            const locationCard = document.getElementById('locationCard');
            const locationTitle = document.getElementById('locationTitle');
            const locationSubtitle = document.getElementById('locationSubtitle');
            const requestBtn = document.getElementById('requestLocationBtn');
            const permissionOption = document.getElementById('permissionOption');
            const cellCenter = document.getElementById('cellCenter');

            locationCard.className = 'location-card';
            locationTitle.textContent = ' Location access needed';
            locationSubtitle.textContent = 'Tap the button below to enable location';

            requestBtn.style.display = 'block';
            requestBtn.textContent = ' Enable Location Access';
            requestBtn.onclick = function() {
                requestLocation();
            };
            requestBtn.disabled = false;

            permissionOption.style.display = 'flex';
            cellCenter.disabled = true;
            cellCenter.innerHTML = '<option value="">-- First enable location --</option>';
        }

        // Request location
        function requestLocation() {
            const locationCard = document.getElementById('locationCard');
            const locationTitle = document.getElementById('locationTitle');
            const locationSubtitle = document.getElementById('locationSubtitle');
            const requestBtn = document.getElementById('requestLocationBtn');

            locationCard.className = 'location-card';
            locationTitle.textContent = 'Requesting location access...';
            locationSubtitle.textContent = 'Please allow location when prompted';
            requestBtn.style.display = 'none';
            locationAttempts++;

            navigator.geolocation.getCurrentPosition(
                positionSuccess,
                positionError, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }

        // Position success
        function positionSuccess(position) {
            console.log('Location captured successfully!');

            userLocation = {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
                accuracy: position.coords.accuracy
            };

            document.getElementById('debugStatus').textContent = 'Location captured!';

            // Save permission if checkbox is checked
            const rememberCheckbox = document.getElementById('rememberLocation');
            if (rememberCheckbox && rememberCheckbox.checked) {
                localStorage.setItem('locationPermission', 'granted');
                console.log('Permission saved to localStorage');
            }

            // Start watching position
            if (watchId === null) {
                watchId = navigator.geolocation.watchPosition(
                    positionUpdated,
                    null, {
                        enableHighAccuracy: true,
                        maximumAge: 10000,
                        timeout: 5000
                    }
                );
            }

            const locationCard = document.getElementById('locationCard');
            const locationTitle = document.getElementById('locationTitle');
            const locationSubtitle = document.getElementById('locationSubtitle');
            const cellCenter = document.getElementById('cellCenter');
            const requestBtn = document.getElementById('requestLocationBtn');
            const permissionOption = document.getElementById('permissionOption');
            const locationAccuracy = document.getElementById('locationAccuracy');
            const lastUpdated = document.getElementById('lastUpdated');

            locationCard.className = 'location-card success';
            locationTitle.textContent = '✅ Location active';
            locationSubtitle.textContent = ` ${userLocation.lat.toFixed(6)}, ${userLocation.lng.toFixed(6)}`;

            if (locationAccuracy) {
                locationAccuracy.textContent = `±${Math.round(userLocation.accuracy)}m accuracy`;
                locationAccuracy.style.display = 'inline-block';
            }

            if (lastUpdated) {
                lastUpdated.textContent = `Updated: ${new Date().toLocaleTimeString()}`;
                lastUpdated.style.display = 'flex';
            }

            requestBtn.style.display = 'none';
            if (permissionOption) permissionOption.style.display = 'none';

            // Enable and populate dropdown - FIXED VERSION
            cellCenter.disabled = false;
            cellCenter.innerHTML = '<option value="">-- Select your cell center --</option>';

            // Add options from PHP data
            <?php foreach ($cell_centers as $center): ?>
                    (function() {
                        const option = document.createElement('option');
                        option.value = '<?= $center['id'] ?>';
                        option.setAttribute('data-lat', '<?= $center['latitude'] ?>');
                        option.setAttribute('data-lng', '<?= $center['longitude'] ?>');
                        option.setAttribute('data-radius', '<?= $center['radius_meters'] ?>');
                        option.textContent = '<?= addslashes($center['name']) ?> - <?= addslashes($center['address']) ?> <?= $center['meeting_time'] ? "(⏰ " . addslashes($center['meeting_time']) . ")" : '' ?>';
                        cellCenter.appendChild(option);
                    })();
            <?php endforeach; ?>

            validateForm();

            if (currentMember && selectedCenter) {
                checkLocationMatch();
            }
        }

        // Position updated
        function positionUpdated(position) {
            if (userLocation) {
                userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy
                };

                const locationSubtitle = document.getElementById('locationSubtitle');
                const locationAccuracy = document.getElementById('locationAccuracy');
                const lastUpdated = document.getElementById('lastUpdated');

                if (locationSubtitle) {
                    locationSubtitle.textContent = ` ${userLocation.lat.toFixed(6)}, ${userLocation.lng.toFixed(6)}`;
                }
                if (locationAccuracy) {
                    locationAccuracy.textContent = `±${Math.round(userLocation.accuracy)}m accuracy`;
                }
                if (lastUpdated) {
                    lastUpdated.textContent = `Updated: ${new Date().toLocaleTimeString()}`;
                }

                if (currentMember && selectedCenter) {
                    checkLocationMatch();
                }
            }
        }

        // Position error
        function positionError(error) {
            console.log('Location error:', error.code);
            document.getElementById('debugStatus').textContent = 'Error: ' + error.code;

            const locationCard = document.getElementById('locationCard');
            const locationTitle = document.getElementById('locationTitle');
            const locationSubtitle = document.getElementById('locationSubtitle');
            const requestBtn = document.getElementById('requestLocationBtn');
            const permissionOption = document.getElementById('permissionOption');
            const cellCenter = document.getElementById('cellCenter');

            locationCard.className = 'location-card error';

            switch (error.code) {
                case error.PERMISSION_DENIED:
                    locationTitle.textContent = '❌ Location access denied';
                    locationSubtitle.textContent = 'Please allow location access to check in';
                    localStorage.removeItem('locationPermission');
                    requestBtn.style.display = 'block';
                    requestBtn.textContent = '🔓 Enable Location';
                    requestBtn.onclick = function() {
                        requestLocation();
                    };
                    requestBtn.disabled = false;
                    permissionOption.style.display = 'flex';
                    break;
                case error.POSITION_UNAVAILABLE:
                    locationTitle.textContent = '❌ Location unavailable';
                    locationSubtitle.textContent = 'Unable to detect your location. Check GPS.';
                    requestBtn.style.display = 'block';
                    requestBtn.textContent = '🔄 Try Again';
                    requestBtn.onclick = function() {
                        requestLocation();
                    };
                    break;
                case error.TIMEOUT:
                    locationTitle.textContent = '❌ Location request timeout';
                    locationSubtitle.textContent = 'Please try again';
                    requestBtn.style.display = 'block';
                    requestBtn.textContent = '🔄 Try Again';
                    requestBtn.onclick = function() {
                        requestLocation();
                    };
                    break;
                default:
                    locationTitle.textContent = '❌ Location error';
                    locationSubtitle.textContent = 'An unknown error occurred';
                    requestBtn.style.display = 'block';
                    requestBtn.textContent = '🔄 Try Again';
                    requestBtn.onclick = function() {
                        requestLocation();
                    };
            }

            cellCenter.disabled = true;
            cellCenter.innerHTML = '<option value="">-- First enable location --</option>';
        }

        // SEARCH MEMBERS AS YOU TYPE
        function searchMembers() {
            const searchTerm = document.getElementById('phone').value.trim();
            const dropdown = document.getElementById('phoneDropdown');

            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            if (searchTerm.length < 3) {
                dropdown.classList.remove('show');
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch('search_members.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'search=' + encodeURIComponent(searchTerm)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.members.length > 0) {
                            displayMemberSuggestions(data.members);
                        } else {
                            dropdown.innerHTML = '<div style="padding: 15px; color: #64748b; text-align: center;">No members found</div>';
                            dropdown.classList.add('show');
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                    });
            }, 300);
        }

        // DISPLAY MEMBER SUGGESTIONS
        function displayMemberSuggestions(members) {
            const dropdown = document.getElementById('phoneDropdown');
            let html = '';

            members.forEach(member => {
                html += `
                    <div class="phone-dropdown-item" onclick="selectMember(${member.id}, '${member.phone}', '${member.name.replace(/'/g, "\\'")}', '${member.department_name || 'Not assigned'}', '${member.ministerial_status || 'Member'}')">
                        <div>
                            <div class="member-name">${member.name}</div>
                            <div class="member-phone">${member.phone}</div>
                        </div>
                        <div>
                            <span class="member-dept">${member.department_name || 'Member'}</span>
                        </div>
                    </div>
                `;
            });

            dropdown.innerHTML = html;
            dropdown.classList.add('show');
        }

        // SELECT MEMBER
        function selectMember(id, phone, name, department, ministerialStatus) {
            document.getElementById('phone').value = phone;
            document.getElementById('phoneDropdown').classList.remove('show');

            currentMember = {
                id: id,
                name: name,
                phone: phone,
                department: department,
                ministerial_status: ministerialStatus
            };

            displayMemberInfo(currentMember);
            validateForm();
        }

        function handleCenterChange() {
            const select = document.getElementById('cellCenter');
            const option = select.options[select.selectedIndex];

            if (select.value && userLocation) {
                selectedCenter = {
                    id: select.value,
                    name: option.text.split(' - ')[0],
                    lat: parseFloat(option.dataset.lat),
                    lng: parseFloat(option.dataset.lng),
                    radius: parseInt(option.dataset.radius)
                };
            } else {
                selectedCenter = null;
            }

            validateForm();

            if (document.getElementById('memberCard').classList.contains('active')) {
                checkLocationMatch();
            }
        }

        function validateForm() {
            if (currentMember && selectedCenter) {
                checkLocationMatch();
            }
        }

        function verifyMember() {
            if (currentMember && selectedCenter) {
                checkLocationMatch();
            }
        }

        function displayMemberInfo(member) {
            const memberCard = document.getElementById('memberCard');
            const memberDetails = document.getElementById('memberDetails');

            memberDetails.innerHTML = `
                <div class="member-detail">
                    <span style="font-size: 20px;">👤</span>
                    <div><strong>Name:</strong> ${member.name}</div>
                </div>
                <div class="member-detail">
                    <span style="font-size: 20px;">📱</span>
                    <div><strong>Phone:</strong> ${member.phone}</div>
                </div>
                <div class="member-detail">
                    <span style="font-size: 20px;">🏢</span>
                    <div><strong>Department:</strong> ${member.department || 'Not assigned'}</div>
                </div>
                <div class="member-detail">
                    <span style="font-size: 20px;">👔</span>
                    <div><strong>Status:</strong> ${member.ministerial_status || 'Member'}</div>
                </div>
            `;

            memberCard.classList.add('active');

            if (selectedCenter) {
                checkLocationMatch();
            }
        }

        function checkLocationMatch() {
            if (!userLocation || !selectedCenter || !currentMember) return;

            const distance = calculateDistance(
                userLocation.lat, userLocation.lng,
                selectedCenter.lat, selectedCenter.lng
            );
            const distanceMeters = distance * 1000;
            const isMatch = distanceMeters <= selectedCenter.radius;

            const matchStatus = document.getElementById('matchStatus');
            const matchTitle = document.getElementById('matchTitle');
            const matchMessage = document.getElementById('matchMessage');
            const markBtn = document.getElementById('markBtn');

            if (isMatch) {
                matchStatus.className = 'match-status match-success';
                matchTitle.textContent = '✅ Location Verified!';
                matchMessage.textContent = `You are within ${Math.round(distanceMeters)}m of ${selectedCenter.name || 'this center'}`;
                markBtn.disabled = false;
            } else {
                matchStatus.className = 'match-status match-error';
                matchTitle.textContent = '❌ Location Mismatch';
                matchMessage.textContent = `You are ${Math.round(distanceMeters)}m away. Please move closer to the cell center.`;
                markBtn.disabled = true;
            }
        }

        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function markAttendance() {
            if (!currentMember || !selectedCenter || !userLocation) {
                showAlert('Missing required information', 'error');
                return;
            }

            showLoading(true);
            document.getElementById('markBtn').disabled = true;

            fetch('record_cell_attendance_pdo.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        member_id: currentMember.id,
                        cell_center_id: selectedCenter.id,
                        service_id: 5,
                        location_lat: userLocation.lat,
                        location_lng: userLocation.lng,
                        location_accuracy: userLocation.accuracy || 10
                    })
                })
                .then(response => response.json())
                .then(data => {
                    showLoading(false);
                    if (data.success) {
                        showAlert('✅ Check-in successful! Welcome!', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        showAlert(data.message || 'Check-in failed', 'error');
                        document.getElementById('markBtn').disabled = false;
                    }
                })
                .catch(error => {
                    showLoading(false);
                    showAlert('Error recording attendance', 'error');
                    document.getElementById('markBtn').disabled = false;
                    console.error('Error:', error);
                });
        }

        function showAlert(message, type) {
            const alert = document.getElementById('alertMessage');
            alert.textContent = message;
            alert.className = 'alert show alert-' + type;
            setTimeout(() => alert.classList.remove('show'), 5000);
        }

        function showLoading(show) {
            const spinner = document.getElementById('loadingSpinner');
            if (show) {
                spinner.classList.add('show');
            } else {
                spinner.classList.remove('show');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('phoneDropdown');
            const input = document.getElementById('phone');
            if (!input.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Clean up watch position when page unloads
        window.addEventListener('beforeunload', function() {
            if (watchId !== null) {
                navigator.geolocation.clearWatch(watchId);
            }
        });
    </script>
</body>

</html>