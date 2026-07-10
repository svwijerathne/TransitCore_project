document.addEventListener('DOMContentLoaded', () => {
    
    // Fetch FAQs dynamically from backend ledger connection script
    const faqContainer = document.getElementById('faq-target-container');
    
    fetch('connect.php?action=get_faqs')
        .then(response => {
            if (!response.ok) throw new Error('Network status connection failure');
            return response.json();
        })
        .then(data => {
            if (data.error) {
                faqContainer.innerHTML = `<p style="color: red;">Configuration Error: ${data.error}</p>`;
                return;
            }
            
            // Clear baseline loader markup text string
            faqContainer.innerHTML = '';
            
            // Map table payloads cleanly into interactive layout fragments
            data.forEach(item => {
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
            
            // Initialize event handling triggers immediately after structure injection
            bindAccordionLogic();
        })
        .catch(err => {
            faqContainer.innerHTML = `<p style="color: var(--text-muted);">Failed to pull live ledger configurations. Displaying offline system cache.</p>`;
            console.error('Fetch operations exception:', err);
        });

    // Strategy design interactive presentation tab controls
    const liquidationItems = document.querySelectorAll('.liquidation-item');
    liquidationItems.forEach(item => {
        item.addEventListener('click', function() {
            liquidationItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Encapsulated binder engine for standard accordions
    function bindAccordionLogic() {
        const headers = document.querySelectorAll('.accordion-header');
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const item = this.parentElement;
                const panel = this.nextElementSibling;
                const icon = this.querySelector('.icon');
                const isActive = item.classList.contains('active');
                
                // Collapse all open rows to preserve layout state integrity
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

    // Helper utility to escape strings and defend against XSS vulnerabilities
    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>'"]/g, m => map[m]);
    }
});