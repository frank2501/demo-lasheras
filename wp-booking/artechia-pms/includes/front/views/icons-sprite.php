<?php
/**
 * SVG Icon Sprite for Public Front-end.
 * Injected into wp_footer.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
    <!-- Calendar -->
    <symbol id="icon-calendar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
    </symbol>

    <!-- Moon / Night -->
    <symbol id="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
    </symbol>

    <!-- Users/Capacity -->
    <symbol id="icon-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
    </symbol>
    
    <!-- Bed -->
    <symbol id="icon-bed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M2 4v16"></path>
        <path d="M2 8h18a2 2 0 0 1 2 2v10"></path>
        <path d="M2 17h20"></path>
        <path d="M6 8v9"></path>
    </symbol>

    <!-- Clock -->
    <symbol id="icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <polyline points="12 6 12 12 16 14"></polyline>
    </symbol>

    <!-- Amenities -->
    <!-- WiFi -->
    <symbol id="icon-wifi" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 12.55a11 11 0 0 1 14.08 0"></path>
        <path d="M1.42 9a16 16 0 0 1 21.16 0"></path>
        <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
        <line x1="12" y1="20" x2="12.01" y2="20"></line>
    </symbol>

    <!-- AC / Snowflake -->
    <symbol id="icon-ac" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="2" x2="12" y2="22"></line>
        <line x1="20" y1="12" x2="4" y2="12"></line>
        <line x1="18.36" y1="18.36" x2="5.64" y2="5.64"></line>
        <line x1="18.36" y1="5.64" x2="5.64" y2="18.36"></line>
        <line x1="16" y1="2" x2="12" y2="6"></line>
        <line x1="8" y1="2" x2="12" y2="6"></line>
        <line x1="22" y1="16" x2="18" y2="12"></line>
        <line x1="22" y1="8" x2="18" y2="12"></line>
        <line x1="16" y1="22" x2="12" y2="18"></line>
        <line x1="8" y1="22" x2="12" y2="18"></line>
        <line x1="2" y1="16" x2="6" y2="12"></line>
        <line x1="2" y1="8" x2="6" y2="12"></line>
    </symbol>

    <!-- Heating / Flame -->
    <symbol id="icon-heating" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path>
    </symbol>

    <!-- TV -->
    <symbol id="icon-tv" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect>
        <polyline points="17 2 12 7 7 2"></polyline>
    </symbol>

    <!-- Minibar / Refrigerator / Glass -->
    <symbol id="icon-minibar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"></path>
        <line x1="4" y1="10" x2="20" y2="10"></line>
        <line x1="9" y1="5" x2="9" y2="7"></line>
    </symbol>

    <!-- Safe / Shield -->
    <symbol id="icon-safe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
    </symbol>

    <!-- Balcony / Window -->
    <symbol id="icon-balcony" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="3" y1="9" x2="21" y2="9"></line>
        <line x1="9" y1="21" x2="9" y2="9"></line>
    </symbol>

    <!-- Garden/Pool View / Mountain/Sun -->
    <symbol id="icon-view" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
        <circle cx="8.5" cy="8.5" r="1.5"></circle>
        <polyline points="21 15 16 10 5 21"></polyline>
    </symbol>

    <!-- Parking / Car -->
    <symbol id="icon-parking" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="1" y="4.5" width="22" height="15" rx="2" ry="2"></rect>
        <path d="M3.5 16.5c0-1.5 1-2.5 2.5-2.5h12c1.5 0 2.5 1 2.5 2.5"></path>
        <circle cx="7" cy="16.5" r="2.5"></circle>
        <circle cx="17" cy="16.5" r="2.5"></circle>
        <path d="M5 9.5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v5H5v-5z"></path>
    </symbol>

    <!-- Kitchen / Utensils -->
    <symbol id="icon-kitchen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 2v7c0 1.1.9 2 2 2h4V2"></path>
        <path d="M7 2v20"></path>
        <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
    </symbol>

    <!-- Jacuzzi / Bath -->
    <symbol id="icon-jacuzzi" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 6 6.5 3.5a1.5 1.5 0 0 0-1-1C4.3 2.5 3 3.8 3.5 5l2.5 2.5"></path>
        <path d="M12 6V3a1 1 0 0 0-1-1H9"></path>
        <path d="M3 13V7a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v6"></path>
        <path d="M4 11a4 4 0 0 0 16 0"></path>
        <path d="M2 13h20"></path>
        <path d="M5 13v2a2 2 0 0 1-2 2"></path>
        <path d="M19 13v2a2 2 0 0 0 2 2"></path>
    </symbol>

    <!-- Fireplace -->
    <symbol id="icon-fireplace" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M7 22h10"></path>
        <path d="M12 22v-4"></path>
        <path d="M15.5 11.5c-1-1.5-2-2.5-3.5-2.5s-2.5 1-3.5 2.5S10 16 12 16s3.5-3 3.5-4.5z"></path>
        <path d="M4 18v-8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8"></path>
    </symbol>

    <!-- BBQ / Grill -->
    <symbol id="icon-bbq" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 12v6"></path>
        <path d="M7 18v3"></path>
        <path d="M17 18v3"></path>
        <path d="M20 12c0-4.4-3.6-8-8-8s-8 3.6-8 8h16z"></path>
        <line x1="8" y1="12" x2="8" y2="10"></line>
        <line x1="12" y1="12" x2="12" y2="10"></line>
        <line x1="16" y1="12" x2="16" y2="10"></line>
    </symbol>

    <!-- Washer -->
    <symbol id="icon-washer" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="4" y="2" width="16" height="20" rx="2"></rect>
        <circle cx="12" cy="13" r="5"></circle>
        <path d="M12 11a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"></path>
        <line x1="8" y1="6" x2="8" y2="6"></line>
        <line x1="12" y1="6" x2="12" y2="6"></line>
    </symbol>
    
    <!-- Generic Star / Amenity -->
    <symbol id="icon-amenity" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
    </symbol>
</svg>
