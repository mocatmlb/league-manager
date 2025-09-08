/**
 * District 8 Travel League - Timezone Utilities
 * Handles timezone-aware date formatting for the application
 */

// Get the application timezone from PHP
let appTimezone = 'America/New_York'; // Default fallback

// Function to set the application timezone (called from PHP)
function setAppTimezone(timezone) {
    appTimezone = timezone;
}

/**
 * Format date string in application timezone
 * @param {string} dateString - Date string from database
 * @param {Object} options - Intl.DateTimeFormat options
 * @returns {string} Formatted date string
 */
function formatDateTZ(dateString, options = {}) {
    if (!dateString) return '';
    
    // Default options for date formatting
    const defaultOptions = {
        timeZone: appTimezone,
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    };
    
    const formatOptions = { ...defaultOptions, ...options };
    
    try {
        // Parse the date string - handle both date-only and datetime strings
        let date;
        if (dateString.includes('T') || dateString.includes(' ')) {
            // Full datetime string
            date = new Date(dateString);
        } else {
            // Date-only string - parse as local date to avoid timezone shift
            const parts = dateString.split('-');
            date = new Date(parts[0], parts[1] - 1, parts[2]);
        }
        
        return new Intl.DateTimeFormat('en-US', formatOptions).format(date);
    } catch (error) {
        console.error('Error formatting date:', dateString, error);
        return dateString;
    }
}

/**
 * Format time string in application timezone
 * @param {string} timeString - Time string from database (HH:MM:SS or full datetime)
 * @param {Object} options - Intl.DateTimeFormat options
 * @returns {string} Formatted time string
 */
function formatTimeTZ(timeString, options = {}) {
    if (!timeString) return '';
    
    // Default options for time formatting
    const defaultOptions = {
        timeZone: appTimezone,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    };
    
    const formatOptions = { ...defaultOptions, ...options };
    
    try {
        let date;
        
        if (timeString.includes('T') || (timeString.includes(' ') && timeString.includes(':'))) {
            // Full datetime string
            date = new Date(timeString);
        } else if (timeString.includes(':')) {
            // Time-only string - create date for today with this time
            const today = new Date();
            const [hours, minutes, seconds = '00'] = timeString.split(':');
            date = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 
                          parseInt(hours), parseInt(minutes), parseInt(seconds));
        } else {
            throw new Error('Invalid time format');
        }
        
        return new Intl.DateTimeFormat('en-US', formatOptions).format(date);
    } catch (error) {
        console.error('Error formatting time:', timeString, error);
        return timeString;
    }
}

/**
 * Format datetime string in application timezone
 * @param {string} datetimeString - Datetime string from database
 * @param {Object} options - Intl.DateTimeFormat options
 * @returns {string} Formatted datetime string
 */
function formatDateTimeTZ(datetimeString, options = {}) {
    if (!datetimeString) return '';
    
    // Default options for datetime formatting
    const defaultOptions = {
        timeZone: appTimezone,
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    };
    
    const formatOptions = { ...defaultOptions, ...options };
    
    try {
        const date = new Date(datetimeString);
        return new Intl.DateTimeFormat('en-US', formatOptions).format(date);
    } catch (error) {
        console.error('Error formatting datetime:', datetimeString, error);
        return datetimeString;
    }
}

/**
 * Format date for display in tables (compact format)
 * @param {string} dateString - Date string from database
 * @returns {string} Formatted date string (M/d/yy format)
 */
function formatDateCompact(dateString) {
    return formatDateTZ(dateString, {
        month: 'numeric',
        day: 'numeric',
        year: '2-digit'
    });
}

/**
 * Format time for display in tables (compact format)
 * @param {string} timeString - Time string from database
 * @returns {string} Formatted time string (h:mm AM/PM format)
 */
function formatTimeCompact(timeString) {
    return formatTimeTZ(timeString, {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

// Legacy function names for backward compatibility
function formatDate(dateString) {
    return formatDateCompact(dateString);
}

function formatTime(timeString) {
    return formatTimeCompact(timeString);
}

// Export functions for module use (if supported)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        setAppTimezone,
        formatDateTZ,
        formatTimeTZ,
        formatDateTimeTZ,
        formatDateCompact,
        formatTimeCompact,
        formatDate,
        formatTime
    };
}

