
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('artechia-calendar-app');
    if (!container) return;

    const API_ROOT = artechia_pms_vars.api_root + 'artechia/v1/admin/'; 
    const NONCE    = artechia_pms_vars.nonce;
    const PROPERTY_ID = artechia_pms_vars.property_id || 1; 

    // Helper: get today's date as YYYY-MM-DD in LOCAL timezone (not UTC)
    function localToday() {
        const now = new Date();
        return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    }

    let startDate = localToday();
    let days      = 30;
    let calendarData = null;
    let cache = { data: null, expire: 0 };

    // Constants for rendering
    const ROW_HEIGHT = 50;
    const COL_WIDTH = 60;

    // Elements
    const gridContainer = document.getElementById('ac-grid-container');
    const sidebar       = document.getElementById('ac-sidebar');
    const modal         = document.getElementById('ac-booking-modal');
    const modalContent  = document.getElementById('ac-modal-body');
    const closeBtn      = document.querySelector('.ac-close');
    const searchInput   = document.querySelector('.calendar-search-input');
    const filterType    = document.getElementById('ac-filter-type');
    const filterStatus  = document.getElementById('ac-filter-status');

    // Controls
    document.getElementById('ac-prev').addEventListener('click', () => shiftDate(-7));
    document.getElementById('ac-next').addEventListener('click', () => shiftDate(7));
    document.getElementById('ac-today').addEventListener('click', () => {
        startDate = localToday();
        fetchCalendar();
    });

    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 300));
    }

    // Filters
    if (filterType) filterType.addEventListener('change', () => renderCalendar());
    if (filterStatus) filterStatus.addEventListener('change', () => renderCalendar());

    closeBtn.onclick = () => { modal.style.display = 'none'; document.documentElement.classList.remove('artechia-no-scroll'); };
    window.onclick = (event) => {
        if (event.target == modal) { modal.style.display = 'none'; document.documentElement.classList.remove('artechia-no-scroll'); }
    }

    // Init
    fetchCalendar();

    function shiftDate(offset) {
        // Parse current startDate as UTC, shift by offset days, format back
        const parts = startDate.split('-');
        const ms = Date.UTC(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        const shifted = new Date(ms + offset * 86400000);
        startDate = shifted.getUTCFullYear() + '-' + String(shifted.getUTCMonth() + 1).padStart(2, '0') + '-' + String(shifted.getUTCDate()).padStart(2, '0');
        fetchCalendar();
    }

    async function fetchCalendar(force = false) {
        const now = Date.now();
        if (!force && cache.data && now < cache.expire && cache.startDate === startDate) {
            calendarData = cache.data;
            renderCalendar();
            return;
        }

        gridContainer.innerHTML = '<div style="padding:20px;">Cargando...</div>';
        try {
            const url = `${API_ROOT}calendar?property_id=${PROPERTY_ID}&start_date=${startDate}&days=${days}`;
            const response = await fetch(url, { headers: { 'X-WP-Nonce': NONCE } });
            const rawData = await response.json();
            
            // Exclude cancelled bookings from the calendar entirely
            if (rawData && rawData.bookings) {
                rawData.bookings = rawData.bookings.filter(b => b.status !== 'cancelled');
            }
            
            calendarData = rawData;
            
            // Cache for 60s
            cache = { 
                data: calendarData, 
                expire: now + 60000, 
                startDate: startDate 
            };
            
            renderCalendar();
            populateRoomTypeFilter();
        } catch (error) {
            console.error(error);
            gridContainer.innerHTML = '<div style="padding:20px;color:red;">Error al cargar el calendario</div>';
        }
    }

    function populateRoomTypeFilter() {
        if (!filterType || !calendarData || !calendarData.units) return;
        const currentVal = filterType.value;
        // Keep first option ("Todos")
        filterType.length = 1;
        // Extract unique room types
        const seen = new Map();
        calendarData.units.forEach(u => {
            if (u.room_type_id && u.room_type_name && !seen.has(u.room_type_id)) {
                seen.set(u.room_type_id, u.room_type_name);
            }
        });
        seen.forEach((name, id) => {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = name;
            filterType.appendChild(opt);
        });
        // Restore selection if still valid
        if (currentVal) filterType.value = currentVal;
    }

    function renderCalendar() {
        if (!calendarData || !calendarData.units) return;

        sidebar.innerHTML = '';
        gridContainer.innerHTML = '';

        // Apply filters
        const typeFilter = filterType ? filterType.value : '';
        const statusFilter = filterStatus ? filterStatus.value : '';

        // 1. Sidebar
        sidebar.innerHTML += `<div class="sidebar-header">Unidad</div>`;
        let units = calendarData.units;
        if (typeFilter) {
            units = units.filter(u => String(u.room_type_id) === typeFilter);
        }

        units.forEach(unit => {
            const row = document.createElement('div');
            row.className = 'sidebar-row';
            row.innerHTML = `
                <div class="unit-cell" data-unit="${unit.id}">
                    <div class="unit-header">
                        <strong title="${unit.name}">${unit.name}</strong>
                    </div>
            `;
            sidebar.appendChild(row);
        });

        // 2. Build TABLE (table-layout:fixed + border-collapse:separate + border-spacing:0
        //    guarantees each column is EXACTLY COL_WIDTH pixels wide)
        const dates = getDates(startDate, days);
        const todayStr = localToday();

        const table = document.createElement('table');
        table.className = 'calendar-table';

        // 2a. THEAD
        const thead = document.createElement('thead');
        const headerTr = document.createElement('tr');
        dates.forEach(date => {
            const th = document.createElement('th');
            if (date === todayStr) th.classList.add('today');
            let formatted = formatDate(date);
            formatted = formatted.replace(/\/[0-9]{4}$|-[0-9]{4}$/, '');
            th.innerText = formatted;
            headerTr.appendChild(th);
        });
        thead.appendChild(headerTr);
        table.appendChild(thead);

        // 2b. TBODY — track unit index for vertical positioning
        const tbody = document.createElement('tbody');
        const unitIndexMap = {}; // unit_id → row index (0-based)

        units.forEach((unit, uIdx) => {
            unitIndexMap[unit.id] = uIdx;
            const tr = document.createElement('tr');
            tr.className = 'unit-row';

            dates.forEach(date => {
                const td = document.createElement('td');
                td.dataset.unitId = unit.id;
                td.dataset.date = date;

                if (date === todayStr) {
                    td.classList.add('today');
                } else if (date < todayStr) {
                    td.classList.add('past');
                }

                td.addEventListener('dragover', e => {
                    e.preventDefault();
                    td.classList.add('drop-target');
                });
                td.addEventListener('dragleave', () => td.classList.remove('drop-target'));
                td.addEventListener('drop', e => handleDrop(e, td));

                tr.appendChild(td);
            });

            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        gridContainer.appendChild(table);

        // 3. Bookings — PURE ARITHMETIC positioning
        // With table-layout:fixed + border-collapse:separate + border-spacing:0:
        //   Column N starts at exactly N * COL_WIDTH px from table left edge.
        //   Row M (body) starts at HEADER_HEIGHT + M * ROW_HEIGHT from table top edge.
        // Blocks are children of the TABLE (position:relative).
        const HEADER_H = 40; // matches --header-height

        let bookings = calendarData.bookings;
        if (statusFilter) {
            bookings = bookings.filter(b => b.status === statusFilter);
        }

        bookings.forEach(booking => {
            const uIdx = unitIndexMap[booking.room_unit_id];
            if (uIdx === undefined) return;

            let startIdx = dates.indexOf(booking.check_in);
            let endIdx   = dates.indexOf(booking.check_out);

            let startsBefore = false;
            let endsAfter    = false;

            if (startIdx === -1 && booking.check_in < dates[0]) {
                startIdx = 0;
                startsBefore = true;
            }
            if (endIdx === -1 && booking.check_out > dates[dates.length - 1]) {
                endIdx = dates.length;
                endsAfter = true;
            }

            if (startIdx === -1 || (endIdx === -1 && !endsAfter) || startIdx >= (endsAfter ? dates.length : endIdx)) return;

            const block = document.createElement('div');
            block.className = `booking-block status-${booking.status}`;
            if (startsBefore) block.classList.add('starts-before');
            if (endsAfter)    block.classList.add('ends-after');

            block.id = `booking-${booking.id}`;
            block.dataset.bookingId = booking.id;
            block.draggable = !['checked_out', 'cancelled'].includes(booking.status);

            if (booking.payment_status === 'paid') block.style.border = '2px solid gold';

            // Pure arithmetic: no DOM measurement needed
            // Block starts flush at check-in column, extends to MIDDLE of checkout column
            const leftPx  = (startIdx * COL_WIDTH) + (startsBefore ? 0 : 1);
            const topPx   = HEADER_H + (uIdx * ROW_HEIGHT) + 6;
            const widthPx = ((endIdx - startIdx) * COL_WIDTH) + (endsAfter ? 0 : Math.round(COL_WIDTH / 2)) - (startsBefore ? 0 : 1);

            block.style.left   = leftPx + 'px';
            block.style.top    = topPx + 'px';
            block.style.width  = Math.max(0, widthPx) + 'px';
            block.style.height = (ROW_HEIGHT - 12) + 'px';

            block.innerHTML = `
                <span class="booking-label">${booking.status === 'hold' ? 'HOLD' : (booking.first_name + ' ' + booking.last_name.substring(0, 1) + '.')}</span>
                <div class="resize-handle"></div>
            `;
            block.title = `${booking.status === 'hold' ? 'HOLD' : booking.booking_code}: ${booking.check_in} - ${booking.check_out}`;

            block.onclick = () => openBookingModal(booking);

            block.addEventListener('dragstart', e => {
                e.dataTransfer.setData('text/plain', booking.id);
                block.classList.add('is-dragging');
            });
            block.addEventListener('dragend', () => block.classList.remove('is-dragging'));

            const handle = block.querySelector('.resize-handle');
            if (handle && !['checked_out', 'cancelled'].includes(booking.status)) {
                handle.addEventListener('mousedown', e => initResize(e, booking, block));
            }

            // Append to the TABLE (which has position:relative)
            table.appendChild(block);
        });
    }

    // --- Interactions ---

    async function handleDrop(e, cell) {
        e.preventDefault();
        cell.classList.remove('drop-target');
        const bookingId = e.dataTransfer.getData('text/plain');
        const newUnitId = cell.dataset.unitId;
        const newStart  = cell.dataset.date;

        // Find current booking to maintain duration
        const booking = calendarData.bookings.find(b => b.id == bookingId);
        if (!booking) return;

        const duration = (new Date(booking.check_out) - new Date(booking.check_in)) / (1000 * 60 * 60 * 24);
        const newEndDate = new Date(newStart);
        newEndDate.setDate(newEndDate.getDate() + duration);
        const newEndStr = newEndDate.toISOString().split('T')[0];

        // Optimistic UI
        const block = document.getElementById(`booking-${bookingId}`);
        if (block) {
            block.style.opacity = '0.5';
        }

        try {
            const res = await fetch(`${API_ROOT}calendar/booking/${bookingId}/move`, {
                method: 'POST',
                headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    room_unit_id: newUnitId,
                    check_in: newStart,
                    check_out: newEndStr
                })
            });
            const json = await res.json();
            if (res.ok) {
                fetchCalendar(true); // reload & clear cache
            } else {
                artechiaPMS.toast.show(json.message || 'Error al mover la reserva', 'error');
                fetchCalendar(true); // Revert
            }
        } catch (e) {
            artechiaPMS.toast.show('Error de red', 'error');
            fetchCalendar(true);
        }
    }

    let isResizing = false;
    function initResize(e, booking, block) {
        e.stopPropagation();
        e.preventDefault();
        isResizing = true;
        block.classList.add('is-resizing');

        const initialX = e.clientX;
        const initialWidth = parseInt(block.style.width);

        function onMouseMove(moveEvent) {
            const deltaX = moveEvent.clientX - initialX;
            // Snap to grid
            const snappedDelta = Math.round(deltaX / COL_WIDTH) * COL_WIDTH;
            block.style.width = (initialWidth + snappedDelta) + 'px';
        }

        async function onMouseUp(upEvent) {
            window.removeEventListener('mousemove', onMouseMove);
            window.removeEventListener('mouseup', onMouseUp);
            block.classList.remove('is-resizing');
            isResizing = false;

            const finalWidth = parseInt(block.style.width);
            const extraDays = Math.round((finalWidth - initialWidth) / COL_WIDTH);
            
            if (extraDays === 0) return;

            const newCO = new Date(booking.check_out);
            newCO.setDate(newCO.getDate() + extraDays);
            const newCOStr = newCO.toISOString().split('T')[0];

            try {
                const res = await fetch(`${API_ROOT}calendar/booking/${booking.id}/resize`, {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ check_out: newCOStr })
                });
                const json = await res.json();
                if (res.ok) {
                    fetchCalendar(true);
                } else {
                    artechiaPMS.toast.show(json.message || 'Error al cambiar el tamaño', 'error');
                    fetchCalendar(true);
                }
            } catch (err) {
                fetchCalendar(true);
            }
        }

        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
    }

    function handleSearch() {
        const query = searchInput.value.trim().toLowerCase();
        if (!calendarData || !calendarData.bookings) return;

        // Remove previous highlights
        document.querySelectorAll('.booking-block.search-highlight').forEach(el => {
            el.classList.remove('search-highlight');
            el.style.outline = '';
            el.style.zIndex = '';
        });

        if (query.length < 2) return;

        // Search bookings client-side
        const matches = calendarData.bookings.filter(b => {
            const name = ((b.first_name || b.guest_first_name || '') + ' ' + (b.last_name || b.guest_last_name || '')).toLowerCase();
            const email = (b.email || b.guest_email || '').toLowerCase();
            const code = (b.booking_code || '').toLowerCase();
            return name.includes(query) || email.includes(query) || code.includes(query);
        });

        if (matches.length === 0) return;

        // Highlight matching blocks
        let firstBlock = null;
        matches.forEach(m => {
            const block = document.querySelector(`.booking-block[data-booking-id="${m.id}"]`);
            if (block) {
                block.classList.add('search-highlight');
                block.style.outline = '3px solid #2271b1';
                block.style.zIndex = '20';
                if (!firstBlock) firstBlock = block;
            }
        });

        // Scroll to first match
        if (firstBlock) {
            firstBlock.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        }
    }

    // --- Helpers ---

    function getDates(start, count) {
        const arr = [];
        // Parse start as UTC explicitly to avoid timezone offset issues
        const parts = start.split('-');
        let dt = Date.UTC(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        for (let i = 0; i < count; i++) {
            const d = new Date(dt);
            const y = d.getUTCFullYear();
            const m = String(d.getUTCMonth() + 1).padStart(2, '0');
            const day = String(d.getUTCDate()).padStart(2, '0');
            arr.push(`${y}-${m}-${day}`);
            dt += 86400000; // +1 day in ms
        }
        return arr;
    }

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function openBookingModal(booking) {
        if (isResizing) return;

        modalContent.innerHTML = `
            <h2>Reserva ${booking.booking_code}</h2>
            <p><strong>Huésped:</strong> ${booking.first_name} ${booking.last_name}</p>
            <p><strong>Fechas:</strong> ${formatDate(booking.check_in)} a ${formatDate(booking.check_out)}</p>
            <p><strong>Estado:</strong> ${getFriendlyStatus(booking.status)}</p>
            <p><strong>Pago:</strong> ${getFriendlyPayment(booking.payment_status)}</p>
            <p><strong>Total:</strong> ${formatPrice(booking.grand_total)}</p>
            <hr>
            <div style="margin-top:20px;">
                <a href="admin.php?page=artechia-reservations&booking_code=${booking.booking_code}" class="button">Ver detalles</a>
            </div>
        `;
        modal.style.display = 'block';
        document.documentElement.classList.add('artechia-no-scroll');
    }

    window.doAction = async function(id, action) {
        if (!confirm(`¿Estás seguro que deseas ${action}?`)) return;
        try {
            const url = `${API_ROOT}booking/${id}/${action}`;
            const res = await fetch(url, { method: 'POST', headers: { 'X-WP-Nonce': NONCE } });
            const json = await res.json();
            if (res.ok) {
                modal.style.display = 'none';
                document.documentElement.classList.remove('artechia-no-scroll');
                fetchCalendar(true);
            } else {
                artechiaPMS.toast.show('Error: ' + (json.message || 'Unknown'), 'error');
            }
        } catch(e) { artechiaPMS.toast.show('Error de red', 'error'); }
    }
    function getFriendlyStatus(status) {
        const map = {
            'pending': 'Pendiente',
            'confirmed': 'Confirmada',
            'cancelled': 'Cancelada',
            'checked_in': 'In-House',
            'checked_out': 'Finalizada',
            'hold': 'Checkout (En Proceso)'
        };
        return map[status] || status;
    }

    function getFriendlyPayment(status) {
        const map = {
            'unpaid': 'Sin Pagar',
            'deposit_paid': 'Seña',
            'paid': 'Pagado'
        };
        return map[status] || status;
    }

    function formatPrice(amount) {
        if (!window.artechiaPMS || !window.artechiaPMS.format) return amount;
        const opts = window.artechiaPMS.format;
        const num = parseFloat(amount) || 0;
        
        let formatted = num.toFixed(opts.decimals);
        let parts = formatted.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, opts.thousand_separator);
        formatted = parts.join(opts.decimal_separator);
        
        if (opts.currency_position === 'before') {
            return opts.currency_symbol + formatted;
        } else {
            return formatted + opts.currency_symbol;
        }
    }

    function formatDate(dateStr) {
        if (!window.artechiaPMS || !window.artechiaPMS.format || !dateStr) return dateStr;
        const format = window.artechiaPMS.format.date_format;
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        
        const y = parts[0];
        const m = parts[1];
        const d = parts[2];
        
        return format
            .replace('Y', y)
            .replace('m', m)
            .replace('d', d)
            .replace('y', y.slice(2));
    }
});
