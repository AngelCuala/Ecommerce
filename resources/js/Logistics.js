// Logistics JavaScript Functions

// Pickup request management
function confirmRejectPickupRequest() {
    return confirm('Reject this pickup request?');
}

function confirmApprovePickupRequest() {
    return confirm('Approve this pickup request?');
}

// Chat functions
function initializeLogisticsChat() {
    // Auto-scroll to bottom of chat messages
    const messagesContainer = document.getElementById('chat-messages');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Setup message sending
    setupMessageSending();
    
    // Setup auto-refresh for new messages
    setupMessageRefresh();
}

function setupMessageSending() {
    const messageForm = document.getElementById('message-form');
    const messageInput = document.getElementById('message-input');
    
    if (messageForm && messageInput) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const message = messageInput.value.trim();
            if (!message) return;
            
            // Send message via AJAX
            sendChatMessage(message);
            
            // Clear input
            messageInput.value = '';
        });
        
        // Send on Enter key
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                messageForm.dispatchEvent(new Event('submit'));
            }
        });
    }
}

function sendChatMessage(message) {
    const messagesContainer = document.getElementById('chat-messages');
    
    // Add message to UI immediately
    appendMessage({
        content: message,
        sender: 'You',
        timestamp: new Date().toLocaleTimeString(),
        type: 'sent'
    });
    
    // Send to backend (you would implement the actual AJAX call here)
    fetch('/logistics/chat/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            message: message,
            chat_id: getCurrentChatId()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Message sent successfully');
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
    });
}

function appendMessage(messageData) {
    const messagesContainer = document.getElementById('chat-messages');
    if (!messagesContainer) return;
    
    const messageElement = document.createElement('div');
    messageElement.className = `message ${messageData.type === 'sent' ? 'sent' : 'received'}`;
    messageElement.innerHTML = `
        <div class="message-content">
            <div class="message-header">
                <span class="sender">${messageData.sender}</span>
                <span class="timestamp">${messageData.timestamp}</span>
            </div>
            <div class="message-text">${messageData.content}</div>
        </div>
    `;
    
    messagesContainer.appendChild(messageElement);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function getCurrentChatId() {
    // Get chat ID from URL or data attribute
    const chatIdElement = document.querySelector('[data-chat-id]');
    return chatIdElement ? chatIdElement.dataset.chatId : null;
}

function setupMessageRefresh() {
    // Refresh messages every 5 seconds
    setInterval(() => {
        refreshMessages();
    }, 5000);
}

function refreshMessages() {
    const chatId = getCurrentChatId();
    if (!chatId) return;
    
    fetch(`/logistics/chat/${chatId}/messages`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.messages) {
            updateMessagesContainer(data.messages);
        }
    })
    .catch(error => {
        console.error('Error refreshing messages:', error);
    });
}

function updateMessagesContainer(messages) {
    const messagesContainer = document.getElementById('chat-messages');
    if (!messagesContainer) return;
    
    // Store current scroll position
    const wasAtBottom = messagesContainer.scrollTop >= messagesContainer.scrollHeight - messagesContainer.clientHeight - 10;
    
    // Clear and rebuild messages
    messagesContainer.innerHTML = '';
    
    messages.forEach(message => {
        appendMessage({
            content: message.content,
            sender: message.sender_name || message.sender,
            timestamp: formatMessageTime(message.created_at),
            type: message.is_from_user ? 'received' : 'sent'
        });
    });
    
    // Auto-scroll if user was at bottom
    if (wasAtBottom) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
}

function formatMessageTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Parcel management functions
function confirmAssignParcel() {
    return confirm('Assign this parcel to the selected courier?');
}

function confirmReassignParcel() {
    return confirm('Reassign this parcel to a different courier?');
}

function updateParcelStatus(parcelId, status) {
    if (!confirm(`Update parcel status to ${status}?`)) {
        return false;
    }
    
    // AJAX call to update status
    fetch(`/logistics/parcels/${parcelId}/status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error updating parcel status:', error);
    });
    
    return true;
}

// Delivery tracking functions
function trackDelivery(trackingNumber) {
    if (!trackingNumber) {
        alert('Please enter a tracking number');
        return;
    }
    
    // Show loading state
    const trackButton = document.getElementById('track-button');
    const originalText = trackButton ? trackButton.textContent : '';
    if (trackButton) {
        trackButton.textContent = 'Tracking...';
        trackButton.disabled = true;
    }
    
    fetch(`/logistics/track/${trackingNumber}`)
        .then(response => response.json())
        .then(data => {
            displayTrackingResult(data);
        })
        .catch(error => {
            console.error('Error tracking delivery:', error);
            alert('Error tracking delivery. Please try again.');
        })
        .finally(() => {
            if (trackButton) {
                trackButton.textContent = originalText;
                trackButton.disabled = false;
            }
        });
}

function displayTrackingResult(data) {
    const resultContainer = document.getElementById('tracking-result');
    if (!resultContainer) return;
    
    if (data.found) {
        resultContainer.innerHTML = `
            <div class="tracking-info">
                <h3>Tracking: ${data.tracking_number}</h3>
                <p><strong>Status:</strong> ${data.status}</p>
                <p><strong>Current Location:</strong> ${data.location}</p>
                <p><strong>Last Update:</strong> ${data.last_update}</p>
                <div class="tracking-history">
                    <h4>Tracking History:</h4>
                    ${data.history.map(event => `
                        <div class="tracking-event">
                            <span class="event-date">${event.date}</span>
                            <span class="event-status">${event.status}</span>
                            <span class="event-location">${event.location}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    } else {
        resultContainer.innerHTML = `
            <div class="tracking-error">
                <p>Tracking number not found. Please check the number and try again.</p>
            </div>
        `;
    }
    
    resultContainer.style.display = 'block';
}

// Reports and analytics
function generateLogisticsReport(reportType) {
    const dateFrom = document.getElementById('report-date-from')?.value;
    const dateTo = document.getElementById('report-date-to')?.value;
    
    if (!dateFrom || !dateTo) {
        alert('Please select both start and end dates');
        return;
    }
    
    const reportParams = new URLSearchParams({
        type: reportType,
        date_from: dateFrom,
        date_to: dateTo
    });
    
    // Open report in new window
    window.open(`/logistics/reports/generate?${reportParams.toString()}`, '_blank');
}

// Courier management
function assignCourierToArea(courierId, areaId) {
    if (!confirm('Assign courier to this delivery area?')) {
        return false;
    }
    
    fetch('/logistics/couriers/assign-area', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            courier_id: courierId,
            area_id: areaId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Courier assigned successfully');
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error assigning courier:', error);
        alert('Error assigning courier. Please try again.');
    });
    
    return true;
}

// Initialize logistics functions
document.addEventListener('DOMContentLoaded', function() {
    // Initialize chat if on chat page
    if (document.getElementById('chat-messages')) {
        initializeLogisticsChat();
    }
    
    // Setup tracking form if present
    setupTrackingForm();
    
    // Setup report generators
    setupReportGenerators();
});

function setupTrackingForm() {
    const trackingForm = document.getElementById('tracking-form');
    const trackingInput = document.getElementById('tracking-input');
    
    if (trackingForm && trackingInput) {
        trackingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            trackDelivery(trackingInput.value.trim());
        });
    }
}

function setupReportGenerators() {
    document.querySelectorAll('[data-report-type]').forEach(button => {
        button.addEventListener('click', function() {
            const reportType = this.getAttribute('data-report-type');
            generateLogisticsReport(reportType);
        });
    });
}