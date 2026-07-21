document.addEventListener('DOMContentLoaded', () => {
    
    if (document.getElementById('map')) {
        const map = L.map('map', {
            zoomControl: true,
            attributionControl: false
        }).setView([7.8731, 80.7718], 8);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            maxZoom: 19
        }).addTo(map);

        let pickupMarker = null;
        let dropoffMarker = null;

        map.on('click', function(e) {
            const lat = e.latlng.lat.toFixed(6);
            const lng = e.latlng.lng.toFixed(6);
            
            const wktPoint = `POINT(${lng} ${lat})`;
            
            const passFromInput = document.querySelector('input[placeholder*="From"]');
            const passToInput = document.querySelector('input[placeholder*="To"]');
            
            if (passFromInput && passToInput) {
                if (!passFromInput.value) {
                    passFromInput.value = `${lat}, ${lng}`;
                    
                    pickupMarker = L.marker(e.latlng, {title: "Pickup"}).addTo(map)
                        .bindPopup("<b>Confirmed Pickup Node</b><br>" + wktPoint).openPopup();
                        
                } else if (!passToInput.value) {
                    passToInput.value = `${lat}, ${lng}`;
                    
                    dropoffMarker = L.marker(e.latlng, {title: "Dropoff"}).addTo(map)
                        .bindPopup("<b>Confirmed Dropoff Node</b><br>" + wktPoint).openPopup();
                        
                    console.log(`Geospatial Pair Locked: Origin=${passFromInput.value} Target=${passToInput.value}`);
                } else {
                    if (pickupMarker) map.removeLayer(pickupMarker);
                    if (dropoffMarker) map.removeLayer(dropoffMarker);
                    pickupMarker = null;
                    dropoffMarker = null;
                    passFromInput.value = '';
                    passToInput.value = '';
                }
            }
        });
    }

    const faqContainer = document.getElementById('faq-target-container');
    if (faqContainer) {
        const mockFaqData = [
            { q: "How do I create an account?", a: "Visit our registration page and fill in your details as either a driver or passenger." },
            { q: "What payment methods are accepted?", a: "We accept bank transfers, digital wallets, and online payment systems." },
            { q: "How is matching calculated?", a: "Our system uses route optimization, time proximity, and safety ratings to match rides." },
            { q: "Can I cancel a ride?", a: "Yes, you can cancel rides up to 30 minutes before departure with reduced fees." }
        ];

        try {
            faqContainer.innerHTML = '';
            
            mockFaqData.forEach(item => {
                const accordionItem = document.createElement('div');
                accordionItem.className = 'accordion-item';
                
                accordionItem.innerHTML = `
                    <button class="accordion-header">
                        <span>${escapeHtml(item.q)}</span>
                        <span class="icon">+</span>
                    </button>
                    <div class="accordion-panel">
                        <p>${escapeHtml(item.a)}</p>
                    </div>
                `;
                faqContainer.appendChild(accordionItem);
            });
            
            bindAccordionLogic();
        } catch (err) {
            faqContainer.innerHTML = `<p style="color: var(--text-muted);">Failed to load FAQs. Please refresh the page.</p>`;
            console.error('FAQ loading error:', err);
        }
    }

    const liquidationItems = document.querySelectorAll('.liquidation-item');
    liquidationItems.forEach(item => {
        item.addEventListener('click', function() {
            liquidationItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });

    function bindAccordionLogic() {
        const headers = document.querySelectorAll('.accordion-header');
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const item = this.parentElement;
                const panel = this.nextElementSibling;
                const icon = this.querySelector('.icon');
                const isActive = item.classList.contains('active');
                
                document.querySelectorAll('.accordion-item').forEach(el => {
                    el.classList.remove('active');
                    const p = el.querySelector('.accordion-panel');
                    if(p) p.style.maxHeight = null;
                    const i = el.querySelector('.icon');
                    if(i) i.textContent = '+';
                });
                
                if (!isActive) {
                    item.classList.add('active');
                    panel.style.maxHeight = panel.scrollHeight + "px";
                    icon.textContent = '−';
                }
            });
        });
    }

    function escapeHtml(text) {
        const map = { 
            '&': '&amp;', 
            '<': '&lt;', 
            '>': '&gt;', 
            '"': '&quot;', 
            "'": '&#039;' 
        };
        return String(text || '').replace(/[&<>'"]/g, m => map[m]);
    }
});