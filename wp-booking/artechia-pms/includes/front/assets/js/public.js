/**
 * ArtechIA PMS — Public Booking JS
 *
 * Handles the entire front-end booking flow via fetch() to REST API.
 * Pages: Search → Results → Checkout → Confirmation
 * Also: My Booking detail page.
 */
(function () {
    'use strict';

    const cfg = window.artechiaConfig || {};

    /* ── Utility ──────────────────────────── */

    function api(endpoint, opts = {}) {
        const url = cfg.restBase + endpoint;
        const headers = {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cfg.nonce,
        };
        return fetch(url, {
            method: opts.method || 'GET',
            headers,
            body: opts.body ? JSON.stringify(opts.body) : undefined,
        }).then(r => r.json().then(data => ({ ok: r.ok, status: r.status, data })));
    }

    function qs(sel, root = document) { return root.querySelector(sel); }
    function qsa(sel, root = document) { return root.querySelectorAll(sel); }

    function show(el) { 
        if (el) {
            el.style.display = ''; 
            if (el.classList.contains('artechia-modal-overlay')) {
                document.body.classList.add('artechia-no-scroll');
            }
        }
    }
    function hide(el) { 
        if (el) {
            el.style.display = 'none'; 
            if (el.classList.contains('artechia-modal-overlay')) {
                document.body.classList.remove('artechia-no-scroll');
            }
        }
    }

    function formatMoney(amount) {
        const n = parseFloat(amount) || 0;
        const decimals = parseInt(cfg.decimals !== undefined ? cfg.decimals : 2);
        const decSep = cfg.decimalSeparator || ',';
        const thoSep = cfg.thousandSeparator !== undefined ? cfg.thousandSeparator : '.';
        const pos = cfg.currencyPosition || 'before';
        const sym = cfg.currencySymbol || '$';

        let formatted = n.toFixed(decimals);
        const parts = formatted.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thoSep);
        formatted = parts.join(decSep);
        
        return pos === 'before' ? sym + formatted : formatted + sym;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d)) return dateStr;
        return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function whatsappUrl(bookingCode = '') {
        if (!cfg.whatsapp || !cfg.whatsapp.number) return '';
        const msg = (cfg.whatsapp.message || '').replace('{booking_code}', bookingCode);
        const phone = cfg.whatsapp.number.replace(/[^0-9]/g, '');
        return `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`;
    }

    function getUrlParams() {
        return Object.fromEntries(new URLSearchParams(window.location.search));
    }

    function btnLoading(btn, loading) {
        if (loading) {
            btn.disabled = true;
            btn.classList.add('artechia-btn--loading');
        } else {
            btn.disabled = false;
            btn.classList.remove('artechia-btn--loading');
        }
    }

    function showError(container, msg, requestId = null) {
        if (container) {
            let text = msg;
            if (requestId) {
                text += ` (Solicitud: ${requestId})`;
            }
            container.textContent = text;
            show(container);
        }
    }

    /* ── Search Page ──────────────────────── */

    function initSearch() {
        const form = qs('[data-artechia-action="search"]');
        if (!form) return;

        const datesInput = qs('#artechia-dates');
        const ciHidden = qs('#artechia-checkin');
        const coHidden = qs('#artechia-checkout');

        if (datesInput && typeof flatpickr !== 'undefined') {
            const isMobile = window.innerWidth < 768;
            let calendarHints = {}; // { '2026-03-09': { s: 'full'|'low'|'available', p: 10 } }

            // Fetch calendar hints on load
            if (cfg.propertyId) {
                api('calendar-hints?property_id=' + cfg.propertyId + '&months=4')
                    .then(({ ok, data }) => {
                        if (ok && data.days) {
                            calendarHints = data.days;
                            // Re-render current calendar with hints
                            const fp = datesInput._flatpickr;
                            if (fp) fp.redraw();
                        }
                    })
                    .catch(() => {});
            }

            function applyDayHint(dayElem, dateObj) {
                if (!dateObj) return;
                const ds = dateObj.getFullYear() + '-' +
                    String(dateObj.getMonth() + 1).padStart(2, '0') + '-' +
                    String(dateObj.getDate()).padStart(2, '0');
                const hint = calendarHints[ds];
                if (!hint) return;

                // Remove previous hints
                dayElem.classList.remove('artechia-day--full', 'artechia-day--low', 'artechia-day--promo');
                const existingBadge = dayElem.querySelector('.artechia-day-badge');
                if (existingBadge) existingBadge.remove();

                if (hint.s === 'full') {
                    dayElem.classList.add('artechia-day--full');
                } else if (hint.s === 'low') {
                    dayElem.classList.add('artechia-day--low');
                }

                if (hint.p) {
                    dayElem.classList.add('artechia-day--promo');
                    // Show badge only on first day of a contiguous promo block
                    const prevDate = new Date(dateObj);
                    prevDate.setDate(prevDate.getDate() - 1);
                    const prevDs = prevDate.getFullYear() + '-' +
                        String(prevDate.getMonth() + 1).padStart(2, '0') + '-' +
                        String(prevDate.getDate()).padStart(2, '0');
                    const prevHint = calendarHints[prevDs];
                    if (!prevHint || !prevHint.p) {
                        const badge = document.createElement('span');
                        badge.className = 'artechia-day-badge';
                        badge.textContent = '-' + hint.p + '%';
                        dayElem.style.position = 'relative';
                        dayElem.appendChild(badge);
                    }
                }
            }

            flatpickr(datesInput, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j F',
                locale: {
                    ...flatpickr.l10ns.es,
                    rangeSeparator: ' - '
                },
                disableMobile: true,
                animate: true,
                showMonths: isMobile ? 1 : 2,
                monthSelectorType: 'static',
                minDate: 'today',
                prevArrow: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>',
                nextArrow: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>',
                onDayCreate(datesSelected, dateStr, instance, dayElem) {
                    applyDayHint(dayElem, dayElem.dateObj);
                },
                onReady(selectedDates, dateStr, instance) {
                    instance.calendarContainer.classList.add('artechia-flatpickr');
                    if (!isMobile) instance.calendarContainer.classList.add('artechia-flatpickr--dual');

                    // Add footer with clear button + legend on same row
                    const footer = document.createElement('div');
                    footer.className = 'artechia-flatpickr-footer';
                    
                    // Legend (left)
                    const legend = document.createElement('div');
                    legend.className = 'artechia-flatpickr-legend';

                    // Detect max promo percentage from hints for legend label
                    let maxPct = 0;
                    Object.values(calendarHints).forEach(h => { if (h.p && h.p > maxPct) maxPct = h.p; });
                    const promoLabel = maxPct > 0 ? `Oferta -${maxPct}%` : 'Oferta';

                    legend.innerHTML = `
                        <span class="artechia-legend-item"><span class="artechia-legend-dot artechia-legend-dot--full"></span>Ocupado</span>
                        <span class="artechia-legend-item"><span class="artechia-legend-dot artechia-legend-dot--low"></span>Últimos lugares</span>
                        <span class="artechia-legend-item"><span class="artechia-legend-dot artechia-legend-dot--promo"></span>${promoLabel}</span>
                    `;
                    footer.appendChild(legend);

                    // Clear button (right)
                    const clearBtn = document.createElement('button');
                    clearBtn.type = 'button';
                    clearBtn.className = 'artechia-flatpickr-clear';
                    clearBtn.innerHTML = 'Borrar selección';
                    clearBtn.addEventListener('click', () => {
                        instance.clear();
                        ciHidden.value = '';
                        coHidden.value = '';
                    });
                    footer.appendChild(clearBtn);

                    instance.calendarContainer.appendChild(footer);
                },
                onChange(selectedDates, dateStr, instance) {
                    const nightsLabel = qs('#artechia-nights-count');
                    const searchBtn = qs('#artechia-search-submit');
                    if (selectedDates.length === 2) {
                        try {
                            ciHidden.value = instance.formatDate(selectedDates[0], 'Y-m-d');
                            coHidden.value = instance.formatDate(selectedDates[1], 'Y-m-d');
                            // Night counter
                            const nights = Math.ceil((selectedDates[1] - selectedDates[0]) / 86400000);
                            if (nights < 1) {
                                // Same date selected = 0 nights, clear selection
                                instance.clear();
                                ciHidden.value = '';
                                coHidden.value = '';
                                if (nightsLabel) {
                                    nightsLabel.textContent = 'Seleccioná al menos 1 noche';
                                    nightsLabel.style.display = 'inline-block';
                                    nightsLabel.style.color = '#dc3545';
                                }
                                return;
                            }
                            if (nightsLabel) {
                                const maxStay = parseInt(cfg.maxStay) || 30;
                                if (nights > maxStay) {
                                    nightsLabel.textContent = `La estadía máxima permitida es de ${maxStay} noches`;
                                    nightsLabel.style.display = 'inline-block';
                                    nightsLabel.style.color = '#dc3545';
                                    if (searchBtn) searchBtn.disabled = true;
                                } else {
                                    nightsLabel.textContent = nights + ' noche' + (nights !== 1 ? 's' : '');
                                    nightsLabel.style.display = 'inline-block';
                                    nightsLabel.style.color = '';
                                    if (searchBtn) searchBtn.disabled = false;
                                }
                            }
                        } catch (e) {
                            console.error('Date formatting error', e);
                        }
                    } else {
                        ciHidden.value = '';
                        coHidden.value = '';
                        if (nightsLabel) {
                            nightsLabel.textContent = '';
                            nightsLabel.style.display = 'none';
                            nightsLabel.style.color = '';
                        }
                        if (searchBtn) searchBtn.disabled = false;
                    }
                }
            });
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const params = new URLSearchParams({
                check_in: fd.get('check_in'),
                check_out: fd.get('check_out'),
                adults: fd.get('adults'),
                children: fd.get('children'),
            });

            if (!fd.get('check_in') || !fd.get('check_out')) {
                showError(qs('#artechia-search-error'), 'Seleccioná las fechas de check-in y check-out.');
                return;
            }

            // Max stay validation
            const maxStay = parseInt(cfg.maxStay) || 30;
            const ci = new Date(fd.get('check_in'));
            const co = new Date(fd.get('check_out'));
            const nights = Math.ceil((co - ci) / 86400000);
            if (nights > maxStay) {
                showError(qs('#artechia-search-error'), `La estadía máxima permitida es de ${maxStay} noches.`);
                return;
            }

            if (cfg.resultsUrl) {
                window.location.href = cfg.resultsUrl + '?' + params.toString();
            }
        });
    }

    /* ── Results Page ─────────────────────── */

    function initResults() {
        const grid = qs('#artechia-results-grid');
        if (!grid) return;

        // Fetch calendar hints for alternative dates suggestions
        let resultsHints = {};
        const hintsReady = cfg.propertyId
            ? api('calendar-hints?property_id=' + cfg.propertyId + '&months=4')
                .then(({ ok, data }) => {
                    if (ok && data.days) resultsHints = data.days;
                })
                .catch(() => {})
            : Promise.resolve();

        const params = getUrlParams();
        if (!params.check_in || !params.check_out) {
            hide(qs('#artechia-results-loading'));
            show(qs('#artechia-results-empty'));
            return;
        }

        // Show summary.
        // Populate the search details bar.
        const searchBar = qs('#artechia-results-search-bar');
        if (searchBar) {
            const nights = Math.ceil((new Date(params.check_out) - new Date(params.check_in)) / 86400000);
            const ciEl = qs('#artechia-search-checkin');
            const coEl = qs('#artechia-search-checkout');
            const nightsEl = qs('#artechia-search-nights');
            const guestsEl = qs('#artechia-search-guests');
            if (ciEl) ciEl.textContent = formatDate(params.check_in);
            if (coEl) coEl.textContent = formatDate(params.check_out);
            if (nightsEl) nightsEl.textContent = nights;
            const kids = parseInt(params.children) || 0;
            const guestText = kids > 0 ? `${params.adults} + ${kids}` : `${params.adults}`;
            if (guestsEl) guestsEl.textContent = guestText;
            searchBar.style.display = 'flex';
        }

        const fetchBody = {
            property_id: cfg.propertyId,
            check_in: params.check_in,
            check_out: params.check_out,
            adults: parseInt(params.adults) || 2,
            children: parseInt(params.children) || 0,
        };

        // Add debug param if present in URL (for admins/devs)
        if (params.debug) {
            fetchBody.debug = true;
        }

        // Fetch availability.
        api('availability', {
            method: 'POST',
            body: fetchBody,
        }).then(({ ok, data }) => {
            hide(qs('#artechia-results-loading'));

            if (!ok || data.error) {
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                
                const errorContainer = qs('#artechia-results-error');
                if (data.error === 'NO_RATES') {
                    showError(errorContainer, 'No hay tarifas configuradas para estas fechas. Por favor, seleccioná otras fechas o contactanos.');
                } else if (data.error === 'NO_AVAILABILITY') {
                    show(qs('#artechia-results-empty'));
                    try { hintsReady.then(() => showAlternativeDates(params, resultsHints)); } catch(e) { console.warn('Alt dates error:', e); }
                } else {
                    showError(errorContainer, data.message || 'Error al buscar disponibilidad.', data.request_id);
                }
                return;
            }

            if (!data.room_types || data.room_types.length === 0) {
                show(qs('#artechia-results-empty'));
                return;
            }

            const template = qs('#artechia-room-card-template');
            data.room_types.forEach(rt => {
                const card = template.content.cloneNode(true);
                const cardEl = card.querySelector('.artechia-room-card');

                // Populate.
                const img = card.querySelector('img');
                if (rt.photos && rt.photos.length > 0) {
                    img.src = rt.photos[0];
                    img.alt = rt.name;
                } else {
                    // Hide image container if no photos.
                    card.querySelector('.artechia-room-card__image').style.display = 'none';
                    cardEl.classList.add('artechia-room-card--no-image');
                }

                card.querySelector('.artechia-room-card__name').textContent = rt.name;
                card.querySelector('.artechia-room-card__desc').textContent = rt.description || '';

                const capEl = card.querySelector('[data-field="capacity"]');
                if (capEl) capEl.textContent = `Hasta ${rt.max_occupancy || rt.base_occupancy} huéspedes`;

                const bedsEl = card.querySelector('[data-field="beds"]');
                const bedsContainer = card.querySelector('[data-field="beds-container"]');
                if (rt.bed_config) {
                    if (bedsEl) bedsEl.textContent = rt.bed_config;
                    if (bedsContainer) show(bedsContainer);
                } else {
                    if (bedsContainer) hide(bedsContainer);
                }

                // Amenities.
                const amenitiesEl = card.querySelector('[data-field="amenities"]');
                if (amenitiesEl && rt.amenities_json) {
                    try {
                        const amenities = typeof rt.amenities_json === 'string'
                            ? JSON.parse(rt.amenities_json)
                            : rt.amenities_json;

                        const iconMap = {
                            'wifi': 'wifi',
                            'ac': 'ac',
                            'heating': 'heating',
                            'tv': 'tv',
                            'minibar': 'minibar',
                            'safe': 'safe',
                            'balcony': 'balcony',
                            'pool_view': 'view',
                            'garden_view': 'view',
                            'parking': 'parking',
                            'kitchen': 'kitchen',
                            'jacuzzi': 'jacuzzi',
                            'fireplace': 'fireplace',
                            'bbq': 'bbq',
                            'washer': 'washer'
                        };

                        const labels = {
                            'wifi': 'WiFi', 'ac': 'AA', 'heating': 'Calefacción',
                            'tv': 'TV', 'minibar': 'Minibar', 'safe': 'Caja Fuerte',
                            'balcony': 'Balcón', 'pool_view': 'Vista Piscina', 'garden_view': 'Vista Jardín',
                            'parking': 'Parking', 'kitchen': 'Cocina', 'jacuzzi': 'Jacuzzi',
                            'fireplace': 'Hogar', 'bbq': 'Parrilla', 'washer': 'Lavarropas'
                        };

                        amenities.slice(0, 6).forEach(a => {
                            const tag = document.createElement('span');
                            tag.className = 'artechia-room-card__amenity';
                            
                            const iconId = iconMap[a] || 'amenity';
                            const label = labels[a] || a;

                            tag.innerHTML = `
                                <svg class="artechia-icon" width="14" height="14">
                                    <use href="#icon-${iconId}"/>
                                </svg>
                                <span>${label}</span>
                            `;
                            amenitiesEl.appendChild(tag);
                        });
                    } catch (e) { /* ignore */ }
                }

                // Pricing.
                if (rt.quote) {
                    const q = rt.quote;
                    const totalEl = card.querySelector('[data-field="total"]');
                    const nightsLabel = card.querySelector('[data-field="nights-label"]');
                    const perNight = card.querySelector('[data-field="per-night"]');
                    
                    const promoBadge = card.querySelector('[data-field="promo-badge"]');
                    const priceOrig = card.querySelector('[data-field="price-original"]');
                    const nowLabel = card.querySelector('[data-field="price-now-label"]');

                    totalEl.textContent = formatMoney(q.total);
                    
                    if (q.discount_amount > 0) {
                        if (promoBadge && q.promo_description) {
                            promoBadge.textContent = q.promo_description;
                            show(promoBadge);
                        }
                        if (priceOrig && q.original_subtotal) {
                            priceOrig.textContent = formatMoney(q.original_subtotal);
                            show(priceOrig);
                        }
                        if (nowLabel) show(nowLabel);
                        totalEl.classList.add('artechia-room-card__price--discounted');
                    }

                    const n = q.nights ? q.nights.length : 0;
                    if (nightsLabel) nightsLabel.textContent = `/ ${n} noche${n !== 1 ? 's' : ''}`;
                    if (n > 0 && perNight) {
                        perNight.textContent = formatMoney(q.total / n) + ' / noche';
                        perNight.style.display = 'block';
                    }
                }

                // Book button.
                const bookBtn = card.querySelector('[data-action="book"]');
                bookBtn.dataset.roomTypeId = rt.id || rt.room_type_id;
                bookBtn.dataset.ratePlanId = rt.rate_plan_id || '';
                bookBtn.addEventListener('click', () => handleBook(bookBtn, params));

                // Scarcity badge — show urgency when low availability.
                const avail = parseInt(rt.available) || 0;
                const baseUnits = parseInt(rt.base_units) || 0;
                const scarcityEl = card.querySelector('[data-field="scarcity"]');
                if (scarcityEl && avail > 0 && avail <= 3 && avail < baseUnits) {
                    if (avail === 1) {
                        scarcityEl.innerHTML = '🔥 ¡Última disponible!';
                    } else {
                        scarcityEl.innerHTML = `🔥 ¡Quedan solo ${avail}!`;
                    }
                    show(scarcityEl);
                }

                grid.appendChild(card);
            });
        }).catch(err => {
            hide(qs('#artechia-results-loading'));
            showError(qs('#artechia-results-error'), 'Error al buscar disponibilidad. Intentá de nuevo.');
        });
    }

    function showAlternativeDates(params, hints) {
        const altContainer = qs('#artechia-results-alternatives');
        const altGrid = qs('#artechia-alternatives-grid');
        if (!altContainer || !altGrid) return;

        const nights = Math.ceil((new Date(params.check_out) - new Date(params.check_in)) / 86400000);
        if (nights < 1) return;

        // Helper: format YYYY-MM-DD
        function toDs(date) {
            return date.getFullYear() + '-' +
                String(date.getMonth() + 1).padStart(2, '0') + '-' +
                String(date.getDate()).padStart(2, '0');
        }

        // Helper: add days to a date
        function addDays(date, n) {
            const d = new Date(date);
            d.setDate(d.getDate() + n);
            return d;
        }

        // Check if a date range has no 'full' days in hints
        function isRangeAvailable(checkIn, numNights) {
            for (let i = 0; i < numNights; i++) {
                const ds = toDs(addDays(checkIn, i));
                const hint = hints[ds];
                if (hint && hint.s === 'full') return false;
            }
            return true;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const requestedCI = new Date(params.check_in);
        const alternatives = [];
        const maxSearch = 60; // search up to 60 days forward/backward
        const maxResults = 4;

        // Search forward from the requested check-in
        for (let offset = 1; offset <= maxSearch && alternatives.length < maxResults; offset++) {
            const ci = addDays(requestedCI, offset);
            if (ci < today) continue;
            if (isRangeAvailable(ci, nights)) {
                alternatives.push({ checkIn: ci, checkOut: addDays(ci, nights) });
            }
        }

        // Search backward (before the requested check-in)
        for (let offset = 1; offset <= maxSearch && alternatives.length < maxResults; offset++) {
            const ci = addDays(requestedCI, -offset);
            if (ci < today) break;
            if (isRangeAvailable(ci, nights)) {
                alternatives.push({ checkIn: ci, checkOut: addDays(ci, nights) });
            }
        }

        if (alternatives.length === 0) return;

        // Sort by proximity to original date
        alternatives.sort((a, b) => {
            return Math.abs(a.checkIn - requestedCI) - Math.abs(b.checkIn - requestedCI);
        });

        // Render alternatives
        altGrid.innerHTML = '';
        alternatives.slice(0, maxResults).forEach(alt => {
            const ciStr = toDs(alt.checkIn);
            const coStr = toDs(alt.checkOut);

            const card = document.createElement('a');
            card.className = 'artechia-alt-card';
            card.href = window.location.pathname + '?check_in=' + ciStr + '&check_out=' + coStr +
                '&adults=' + (params.adults || 2) + (params.children ? '&children=' + params.children : '');

            card.innerHTML = `
                <div class="artechia-alt-card__dates">
                    <svg class="artechia-icon" width="16" height="16"><use href="#icon-calendar"/></svg>
                    <span>${formatDate(ciStr)} → ${formatDate(coStr)}</span>
                </div>
                <span class="artechia-alt-card__nights">${nights} noche${nights !== 1 ? 's' : ''}</span>
                <span class="artechia-alt-card__action">Ver disponibilidad →</span>
            `;
            altGrid.appendChild(card);
        });

        show(altContainer);
    }

    function handleBook(btn, params) {
        btnLoading(btn, true);

        api('checkout/start', {
            method: 'POST',
            body: {
                property_id: cfg.propertyId,
                room_type_id: parseInt(btn.dataset.roomTypeId),
                rate_plan_id: btn.dataset.ratePlanId ? parseInt(btn.dataset.ratePlanId) : 0,
                check_in: params.check_in,
                check_out: params.check_out,
                adults: parseInt(params.adults) || 2,
                children: parseInt(params.children) || 0,
            },
        }).then(({ ok, data }) => {
            btnLoading(btn, false);

            if (!ok) {
                showError(qs('#artechia-results-error'), data.message || 'No se pudo iniciar la reserva.', data.request_id);
                return;
            }

            // Redirect to checkout with token.
            const checkoutParams = new URLSearchParams({
                token: data.checkout_token
            });
            window.location.href = cfg.checkoutUrl + '?' + checkoutParams.toString();
        }).catch(() => {
            btnLoading(btn, false);
            showError(qs('#artechia-results-error'), 'Error de conexión. Intentá de nuevo.');
        });
    }

    /* ── Checkout Page ────────────────────── */

    function initCheckout() {
        const form = qs('#artechia-checkout-form');
        if (!form) return;

        const params = getUrlParams();
        if (!params.token) {
            hide(qs('#artechia-checkout-content'));
            show(qs('#artechia-checkout-expired'));
            return;
        }

        // Decode token to show summary (base64 part before the dot).
        let payload = {};
        try {
            payload = JSON.parse(atob(params.token.split('.')[0]));
            
            // Set hidden token.
            qs('#artechia-checkout-token').value = params.token;

            // Start countdown timer from token payload.
            if (payload.expires_at) {
                startTimer(payload.expires_at);
            }

            // Initial render
            renderCheckoutSummary(payload);

            // Fetch extras
            const extrasContainer = qs('#artechia-checkout-extras');
            if (extrasContainer) {
                api(`property/${payload.property_id}/extras`).then(({ ok, data }) => {
                    if (ok && data.length > 0) {
                        renderExtrasSelection(data, payload);
                        show(extrasContainer);
                    }
                });
            }

            // Special requests
            if (!cfg.enableSpecialRequests) {
                const requestsField = qs('#guest-requests')?.closest('.artechia-form-row');
                hide(requestsField);
            }

            // Coupon handling
            const couponSection = qs('.artechia-checkout__coupon');
            const applyBtn = qs('#artechia-apply-coupon');
            const couponInput = qs('#artechia-coupon-code');
            const emailInput = qs('#guest-email');

            if (!cfg.enableCoupons) {
                hide(couponSection);
            } else {
                if (applyBtn && couponInput) {
                    applyBtn.addEventListener('click', () => {
                        renderCheckoutSummary(payload, couponInput.value);
                    });
                }

                // Re-validate if email changes (as some coupons are email-specific)
                if (emailInput) {
                    emailInput.addEventListener('blur', () => {
                        if (couponInput.value) {
                            renderCheckoutSummary(payload, couponInput.value);
                        }
                    });
                }
            }

            // Real-time numeric-only restriction for Phone and DNI
            const phoneInput = qs('#guest-phone');
            const dniInput = qs('#guest-doc-number');
            [phoneInput, dniInput].forEach(el => {
                if (el) {
                    el.addEventListener('input', (e) => {
                        e.target.value = e.target.value.replace(/\D/g, '');
                    });
                }
            });
        } catch (e) {
            // Couldn't decode; summary will be empty.
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = qs('#artechia-checkout-submit');
            btnLoading(submitBtn, true);

            const fd = new FormData(form);

            api('checkout/confirm', {
                method: 'POST',
                body: {
                    checkout_token: fd.get('checkout_token'),
                    coupon_code: qs('#artechia-coupon-code')?.value || '',
                    accept_terms: qs('#artechia-accept-terms')?.checked || false,
                    payment_method: qs('input[name="payment_method"]:checked')?.value || 'mercadopago',
                    extras: payload.extras || {},
                    guest: {
                        first_name: fd.get('guest[first_name]'),
                        last_name: fd.get('guest[last_name]'),
                        email: fd.get('guest[email]'),
                        phone: fd.get('guest[phone]'),
                        document_type: fd.get('guest[document_type]'),
                        document_number: fd.get('guest[document_number]'),
                        special_requests: fd.get('guest[special_requests]'),
                    },
                },
            }).then(({ ok, data }) => {
                btnLoading(submitBtn, false);

                if (!ok) {
                    if (data.error === 'LOCK_EXPIRED') {
                        hide(qs('#artechia-checkout-content'));
                        show(qs('#artechia-checkout-expired'));
                    } else if (data.error === 'GUEST_BLACKLISTED') {
                        showError(qs('#artechia-checkout-error'), 'No se pudo completar la reserva.');
                    } else {
                        showError(qs('#artechia-checkout-error'), data.message || 'Error al crear la reserva.', data.request_id);
                    }
                    return;
                }

                // If Mercado Pago provided a payment URL, redirect there.
                if (data.payment_url) {
                    window.location.href = data.payment_url;
                    return;
                }

                // Otherwise, redirect to confirmation page (e.g. Bank Transfer).
                const confirmParams = new URLSearchParams({
                    code: data.booking_code,
                    token: data.access_token,
                    total: data.grand_total,
                    payment_method: qs('input[name="payment_method"]:checked')?.value || 'mercadopago',
                });
                if (data.deposit_pct !== undefined) confirmParams.set('deposit_pct', data.deposit_pct);
                if (data.deposit_due !== undefined) confirmParams.set('deposit_due', data.deposit_due);

                window.location.href = cfg.confirmationUrl + '?' + confirmParams.toString();
            }).catch(() => {
                btnLoading(submitBtn, false);
                showError(qs('#artechia-checkout-error'), 'Error de conexión.');
            });
        });
    }

    function renderExtrasSelection(extras, payload) {
        const list = qs('#artechia-extras-list');
        if (!list) return;

        list.innerHTML = '';
        extras.forEach(extra => {
            const div = document.createElement('div');
            div.className = 'artechia-extra-item';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = `extra-${extra.id}`;
            checkbox.value = extra.id;
            checkbox.className = 'artechia-extra-checkbox';
            
            // Pre-select if already in payload (e.g. from previous step, though currently we don't pass them)
            if (payload.extras && payload.extras[extra.id]) {
                checkbox.checked = true;
            }

            checkbox.addEventListener('change', () => {
                // Update payload extras from all currently checked checkboxes
                const selectedExtras = {};
                qsa('.artechia-extra-checkbox', list).forEach(cb => {
                    if (cb.checked) {
                        selectedExtras[cb.value] = 1;
                    }
                });
                payload.extras = selectedExtras;
                
                // Re-calculate quote
                const couponCode = qs('#artechia-coupon-code')?.value || '';
                renderCheckoutSummary(payload, couponCode);
            });

            const label = document.createElement('label');
            label.htmlFor = `extra-${extra.id}`;
            label.className = 'artechia-extra-label';

            const priceText = extra.price_type === 'per_stay' 
                ? `${formatMoney(extra.price)} (total)` 
                : `${formatMoney(extra.price)} / noche`; // per_night

            label.innerHTML = `
                <span class="artechia-extra-name">${extra.name}</span>
                <span class="artechia-extra-price">${priceText}</span>
            `;
            if (extra.description) {
                const desc = document.createElement('div');
                desc.className = 'artechia-extra-desc';
                desc.textContent = extra.description;
                label.appendChild(desc);
            }

            div.appendChild(checkbox);
            div.appendChild(label);
            list.appendChild(div);
        });
    }

    function renderCheckoutSummary(payload, couponCode = '') {
        const roomEl = qs('#artechia-summary-room');
        const datesEl = qs('#artechia-summary-dates');
        const guestsEl = qs('#artechia-summary-guests');
        const statusEl = qs('#artechia-coupon-status');
        const applyBtn = qs('#artechia-apply-coupon');

        const mpRadio = qs('input[name="payment_method"][value="mercadopago"]');
        const bankRadio = qs('input[name="payment_method"][value="bank_transfer"]');
        
        // Terms modal logic
        const termsModal = qs('#artechia-terms-modal');
        const openTerms = qs('#artechia-open-terms');
        const closeTerms = qs('#artechia-close-terms');
        const closeTermsBtn = qs('#artechia-close-terms-btn');

        if (openTerms && termsModal) {
            openTerms.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Update terms content if configured
                const termsBody = termsModal.querySelector('#artechia-terms-content') || termsModal.querySelector('.artechia-terms-full-text');
                if (termsBody && cfg.termsConditions) {
                    if (cfg.termsConditionsType === 'html') {
                        termsBody.innerHTML = cfg.termsConditions;
                    } else {
                        termsBody.style.whiteSpace = 'pre-wrap';
                        termsBody.textContent = cfg.termsConditions;
                    }
                }
                
                show(termsModal);
            });
        }

        [closeTerms, closeTermsBtn].forEach(btn => {
            if (btn && termsModal) {
                btn.addEventListener('click', () => hide(termsModal));
            }
        });

        if (termsModal) {
            termsModal.addEventListener('click', (e) => {
                if (e.target === termsModal) hide(termsModal);
            });
        }

        if (statusEl) hide(statusEl);
        if (applyBtn && couponCode) btnLoading(applyBtn, true);

        if (datesEl) {
            datesEl.innerHTML = `
                <div class="artechia-summary__detail">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3v4M9 3v4M4 11h16M5 7h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2z"/></svg>
                    <span>${formatDate(payload.check_in)} → ${formatDate(payload.check_out)}</span>
                </div>
            `;
        }

        const nights = Math.ceil((new Date(payload.check_out + 'T00:00:00') - new Date(payload.check_in + 'T00:00:00')) / 86400000);
        if (roomEl) {
            const nightsText = `${nights} noche${nights !== 1 ? 's' : ''}`;
            roomEl.innerHTML = `
                <div class="artechia-summary__detail">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v11a2 2 0 002 2h14a2 2 0 002-2V7M3 7l9-4 9 4"/><path d="M9 22V12h6v10"/></svg>
                    <span>${payload.room_unit_name || ''} · ${nightsText}</span>
                </div>
            `;
        }

        if (guestsEl) {
            let text = `${payload.adults} adulto${payload.adults > 1 ? 's' : ''}`;
            if (payload.children > 0) text += ` · ${payload.children} niño${payload.children > 1 ? 's' : ''}`;
            guestsEl.innerHTML = `
                <div class="artechia-summary__detail">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    <span>${text}</span>
                </div>
            `;
        }

        // We need to fetch the quote to show pricing.
        api('quote', {
            method: 'POST',
            body: {
                property_id: cfg.propertyId,
                room_type_id: payload.room_type_id,
                rate_plan_id: payload.rate_plan_id,
                check_in: payload.check_in,
                check_out: payload.check_out,
                adults: payload.adults,
                children: payload.children,
                extras: payload.extras || {},
                coupon_code: couponCode,
                guest_email: qs('#guest-email')?.value || '',
            },
        }).then(({ ok, data }) => {
            if (applyBtn) btnLoading(applyBtn, false);
            if (!ok) return;

            const subtotalEl = qs('#artechia-summary-subtotal');
            const extrasEl = qs('#artechia-summary-extras');
            const taxesEl = qs('#artechia-summary-taxes');
            const discountEl = qs('#artechia-summary-discount');
            const totalEl = qs('#artechia-summary-total');
            const policyEl = qs('#artechia-summary-policy');

            const totals = data.totals;

            // Handle coupon validation feedback
            if (statusEl && couponCode) {
                show(statusEl);
                if (data.validation && data.validation.coupon_error) {
                    statusEl.className = 'artechia-coupon-status artechia-coupon-status--error';
                    const errorMap = {
                        'INVALID_CODE': 'Código inválido.',
                        'EXPIRED': 'El cupón ha expirado.',
                        'NOT_STARTED': 'El cupón aún no es válido.',
                        'MIN_NIGHTS_NOT_MET': 'No cumple con el mínimo de noches.',
                        'LIMIT_REACHED': 'Cupón agotado.',
                        'USER_LIMIT_REACHED': 'Ya usaste este cupón.',
                        'ROOM_NOT_ELIGIBLE': 'No válido para este tipo de habitación.',
                        'RATE_NOT_ELIGIBLE': 'No válido para esta tarifa.',
                    };
                    statusEl.textContent = errorMap[data.validation.coupon_error] || 'Error al aplicar cupón.';
                } else if (data.coupon) {
                    statusEl.className = 'artechia-coupon-status artechia-coupon-status--success';
                    statusEl.textContent = '¡Cupón aplicado!';
                }
            }

            if (subtotalEl) subtotalEl.innerHTML = `<span>Habitación</span><span>${formatMoney(totals.subtotal_base)}</span>`;
            
            if (extrasEl) {
                if (totals.extras_total > 0) {
                    show(extrasEl);
                    extrasEl.innerHTML = `<span>Extras</span><span>${formatMoney(totals.extras_total)}</span>`;
                } else {
                    hide(extrasEl);
                    extrasEl.innerHTML = '';
                }
            }

            if (taxesEl && totals.taxes_total > 0) taxesEl.innerHTML = `<span>Impuestos</span><span>${formatMoney(totals.taxes_total)}</span>`;
            if (discountEl) {
                if (totals.discount_amount > 0) {
                    show(discountEl);
                    // Build discount label with coupon code or promo name
                    let discountLabel = 'Descuento';
                    if (data.coupon && data.coupon.code) {
                        discountLabel = `Cupón <strong>${data.coupon.code}</strong>`;
                    } else if (totals.promo_description) {
                        discountLabel = totals.promo_description;
                    }
                    
                    // Build detail text (type of discount)
                    let detailText = '';
                    if (data.coupon) {
                        const typeMap = { 'percent': `${data.coupon.value}% de descuento`, 'fixed': `$${formatMoney(data.coupon.value)} de descuento`, 'free_night': 'Noche gratis', 'x_for_y': `Pague ${data.coupon.value}` };
                        detailText = typeMap[data.coupon.type] || '';
                    }
                    
                    discountEl.innerHTML = `<span>${discountLabel}${detailText ? ` <small style="color:#64748b; font-weight:400;">(${detailText})</small>` : ''}</span><span style="color:#16a34a; font-weight:600;">-${formatMoney(Math.abs(totals.discount_amount))}</span>`;
                } else {
                    hide(discountEl);
                }
            }
            const depositPercent = totals.deposit_pct !== undefined ? totals.deposit_pct : (cfg.depositPercent || 100);
            const depositEl = qs('#artechia-summary-deposit');

             if (depositPercent > 0 && depositPercent < 100) {
                const depositAmount = (totals.total * depositPercent) / 100;

                 let depositLine = qs('#artechia-summary-deposit');
                 if (!depositLine) {
                    depositLine = document.createElement('div');
                    depositLine.className = 'artechia-summary__line';
                    depositLine.id = 'artechia-summary-deposit';
                    if (totalEl && totalEl.parentNode) {
                        totalEl.parentNode.insertBefore(depositLine, totalEl);
                    }
                 }
                 if (depositLine) {
                    depositLine.innerHTML = `<span>A pagar ahora (${depositPercent}%)</span><span class="artechia-price-highlight">${formatMoney(depositAmount)}</span>`;
                    show(depositLine);
                 }
            } else {
                if (depositEl) hide(depositEl);
            }

            // Remove balance line if it exists
            const existingBalance = qs('#artechia-summary-balance');
            if (existingBalance) existingBalance.remove();

            if (totalEl) totalEl.innerHTML = `<span>Total</span><span>${formatMoney(totals.total)}</span>`;

            // Cancellation Policy
            if (policyEl && data.policy) {
                const p = data.policy;
                if (p.is_refundable) {
                    const dateObj = new Date(p.deadline_date + 'T23:59:59');
                    const options = { day: 'numeric', month: 'long' };
                    const formattedDate = dateObj.toLocaleDateString('es-AR', options);
                    
                    let penaltyText = '';
                    if (p.penalty_type === '100') penaltyText = 'el 100% del total';
                    else if (p.penalty_type === '50') penaltyText = 'el 50% del total';
                    else if (p.penalty_type === '1_night') penaltyText = 'el costo de 1 noche';
                    
                    let html = `<div class="artechia-policy artechia-policy--refundable">Cancelación gratuita hasta el <strong>${formattedDate}</strong>.`;
                    if (penaltyText) {
                        html += ` Pasado ese plazo, se cobrará ${penaltyText}.`;
                    }
                    html += `</div>`;
                    policyEl.innerHTML = html;
                } else {
                    policyEl.innerHTML = `<div class="artechia-policy artechia-policy--non-refundable">Tarifa no reembolsable</div>`;
                }
            }
        });
    }

    function startTimer(expiresAt) {
        const timerText = qs('#artechia-timer-text');
        if (!timerText) return;

        const expiry = new Date(expiresAt).getTime();

        function tick() {
            const remaining = expiry - Date.now();
            if (remaining <= 0) {
                timerText.textContent = 'Tu reserva temporal ha expirado';
                hide(qs('#artechia-checkout-content'));
                show(qs('#artechia-checkout-expired'));
                return;
            }

            const mins = Math.floor(remaining / 60000);
            const secs = Math.floor((remaining % 60000) / 1000);
            timerText.textContent = `Tu habitación está reservada por ${mins}:${secs.toString().padStart(2, '0')}`;
            requestAnimationFrame(tick);
        }

        tick();
    }

    /* ── Confirmation Page ────────────────── */

    function initConfirmation() {
        const container = qs('#artechia-confirmation');
        if (!container) return;

        const params = getUrlParams();
        if (!params.code) return;

        hide(qs('#artechia-confirmation-loading'));
        show(qs('#artechia-confirmation-content'));

        qs('#artechia-confirm-code').textContent = params.code;
        qs('#artechia-confirm-total').textContent = formatMoney(params.total || 0);

        const manageLink = qs('#artechia-confirm-manage');
        if (manageLink && cfg.myBookingUrl) {
            manageLink.href = cfg.myBookingUrl + '?code=' + params.code + '&token=' + params.token;
        }

        // Bank transfer details are shown on the My Booking page only.
        const bankDetails = qs('#artechia-bank-details');
        const bankWhatsappMsg = qs('#artechia-bank-whatsapp-msg');
        if (bankDetails) hide(bankDetails);
        if (bankWhatsappMsg) hide(bankWhatsappMsg);
    }

    /* ── My Booking Page ──────────────────── */

    function initMyBooking() {
        const container = qs('#artechia-my-booking');
        if (!container) return;

        const params = getUrlParams();
        if (!params.code || !params.token) {
            hide(qs('#artechia-mybooking-loading'));
            show(qs('#artechia-mybooking-notfound'));
            return;
        }

        api(`booking/${params.code}?token=${encodeURIComponent(params.token)}`).then(({ ok, data }) => {
            hide(qs('#artechia-mybooking-loading'));

            if (!ok) {
                show(qs('#artechia-mybooking-notfound'));
                return;
            }

            const booking = data;
            renderMyBooking(booking);

            // Post-payment handling (Mercado Pago redirect)
            const query = getUrlParams();
            const isReturn = query.payment_id || query.status === 'approved' || query.merchant_order_id;
            
            if (isReturn && booking.status === 'hold') {
                startStatusPolling(params.code, params.token);
            }
        }).catch(err => {
            hide(qs('#artechia-mybooking-loading'));
            show(qs('#artechia-mybooking-notfound'));
        });
    }

    function renderMyBooking(data) {
        show(qs('#artechia-mybooking-content'));

        // Status badge.
        const statusEl = qs('#artechia-mybooking-status');
        if (statusEl) {
            const statusMap = {
                'pending': 'Pendiente',
                'hold': 'En Espera de Pago',
                'confirmed': 'Confirmada',
                'deposit_paid': 'Seña Pagada',
                'paid': 'Pagada',
                'cancelled': 'Cancelada',
                'expired': 'Expirada',
                'checked_in': 'Checked-in',
                'checked_out': 'Reserva Finalizada'
            };
            const statusKey = data.status || 'pending';
            statusEl.textContent = (statusMap[statusKey] || statusKey).toUpperCase();
            statusEl.className = 'artechia-badge artechia-badge--' + statusKey;
        }

        // Details.
        if (data.property) {
            setText('#artechia-mybooking-property', data.property);
            const propRow = qs('#artechia-mybooking-property-row');
            if (propRow) show(propRow);
        }
        setText('#artechia-mybooking-code', data.booking_code);
        setText('#artechia-mybooking-checkin', formatDate(data.check_in));
        setText('#artechia-mybooking-checkout', formatDate(data.check_out));
        setText('#artechia-mybooking-nights', data.nights + (data.nights === 1 ? ' noche' : ' noches'));
        let guestText = `${data.adults} adulto${data.adults > 1 ? 's' : ''}`;
        if (data.children > 0) guestText += `, ${data.children} niño${data.children > 1 ? 's' : ''}`;
        setText('#artechia-mybooking-guests', guestText);

        // Rooms.
        const roomsEl = qs('#artechia-mybooking-rooms');
        if (roomsEl && data.rooms) {
            roomsEl.textContent = data.rooms.map(r => {
                let text = r.room_type;
                if (r.room_unit) text += ' — ' + r.room_unit;
                return text;
            }).join(', ');
        }

        // Extras (List).
        if (data.extras && data.extras.length > 0) {
            show(qs('#artechia-mybooking-extras-card'));
            const extrasListEl = qs('#artechia-mybooking-extras'); 
            if (extrasListEl) {
                extrasListEl.innerHTML = '';
                data.extras.forEach(e => {
                    const div = document.createElement('div');
                    div.innerHTML = `${e.name} ×${e.quantity} — ${formatMoney(e.total)}`;
                    extrasListEl.appendChild(div);
                });
            }
        } else {
            hide(qs('#artechia-mybooking-extras-card'));
        }

        // Special requests.
        if (data.special_requests) {
            show(qs('#artechia-mybooking-requests-card'));
            setText('#artechia-mybooking-requests', data.special_requests);
        } else {
            hide(qs('#artechia-mybooking-requests-card'));
        }

        // Pricing lines (Payment Summary).
        setText('#artechia-mybooking-subtotal', formatMoney(data.subtotal));
        const nightsLabel = qs('#artechia-mybooking-subtotal-nights');
        if (nightsLabel && data.nights) {
            nightsLabel.textContent = ` (${data.nights} ${data.nights === 1 ? 'noche' : 'noches'})`;
        }
        
        // Extras (Total in Sidebar).
        const sidebarExtras = qs('#artechia-mybooking-extras-total');
        if (sidebarExtras) {
            const label = sidebarExtras.previousElementSibling;
            if (data.extras_total > 0) {
                sidebarExtras.textContent = formatMoney(data.extras_total);
                show(sidebarExtras); if (label) show(label);
            } else {
                hide(sidebarExtras); if (label) hide(label);
            }
        }

        // Taxes.
        const sidebarTaxes = qs('#artechia-mybooking-taxes-total');
        if (sidebarTaxes) {
            const label = sidebarTaxes.previousElementSibling;
            if (data.taxes_total > 0) {
                sidebarTaxes.textContent = formatMoney(data.taxes_total);
                show(sidebarTaxes); if (label) show(label);
            } else {
                hide(sidebarTaxes); if (label) hide(label);
            }
        }
        // Discount / Coupon.
        const discountLine = qs('#artechia-mybooking-discount-total');
        const discountLabel = qs('#artechia-mybooking-discount-label');
        if (discountLine) {
            if (parseFloat(data.discount_total || 0) > 0) {
                // Build type description
                let typeDesc = '';
                if (data.coupon_type && data.coupon_value) {
                    const typeMap = { 'percent': `${data.coupon_value}% de desc.`, 'fixed': `$${formatMoney(data.coupon_value)} de desc.`, 'free_night': 'Noche gratis' };
                    typeDesc = typeMap[data.coupon_type] || '';
                }
                
                if (discountLabel) {
                    let labelHtml = 'Descuento';
                    if (data.coupon_code) {
                        labelHtml = `<span style="display:inline-flex; align-items:center; gap:6px;">🏷️ <span style="background:#f3f0ff; color:#7c3aed; padding:2px 8px; border-radius:12px; font-size:12px; font-weight:700; border:1px solid #e0d5ff;">${data.coupon_code.toUpperCase()}</span>${typeDesc ? `<span style="font-size:11px; color:#64748b; font-weight:400;">${typeDesc}</span>` : ''}</span>`;
                    }
                    discountLabel.innerHTML = labelHtml;
                    discountLabel.style.display = '';
                }
                discountLine.textContent = '-' + formatMoney(data.discount_total);
                discountLine.style.color = '#16a34a';
                discountLine.style.fontWeight = '600';
                show(discountLine);
            } else {
                if (discountLabel) hide(discountLabel);
                hide(discountLine);
            }
        }

        setText('#artechia-mybooking-total', formatMoney(data.grand_total));
        const paidVal = parseFloat(data.amount_paid || 0);
        const paidLabel = qs('#artechia-mybooking-paid-label');
        const paidEl = qs('#artechia-mybooking-paid');
        const balanceLabel = qs('#artechia-mybooking-balance-label');
        const balanceEl = qs('#artechia-mybooking-balance');

        if (paidVal > 0) {
            if (paidLabel) show(paidLabel);
            if (paidEl) {
                show(paidEl);
                paidEl.textContent = formatMoney(data.amount_paid);
            }
        } else {
            if (paidLabel) hide(paidLabel);
            if (paidEl) hide(paidEl);
        }

        const balanceVal = parseFloat(data.balance_due || 0);
        const grandTotal = parseFloat(data.grand_total || 0);
        
        // Hide "Restante a pagar" if nothing was paid yet (i.e. balance equals total)
        const isFullBalance = Math.abs(balanceVal - grandTotal) < 0.01;
        
        if (balanceVal > 0 && !isFullBalance) {
            if (balanceLabel) {
                show(balanceLabel);
                balanceLabel.textContent = 'Restante a pagar:';
            }
            if (balanceEl) {
                show(balanceEl);
                balanceEl.textContent = formatMoney(data.balance_due);
            }
        } else {
            if (balanceLabel) hide(balanceLabel);
            if (balanceEl) hide(balanceEl);
        }

        // Guest info.
        if (data.guest) {
            setText('#artechia-mybooking-guestname', data.guest.first_name + ' ' + data.guest.last_name);
            setText('#artechia-mybooking-guestemail', data.guest.email);
            setText('#artechia-mybooking-guestphone', data.guest.phone);
        }

        // WhatsApp link in My Booking
        const waContainer = qs('#artechia-mybooking-whatsapp-container');
        const waLink = qs('#artechia-mybooking-whatsapp-link');
        const waUrl = data.whatsapp_url || whatsappUrl(data.booking_code);
        if (waContainer && waLink && waUrl) {
            waLink.href = waUrl;
            show(waContainer);
        }

        // Cancellation Policy.
        if (data.cancellation_policy) {
            try {
                const cp = typeof data.cancellation_policy === 'string' ? JSON.parse(data.cancellation_policy) : data.cancellation_policy;
                const policyEl = qs('#artechia-mybooking-policy');
                if (policyEl) {
                    if (cp.is_refundable) {
                        let penaltyText = '';
                        if (cp.penalty_type === '100') penaltyText = 'el 100% del total';
                        else if (cp.penalty_type === '50') penaltyText = 'el 50% del total';
                        else if (cp.penalty_type === '1_night') penaltyText = 'el costo de 1 noche';

                        const deadlineDate = new Date(data.check_in + 'T23:59:59');
                        deadlineDate.setDate(deadlineDate.getDate() - cp.deadline_days);
                        const options = { day: 'numeric', month: 'long' };
                        const formattedDate = deadlineDate.toLocaleDateString('es-AR', options);

                        let html = `<div class="artechia-policy artechia-policy--refundable">Cancelación gratuita hasta el <strong>${formattedDate}</strong>.`;
                        if (penaltyText) {
                            html += ` Pasado ese plazo, se cobrará ${penaltyText}.`;
                        }
                        html += `</div>`;
                        policyEl.innerHTML = html;
                    } else {
                        policyEl.innerHTML = `<div class="artechia-policy artechia-policy--non-refundable">Tarifa no reembolsable</div>`;
                    }
                }
            } catch(e) {
                console.error('Error parsing policy', e);
            }
        }

        // Payment Method & Bank Data.
        const pmEl = qs('#artechia-mybooking-payment-method');
        if (pmEl && data.payment_method) {
            const methods = {
                'mercadopago': 'Mercado Pago',
                'bank_transfer': 'Transferencia Bancaria'
            };
            pmEl.textContent = methods[data.payment_method] || data.payment_method;
        }

        const bankCard2 = qs('#artechia-mybooking-bank-details');
        const whatsappCard2 = qs('#artechia-mybooking-bank-whatsapp');
        
        if (data.payment_method === 'bank_transfer') {
            const displayMode2 = cfg.bankTransfer ? (cfg.bankTransfer.displayMode || 'details') : 'details';
            
            // Determine amount to pay
            let amountToPay = data.balance_due;
            let amountText = formatMoney(amountToPay);
            let isDeposit = false;
            if (data.amount_paid === 0 && data.deposit_pct > 0 && data.deposit_pct < 100) {
                amountToPay = data.deposit_due;
                amountText = formatMoney(amountToPay) + ` (${data.deposit_pct}% de seña)`;
                isDeposit = true;
            }

            // If nothing to pay, hide bank cards
            if (amountToPay <= 0) {
                if (bankCard2) hide(bankCard2);
                if (whatsappCard2) hide(whatsappCard2);
            } else
            
            if (displayMode2 === 'details') {
                if (whatsappCard2) hide(whatsappCard2);
                if (bankCard2) {
                    bankCard2.style.display = 'block';
                    const bd = cfg.bankTransfer;
                    const setVal = (id, val) => {
                        const el = qs(id);
                        if (el) el.textContent = val || '—';
                    };

                    // If they are only transferring the deposit, change label
                    const amountLabelEl = qs('#artechia-mybooking-bank-amount-label');
                    if (amountLabelEl) {
                        amountLabelEl.textContent = isDeposit 
                            ? 'Monto a transferir para seña:' 
                            : 'Monto a transferir:';
                    }

                    const amtEl = qs('#artechia-mybooking-bank-amount');
                    if (amtEl) amtEl.textContent = amountText;

                    setVal('#artechia-mybooking-bank-name', bd.bank);
                    setVal('#artechia-mybooking-bank-holder', bd.holder);
                    setVal('#artechia-mybooking-bank-cbu', bd.cbu);
                    setVal('#artechia-mybooking-bank-alias', bd.alias);
                    setVal('#artechia-mybooking-bank-cuit', bd.cuit);
                    
                    // Add WhatsApp link for receipt to bank note
                    const waUrl = data.whatsapp_url || (cfg.bankTransfer && cfg.bankTransfer.whatsappPhone ? `https://wa.me/${cfg.bankTransfer.whatsappPhone}` : '');
                    const bankNote = qs('#artechia-mybooking-bank-note');
                    if (bankNote && waUrl) {
                        bankNote.innerHTML = `Una vez realizada la transferencia, por favor envíe el comprobante a nuestro WhatsApp: <a href="${waUrl}" target="_blank" style="color:#25D366; font-weight:bold; text-decoration:none;">Enviar comprobante</a>`;
                    }
                }
            } else {
                if (bankCard2) hide(bankCard2);
                if (whatsappCard2) {
                    whatsappCard2.style.display = 'block';
                    
                    const amtEl = qs('#artechia-mybooking-whatsapp-amount');
                    if (amtEl) amtEl.textContent = amountText;
                    
                    const phone = cfg.bankTransfer ? (cfg.bankTransfer.whatsappPhone || '') : '';
                    const msg = encodeURIComponent(`Hola, me gustaría confirmar realizar el pago para mi reserva con código: ${data.booking_code}. El monto a transferir es de ${formatMoney(amountToPay)}.`);
                    const btn = qs('#artechia-mybooking-whatsapp-btn');
                    if (btn && phone) {
                        btn.href = `https://wa.me/${phone}?text=${msg}`;
                    } else if (btn) {
                        // Fallback if no phone configured
                        btn.href = '#';
                        btn.onclick = (e) => { e.preventDefault(); alert('El número de WhatsApp no está configurado.'); };
                    }
                }
            }
        } else {
            if (bankCard2) hide(bankCard2);
            if (whatsappCard2) hide(whatsappCard2);
        }
    }

    function startStatusPolling(code, token) {
        // Find whichever alert is available (My Booking or Confirmation page)
        const alert = qs('#artechia-mybooking-confirmation-alert') || qs('#artechia-confirmation-post-payment-alert');
        const title = qs('#artechia-confirm-alert-title') || qs('#artechia-post-confirm-alert-title');
        const msg = qs('#artechia-confirm-alert-msg') || qs('#artechia-post-confirm-alert-msg');
        const loader = qs('#artechia-confirm-alert-loader') || qs('#artechia-post-confirm-alert-loader');

        if (!alert) return;

        alert.className = 'artechia-alert artechia-alert--info';
        title.textContent = 'Procesando pago...';
        msg.textContent = 'Estamos verificando el estado de tu pago con Mercado Pago. No cierres esta ventana.';
        show(alert);
        show(loader);

        // Hide redundant elements immediately while processing
        hide(qs('#artechia-confirmation-header'));
        hide(qs('#artechia-confirmation-next-steps'));
        show(qs('#artechia-confirmation-main-icon'));

        let attempts = 0;
        const maxAttempts = 20; // 1 minute approx
        const params = getUrlParams();
        const paymentId = params.payment_id;

        const poll = () => {
            attempts++;
            
            // If we have a payment_id (returned from MP), we can proactively verify it.
            // This is crucial for local environments where webhooks never arrive.
            const endpoint = (paymentId && attempts === 1) 
                ? `booking/${code}/verify-payment?token=${encodeURIComponent(token)}&payment_id=${paymentId}`
                : `booking/${code}?token=${encodeURIComponent(token)}`;

            const method = (paymentId && attempts === 1) ? 'POST' : 'GET';

            api(endpoint, { method }).then(({ ok, data }) => {
                if (ok) {
                    const status = data.status || 'hold';
                    
                    if (status !== 'hold' && status !== 'expired') {
                        // Success!
                        if (typeof renderMyBooking === 'function' && qs('#artechia-mybooking-content')) {
                            renderMyBooking(data);
                        }
                        if (typeof renderConfirmationDetails === 'function' && qs('#artechia-confirmation-content')) {
                            renderConfirmationDetails(data);
                        }

                        alert.className = 'artechia-alert artechia-alert--success';
                        // Use H2 for title and larger text for msg as per updated template
                        if (title.tagName === 'H2' || title.id === 'artechia-post-confirm-alert-title') {
                            title.textContent = '¡Pago confirmado!';
                        } else {
                            title.textContent = '¡Pago confirmado!';
                        }
                        msg.textContent = 'Tu reserva ha sido confirmada con éxito. Acabamos de enviarte los detalles a tu email.';
                        
                        // Hide redundant elements
                        hide(qs('#artechia-confirmation-header'));
                        hide(qs('#artechia-confirmation-next-steps'));
                        show(qs('#artechia-confirmation-main-icon'));

                        hide(loader);
                        return;
                    }
                }

                if (attempts < maxAttempts) {
                    setTimeout(poll, 3000);
                } else {
                    alert.className = 'artechia-alert artechia-alert--warning';
                    title.textContent = 'El pago se está demorando';
                    msg.textContent = 'Mercado Pago aún no nos ha confirmado tu pago. Podrás ver el estado actualizado en unos minutos refrescando esta página.';
                    hide(loader);
                }
            });
        };

        // Start polling. If we have paymentId, we start immediately.
        setTimeout(poll, paymentId ? 500 : 2000);
    }

    function setText(sel, text) {
        const el = qs(sel);
        if (el) el.textContent = text;
    }

    /* ── Init ─────────────────────────────── */

    document.addEventListener('DOMContentLoaded', () => {
        initSearch();
        initResults();
        initCheckout();
        initConfirmation();
        initMyBooking();
        initFindBooking();
    });

    /* ── Confirmation Page ────────────────── */

    function initConfirmation() {
        const params = getUrlParams();
        const container = qs('#artechia-confirmation');
        if (!container || !params.code) return;

        const loading = qs('#artechia-confirmation-loading');
        const content = qs('#artechia-confirmation-content');

        // Initial setup
        qs('#artechia-confirm-code').textContent = params.code;
        qs('#artechia-confirm-total').textContent = formatMoney(params.total || 0);
        
        const manageBtn = qs('#artechia-confirm-manage');
        if (manageBtn) {
            manageBtn.href = cfg.myBookingUrl + '?code=' + params.code + '&token=' + encodeURIComponent(params.token || '');
        }

        // Fetch full booking data to show more details
        api(`booking/${params.code}?token=${encodeURIComponent(params.token || '')}`).then(({ ok, data }) => {
            hide(loading);
            if (ok) {
                renderConfirmationDetails(data);
                
                // If returning from payment and still on hold, start polling/verifying
                if (params.is_return && data.status === 'hold') {
                    startStatusPolling(params.code, params.token);
                }
            } else {
                show(content); // Show at least basic info from URL
            }
        }).catch(() => {
            hide(loading);
            show(content);
        });
    }

    function renderConfirmationDetails(data) {
        show(qs('#artechia-confirmation-content'));
        
        // Update status badge in confirmation card
        const statusBadge = qs('#artechia-confirmation-content .artechia-badge');
        if (statusBadge) {
            const statusMap = {
                'pending': 'Pendiente',
                'hold': 'Pago en proceso',
                'confirmed': 'Confirmada',
                'paid': 'Pagada',
                'deposit_paid': 'Seña Pagada',
                'cancelled': 'Cancelada',
                'expired': 'Expirada'
            };
            statusBadge.textContent = (statusMap[data.status] || data.status || 'Pendiente').toUpperCase();
            statusBadge.className = 'artechia-confirmation__value artechia-badge artechia-badge--' + (data.status || 'pending');
        }

        // If confirmed/paid, hide redundant "Reserva recibida" header and next steps
        if (data.status !== 'hold' && data.status !== 'pending' && data.status !== 'expired') {
            hide(qs('#artechia-confirmation-header'));
            hide(qs('#artechia-confirmation-next-steps'));
            show(qs('#artechia-confirmation-main-icon'));
        }

        // The bank details HTML in confirmation view was removed so we don't need to try to render it here anymore.
    }

    /* ── Find Booking ─────────────────────── */

    function initFindBooking() {
        const form = qs('#artechia-find-booking-form');
        if (!form) return;

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = qs('#artechia-find-booking-submit');
            const errorEl = qs('#artechia-find-booking-error');
            
            hide(errorEl);
            btnLoading(submitBtn, true);

            const fd = new FormData(form);
            const body = {
                code: fd.get('code'),
                email: fd.get('email'),
            };

            api('booking/find', {
                method: 'POST',
                body: body
            }).then(({ ok, data }) => {
                btnLoading(submitBtn, false);

                if (!ok) {
                    showError(errorEl, data.message || 'No se pudo encontrar la reserva.');
                    return;
                }

                if (data.success && data.access_token) {
                    // Redirect to My Booking portal
                    const params = new URLSearchParams({
                        code: data.booking_code,
                        token: data.access_token
                    });
                    window.location.href = cfg.myBookingUrl + '?' + params.toString();
                } else {
                    showError(errorEl, 'Respuesta inesperada del servidor.');
                }
            }).catch(() => {
                btnLoading(submitBtn, false);
                showError(errorEl, 'Error de conexión. Intentá de nuevo.');
            });
        });
    }

})();
