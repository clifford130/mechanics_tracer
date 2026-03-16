/**
 * Real-time Chat Logic for MechanicTracer
 */

const MT_Chat = {
    currentBookingId: null,
    lastMsgId: 0,
    pollInterval: null,
    currentUser: null,

    init(userId) {
        this.currentUser = userId;
        this.injectModal();
    },

    injectModal() {
        if (document.getElementById('chatModal')) return;
        
        const modal = document.createElement('div');
        modal.id = 'chatModal';
        modal.className = 'chat-modal';
        modal.innerHTML = `
            <div class="chat-container">
                <div class="chat-header">
                    <h3 id="chatTitle">Chat</h3>
                    <span class="close-chat" onclick="MT_Chat.close()" style="cursor:pointer;font-size:24px;">&times;</span>
                </div>
                <div class="chat-messages" id="chatMessages"></div>
                <div class="chat-footer">
                    <input type="text" id="chatInput" class="chat-input" placeholder="Type a message..." autocomplete="off">
                    <button class="chat-send-btn" onclick="MT_Chat.send()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Close on background click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.close();
        });

        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.send();
        });
    },

    open(bookingId, recipientName) {
        this.currentBookingId = bookingId;
        this.lastMsgId = 0;
        document.getElementById('chatTitle').textContent = 'Chat with ' + recipientName;
        document.getElementById('chatMessages').innerHTML = '';
        document.getElementById('chatModal').style.display = 'block';
        
        this.fetchMessages(true);
        this.startPolling();
    },

    close() {
        document.getElementById('chatModal').style.display = 'none';
        this.stopPolling();
    },

    send() {
        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (!msg || !this.currentBookingId) return;

        input.value = '';
        
        const fd = new FormData();
        fd.append('booking_id', this.currentBookingId);
        fd.append('message', msg);

        fetch('/mechanics_tracer/dashboard/api/chat.php?action=send', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.fetchMessages();
            }
        });
    },

    fetchMessages(isInitial = false) {
        if (!this.currentBookingId) return;

        if (isInitial) MT_Loader.showSection('chatMessages');

        fetch(`/mechanics_tracer/dashboard/api/chat.php?action=fetch&booking_id=${this.currentBookingId}`)
        .then(res => res.json())
        .then(data => {
            if (isInitial) MT_Loader.hideSection('chatMessages');
            if (data.success && data.messages) {
                const container = document.getElementById('chatMessages');
                
                // If the number of messages hasn't changed AND we don't need to update status, skip
                // Actually, for status updates (sent -> read), we might need to re-render or update specifically.
                // For simplicity at this scale, we re-render if something changed.
                
                let html = '';
                data.messages.forEach(m => {
                    const isSent = m.sender_id == this.currentUser;
                    const time = new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    
                    // Status icon logic:
                    // Only show status icons for messages SENT by the current user
                    let statusHtml = '';
                    if (isSent) {
                        const iconClass = m.is_read == 1 ? 'fas fa-check-double read' : 'fas fa-check';
                        const title = m.is_read == 1 ? 'Read' : 'Sent';
                        statusHtml = `<i class="${iconClass} status-icon" title="${title}"></i>`;
                    }

                    html += `
                        <div class="message ${isSent ? 'sent' : 'received'}">
                            ${m.message}
                            <div class="msg-footer">
                                <span class="msg-info">${time}</span>
                                ${statusHtml}
                            </div>
                        </div>
                    `;
                });

                // Only update DOM if HTML changed to prevent flickering
                if (container.innerHTML !== html) {
                    const shouldScroll = container.scrollTop + container.offsetHeight >= container.scrollHeight - 50;
                    container.innerHTML = html;
                    if (shouldScroll || this.lastMsgId === 0) {
                        container.scrollTop = container.scrollHeight;
                    }
                }

                if (data.messages.length > 0) {
                    this.lastMsgId = data.messages[data.messages.length - 1].id;
                }
            }
        })
        .catch(err => {
            if (isInitial) MT_Loader.hideSection('chatMessages');
            console.error('Chat fetch error:', err);
        });
    },

    startPolling() {
        this.stopPolling();
        this.pollInterval = setInterval(() => this.fetchMessages(), 3000);
    },

    stopPolling() {
        if (this.pollInterval) clearInterval(this.pollInterval);
    }
};
