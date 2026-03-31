/**
 * AI Mechanic Recommender Frontend Logic
 */
const AIRecommender = {
    isTyping: false,
    
    init() {
        if (document.getElementById('aiChatPanel')) return;

        // 1. Inject Floating Button
        const btn = document.createElement('button');
        btn.className = 'ai-recommender-btn';
        btn.innerHTML = '<i class="fas fa-magic"></i> AI Recommender';
        btn.onclick = () => this.togglePanel();
        document.body.appendChild(btn);

        // 2. Inject Chat Panel
        const panel = document.createElement('div');
        panel.id = 'aiChatPanel';
        panel.className = 'ai-chat-panel';
        panel.innerHTML = `
            <div class="ai-chat-header">
                <h3><i class="fas fa-robot text-blue-400"></i> Auto AI Assistant</h3>
                <button class="close-btn" onclick="AIRecommender.togglePanel()"><i class="fas fa-times"></i></button>
            </div>
            <div class="ai-chat-messages" id="aiChatMessages">
                <div class="ai-msg system">
                    Hello! I'm your AI assistant. Tell me what's wrong with your vehicle (e.g. "My brakes are squeaking and the steering is shaking") and I'll find the best mechanics nearby.
                </div>
            </div>
            <div class="ai-chat-input-area">
                <input type="text" id="aiChatInput" class="ai-chat-input" placeholder="Describe your car problem..." autocomplete="off" onkeypress="if(event.key === 'Enter') AIRecommender.submit()">
                <button id="aiChatSubmitBtn" class="ai-chat-submit" onclick="AIRecommender.submit()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        `;
        document.body.appendChild(panel);
        this.loadHistory();
    },

    loadHistory() {
        const saved = sessionStorage.getItem('aiChatHistory');
        if (saved) {
            const container = document.getElementById('aiChatMessages');
            container.innerHTML = saved;
            // Ensure no typing indicator was accidentally saved
            const typing = document.getElementById('aiTypingIndicator');
            if (typing) typing.remove();
        }
    },

    saveHistory() {
        // Remove typing indicator temporarily if present so it's not saved
        const typing = document.getElementById('aiTypingIndicator');
        let tempHtml = "";
        if (typing) {
            tempHtml = typing.outerHTML;
            typing.remove();
        }
        
        const container = document.getElementById('aiChatMessages');
        if (container) {
            sessionStorage.setItem('aiChatHistory', container.innerHTML);
        }
        
        // Restore typing indicator if it was there
        if (typing && container) {
            container.insertAdjacentHTML('beforeend', tempHtml);
        }
    },

    togglePanel() {
        const panel = document.getElementById('aiChatPanel');
        panel.classList.toggle('active');
        if (panel.classList.contains('active')) {
            document.getElementById('aiChatInput').focus();
        }
    },

    addMessage(text, type, rawHtml = false) {
        const container = document.getElementById('aiChatMessages');
        const msg = document.createElement('div');
        msg.className = `ai-msg ${type}`;
        
        if (rawHtml) {
            msg.innerHTML = text;
        } else {
            msg.textContent = text;
        }
        
        container.appendChild(msg);
        this.saveHistory();
        this.scrollToBottom();
        return msg;
    },

    scrollToBottom() {
        const container = document.getElementById('aiChatMessages');
        container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
    },

    showTyping() {
        this.isTyping = true;
        document.getElementById('aiChatSubmitBtn').disabled = true;
        const container = document.getElementById('aiChatMessages');
        const typingIndicator = document.createElement('div');
        typingIndicator.id = 'aiTypingIndicator';
        typingIndicator.className = 'ai-msg system typing-indicator';
        typingIndicator.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
        container.appendChild(typingIndicator);
        this.scrollToBottom();
    },

    hideTyping() {
        this.isTyping = false;
        document.getElementById('aiChatSubmitBtn').disabled = false;
        const doc = document.getElementById('aiTypingIndicator');
        if (doc) doc.remove();
    },

    submit() {
        if (this.isTyping) return;
        
        const input = document.getElementById('aiChatInput');
        const problem = input.value.trim();
        
        if (!problem) return;
        
        // Add user msg
        this.addMessage(problem, 'user');
        input.value = '';
        
        this.showTyping();

        // Pass global driver coords if available from driver_dashboard
        const lat = window.driverLat || null;
        const lng = window.driverLng || null;

        fetch('/mechanics_tracer/dashboard/api/ai_recommend.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ problem, lat, lng })
        })
        .then(res => res.json())
        .then(data => {
            this.hideTyping();
            
            if (!data.success) {
                this.addMessage(`<i class="fas fa-exclamation-circle" style="color:#ef4444"></i> ${data.message}`, 'system', true);
                return;
            }

            // Success case
            let responseHtml = `<strong>Detected Services:</strong> ${data.detected_services.join(', ')}<br><br>`;
            
            if (data.mechanics.length === 0) {
                responseHtml += `I found what you need, but no mechanics are currently available nearby offering those services.`;
                this.addMessage(responseHtml, 'system', true);
                return;
            }

            responseHtml += `Here are the top ${data.mechanics.length} recommended mechanics:`;
            
            // Build Mechanic Cards
            data.mechanics.forEach(m => {
                // Determine stars html
                let starsHtml = '';
                if (m.rating_count > 0) {
                    starsHtml = `<span class="stars"><i class="fas fa-star"></i> ${m.avg_rating}</span> (${m.rating_count} reviews)`;
                } else {
                    starsHtml = `<span>No reviews yet</span>`;
                }

                // Distance text
                let distText = m.distance_km ? `${m.distance_km} km away` : 'Distance unknown';

                // We escape JSON params for the button clicks
                const mechanicJson = JSON.stringify(m).replace(/"/g, '&quot;');
                const servicesString = data.detected_services.join(', ').replace(/"/g, '&quot;');
                const problemEsc = problem.replace(/"/g, '&quot;');

                responseHtml += `
                    <div class="mech-card" id="mech-card-${m.id}">
                        <h4>${m.garage_name}</h4>
                        <div class="mech-meta">
                            <div>${starsHtml} &bull; ${distText}</div>
                        </div>
                        <div class="mech-actions">
                            <button class="btn-map" onclick="AIRecommender.viewOnMap(${mechanicJson})"><i class="fas fa-map-marker-alt"></i> View on Map</button>
                            <button class="btn-book" onclick="AIRecommender.bookInline(${m.id}, '${servicesString}', '${problemEsc}')"><i class="fas fa-calendar-check"></i> Book Now</button>
                        </div>
                    </div>
                `;
            });

            this.addMessage(responseHtml, 'system', true);
            
            // Add any missing mechanics to global window.mechanics so drawRoute handles them
            if (window.mechanics) {
                data.mechanics.forEach(newMech => {
                    const exists = window.mechanics.find(em => em.id == newMech.id);
                    if (!exists) {
                        newMech.latitude = Number(newMech.latitude);
                        newMech.longitude = Number(newMech.longitude);
                        window.mechanics.push(newMech);
                        // Plot marker on map
                        if(window._mapInstance) {
                            var icon = L.icon({iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',iconSize:[25,41],iconAnchor:[12,41],popupAnchor:[1,-34]});
                            var marker = L.marker([newMech.latitude, newMech.longitude], {icon: icon}).addTo(window._mapInstance);
                            newMech.marker = marker;
                            marker.bindPopup(`<b>${newMech.garage_name}</b>`);
                        }
                    }
                });
            }

        })
        .catch(err => {
            console.error(err);
            this.hideTyping();
            this.addMessage(`<i class="fas fa-wifi" style="color:#ef4444"></i> Network error connecting to AI. Please try again.`, 'system', true);
        });
    },

    viewOnMap(mechanic) {
        // Look up the exact object reference from the window map pool to trigger drawRoute
        if (window.mechanics && window.drawRoute && window._mapInstance) {
            const mapMech = window.mechanics.find(m => m.id == mechanic.id);
            if (mapMech && mapMech.marker) {
                if (window.innerWidth < 600) {
                    this.togglePanel(); // Hide panel on mobile to see map
                }
                mapMech.marker.openPopup();
                window.drawRoute(mapMech);
            } else {
                alert("Cannot pan map to the mechanic. Data not fully loaded.");
            }
        }
    },

    bookInline(mechanicId, servicesRequested, problem) {
        if (!confirm('Are you sure you want to book this mechanic?')) return;

        const btnRow = document.querySelector(`#mech-card-${mechanicId} .mech-actions`);
        if (btnRow) btnRow.innerHTML = `<span style="font-size:0.85rem; color:#64748b;">Booking...</span>`;

        fetch('/mechanics_tracer/dashboard/api/inline_book_mechanic.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                mechanic_id: mechanicId, 
                problem: problem,
                services_requested: servicesRequested,
                lat: window.driverLat || 0,
                lng: window.driverLng || 0
            })
        })
        .then(res => res.json())
        .then(data => {
            const card = document.getElementById(`mech-card-${mechanicId}`);
            if (!card) return;

            if (data.success) {
                card.style.background = '#ecfdf5';
                card.style.borderColor = '#10b981';
                card.innerHTML = `
                    <div style="color: #059669; font-weight: 600; font-size: 0.95rem; display:flex; align-items:center; gap:6px;">
                        <i class="fas fa-check-circle"></i> Booking Confirmed!
                    </div>
                    <p style="margin:6px 0 0 0; font-size:0.85rem; color:#047857;">The mechanic has received your request regarding "<span style="font-style:italic;">${problem}</span>". Check "My Bookings" for updates.</p>
                `;
                this.saveHistory();
            } else {
                if (btnRow) {
                    btnRow.innerHTML = `<span style="font-size:0.85rem; color:#ef4444;"><i class="fas fa-exclamation-circle"></i> ${data.message}</span>`;
                }
            }
        })
        .catch(err => {
            console.error(err);
            if (btnRow) {
                btnRow.innerHTML = `<span style="font-size:0.85rem; color:#ef4444;"><i class="fas fa-wifi"></i> Booking failed</span>`;
            }
        });
    }
};
